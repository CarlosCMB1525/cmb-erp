<?php
declare(strict_types=1);
namespace CMBERP\Modules\Reports\Services;

use CMBERP\Modules\Dashboard\DashboardService;
use CMBERP\Modules\Cashflow\Settings as CashflowSettings;
use CMBERP\Modules\Reports\Cpt\TaxProfileCpt;

if (!defined('ABSPATH')) { exit; }

require_once dirname(__DIR__, 3) . '/dashboard/src/DashboardService.php';

/**
 * Servicio de negocio: construye el Estado de Resultados (P&L) mensual/anual.
 */
final class PnlReportService {

    /**
     * @return array{payload:array,error?:string}
     */
    public static function build_monthly(string $ym): array {
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            return ['error' => 'Periodo mensual inválido (YYYY-MM).', 'payload' => []];
        }

        $start = $ym . '-01';
        $end = (new \DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');

        $tax = self::get_tax_profile('MONTH', $ym);
        $sales_tax_rate = (float)$tax['sales_tax_rate'];
        $annual_tax_rate = (float)$tax['annual_tax_rate'];
        $annual_tax_mode = (string)$tax['annual_tax_mode'];

        // Ingresos desde ventas (dashboard queries)
        $m = DashboardService::get_metrics($start, $end, 'TODAS', 'TODOS', 'TODOS', '', '');
        $facturado = (float)($m['total_facturado'] ?? 0);
        $recibos = (float)($m['total_recibos'] ?? 0);
        $gross_income = $facturado + $recibos;

        $sales_tax = $facturado * $sales_tax_rate; // 16% devengado sobre facturado
        $net_sales = $gross_income - $sales_tax;

        // Egresos cashflow agrupados por categoría
        $egresos = self::sum_cashflow_by_category($start, $end);

        // Map de tipos (COGS/OPEX/OTROS)
        $map = CategorySyncService::get_category_type_map();

        // IVA/IT pagado para conciliación
        $iva_paid = 0.0;
        foreach (['IVA/IT (Pagado)','Impuestos'] as $k) {
            if (isset($egresos[$k])) $iva_paid += (float)$egresos[$k];
        }

        $cogs_total = 0.0;
        $opex_lines = [];
        $opex_total = 0.0;
        $otros_total = 0.0;

        foreach ($egresos as $cat => $amount) {
            $type = $map[$cat] ?? 'OPEX';
            $amount = (float)$amount;

            // Impuestos IVA/IT pagado se excluye del OPEX (conciliación)
            if (in_array($cat, ['IVA/IT (Pagado)','Impuestos'], true)) {
                $otros_total += $amount;
                continue;
            }

            if ($type === 'COGS') {
                $cogs_total += $amount;
            } elseif ($type === 'OPEX') {
                $opex_total += $amount;
                $opex_lines[] = ['label' => $cat, 'amount' => $amount];
            } else {
                $otros_total += $amount;
            }
        }

        // Ordenar OPEX por monto desc
        usort($opex_lines, fn($a,$b) => ($b['amount'] <=> $a['amount']));

        $gross_profit = $net_sales - $cogs_total;
        $ebitda = $gross_profit - $opex_total;

        // Provisión impuesto anual prorrateado (opcional)
        $annual_tax_provision = 0.0;
        if ($annual_tax_mode === 'PRORRATED') {
            $annual_tax_provision = ($gross_profit * $annual_tax_rate) / 12.0;
        }

        $payload = [
            'type' => 'MONTH',
            'period' => $ym,
            'period_label' => self::month_label($ym),
            'currency' => 'Bs',
            'rates' => [
                'sales_tax_rate' => $sales_tax_rate,
                'annual_tax_rate' => $annual_tax_rate,
                'annual_tax_mode' => $annual_tax_mode,
                'annual_tax_base' => 'GROSS_PROFIT',
            ],
            'sales' => [
                'facturado' => $facturado,
                'recibos' => $recibos,
            ],
            'lines' => [
                ['label' => 'Ingresos Brutos Totales', 'amount' => $gross_income, 'pct_of_net' => null],
                ['label' => 'Impuestos sobre Ventas (solo facturado)', 'amount' => $sales_tax, 'pct_of_net' => ($net_sales > 0 ? ($sales_tax / $net_sales) : null)],
                ['label' => 'Ventas Netas Totales', 'amount' => $net_sales, 'pct_of_net' => 1.0],
                ['label' => '(-) Costo de Ventas (COGS)', 'amount' => $cogs_total, 'pct_of_net' => ($net_sales > 0 ? ($cogs_total / $net_sales) : null)],
                ['label' => '(=) Utilidad Bruta', 'amount' => $gross_profit, 'pct_of_net' => ($net_sales > 0 ? ($gross_profit / $net_sales) : null)],
                // OPEX se inyecta como detalle
                ['label' => '(-) Gastos Operativos (OPEX)', 'amount' => $opex_total, 'pct_of_net' => ($net_sales > 0 ? ($opex_total / $net_sales) : null)],
                ['label' => '(=) EBITDA / Utilidad de Operación', 'amount' => $ebitda, 'pct_of_net' => ($net_sales > 0 ? ($ebitda / $net_sales) : null)],
            ],
            'opex_breakdown' => array_map(function($l) use ($net_sales) {
                return [
                    'label' => $l['label'],
                    'amount' => (float)$l['amount'],
                    'pct_of_net' => ($net_sales > 0 ? ((float)$l['amount'] / $net_sales) : null),
                ];
            }, $opex_lines),
            'reconciliation' => [
                'iva_devengado' => $sales_tax,
                'iva_pagado_cashflow' => $iva_paid,
                'diferencia' => $sales_tax - $iva_paid,
                'note' => 'Conciliación: IVA/IT devengado (16% sobre facturado) vs IVA/IT pagado (cashflow).',
            ],
            'annual_tax_provision' => $annual_tax_provision,
        ];

        return ['payload' => $payload];
    }

    /**
     * @return array{payload:array,error?:string}
     */
    public static function build_annual(string $y): array {
        if (!preg_match('/^\d{4}$/', $y)) {
            return ['error' => 'Periodo anual inválido (YYYY).', 'payload' => []];
        }

        $start = $y . '-01-01';
        $end = $y . '-12-31';

        $tax = self::get_tax_profile('YEAR', $y);
        $sales_tax_rate = (float)$tax['sales_tax_rate'];
        $annual_tax_rate = (float)$tax['annual_tax_rate'];
        $annual_tax_mode = (string)$tax['annual_tax_mode']; // YEAR_END normalmente

        // Ingresos (sum anual)
        $m = DashboardService::get_metrics($start, $end, 'TODAS', 'TODOS', 'TODOS', '', '');
        $facturado = (float)($m['total_facturado'] ?? 0);
        $recibos = (float)($m['total_recibos'] ?? 0);
        $gross_income = $facturado + $recibos;

        $sales_tax = $facturado * $sales_tax_rate;
        $net_sales = $gross_income - $sales_tax;

        // Egresos cashflow del año
        $egresos = self::sum_cashflow_by_category($start, $end);
        $map = CategorySyncService::get_category_type_map();

        $iva_paid = 0.0;
        foreach (['IVA/IT (Pagado)','Impuestos'] as $k) {
            if (isset($egresos[$k])) $iva_paid += (float)$egresos[$k];
        }

        $cogs_total = 0.0;
        $opex_lines = [];
        $opex_total = 0.0;

        foreach ($egresos as $cat => $amount) {
            $type = $map[$cat] ?? 'OPEX';
            $amount = (float)$amount;

            if (in_array($cat, ['IVA/IT (Pagado)','Impuestos'], true)) {
                continue;
            }

            if ($type === 'COGS') {
                $cogs_total += $amount;
            } elseif ($type === 'OPEX') {
                $opex_total += $amount;
                $opex_lines[] = ['label' => $cat, 'amount' => $amount];
            }
        }

        usort($opex_lines, fn($a,$b) => ($b['amount'] <=> $a['amount']));

        $gross_profit = $net_sales - $cogs_total;
        $ebitda = $gross_profit - $opex_total;

        // Impuesto anual (25% sobre utilidad bruta)
        $annual_tax = $gross_profit * $annual_tax_rate;
        $net_after_annual_tax = $ebitda - $annual_tax;

        $payload = [
            'type' => 'YEAR',
            'period' => $y,
            'period_label' => 'Año ' . $y,
            'currency' => 'Bs',
            'rates' => [
                'sales_tax_rate' => $sales_tax_rate,
                'annual_tax_rate' => $annual_tax_rate,
                'annual_tax_mode' => $annual_tax_mode,
                'annual_tax_base' => 'GROSS_PROFIT',
            ],
            'sales' => [
                'facturado' => $facturado,
                'recibos' => $recibos,
            ],
            'lines' => [
                ['label' => 'Ingresos Brutos Totales', 'amount' => $gross_income, 'pct_of_net' => null],
                ['label' => 'Impuestos sobre Ventas (solo facturado)', 'amount' => $sales_tax, 'pct_of_net' => ($net_sales > 0 ? ($sales_tax / $net_sales) : null)],
                ['label' => 'Ventas Netas Totales', 'amount' => $net_sales, 'pct_of_net' => 1.0],
                ['label' => '(-) Costo de Ventas (COGS)', 'amount' => $cogs_total, 'pct_of_net' => ($net_sales > 0 ? ($cogs_total / $net_sales) : null)],
                ['label' => '(=) Utilidad Bruta', 'amount' => $gross_profit, 'pct_of_net' => ($net_sales > 0 ? ($gross_profit / $net_sales) : null)],
                ['label' => '(-) Gastos Operativos (OPEX)', 'amount' => $opex_total, 'pct_of_net' => ($net_sales > 0 ? ($opex_total / $net_sales) : null)],
                ['label' => '(=) EBITDA / Utilidad de Operación', 'amount' => $ebitda, 'pct_of_net' => ($net_sales > 0 ? ($ebitda / $net_sales) : null)],
                ['label' => '(-) Impuesto anual (25% sobre Utilidad Bruta)', 'amount' => $annual_tax, 'pct_of_net' => ($net_sales > 0 ? ($annual_tax / $net_sales) : null)],
                ['label' => '(=) Resultado después de impuesto anual', 'amount' => $net_after_annual_tax, 'pct_of_net' => ($net_sales > 0 ? ($net_after_annual_tax / $net_sales) : null)],
            ],
            'opex_breakdown' => array_map(function($l) use ($net_sales) {
                return [
                    'label' => $l['label'],
                    'amount' => (float)$l['amount'],
                    'pct_of_net' => ($net_sales > 0 ? ((float)$l['amount'] / $net_sales) : null),
                ];
            }, $opex_lines),
            'reconciliation' => [
                'iva_devengado' => $sales_tax,
                'iva_pagado_cashflow' => $iva_paid,
                'diferencia' => $sales_tax - $iva_paid,
                'note' => 'Conciliación anual: IVA/IT devengado vs pagado (cashflow).',
            ],
        ];

        return ['payload' => $payload];
    }

    private static function sum_cashflow_by_category(string $start, string $end): array {
        global $wpdb;

        if (!class_exists(CashflowSettings::class)) {
            return [];
        }

        $table = $wpdb->prefix . CashflowSettings::TABLE_NAME;

        // Si no existe tabla, no hay egresos.
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if (empty($exists)) {
            return [];
        }

        $sql = "
            SELECT COALESCE(categoria_egreso,'') AS cat, COALESCE(SUM(monto_bs),0) AS total
            FROM {$table}
            WHERE tipo='Egreso' AND creado_en BETWEEN %s AND %s
            GROUP BY COALESCE(categoria_egreso,'')
        ";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $start . ' 00:00:00', $end . ' 23:59:59'), ARRAY_A);

        $out = [];
        foreach (($rows ?: []) as $r) {
            $cat = trim((string)($r['cat'] ?? ''));
            if ($cat === '') $cat = 'Sin categoría';
            $out[$cat] = (float)($r['total'] ?? 0);
        }
        return $out;
    }

    /**
     * Obtiene tasas por periodo desde el CPT.
     */
    private static function get_tax_profile(string $type, string $key): array {
        // Default
        $out = [
            'sales_tax_rate' => 0.16,
            'annual_tax_rate' => 0.25,
            'annual_tax_mode' => ($type === 'YEAR') ? 'YEAR_END' : 'PRORRATED',
            'annual_tax_base' => 'GROSS_PROFIT',
        ];

        if (!post_type_exists(TaxProfileCpt::CPT)) {
            return $out;
        }

        $q = new \WP_Query([
            'post_type' => TaxProfileCpt::CPT,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'no_found_rows' => true,
            'meta_query' => [
                ['key' => TaxProfileCpt::META_PERIOD_TYPE, 'value' => $type],
                ['key' => TaxProfileCpt::META_PERIOD_KEY, 'value' => $key],
            ],
        ]);

        if (!$q->have_posts()) {
            return $out;
        }

        $id = (int)$q->posts[0]->ID;
        $sales = (float)get_post_meta($id, TaxProfileCpt::META_SALES_TAX_RATE, true);
        $annual = (float)get_post_meta($id, TaxProfileCpt::META_ANNUAL_TAX_RATE, true);
        $mode = (string)get_post_meta($id, TaxProfileCpt::META_ANNUAL_TAX_MODE, true);

        if ($sales > 0 && $sales < 1) $out['sales_tax_rate'] = $sales;
        if ($annual > 0 && $annual < 1) $out['annual_tax_rate'] = $annual;
        if (in_array($mode, ['PRORRATED','YEAR_END'], true)) $out['annual_tax_mode'] = $mode;

        return $out;
    }

    private static function month_label(string $ym): string {
        // ym: YYYY-MM
        $dt = \DateTimeImmutable::createFromFormat('Y-m', $ym);
        if (!$dt) return $ym;
        $months = [
            1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
            7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
        ];
        $m = (int)$dt->format('n');
        $y = $dt->format('Y');
        return ($months[$m] ?? $dt->format('F')) . ' ' . $y;
    }
}

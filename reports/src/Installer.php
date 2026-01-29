<?php
declare(strict_types=1);
namespace CMBERP\Modules\Reports;

use CMBERP\Modules\Reports\Services\CategorySyncService;

if (!defined('ABSPATH')) { exit; }

/**
 * Installer lógico del módulo Reports.
 * - Precarga categorías contables (CPT) y perfiles fiscales (CPT) si aún no existen.
 * - Diseñado para ser idempotente (seguro de ejecutar en cada init).
 */
final class Installer {
    private const SEED_OPT = 'cmb_erp_reports_seed_v1';

    public static function maybe_seed(): void {
        if (get_option(self::SEED_OPT) === '1') {
            return;
        }

        try {
            self::seed_categories();
            self::seed_tax_profiles();
            // Sincroniza categorías a Cashflow para que aparezcan en el dropdown
            CategorySyncService::sync_to_cashflow_options();

            update_option(self::SEED_OPT, '1', false);
        } catch (\Throwable $e) {
            // No romper el sitio: solo log
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[CMBERP][Reports][Installer] ' . $e->getMessage());
            }
        }
    }

    private static function seed_categories(): void {
        // Si ya existe al menos 1 categoría publicada, no sembrar.
        $existing = get_posts([
            'post_type' => Services\CategorySyncService::CPT,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);
        if (!empty($existing)) {
            return;
        }

        $cats = [
            // COGS
            ['name' => 'Diseño gráfico (terceros)', 'type' => 'COGS'],
            ['name' => 'Edición de video (terceros)', 'type' => 'COGS'],

            // OPEX - mensuales
            ['name' => 'Luz', 'type' => 'OPEX'],
            ['name' => 'Internet', 'type' => 'OPEX'],
            ['name' => 'Mantenimiento', 'type' => 'OPEX'],
            ['name' => 'Teléfonos (planes/servicio)', 'type' => 'OPEX'],
            ['name' => 'Alquiler', 'type' => 'OPEX'],
            ['name' => 'Transporte', 'type' => 'OPEX'],
            ['name' => 'Café / refrigerios', 'type' => 'OPEX'],
            ['name' => 'Material de oficina', 'type' => 'OPEX'],
            ['name' => 'Publicidad', 'type' => 'OPEX'],
            ['name' => 'Pagos online (comisiones pasarela)', 'type' => 'OPEX'],

            // OPEX - suscripciones separadas
            ['name' => 'YouTube Premium', 'type' => 'OPEX'],
            ['name' => 'Spotify', 'type' => 'OPEX'],
            ['name' => 'CapCut', 'type' => 'OPEX'],
            ['name' => 'Microsoft 365', 'type' => 'OPEX'],
            ['name' => 'Dominios', 'type' => 'OPEX'],
            ['name' => 'Hosting', 'type' => 'OPEX'],

            // OPEX - finanzas (intereses)
            ['name' => 'Intereses y comisiones bancarias', 'type' => 'OPEX'],
            ['name' => 'Intereses – Tarjeta de crédito', 'type' => 'OPEX'],

            // OTROS - finanzas (capital)
            ['name' => 'Pago de capital – Préstamo bancario', 'type' => 'OTROS'],
            ['name' => 'Pago de capital – Tarjeta de crédito', 'type' => 'OTROS'],

            // OTROS - CAPEX
            ['name' => 'Cámaras / luces / trípodes / micrófonos', 'type' => 'OTROS'],
            ['name' => 'Teléfonos celulares (equipos)', 'type' => 'OTROS'],
            ['name' => 'Computadoras', 'type' => 'OTROS'],
            ['name' => 'Libros', 'type' => 'OTROS'],

            // OTROS - conciliación fiscal (IVA/IT)
            ['name' => 'IVA/IT (Pagado)', 'type' => 'OTROS'],
        ];

        $order = 0;
        foreach ($cats as $c) {
            $order++;
            $post_id = wp_insert_post([
                'post_type' => Services\CategorySyncService::CPT,
                'post_status' => 'publish',
                'post_title' => $c['name'],
            ], true);
            if (is_wp_error($post_id) || !$post_id) {
                continue;
            }
            update_post_meta((int)$post_id, Services\CategorySyncService::META_TYPE, $c['type']);
            update_post_meta((int)$post_id, Services\CategorySyncService::META_ORDER, $order);
        }
    }

    private static function seed_tax_profiles(): void {
        // Si existe al menos 1 perfil fiscal, no sembrar.
        $existing = get_posts([
            'post_type' => Cpt\TaxProfileCpt::CPT,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);
        if (!empty($existing)) {
            return;
        }

        $now = new \DateTimeImmutable('now');
        $year = (int)$now->format('Y');
        $month = $now->format('Y-m');

        // Perfil mensual actual
        $m_id = wp_insert_post([
            'post_type' => Cpt\TaxProfileCpt::CPT,
            'post_status' => 'publish',
            'post_title' => 'Mes ' . $month,
        ], true);
        if (!is_wp_error($m_id) && $m_id) {
            update_post_meta((int)$m_id, Cpt\TaxProfileCpt::META_PERIOD_TYPE, 'MONTH');
            update_post_meta((int)$m_id, Cpt\TaxProfileCpt::META_PERIOD_KEY, $month);
            update_post_meta((int)$m_id, Cpt\TaxProfileCpt::META_SALES_TAX_RATE, '0.16');
            update_post_meta((int)$m_id, Cpt\TaxProfileCpt::META_ANNUAL_TAX_RATE, '0.25');
            update_post_meta((int)$m_id, Cpt\TaxProfileCpt::META_ANNUAL_TAX_MODE, 'PRORRATED');
            update_post_meta((int)$m_id, Cpt\TaxProfileCpt::META_ANNUAL_TAX_BASE, 'GROSS_PROFIT');
        }

        // Perfil anual del año actual
        $y_id = wp_insert_post([
            'post_type' => Cpt\TaxProfileCpt::CPT,
            'post_status' => 'publish',
            'post_title' => 'Año ' . $year,
        ], true);
        if (!is_wp_error($y_id) && $y_id) {
            update_post_meta((int)$y_id, Cpt\TaxProfileCpt::META_PERIOD_TYPE, 'YEAR');
            update_post_meta((int)$y_id, Cpt\TaxProfileCpt::META_PERIOD_KEY, (string)$year);
            update_post_meta((int)$y_id, Cpt\TaxProfileCpt::META_SALES_TAX_RATE, '0.16');
            update_post_meta((int)$y_id, Cpt\TaxProfileCpt::META_ANNUAL_TAX_RATE, '0.25');
            update_post_meta((int)$y_id, Cpt\TaxProfileCpt::META_ANNUAL_TAX_MODE, 'YEAR_END');
            update_post_meta((int)$y_id, Cpt\TaxProfileCpt::META_ANNUAL_TAX_BASE, 'GROSS_PROFIT');
        }
    }
}

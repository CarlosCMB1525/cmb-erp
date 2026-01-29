<?php
namespace CMBERP\Modules\Dashboard;

if (!defined('ABSPATH')) { exit; }

/**
 * DashboardService: encapsula lógica SQL del dashboard.
 *
 * Mantiene:
 * - 6 métricas originales (ventas/pagos/facturas)
 * - Tabla detalle de ventas
 *
 * Añade (recomendación "datos reales"):
 * - Cotizado SIN venta: suma SOLO la última versión por código base, que NO tenga relación a venta
 * - Cotizado CON venta: suma SOLO la última versión por código base, que SÍ tenga relación a venta
 * - Egresos/Ingresos: usa movimientos del módulo Cashflow (cmb_cashflow_movimientos)
 */
final class DashboardService {

    private static array $cache = [];

    public static function can_access(): bool {
        return current_user_can('administrator') || current_user_can('edit_posts');
    }

    public static function nonce_ok(): bool {
        $nonce = $_POST['nonce'] ?? '';
        $nonce = is_string($nonce) ? $nonce : '';
        $action = defined('CMB_ERP_NONCE_ACTION') ? CMB_ERP_NONCE_ACTION : 'cmb_erp_nonce';
        return ($nonce !== '') && wp_verify_nonce($nonce, $action);
    }

    public static function safe_date(string $d): string {
        $d = sanitize_text_field($d);
        $dt = \DateTime::createFromFormat('Y-m-d', $d);
        if (!$dt || $dt->format('Y-m-d') !== $d) return '';
        return $d;
    }

    public static function normalize_categoria($cat): string {
        $cat = strtoupper(trim(sanitize_text_field((string)$cat)));
        if ($cat === '' || $cat === 'TODAS' || $cat === 'ALL') return 'TODAS';
        $cat = preg_replace('/[^A-Z0-9 _\-]/', '', $cat);
        return $cat ?: 'TODAS';
    }

    public static function normalize_doc_tipo($t): string {
        $t = strtoupper(trim(sanitize_text_field((string)$t)));
        $allowed = ['TODOS','FACTURA','RECIBO','OTROS'];
        return in_array($t, $allowed, true) ? $t : 'TODOS';
    }

    public static function normalize_pay_status($st): string {
        $st = strtoupper(trim(sanitize_text_field((string)$st)));
        $allowed = ['TODOS','PENDIENTE','PARCIAL','PAGADO'];
        return in_array($st, $allowed, true) ? $st : 'TODOS';
    }

    public static function normalize_query($q): string {
        $q = sanitize_text_field(wp_unslash((string)$q));
        $q = trim($q);
        if (strlen($q) > 120) $q = substr($q, 0, 120);
        return $q;
    }

    public static function docs_subquery(string $fact_table): string {
        return "
            SELECT
              f.venta_id,
              MAX(CASE
                WHEN f.tipo_documento='Factura'
                 AND f.nro_comprobante IS NOT NULL
                 AND f.nro_comprobante NOT IN ('','SIN FACTURA')
                THEN 1 ELSE 0 END) AS has_factura,
              MAX(CASE
                WHEN f.tipo_documento='Recibo'
                 OR (f.tipo_documento<>'Factura' AND f.nro_comprobante='SIN FACTURA')
                THEN 1 ELSE 0 END) AS has_recibo,
              MAX(f.fecha_emision) AS ff,
              MAX(f.nro_comprobante) AS nro_comprobante
            FROM {$fact_table} f
            GROUP BY f.venta_id
        ";
    }

    public static function pagos_subquery(string $pagos_table): string {
        return "
            SELECT
              p.venta_id,
              COALESCE(SUM(p.monto_pagado),0) AS total_pagado,
              COUNT(*) AS pagos_cnt,
              MAX(p.fecha_pago) AS fp
            FROM {$pagos_table} p
            GROUP BY p.venta_id
        ";
    }

    /** WHERE de ventas (sin cambios) */
    public static function build_where($inicio, $fin, $categoria, $pago_estado, $doc_tipo, $q_quick, $q_adv): array {
        global $wpdb;

        $categoria = self::normalize_categoria($categoria);
        $pago_estado = self::normalize_pay_status($pago_estado);
        $doc_tipo = self::normalize_doc_tipo($doc_tipo);
        $q_quick = self::normalize_query($q_quick);
        $q_adv = self::normalize_query($q_adv);

        $where = ["1=1"]; $args = [];

        if ($inicio && $fin) {
            $where[] = "v.fecha_venta BETWEEN %s AND %s";
            $args[] = $inicio . " 00:00:00";
            $args[] = $fin . " 23:59:59";
        }

        if ($categoria !== 'TODAS') {
            $where[] = "UPPER(COALESCE(c.tipo_cliente,'')) = %s";
            $args[] = strtoupper($categoria);
        }

        if ($q_quick !== '') {
            $like = '%' . $wpdb->esc_like(strtoupper($q_quick)) . '%';
            $where[] = "(UPPER(COALESCE(c.nombre_legal,'')) LIKE %s OR UPPER(COALESCE(d.nro_comprobante,'')) LIKE %s)";
            $args[] = $like; $args[] = $like;
        }

        if ($q_adv !== '') {
            $like2 = '%' . $wpdb->esc_like(strtoupper($q_adv)) . '%';
            $where[] = "("
                . "UPPER(COALESCE(c.nombre_legal,'')) LIKE %s "
                . "OR UPPER(COALESCE(c.nit_id,'')) LIKE %s "
                . "OR UPPER(COALESCE(d.nro_comprobante,'')) LIKE %s "
                . "OR UPPER(COALESCE(v.detalles,'')) LIKE %s"
                . ")";
            $args[] = $like2; $args[] = $like2; $args[] = $like2; $args[] = $like2;
        }

        if ($doc_tipo === 'FACTURA') {
            $where[] = "COALESCE(d.has_factura,0)=1";
        } elseif ($doc_tipo === 'RECIBO') {
            $where[] = "COALESCE(d.has_factura,0)=0 AND COALESCE(d.has_recibo,0)=1";
        } elseif ($doc_tipo === 'OTROS') {
            $where[] = "COALESCE(d.has_factura,0)=0 AND COALESCE(d.has_recibo,0)=0";
        }

        $tol = 0.05;
        if ($pago_estado === 'PENDIENTE') {
            $where[] = "COALESCE(px.total_pagado,0) <= %f";
            $args[] = $tol;
        } elseif ($pago_estado === 'PAGADO') {
            $where[] = "COALESCE(px.total_pagado,0) >= (COALESCE(v.total_bs,0) - %f)";
            $args[] = $tol;
        } elseif ($pago_estado === 'PARCIAL') {
            $where[] = "COALESCE(px.total_pagado,0) > %f AND COALESCE(px.total_pagado,0) < (COALESCE(v.total_bs,0) - %f)";
            $args[] = $tol; $args[] = $tol;
        }

        return ["WHERE " . implode(" AND ", $where), $args];
    }

    /** 6 métricas originales (sin cambios) */
    public static function get_metrics($inicio, $fin, $categoria, $pago_estado, $doc_tipo, $q_quick, $q_adv): array {
        global $wpdb;
        $ventas_table = $wpdb->prefix . 'vn_ventas';
        $emp_table    = $wpdb->prefix . 'cl_empresas';
        $fact_table   = $wpdb->prefix . 'vn_facturas';
        $pagos_table  = $wpdb->prefix . 'vn_pagos';

        $docs_sub  = self::docs_subquery($fact_table);
        $pagos_sub = self::pagos_subquery($pagos_table);

        [$where_sql, $args] = self::build_where($inicio, $fin, $categoria, $pago_estado, $doc_tipo, $q_quick, $q_adv);

        $sql = "
            SELECT
              COALESCE(SUM(v.total_bs),0) AS total_general,
              COALESCE(SUM(CASE WHEN COALESCE(d.has_factura,0)=1 THEN v.total_bs ELSE 0 END),0) AS total_facturado,
              COALESCE(SUM(CASE WHEN COALESCE(d.has_factura,0)=0 AND COALESCE(d.has_recibo,0)=1 THEN v.total_bs ELSE 0 END),0) AS total_recibos,
              COALESCE(SUM(COALESCE(px.total_pagado,0)),0) AS total_cobrado
            FROM {$ventas_table} v
            JOIN {$emp_table} c ON v.cliente_id=c.id
            LEFT JOIN ({$docs_sub}) d ON d.venta_id=v.id
            LEFT JOIN ({$pagos_sub}) px ON px.venta_id=v.id
            {$where_sql}
        ";

        $prepared = !empty($args) ? $wpdb->prepare($sql, $args) : $sql;
        $row = $wpdb->get_row($prepared);

        $general   = (float)($row->total_general ?? 0);
        $facturado = (float)($row->total_facturado ?? 0);
        $recibos   = (float)($row->total_recibos ?? 0);
        $cobrado   = (float)($row->total_cobrado ?? 0);

        $pendiente = $general - $cobrado;
        if ($pendiente < 0) $pendiente = 0;

        $utilidad = $cobrado - ($facturado * 0.16);

        return [
            'total_general'   => number_format($general, 2, '.', ''),
            'total_facturado' => number_format($facturado, 2, '.', ''),
            'total_recibos'   => number_format($recibos, 2, '.', ''),
            'cobrado'         => number_format($cobrado, 2, '.', ''),
            'pendiente'       => number_format($pendiente, 2, '.', ''),
            'utilidad'        => number_format($utilidad, 2, '.', ''),
        ];
    }

    /** Filas (sin cambios) */
    public static function get_rows($inicio, $fin, $categoria, $pago_estado, $doc_tipo, $q_quick, $q_adv) {
        global $wpdb;
        $ventas_table = $wpdb->prefix . 'vn_ventas';
        $emp_table    = $wpdb->prefix . 'cl_empresas';
        $fact_table   = $wpdb->prefix . 'vn_facturas';
        $pagos_table  = $wpdb->prefix . 'vn_pagos';

        $docs_sub  = self::docs_subquery($fact_table);
        $pagos_sub = self::pagos_subquery($pagos_table);

        [$where_sql, $args] = self::build_where($inicio, $fin, $categoria, $pago_estado, $doc_tipo, $q_quick, $q_adv);

        $tol = 0.05;

        $sql = "
            SELECT
              v.id,
              v.fecha_venta,
              v.total_bs,
              v.mes_correspondiente,
              c.nombre_legal,
              c.nit_id,
              c.tipo_cliente,
              COALESCE(d.has_factura,0) AS has_factura,
              COALESCE(d.has_recibo,0) AS has_recibo,
              d.nro_comprobante,
              d.ff,
              COALESCE(px.total_pagado,0) AS total_pagado,
              COALESCE(px.pagos_cnt,0) AS pagos_cnt,
              px.fp AS fp,
              CASE
                WHEN COALESCE(px.total_pagado,0) <= {$tol} THEN 'PENDIENTE'
                WHEN COALESCE(px.total_pagado,0) >= (COALESCE(v.total_bs,0) - {$tol}) THEN 'PAGADO'
                ELSE 'PAGO PARCIAL'
              END AS estado_pago
            FROM {$ventas_table} v
            JOIN {$emp_table} c ON v.cliente_id=c.id
            LEFT JOIN ({$docs_sub}) d ON d.venta_id=v.id
            LEFT JOIN ({$pagos_sub}) px ON px.venta_id=v.id
            {$where_sql}
            ORDER BY v.id DESC
            LIMIT 500
        ";

        $prepared = !empty($args) ? $wpdb->prepare($sql, $args) : $sql;
        return $wpdb->get_results($prepared);
    }

    /* =============================
       MÉTRICAS NUEVAS (datos reales)
       ============================= */

    public static function get_extra_metrics($inicio, $fin, $categoria, $q_quick, $q_adv): array {
        $out = [
            'cotizado_sin_v'    => '0.00',
            'cotizado_con_v'    => '0.00',
            'cashflow_egresos'  => '0.00',
            'cashflow_ingresos' => '0.00',
        ];

        $out = array_merge($out, self::get_quotes_totals_last_version($inicio, $fin, $categoria, $q_quick, $q_adv));
        $out = array_merge($out, self::get_cashflow_totals($inicio, $fin));

        return $out;
    }

    /** Totales cotizados con/sin venta usando SOLO la última versión por base_code */
    private static function get_quotes_totals_last_version($inicio, $fin, $categoria, $q_quick, $q_adv): array {
        global $wpdb;

        $tq = $wpdb->prefix . 'qt_cotizaciones';
        $tlinks = $wpdb->prefix . 'vn_ventas_quotes';
        $temp = $wpdb->prefix . 'cl_empresas';

        // Verificar tablas
        if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tq))) {
            return [ 'cotizado_sin_v' => '0.00', 'cotizado_con_v' => '0.00' ];
        }
        // si no existe tabla links, tratamos todo como sin venta
        $has_links = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tlinks));

        $categoria = self::normalize_categoria($categoria);
        $q_quick = self::normalize_query($q_quick);
        $q_adv = self::normalize_query($q_adv);

        $where = ["qt.estado='EMITIDA'", "qt.cot_codigo IS NOT NULL", "qt.cot_codigo<>''"]; 
        $args = [];

        // fecha: usamos fecha_emision
        if ($inicio && $fin) {
            $where[] = "qt.fecha_emision BETWEEN %s AND %s";
            $args[] = $inicio . ' 00:00:00';
            $args[] = $fin . ' 23:59:59';
        }

        if ($categoria !== 'TODAS') {
            $where[] = "UPPER(COALESCE(c.tipo_cliente,'')) = %s";
            $args[] = strtoupper($categoria);
        }

        if ($q_quick !== '') {
            $like = '%' . $wpdb->esc_like(strtoupper($q_quick)) . '%';
            $where[] = "(UPPER(COALESCE(c.nombre_legal,'')) LIKE %s OR UPPER(COALESCE(qt.cot_codigo,'')) LIKE %s)";
            $args[] = $like; $args[] = $like;
        }

        if ($q_adv !== '') {
            $like2 = '%' . $wpdb->esc_like(strtoupper($q_adv)) . '%';
            $where[] = "(UPPER(COALESCE(c.nombre_legal,'')) LIKE %s OR UPPER(COALESCE(c.nit_id,'')) LIKE %s OR UPPER(COALESCE(qt.cot_codigo,'')) LIKE %s)";
            $args[] = $like2; $args[] = $like2; $args[] = $like2;
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where);

        // base_code: si hay -Vn, usar SUBSTRING_INDEX por "-V", si no, queda igual
        $base_expr = "TRIM(SUBSTRING_INDEX(qt.cot_codigo, '-V', 1))";

        // Subquery de última versión por base_code
        $sub = "
            SELECT MAX(qt.id) AS last_id
            FROM {$tq} qt
            LEFT JOIN {$temp} c ON qt.cliente_id=c.id
            {$where_sql}
            GROUP BY {$base_expr}
        ";

        // Total sin venta
        if ($has_links) {
            $sql_sin = "
              SELECT COALESCE(SUM(COALESCE(q.total,0)),0)
              FROM {$tq} q
              WHERE q.id IN ({$sub})
                AND NOT EXISTS(
                    SELECT 1 FROM {$tlinks} l
                    WHERE (l.cotizacion_id = q.id) OR (l.cot_codigo IS NOT NULL AND l.cot_codigo<>'' AND l.cot_codigo = q.cot_codigo)
                )
            ";
            $sql_con = "
              SELECT COALESCE(SUM(COALESCE(q.total,0)),0)
              FROM {$tq} q
              WHERE q.id IN ({$sub})
                AND EXISTS(
                    SELECT 1 FROM {$tlinks} l
                    WHERE (l.cotizacion_id = q.id) OR (l.cot_codigo IS NOT NULL AND l.cot_codigo<>'' AND l.cot_codigo = q.cot_codigo)
                )
            ";
        } else {
            $sql_sin = "SELECT COALESCE(SUM(COALESCE(q.total,0)),0) FROM {$tq} q WHERE q.id IN ({$sub})";
            $sql_con = "SELECT 0";
        }

        $prepared_sin = !empty($args) ? $wpdb->prepare($sql_sin, $args) : $sql_sin;
        $prepared_con = !empty($args) ? $wpdb->prepare($sql_con, $args) : $sql_con;

        $sin = (float) $wpdb->get_var($prepared_sin);
        $con = (float) $wpdb->get_var($prepared_con);

        return [
            'cotizado_sin_v' => number_format($sin, 2, '.', ''),
            'cotizado_con_v' => number_format($con, 2, '.', ''),
        ];
    }

    /** Totales ingresos/egresos desde Cashflow */
    private static function get_cashflow_totals($inicio, $fin): array {
        global $wpdb;

        // tabla real según Settings si existe
        $table = $wpdb->prefix . 'cmb_cashflow_movimientos';
        if (class_exists('\CMBERP\Modules\Cashflow\Settings')) {
            $table = $wpdb->prefix . \CMBERP\Modules\Cashflow\Settings::TABLE_NAME;
        }

        if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
            return [ 'cashflow_egresos' => '0.00', 'cashflow_ingresos' => '0.00' ];
        }

        $where = 'WHERE 1=1';
        $args = [];
        if ($inicio && $fin) {
            $where .= " AND creado_en BETWEEN %s AND %s";
            $args[] = $inicio . ' 00:00:00';
            $args[] = $fin . ' 23:59:59';
        }

        $sql_e = "SELECT COALESCE(SUM(COALESCE(monto_bs,0)),0) FROM {$table} {$where} AND tipo='Egreso'";
        $sql_i = "SELECT COALESCE(SUM(COALESCE(monto_bs,0)),0) FROM {$table} {$where} AND tipo='Ingreso'";

        $prep_e = !empty($args) ? $wpdb->prepare($sql_e, $args) : $sql_e;
        $prep_i = !empty($args) ? $wpdb->prepare($sql_i, $args) : $sql_i;

        $eg = (float) $wpdb->get_var($prep_e);
        $ing = (float) $wpdb->get_var($prep_i);

        return [
            'cashflow_egresos'  => number_format($eg, 2, '.', ''),
            'cashflow_ingresos' => number_format($ing, 2, '.', ''),
        ];
    }
}

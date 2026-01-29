<?php
namespace CMBERP\Modules\Pagos;

if (!defined('ABSPATH')) { exit; }

final class Shortcode {
    public static function render(): string {
        if (!Logic::can_access()) {
            return '<div class="cmb-crm-card"><p style="color:#ef4444;font-weight:700;">⚠️ No tienes permisos para acceder a Pagos.</p></div>';
        }

        wp_enqueue_script('jquery');

        $can_upload = current_user_can('upload_files');
        if ($can_upload && function_exists('wp_enqueue_media')) {
            wp_enqueue_media();
        }

        // CSS legacy
        if (defined('CMB_ERP_URL') && defined('CMB_ERP_DIR')) {
            $css_rel = 'assets/css/cmb-crm-styles.css';
            if (file_exists(CMB_ERP_DIR . $css_rel)) {
                wp_enqueue_style('cmb-erp-legacy', CMB_ERP_URL . $css_rel, [], (string) filemtime(CMB_ERP_DIR . $css_rel));
            }
        }

        // JS módulo
        $base_url = defined('CMB_ERP_URL') ? CMB_ERP_URL : plugin_dir_url(__FILE__);
        $base_dir = defined('CMB_ERP_DIR') ? CMB_ERP_DIR : dirname(__DIR__, 3) . '/';
        $js_rel = 'modules/pagos/assets/js/pagos.js';
        $js_ver = file_exists($base_dir . $js_rel) ? (string) filemtime($base_dir . $js_rel) : '1.0.0';
        wp_enqueue_script('cmb-erp-pagos', $base_url . $js_rel, ['jquery'], $js_ver, true);

        // Vars legacy para JS
        $nonce = wp_create_nonce('crm_erp_nonce');
        $inline = "window.crm_vars = window.crm_vars || {};" .
            "window.crm_vars.ajaxurl = window.crm_vars.ajaxurl || '" . esc_js(admin_url('admin-ajax.php')) . "';" .
            "window.crm_vars.nonce = window.crm_vars.nonce || '" . esc_js($nonce) . "';" .
            "window.crm_vars.can_upload = " . ($can_upload ? 'true' : 'false') . ";";
        wp_add_inline_script('cmb-erp-pagos', $inline, 'before');

        global $wpdb;
        $t_ventas   = $wpdb->prefix . 'vn_ventas';
        $t_empresas = $wpdb->prefix . 'cl_empresas';
        $t_facturas = $wpdb->prefix . 'vn_facturas';
        $t_pagos    = $wpdb->prefix . 'vn_pagos';

        $ventas = $wpdb->get_results(" 
            SELECT
                v.id,
                v.total_bs,
                v.fecha_venta,
                c.nombre_legal,
                f.nro_comprobante,
                f.fecha_emision AS fecha_facturacion,
                COALESCE(px.total_pagado,0) AS total_pagado,
                px.ultima_fecha_pago AS ultima_fecha_pago,
                COALESCE(f.fecha_emision, DATE(v.fecha_venta)) AS fecha_base_alerta
            FROM $t_ventas v
            JOIN $t_empresas c ON v.cliente_id = c.id
            LEFT JOIN $t_facturas f ON v.id = f.venta_id
            LEFT JOIN (
                SELECT
                    p.venta_id,
                    COALESCE(SUM(p.monto_pagado),0) AS total_pagado,
                    MAX(p.fecha_pago) AS ultima_fecha_pago
                FROM $t_pagos p
                GROUP BY p.venta_id
            ) px ON px.venta_id = v.id
            ORDER BY v.id DESC
        ");

        $auditoria = $wpdb->get_results(" 
            SELECT p.*, c.nombre_legal
            FROM $t_pagos p
            JOIN $t_ventas v ON p.venta_id = v.id
            JOIN $t_empresas c ON v.cliente_id = c.id
            ORDER BY p.fecha_pago DESC
            LIMIT 25
        ");

        // Prefetch pagos por venta
        $pagos_por_venta = [];
        $ids = [];
        foreach ((array)$ventas as $vv) $ids[] = (int)$vv->id;
        $ids = array_values(array_unique(array_filter($ids)));
        if (!empty($ids)) {
            $chunks = array_chunk($ids, 200);
            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
                $sqlp = $wpdb->prepare(
                    "SELECT * FROM $t_pagos WHERE venta_id IN ($placeholders) ORDER BY venta_id ASC, fecha_pago ASC",
                    $chunk
                );
                $rows = $wpdb->get_results($sqlp);
                foreach ((array)$rows as $p) {
                    $vid = (int)$p->venta_id;
                    if (!isset($pagos_por_venta[$vid])) $pagos_por_venta[$vid] = [];
                    $pagos_por_venta[$vid][] = $p;
                }
            }
        }

        $default_dias = 0;
        $tol = 0.05;
        $today_ts = current_time('timestamp');

        ob_start();
        require __DIR__ . '/../templates/pagos-view.php';
        return (string) ob_get_clean();
    }
}

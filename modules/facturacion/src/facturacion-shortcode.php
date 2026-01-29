<?php

namespace CMBERP\Modules\Facturacion;

if (!defined('ABSPATH')) exit;

final class Shortcode {
    public static function register(): void {
        // Principal
        add_shortcode('cmb_invoicing', [__CLASS__, 'render']);
        // Compat opcional
        add_shortcode('registro_facturacion', [__CLASS__, 'render']);
    }

    private static function can_access(): bool {
        return current_user_can('administrator') || current_user_can('edit_posts');
    }

    public static function render($atts = [], $content = null, $tag = ''): string {
        if (!self::can_access()) {
            return '<div class="crm-card"><p style="color:#ef4444;font-weight:700;">⚠️ No tienes permisos para Facturación.</p></div>';
        }

        // Enqueue assets
        $base_url = defined('CMB_ERP_URL') ? CMB_ERP_URL : plugin_dir_url(__FILE__);
        $base_dir = defined('CMB_ERP_DIR') ? CMB_ERP_DIR : dirname(__DIR__, 3) . '/';

        $css_rel = 'modules/facturacion/assets/css/facturacion.css';
        $js_rel  = 'modules/facturacion/assets/js/facturacion.js';

        $css_ver = file_exists($base_dir . $css_rel) ? filemtime($base_dir . $css_rel) : '1.0.0';
        $js_ver  = file_exists($base_dir . $js_rel) ? filemtime($base_dir . $js_rel) : '1.0.0';

        wp_enqueue_style('cmb-erp-invoicing', $base_url . $css_rel, [], $css_ver);
        wp_enqueue_script('jquery');
        wp_enqueue_script('cmb-erp-invoicing', $base_url . $js_rel, ['jquery'], $js_ver, true);

        $nonce_action = defined('CMB_ERP_NONCE_ACTION') ? CMB_ERP_NONCE_ACTION : 'crm_erp_nonce';
        $nonce = wp_create_nonce($nonce_action);

        wp_localize_script('cmb-erp-invoicing', 'cmbInvoicingVars', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => $nonce,
            'actions' => [
                'save' => 'cmb_invoicing_save',
                'delete' => 'cmb_invoicing_delete',
            ]
        ]);

        $repo = new Repository();
        $ventas = $repo->list_ventas();
        $docs = $repo->list_docs();

        ob_start();
        $data = [
            'ventas' => $ventas,
            'docs' => $docs,
            'repo' => $repo,
        ];
        require __DIR__ . '/../templates/facturacion-view.php';
        return (string) ob_get_clean();
    }
}

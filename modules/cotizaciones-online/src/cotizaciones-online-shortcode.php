<?php

namespace CMBERP\Modules\CotizacionesOnline;

if (!defined('ABSPATH')) exit;

final class Shortcode {
    public static function register(): void {
        // Registro seguro (si ya existe, se sobrescribe)
        add_shortcode('cmb_quotes_table', [__CLASS__, 'render']);
    }

    private static function can_access(): bool {
        return current_user_can('administrator') || current_user_can('edit_posts');
    }

    public static function render($atts = [], $content = null, $tag = ''): string {
        if (!self::can_access()) {
            return '<div class="cmb-erp-root"><div class="cmb-erp-card"><p style="color:#ef4444;font-weight:800;">⚠️ No tienes permisos para ver cotizaciones.</p></div></div>';
        }

        $atts = shortcode_atts([
            'limit' => 500,
            'title' => '📋 Cotizaciones',
        ], (array)$atts, 'cmb_quotes_table');

        $limit = (int)$atts['limit'];
        $title = sanitize_text_field((string)$atts['title']);

        $repo = new Repository();
        $rows = $repo->list_quotes($limit);

        // Si hay error de tabla, mostrarlo.
        if (isset($rows['__error'])) {
            $msg = esc_html($rows['__error']);
            return '<div class="cmb-erp-root"><div class="cmb-erp-card"><p style="color:#ef4444;font-weight:800;">⚠️ ' . $msg . '</p></div></div>';
        }

        // Render template
        $nonce_action = defined('CMB_ERP_NONCE_ACTION') ? CMB_ERP_NONCE_ACTION : 'cmb_erp_nonce';
        $nonce = wp_create_nonce($nonce_action);

        ob_start();
        $template = __DIR__ . '/../templates/listado.php';
        $data = [
            'title' => $title,
            'rows' => $rows,
            'nonce' => $nonce,
        ];
        require $template;
        return (string)ob_get_clean();
    }
}

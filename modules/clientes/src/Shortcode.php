<?php
namespace CMBERP\Modules\Clientes;

if (!defined('ABSPATH')) { exit; }

final class Shortcode {

    private static function can_access(): bool {
        return current_user_can('administrator') || current_user_can('edit_posts');
    }

    private static function ver(string $relative): string {
        $full = defined('CMB_ERP_DIR') ? CMB_ERP_DIR . ltrim($relative, '/') : '';
        if ($full && file_exists($full)) return (string) filemtime($full);
        return defined('CMB_ERP_VERSION') ? (string) CMB_ERP_VERSION : '1.0.0';
    }

    private static function render_template(string $file, array $view = []): string {
        $path = __DIR__ . '/../templates/' . ltrim($file, '/');
        if (!is_file($path)) {
            return '<div class="cmb-erp-card"><p style="color:#ef4444;font-weight:800;">⚠️ Template no encontrado: ' . esc_html($file) . '</p></div>';
        }
        $view = $view;
        ob_start();
        include $path;
        return (string) ob_get_clean();
    }

    public static function render($atts = [], $content = null, $tag = ''): string {
        if (!self::can_access()) {
            return '<div class="cmb-erp-card"><p style="color:#ef4444;font-weight:800;">⚠️ No tienes permisos para acceder a Clientes.</p></div>';
        }

        if (defined('CMB_ERP_URL')) {
            $js_rel = 'modules/clientes/assets/js/clientes.js';

            wp_register_script('cmb-erp-clientes', CMB_ERP_URL . $js_rel, [], self::ver($js_rel), true);
            wp_localize_script('cmb-erp-clientes', 'cmbClientesVars', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => defined('CMB_ERP_NONCE_ACTION') ? wp_create_nonce(CMB_ERP_NONCE_ACTION) : wp_create_nonce('crm_erp_nonce'),
                'actions' => [
                    'save_company'     => 'cmb_clients_save_company',
                    'get_company'      => 'cmb_clients_get_company',
                    'delete_company'   => 'cmb_clients_delete_company',
                    'save_contact'     => 'cmb_clients_save_contact',
                    'get_contact'      => 'cmb_clients_get_contact',
                    'delete_contact'   => 'cmb_clients_delete_contact',
                    'search_portfolio' => 'cmb_clients_search_portfolio',
                    'list_recent'      => 'cmb_clients_list_recent',
                ],
            ]);
            wp_enqueue_script('cmb-erp-clientes');
        }

        return self::render_template('app.php', [
            'today' => date('Y-m-d'),
        ]);
    }
}

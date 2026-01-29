<?php
namespace CMBERP\Modules\Ventas;

if (!defined('ABSPATH')) { exit; }

final class Shortcode {

    private static function can_access(): bool {
        return current_user_can('administrator') || current_user_can('edit_posts');
    }

    private static function ver(string $relative): string {
        $full = defined('CMB_ERP_DIR') ? CMB_ERP_DIR . ltrim($relative, '/') : '';
        if ($full && file_exists($full)) {
            return (string) filemtime($full);
        }
        return defined('CMB_ERP_VERSION') ? (string) CMB_ERP_VERSION : '1.0.0';
    }

    private static function render_template(string $file, array $view = []): string {
        $path = __DIR__ . '/../templates/' . ltrim($file, '/');
        if (!is_file($path)) {
            return '<div class="cmb-erp-card"><p style="color:#ef4444;font-weight:800;">⚠️ Template no encontrado: ' . esc_html($file) . '</p></div>';
        }
        ob_start();
        include $path;
        return (string) ob_get_clean();
    }

    public static function render($atts = [], $content = null, $tag = ''): string {
        if (!self::can_access()) {
            return '<div class="cmb-erp-card"><p style="color:#ef4444;font-weight:800;">⚠️ No tienes permisos para acceder a Ventas.</p></div>';
        }

        if (defined('CMB_ERP_URL')) {
            $handle = 'cmb-erp-sales';
            $js_rel = 'modules/ventas/assets/js/sales.js';
            $css_rel = 'modules/ventas/assets/css/ventas-modals.css';

            if (!wp_script_is($handle, 'enqueued')) {
                wp_register_script($handle, CMB_ERP_URL . $js_rel, [], self::ver($js_rel), true);

                wp_localize_script($handle, 'cmbSalesVars', [
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce'   => defined('CMB_ERP_NONCE_ACTION') ? wp_create_nonce(CMB_ERP_NONCE_ACTION) : wp_create_nonce('crm_erp_nonce'),
                    'actions' => [
                        'search_clients' => 'cmb_sales_search_clients',
                        'list_services'  => 'cmb_sales_list_services',
                        'list_quotes'    => 'cmb_sales_list_emitted_quotes',
                        'get_quote'      => 'cmb_sales_get_quote_payload',
                        'save'           => 'cmb_sales_save',
                        'get'            => 'cmb_sales_get',
                        'delete'         => 'cmb_sales_delete',
                        'clone'          => 'cmb_sales_clone_manual',
                        'recurrence'     => 'cmb_sales_set_recurrence',
                        'history'        => 'cmb_sales_history',
                    ],
                    'ui' => [
                        'per_page'      => 20,
                        'history_limit' => 50,
                    ],
                ]);

                wp_enqueue_script($handle);

                // CSS para modales (elimina scroll lateral)
                if (defined('CMB_ERP_DIR') && file_exists(CMB_ERP_DIR . $css_rel)) {
                    // Dependencia opcional: si base tables no está registrado, WP igual encola.
                    wp_enqueue_style('cmb-erp-ventas-modals', CMB_ERP_URL . $css_rel, ['cmb-erp-tables'], self::ver($css_rel));
                }
            }
        }

        return self::render_template('app.php', [
            'today' => date('Y-m-d'),
        ]);
    }
}

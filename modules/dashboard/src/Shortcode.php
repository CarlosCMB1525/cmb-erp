<?php
namespace CMBERP\Modules\Dashboard;

if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/DashboardService.php';

final class Shortcode {

    public static function register(): void {
        add_shortcode('cmb_dashboard', [__CLASS__, 'render']);
    }

    private static function ver(string $relative): string {
        if (defined('CMB_ERP_DIR')) {
            $full = CMB_ERP_DIR . ltrim($relative, '/');
            if (is_file($full)) {
                return (string) filemtime($full);
            }
        }
        return defined('CMB_ERP_VERSION') ? (string) CMB_ERP_VERSION : '1.0.0';
    }

    private static function render_template(string $file, array $view = []): string {
        $path = __DIR__ . '/../templates/' . ltrim($file, '/');
        if (!is_file($path)) {
            return '<div class="cmb-erp-card"><p style="color:#ef4444;font-weight:900;">⚠️ Template no encontrado: ' . esc_html($file) . '</p></div>';
        }
        // Mantener compatibilidad con la versión funcional del dashboard (tabla)
        $view = $view;
        ob_start();
        include $path;
        return (string) ob_get_clean();
    }

    public static function render($atts = [], $content = null, $tag = ''): string {
        if (!DashboardService::can_access()) {
            return '<div class="cmb-erp-card"><p style="color:#ef4444;font-weight:900;">⚠️ No tienes permisos para acceder al Dashboard.</p></div>';
        }

        $inicio_default = date('Y-m-01');
        $fin_default = date('Y-m-t');

        global $wpdb;
        $categoria_default = 'TODAS';
        $pago_default = 'TODOS';
        $doc_default = 'TODOS';

        // Categorías desde clientes
        $emp_table = $wpdb->prefix . 'cl_empresas';
        $cats = $wpdb->get_col("SELECT DISTINCT UPPER(COALESCE(tipo_cliente,'EMPRESA')) AS tipo FROM {$emp_table} WHERE tipo_cliente IS NOT NULL AND tipo_cliente <> '' ORDER BY tipo ASC");
        if (!is_array($cats)) $cats = [];
        $cats = array_values(array_unique(array_filter($cats)));

        // Métricas iniciales
        $metrics = DashboardService::get_metrics($inicio_default, $fin_default, $categoria_default, $pago_default, $doc_default, '', '');
        $extra   = DashboardService::get_extra_metrics($inicio_default, $fin_default, $categoria_default, '', '');
        $rows    = DashboardService::get_rows($inicio_default, $fin_default, $categoria_default, $pago_default, $doc_default, '', '');

        // Assets
        if (defined('CMB_ERP_URL')) {
            // === Dashboard (tabla detalle) ===
            $js_rel  = 'modules/dashboard/assets/js/dashboard.js';
            $css_rel = 'modules/dashboard/assets/css/dashboard.css';

            wp_register_script('cmb-erp-dashboard', CMB_ERP_URL . $js_rel, [], self::ver($js_rel), true);
            wp_localize_script('cmb-erp-dashboard', 'cmbDashboardVars', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce(defined('CMB_ERP_NONCE_ACTION') ? CMB_ERP_NONCE_ACTION : 'cmb_erp_nonce'),
                'action'  => 'cmb_dashboard_filter',
                'defaults'=> [
                    'q_quick'     => '',
                    'q_adv'       => '',
                    'inicio'      => $inicio_default,
                    'fin'         => $fin_default,
                    'categoria'   => 'TODAS',
                    'doc_tipo'    => 'TODOS',
                    'pago_estado' => 'TODOS',
                ],
            ]);
            wp_enqueue_script('cmb-erp-dashboard');

            if (defined('CMB_ERP_DIR') && file_exists(CMB_ERP_DIR . $css_rel)) {
                wp_enqueue_style('cmb-erp-dashboard', CMB_ERP_URL . $css_rel, [], self::ver($css_rel));
            }

            // === Reports (P&L PDF) ===
            // Mantener enfoque modular (loader -> builder -> addon)
            // Para máxima estabilidad, usamos el loader probado del módulo Cotizaciones (CMBJsPDFLoader)
            // sin modificar el flujo del dashboard.

            $r_loader  = 'modules/cotizaciones/assets/js/jspdf-loader.js';
            $r_builder = 'modules/reports/assets/js/pnl-pdf-builder.js';
            $r_addon   = 'modules/reports/assets/js/pnl-dashboard-addon.js';

            $jspdf_urls = (array) apply_filters('cmb_erp_jspdf_urls', [
                'https://cdnjs.cloudflare.com/ajax/libs/jspdf/4.0.0/jspdf.umd.min.js',
                'https://unpkg.com/jspdf@latest/dist/jspdf.umd.min.js',
                'https://cdn.jsdelivr.net/npm/jspdf@4.0.0/dist/jspdf.umd.min.js',
            ]);

            // Loader (Cotizaciones)
            wp_register_script('cmb-erp-reports-jspdf-loader', CMB_ERP_URL . $r_loader, [], self::ver($r_loader), true);
            // El loader de Cotizaciones lee window.cmbQuotesVars.vendor.jspdf_urls
            wp_localize_script('cmb-erp-reports-jspdf-loader', 'cmbQuotesVars', [
                'vendor' => [
                    'jspdf_urls' => array_values(array_filter(array_map('esc_url_raw', $jspdf_urls))),
                ],
            ]);

            // Builder y Add-on de Reports
            wp_register_script('cmb-erp-reports-pnl-builder', CMB_ERP_URL . $r_builder, ['cmb-erp-reports-jspdf-loader'], self::ver($r_builder), true);
            wp_register_script('cmb-erp-reports-pnl-addon',   CMB_ERP_URL . $r_addon,   ['cmb-erp-reports-pnl-builder'], self::ver($r_addon), true);

            // Vars para el add-on (AJAX + urls)
            wp_localize_script('cmb-erp-reports-pnl-addon', 'cmbReportsVars', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce(defined('CMB_ERP_NONCE_ACTION') ? CMB_ERP_NONCE_ACTION : 'cmb_erp_nonce'),
                'actions' => [
                    'pnl_payload' => 'cmb_reports_pnl_payload',
                ],
                'vendor' => [
                    'jspdf_urls' => array_values(array_filter(array_map('esc_url_raw', $jspdf_urls))),
                ],
            ]);

            wp_enqueue_script('cmb-erp-reports-jspdf-loader');
            wp_enqueue_script('cmb-erp-reports-pnl-builder');
            wp_enqueue_script('cmb-erp-reports-pnl-addon');
        }

        return self::render_template('app.php', [
            'inicio_default' => $inicio_default,
            'fin_default'    => $fin_default,
            'cats'           => $cats,
            'metrics'        => array_merge($metrics, $extra),
            'rows'           => $rows,
        ]);
    }
}

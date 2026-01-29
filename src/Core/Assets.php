<?php
namespace CMBERP\Core;

if (!defined('ABSPATH')) exit;

class Assets {
    public static function register(): void {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_front'], 9999);
    }

    private static function ver(string $relativePath): string {
        $full = defined('CMB_ERP_DIR') ? CMB_ERP_DIR . ltrim($relativePath, '/') : '';
        if ($full && file_exists($full)) return (string) filemtime($full);
        return defined('CMB_ERP_VERSION') ? (string) CMB_ERP_VERSION : '1.0.0';
    }

    private static function nonce_action(): string {
        return defined('CMB_ERP_NONCE_ACTION') ? (string) CMB_ERP_NONCE_ACTION : 'crm_erp_nonce';
    }

    public static function enqueue_front(): void {
        if (is_admin()) return;
        global $post;
        if (!$post || empty($post->post_content)) return;

        $shortcodes = [
            'cmb_dashboard','cmb_customers','cmb_services','cmb_sales','cmb_quotes','cmb_quotes_table',
            'cmb_invoicing','cmb_payments','cmb_audit','cmb_vendor_payments'
        ];

        $need = false;
        foreach ($shortcodes as $sc) {
            if (has_shortcode($post->post_content, $sc)) { $need = true; break; }
        }
        if (!$need) return;

        // Base styles
        wp_enqueue_style('cmb-erp-brand', CMB_ERP_URL . 'assets/css/brand.css', [], self::ver('assets/css/brand.css'));
        wp_enqueue_style('cmb-erp-core-ui', CMB_ERP_URL . 'assets/css/core-ui.css', ['cmb-erp-brand'], self::ver('assets/css/core-ui.css'));
        wp_enqueue_style('cmb-erp-tables', CMB_ERP_URL . 'assets/css/tables.css', ['cmb-erp-core-ui'], self::ver('assets/css/tables.css'));

        // Legacy CSS
        if (file_exists(CMB_ERP_DIR . 'assets/css/cmb-crm-styles.css')) {
            wp_enqueue_style('cmb-erp-legacy', CMB_ERP_URL . 'assets/css/cmb-crm-styles.css', ['cmb-erp-tables'], self::ver('assets/css/cmb-crm-styles.css'));
        }

        // Always jQuery
        wp_enqueue_script('jquery');

        // =========================
        // Dashboard
        // =========================
        if (has_shortcode($post->post_content, 'cmb_dashboard')) {
            $css = 'modules/dashboard/assets/css/dashboard.css';
            $js  = 'modules/dashboard/assets/js/dashboard.js';
            if (file_exists(CMB_ERP_DIR . $css)) wp_enqueue_style('cmb-erp-dashboard', CMB_ERP_URL . $css, ['cmb-erp-tables'], self::ver($css));
            if (file_exists(CMB_ERP_DIR . $js)) wp_enqueue_script('cmb-erp-dashboard', CMB_ERP_URL . $js, ['jquery'], self::ver($js), true);
        }

        // =========================
        // Servicios
        // =========================
        if (has_shortcode($post->post_content, 'cmb_services')) {
            $css = 'modules/servicios/assets/css/servicios.css';
            $js  = 'modules/servicios/assets/js/servicios.js';
            if (file_exists(CMB_ERP_DIR . $css)) wp_enqueue_style('cmb-erp-services', CMB_ERP_URL . $css, ['cmb-erp-tables'], self::ver($css));
            if (file_exists(CMB_ERP_DIR . $js)) wp_enqueue_script('cmb-erp-services', CMB_ERP_URL . $js, ['jquery'], self::ver($js), true);
        }

        // =========================
        // Facturaci¨®n (HOTFIX)
        // - Asegura cmbInvoicingVars ANTES de que corra el JS.
        // =========================
        if (has_shortcode($post->post_content, 'cmb_invoicing')) {
            $css = 'modules/facturacion/assets/css/facturacion.css';
            $js  = 'modules/facturacion/assets/js/facturacion.js';

            if (file_exists(CMB_ERP_DIR . $css)) {
                wp_enqueue_style('cmb-erp-invoicing', CMB_ERP_URL . $css, ['cmb-erp-tables'], self::ver($css));
            }

            if (file_exists(CMB_ERP_DIR . $js)) {
                // Registramos para poder localize antes del print.
                wp_register_script('cmb-erp-invoicing', CMB_ERP_URL . $js, ['jquery'], self::ver($js), true);
                wp_localize_script('cmb-erp-invoicing', 'cmbInvoicingVars', [
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce(self::nonce_action()),
                    'actions' => [
                        'save' => 'cmb_invoicing_save',
                        'delete' => 'cmb_invoicing_delete',
                    ],
                ]);
                wp_enqueue_script('cmb-erp-invoicing');
            }
        }

        // =========================
        // Auditor¨ªa (HOTFIX)
        // - Asegura SheetJS + cmbAuditVars.
        // =========================
        if (has_shortcode($post->post_content, 'cmb_audit')) {
            // SheetJS CDN (necesario para export/import)
            if (!wp_script_is('sheetjs', 'enqueued')) {
                wp_enqueue_script('sheetjs', 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js', [], '0.18.5', true);
            }

            $css = 'modules/auditoria/assets/css/auditoria.css';
            $js  = 'modules/auditoria/assets/js/auditoria.js';

            if (file_exists(CMB_ERP_DIR . $css)) {
                wp_enqueue_style('cmb-erp-audit', CMB_ERP_URL . $css, ['cmb-erp-tables'], self::ver($css));
            }

            if (file_exists(CMB_ERP_DIR . $js)) {
                wp_register_script('cmb-erp-audit', CMB_ERP_URL . $js, ['jquery','sheetjs'], self::ver($js), true);
                global $wpdb;
                wp_localize_script('cmb-erp-audit', 'cmbAuditVars', [
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce(self::nonce_action()),
                    'wp_prefix' => $wpdb ? $wpdb->prefix : 'wp_',
                    'actions' => [
                        'load' => 'cmb_audit_load_table',
                        'save' => 'cmb_audit_save_row',
                        'del'  => 'cmb_audit_delete_row',
                        'truncate' => 'cmb_audit_truncate',
                        'import' => 'cmb_audit_import_json',
                        'export_all' => 'cmb_audit_export_all',
                    ],
                ]);
                wp_enqueue_script('cmb-erp-audit');
            }
        }

        // =========================
        // Cotizaciones Online (si ya te funciona, lo dejamos estable)
        // =========================
        if (has_shortcode($post->post_content, 'cmb_quotes_table')) {
            $css = 'modules/cotizaciones-online/assets/css/cotizaciones-online.css';
            $pdf_builder = 'modules/cotizaciones-online/assets/js/pdf-builder.js';
            $js  = 'modules/cotizaciones-online/assets/js/cotizaciones-online.js';

            if (file_exists(CMB_ERP_DIR . $css)) wp_enqueue_style('cmb-erp-quotes-online', CMB_ERP_URL . $css, ['cmb-erp-tables'], self::ver($css));

            if (!wp_script_is('jspdf', 'enqueued')) {
                wp_enqueue_script('jspdf', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', [], '2.5.1', true);
            }

            if (file_exists(CMB_ERP_DIR . $pdf_builder)) {
                wp_enqueue_script('cmb-erp-quotes-pdf-builder', CMB_ERP_URL . $pdf_builder, ['jspdf'], self::ver($pdf_builder), true);
            }

            if (file_exists(CMB_ERP_DIR . $js)) {
                $deps = ['jquery'];
                if (wp_script_is('cmb-erp-quotes-pdf-builder', 'enqueued')) $deps[] = 'cmb-erp-quotes-pdf-builder';
                wp_enqueue_script('cmb-erp-quotes-online', CMB_ERP_URL . $js, $deps, self::ver($js), true);
            }
        }

        // =========================
        // Cashflow
        // =========================
        if (has_shortcode($post->post_content, 'cmb_vendor_payments')) {
            $css = 'modules/cashflow/assets/css/cashflow.css';
            $js  = 'modules/cashflow/assets/js/cashflow.js';

            if (file_exists(CMB_ERP_DIR . $css)) {
                wp_enqueue_style('cmb-erp-cashflow', CMB_ERP_URL . $css, ['cmb-erp-tables'], self::ver($css));
            }

            if (file_exists(CMB_ERP_DIR . $js)) {
                // Localize: el JS depende de window.cmbCashflowVars (ajaxurl/nonce/cats/mets)
                $cats = get_option('cmb_cashflow_categorias_egreso', ['Mercader¨ªa','Servicios','Operativo','Impuestos','Otros']);
                $mets = get_option('cmb_cashflow_metodos_pago', ['Efectivo','QR','Transferencia','Tarjeta de Cr¨¦dito','Tarjeta Internacional']);
                $cats = array_values(array_filter(array_map('trim', (array)$cats)));
                $mets = array_values(array_filter(array_map('trim', (array)$mets)));
                if (empty($cats)) $cats = ['Mercader¨ªa','Servicios','Operativo','Impuestos','Otros'];
                if (empty($mets)) $mets = ['Efectivo','QR','Transferencia','Tarjeta de Cr¨¦dito','Tarjeta Internacional'];

                wp_register_script('cmb-erp-cashflow', CMB_ERP_URL . $js, ['jquery'], self::ver($js), true);
                wp_localize_script('cmb-erp-cashflow', 'cmbCashflowVars', [
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce'   => wp_create_nonce(self::nonce_action()),
                    'cats'    => $cats,
                    'mets'    => $mets,
                    'can_upload' => current_user_can('upload_files'),
                ]);
                wp_enqueue_script('cmb-erp-cashflow');
            }
        }
    }
}

<?php
namespace CMBERP\Modules\Auditoria;

if (!defined('ABSPATH')) { exit; }

final class Shortcode {
    public static function register(): void {
        add_shortcode('cmb_audit', [__CLASS__, 'render']);
        add_shortcode('sistema_auditoria', [__CLASS__, 'render']);
    }

    public static function render(): string {
        if (!Logic::can_access()) {
            return '<div class="crm-card"><p style="color:#ef4444;font-weight:800;">⚠️ No tienes permisos. Solo administrador.</p></div>';
        }

        // SheetJS (CDN) como el original
        if (!wp_script_is('sheetjs', 'enqueued')) {
            wp_enqueue_script('sheetjs', 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js', [], '0.18.5', true);
        }

        // Assets
        $base_url = defined('CMB_ERP_URL') ? CMB_ERP_URL : plugin_dir_url(__FILE__);
        $base_dir = defined('CMB_ERP_DIR') ? CMB_ERP_DIR : dirname(__DIR__, 3) . '/';
        $css_rel = 'modules/auditoria/assets/css/auditoria.css';
        $js_rel  = 'modules/auditoria/assets/js/auditoria.js';
        $css_ver = file_exists($base_dir . $css_rel) ? (string) filemtime($base_dir . $css_rel) : '1.0.0';
        $js_ver  = file_exists($base_dir . $js_rel)  ? (string) filemtime($base_dir . $js_rel)  : '1.0.0';

        wp_enqueue_style('cmb-erp-audit', $base_url . $css_rel, [], $css_ver);
        wp_enqueue_script('jquery');
        wp_enqueue_script('cmb-erp-audit', $base_url . $js_rel, ['jquery', 'sheetjs'], $js_ver, true);

        // Variables legacy EXACTAS del original (esto es lo que tu JS estable usa)
        global $wpdb;
        $nonce = wp_create_nonce('crm_erp_nonce');
        $inline = "window.crm_vars = window.crm_vars || {};".
            "window.crm_vars.ajaxurl = window.crm_vars.ajaxurl || '" . esc_js(admin_url('admin-ajax.php')) . "';".
            "window.crm_vars.nonce = window.crm_vars.nonce || '" . esc_js($nonce) . "';".
            "window.crm_vars.wp_prefix = window.crm_vars.wp_prefix || '" . esc_js($wpdb->prefix) . "';";
        wp_add_inline_script('cmb-erp-audit', $inline, 'before');

        // También exponemos acciones del módulo (por si el JS decide usar cmb_*)
        wp_localize_script('cmb-erp-audit', 'cmbAuditVars', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => $nonce,
            'wp_prefix' => $wpdb->prefix,
            'actions' => [
                'load' => 'auditoria_cargar_tabla',
                'save' => 'auditoria_guardar_fila',
                'del'  => 'auditoria_eliminar_fila',
                'truncate' => 'auditoria_reset_id',
                'import' => 'auditoria_importar_json',
                'export_all' => 'auditoria_exportar_todo',
                'test' => 'cmb_audit_test_table',
            ],
        ]);

        $tables = array_values(Logic::allowed_tables_map());
        sort($tables);

        ob_start();
        $data = ['tables' => $tables, 'prefix' => $wpdb->prefix];
        require __DIR__ . '/../templates/auditoria-view.php';
        return (string) ob_get_clean();
    }
}

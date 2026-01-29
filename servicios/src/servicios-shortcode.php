<?php
namespace CMBERP\Modules\Servicios;
if (!defined('ABSPATH')) exit;
final class Shortcode {
    public static function register(): void {
        add_shortcode('cmb_services', [__CLASS__, 'render']);
    }
    private static function can_access(): bool {
        return current_user_can('administrator') || current_user_can('edit_posts');
    }
    public static function render($atts = [], $content = null, $tag = ''): string {
        if (!self::can_access()) {
            return '<div class="cmb-erp-root"><div class="cmb-erp-card"><p style="color:#ef4444;font-weight:800;">⚠️ No tienes permisos para acceder a Servicios.</p></div></div>';
        }
        $repo = new Repository();
        $rows = $repo->list(200);
        $nonce_action = defined('CMB_ERP_NONCE_ACTION') ? CMB_ERP_NONCE_ACTION : 'cmb_erp_nonce';
        $nonce = wp_create_nonce($nonce_action);
        ob_start();
        $data = ['rows'=>$rows,'nonce'=>$nonce];
        require __DIR__ . '/../templates/servicios-view.php';
        return (string)ob_get_clean();
    }
}

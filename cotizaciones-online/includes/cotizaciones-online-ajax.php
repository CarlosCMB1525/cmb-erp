<?php

namespace CMBERP\Modules\CotizacionesOnline;

if (!defined('ABSPATH')) exit;

final class Ajax {
    public static function register(): void {
        add_action('wp_ajax_cmb_quotes_online_get', [__CLASS__, 'get_quote']);
        add_action('wp_ajax_cmb_quotes_online_logo', [__CLASS__, 'get_logo']);
        // Si deseas acceso público, habilita también nopriv:
        // add_action('wp_ajax_nopriv_cmb_quotes_online_get', [__CLASS__, 'get_quote']);
        // add_action('wp_ajax_nopriv_cmb_quotes_online_logo', [__CLASS__, 'get_logo']);
    }

    private static function can_access(): bool {
        return current_user_can('administrator') || current_user_can('edit_posts');
    }

    private static function nonce_ok(): bool {
        $nonce = $_POST['nonce'] ?? '';
        $nonce = is_string($nonce) ? $nonce : '';
        return ($nonce !== '') && wp_verify_nonce($nonce, defined('CMB_ERP_NONCE_ACTION') ? CMB_ERP_NONCE_ACTION : 'cmb_erp_nonce');
    }

    /**
     * Proxy seguro para obtener el logo como dataURL (evita problemas de CORS/mixed content).
     * POST: url
     */
    public static function get_logo(): void {
        if (!self::can_access()) {
            wp_send_json_error('No tienes permisos.');
        }
        if (!self::nonce_ok()) {
            wp_send_json_error('Nonce inválido.');
        }

        $url = isset($_POST['url']) ? esc_url_raw((string) wp_unslash($_POST['url'])) : '';
        if ($url === '') {
            wp_send_json_error('URL inválida.');
        }

        if (!preg_match('#^https?://#i', $url)) {
            wp_send_json_error('URL no permitida.');
        }

        $res = wp_remote_get($url, [
            'timeout' => 8,
            'redirection' => 3,
            'user-agent' => 'CMBERP/QuotesOnline'
        ]);

        if (is_wp_error($res)) {
            wp_send_json_error('No se pudo descargar el logo.');
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        if ($code < 200 || $code >= 300) {
            wp_send_json_error('No se pudo descargar el logo (HTTP ' . $code . ').');
        }

        $body = wp_remote_retrieve_body($res);
        if (!$body) {
            wp_send_json_error('Logo vacío.');
        }

        $ctype = (string) wp_remote_retrieve_header($res, 'content-type');
        $ctype = strtolower(trim(explode(';', $ctype)[0] ?? ''));
        $allowed = ['image/png','image/jpeg','image/jpg','image/webp','image/gif'];
        if (!in_array($ctype, $allowed, true)) {
            wp_send_json_error('Tipo de archivo no permitido: ' . $ctype);
        }

        $b64 = base64_encode($body);
        $dataUrl = 'data:' . $ctype . ';base64,' . $b64;

        wp_send_json_success(['dataUrl' => $dataUrl]);
    }

    public static function get_quote(): void {
        if (!self::can_access()) {
            wp_send_json_error('No tienes permisos.');
        }
        if (!self::nonce_ok()) {
            wp_send_json_error('Nonce inválido. Recarga la página.');
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $repo = new Repository();
        $payload = $repo->get_quote_payload($id);
        if (isset($payload['__error'])) {
            wp_send_json_error($payload['__error']);
        }

        wp_send_json_success($payload);
    }
}

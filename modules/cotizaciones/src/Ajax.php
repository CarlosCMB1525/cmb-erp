<?php
namespace CMBERP\Modules\Cotizaciones;

use CMBERP\Modules\Cotizaciones\Repositories\ClientsRepository;
use CMBERP\Modules\Cotizaciones\Repositories\ContactsRepository;
use CMBERP\Modules\Cotizaciones\Repositories\ServicesRepository;
use CMBERP\Modules\Cotizaciones\Domain\QuoteService;

if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/Repositories/ClientsRepository.php';
require_once __DIR__ . '/Repositories/ContactsRepository.php';
require_once __DIR__ . '/Repositories/ServicesRepository.php';
require_once __DIR__ . '/Domain/QuoteService.php';

final class Ajax {

    private static function can_access(): bool {
        return current_user_can('administrator') || current_user_can('edit_posts');
    }

    private static function nonce_ok(): bool {
        $nonce = $_POST['nonce'] ?? '';
        if (!is_string($nonce) || $nonce === '') return false;
        if (defined('CMB_ERP_NONCE_ACTION')) {
            return (bool) wp_verify_nonce($nonce, CMB_ERP_NONCE_ACTION);
        }
        return (bool) wp_verify_nonce($nonce, 'crm_erp_nonce');
    }

    private static function fail(string $msg, int $code = 400): void {
        wp_send_json_error($msg, $code);
    }

    private static function ok(array $data = []): void {
        wp_send_json_success($data);
    }

    public static function register(): void {
        add_action('wp_ajax_cmb_quotes_search_clients', [__CLASS__, 'search_clients']);
        add_action('wp_ajax_cmb_quotes_get_contacts', [__CLASS__, 'get_contacts']);
        add_action('wp_ajax_cmb_quotes_list_services', [__CLASS__, 'list_services']);
        add_action('wp_ajax_cmb_quotes_save_draft', [__CLASS__, 'save_draft']);
        add_action('wp_ajax_cmb_quotes_get', [__CLASS__, 'get_quote']);
        add_action('wp_ajax_cmb_quotes_list_versions', [__CLASS__, 'list_versions']);
        add_action('wp_ajax_cmb_quotes_emit', [__CLASS__, 'emit']);
        add_action('wp_ajax_cmb_quotes_delete', [__CLASS__, 'delete_quote']);
        add_action('wp_ajax_cmb_quotes_pdf_payload', [__CLASS__, 'pdf_payload']);
    }

    public static function search_clients(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.', 403);
        self::ok(ClientsRepository::search((string)($_POST['q'] ?? ''), (int)($_POST['page'] ?? 1), (int)($_POST['per_page'] ?? 20)));
    }

    public static function get_contacts(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.', 403);
        $empresa_id = absint($_POST['empresa_id'] ?? 0);
        if ($empresa_id <= 0) self::fail('Empresa inválida.');
        self::ok(['rows' => ContactsRepository::by_company($empresa_id), 'empresa_id' => $empresa_id]);
    }

    public static function list_services(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.', 403);
        self::ok(ServicesRepository::search((string)($_POST['q'] ?? ''), (int)($_POST['page'] ?? 1), (int)($_POST['per_page'] ?? 20)));
    }

    public static function save_draft(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.', 403);

        $payload = [
            'id' => absint($_POST['id'] ?? 0),
            'fecha' => (string)($_POST['fecha'] ?? ''),
            'moneda' => (string)($_POST['moneda'] ?? 'BOB'),
            'cliente_id' => absint($_POST['cliente_id'] ?? 0),
            'contacto_id' => absint($_POST['contacto_id'] ?? 0),
            'validez_sel' => (string)($_POST['validez_sel'] ?? '15'),
            'pago_sel' => (string)($_POST['pago_sel'] ?? '50_50'),
            'condiciones' => wp_unslash((string)($_POST['condiciones'] ?? '')),
            'groups' => wp_unslash((string)($_POST['groups'] ?? '[]')),
            'items' => wp_unslash((string)($_POST['items'] ?? '[]')),
        ];

        $res = QuoteService::save_draft($payload);
        if (!empty($res['error'])) self::fail((string)$res['error']);
        self::ok($res);
    }

    public static function get_quote(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.', 403);
        $id = absint($_POST['id'] ?? 0);
        $res = QuoteService::get_quote($id);
        if (!empty($res['error'])) self::fail((string)$res['error'], 404);
        self::ok($res);
    }

    public static function list_versions(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.', 403);
        $base = (string)($_POST['base'] ?? '');
        $limit = (int)($_POST['limit'] ?? 200);
        self::ok(QuoteService::list_versions($base, $limit));
    }

    public static function emit(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.', 403);
        $id = absint($_POST['id'] ?? 0);
        if ($id <= 0) self::fail('ID inválido.');
        $res = QuoteService::emit($id);
        if (!empty($res['error'])) self::fail((string)$res['error']);
        self::ok($res);
    }

    public static function delete_quote(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.', 403);
        $id = absint($_POST['id'] ?? 0);
        if ($id <= 0) self::fail('ID inválido.');
        $res = QuoteService::delete($id);
        if (!empty($res['error'])) self::fail((string)$res['error']);
        self::ok($res);
    }

    public static function pdf_payload(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.', 403);
        $id = absint($_POST['id'] ?? 0);
        if ($id <= 0) self::fail('ID inválido.');
        $res = QuoteService::pdf_payload($id);
        if (!empty($res['error'])) self::fail((string)$res['error']);
        self::ok($res);
    }
}

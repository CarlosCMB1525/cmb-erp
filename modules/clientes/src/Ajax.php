<?php
namespace CMBERP\Modules\Clientes;

use CMBERP\Modules\Clientes\Repositories\CompaniesRepository;
use CMBERP\Modules\Clientes\Repositories\ContactsRepository;
use CMBERP\Modules\Clientes\Repositories\PortfolioRepository;

if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/Installer.php';
require_once __DIR__ . '/Repositories/CompaniesRepository.php';
require_once __DIR__ . '/Repositories/ContactsRepository.php';
require_once __DIR__ . '/Repositories/PortfolioRepository.php';

final class Ajax {

    private static function can_access(): bool {
        return current_user_can('administrator') || current_user_can('edit_posts');
    }

    private static function nonce_ok(): bool {
        $nonce = $_POST['nonce'] ?? ($_POST['_wpnonce'] ?? '');
        if (!is_string($nonce) || $nonce === '') return false;
        $action = defined('CMB_ERP_NONCE_ACTION') ? CMB_ERP_NONCE_ACTION : 'crm_erp_nonce';
        return (bool) wp_verify_nonce($nonce, $action);
    }

    private static function fail(string $msg, int $code=400): void { wp_send_json_error($msg, $code); }
    private static function ok(array $data=[]): void { wp_send_json_success($data); }

    public static function register(): void {
        add_action('wp_ajax_cmb_clients_save_company', [__CLASS__, 'save_company']);
        add_action('wp_ajax_cmb_clients_get_company', [__CLASS__, 'get_company']);
        add_action('wp_ajax_cmb_clients_delete_company', [__CLASS__, 'delete_company']);

        add_action('wp_ajax_cmb_clients_save_contact', [__CLASS__, 'save_contact']);
        add_action('wp_ajax_cmb_clients_get_contact', [__CLASS__, 'get_contact']);
        add_action('wp_ajax_cmb_clients_delete_contact', [__CLASS__, 'delete_contact']);

        add_action('wp_ajax_cmb_clients_search_portfolio', [__CLASS__, 'search_portfolio']);
        add_action('wp_ajax_cmb_clients_list_recent', [__CLASS__, 'list_recent']);
    }

    public static function save_company(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.', 403);

        Installer::maybe_install();

        $id = absint($_POST['id'] ?? 0);
        $nombre = strtoupper(trim(sanitize_text_field(wp_unslash((string)($_POST['nombre_legal'] ?? '')))));
        $nit = strtoupper(trim(sanitize_text_field(wp_unslash((string)($_POST['nit_id'] ?? '')))));
        $razon = strtoupper(trim(sanitize_text_field(wp_unslash((string)($_POST['razon_social'] ?? '')))));
        $tipo = strtoupper(trim(sanitize_text_field(wp_unslash((string)($_POST['tipo_cliente'] ?? 'EMPRESA')))));

        if ($nombre === '') self::fail('Nombre Legal es obligatorio.');
        if ($nit === '') self::fail('NIT / ID es obligatorio.');

        // NIT duplicado permitido (sin validación)
        $data = [
            'nombre_legal' => $nombre,
            'nit_id' => $nit,
            'razon_social' => $razon,
            'tipo_cliente' => $tipo,
        ];

        $saved = CompaniesRepository::save($id, $data);
        if ($saved <= 0) self::fail('DB: no se pudo guardar.');

        self::ok([
            'id' => $saved,
            'msg' => ($id > 0) ? 'Empresa actualizada correctamente.' : 'Empresa registrada correctamente.',
        ]);
    }

    public static function get_company(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido.', 403);

        $id = absint($_POST['id'] ?? 0);
        if ($id <= 0) self::fail('ID inválido.');

        $row = CompaniesRepository::get($id);
        if (!$row) self::fail('Empresa no encontrada.', 404);

        self::ok(['empresa' => $row]);
    }

    public static function delete_company(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido.', 403);

        $id = absint($_POST['id'] ?? 0);
        if ($id <= 0) self::fail('ID inválido.');

        if (ContactsRepository::count_by_company($id) > 0) {
            self::fail('No se puede eliminar: tiene contactos. Elimina contactos primero.');
        }

        if (!CompaniesRepository::delete($id)) self::fail('DB: no se pudo eliminar.');
        self::ok(['msg' => 'Empresa eliminada.']);
    }

    public static function save_contact(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido.', 403);

        $id = absint($_POST['id'] ?? 0);
        $empresa_id = absint($_POST['empresa_id'] ?? 0);
        $nombre = strtoupper(trim(sanitize_text_field(wp_unslash((string)($_POST['nombre_contacto'] ?? '')))));
        $tel = sanitize_text_field(wp_unslash((string)($_POST['telefono_whatsapp'] ?? '')));
        $correo = sanitize_email(wp_unslash((string)($_POST['correo_electronico'] ?? '')));
        $cargo = strtoupper(trim(sanitize_text_field(wp_unslash((string)($_POST['cargo'] ?? '')))));

        if ($empresa_id <= 0) self::fail('Empresa inválida.');
        if ($nombre === '') self::fail('Nombre del contacto es obligatorio.');

        if ($correo !== '' && ContactsRepository::email_exists($correo, $id)) {
            self::fail('EL CORREO YA ESTÁ REGISTRADO.');
        }

        $data = [
            'empresa_id' => $empresa_id,
            'nombre_contacto' => $nombre,
            'telefono_whatsapp' => $tel,
            'correo_electronico' => $correo,
            'cargo' => $cargo,
        ];

        $saved = ContactsRepository::save($id, $data);
        if ($saved <= 0) self::fail('DB: no se pudo guardar.');

        self::ok(['msg' => ($id > 0) ? 'Contacto actualizado.' : 'Contacto registrado.']);
    }

    public static function get_contact(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido.', 403);

        $id = absint($_POST['id'] ?? 0);
        if ($id <= 0) self::fail('ID inválido.');

        $row = ContactsRepository::get($id);
        if (!$row) self::fail('Contacto no encontrado.', 404);

        self::ok(['contacto' => $row]);
    }

    public static function delete_contact(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido.', 403);

        $id = absint($_POST['id'] ?? 0);
        if ($id <= 0) self::fail('ID inválido.');

        if (!ContactsRepository::delete($id)) self::fail('DB: no se pudo eliminar.');
        self::ok(['msg' => 'Contacto eliminado.']);
    }

    public static function search_portfolio(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido.', 403);

        $q = sanitize_text_field(wp_unslash((string)($_POST['q'] ?? '')));
        $q = trim($q);

        $rows = PortfolioRepository::search($q, 200);
        self::ok(['tbody' => self::render_rows($rows)]);
    }

    public static function list_recent(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido.', 403);

        $rows = CompaniesRepository::list_recent(200);
        self::ok(['tbody' => self::render_rows($rows)]);
    }

    private static function render_rows(array $rows): string {
        global $wpdb;
        $t_con = $wpdb->prefix . 'cl_contactos';

        ob_start();
        if (!empty($rows)) {
            foreach ($rows as $e) {
                $eid = (int)($e['id'] ?? 0);
                $contactos = $eid ? $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$t_con} WHERE empresa_id=%d ORDER BY nombre_contacto ASC, id ASC",
                    $eid
                ), ARRAY_A) : [];
                include __DIR__ . '/../templates/partials/row.php';
            }
        } else {
            echo '<tr><td colspan="4" class="cmb-erp-text-muted" style="padding:20px;text-align:center;">No se encontraron resultados.</td></tr>';
        }
        return (string) ob_get_clean();
    }
}

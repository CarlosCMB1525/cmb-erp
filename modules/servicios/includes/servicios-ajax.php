<?php
namespace CMBERP\Modules\Servicios;
if (!defined('ABSPATH')) exit;
final class Ajax {
    public static function register(): void {
        add_action('wp_ajax_cmb_services_save', [__CLASS__, 'save']);
        add_action('wp_ajax_cmb_services_get', [__CLASS__, 'get']);
        add_action('wp_ajax_cmb_services_delete', [__CLASS__, 'delete']);
        add_action('wp_ajax_cmb_services_search', [__CLASS__, 'search']);
    }
    private static function can_access(): bool {
        return current_user_can('administrator') || current_user_can('edit_posts');
    }
    private static function nonce_ok(): bool {
        $nonce = $_POST['nonce'] ?? '';
        $nonce = is_string($nonce) ? $nonce : '';
        return ($nonce !== '') && wp_verify_nonce($nonce, defined('CMB_ERP_NONCE_ACTION') ? CMB_ERP_NONCE_ACTION : 'cmb_erp_nonce');
    }
    private static function fail(string $msg): void { wp_send_json_error($msg); }
    private static function ok(array $data=[]): void { wp_send_json_success($data); }

    public static function search(): void {
        if (!self::can_access()) self::fail('No tienes permisos.');
        if (!self::nonce_ok()) self::fail('Nonce inválido.');
        $q = isset($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '';
        $repo = new Repository();
        $rows = $repo->search($q, 200);
        ob_start();
        $data = ['rows' => $rows];
        require __DIR__ . '/../templates/servicios-tbody.php';
        $tbody = (string)ob_get_clean();
        self::ok(['tbody'=>$tbody,'count'=>count($rows)]);
    }

    public static function get(): void {
        if (!self::can_access()) self::fail('No tienes permisos.');
        if (!self::nonce_ok()) self::fail('Nonce inválido.');
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if ($id<=0) self::fail('ID inválido.');
        $repo = new Repository();
        $row = $repo->get($id);
        if (!$row) self::fail('No encontrado.');
        self::ok(['row'=>$row]);
    }

    public static function save(): void {
        if (!self::can_access()) self::fail('No tienes permisos.');
        if (!self::nonce_ok()) self::fail('Nonce inválido.');
        $repo = new Repository();
        $res = $repo->save([
            'id' => $_POST['id'] ?? 0,
            'nombre_servicio' => $_POST['nombre_servicio'] ?? '',
            'codigo_unico' => $_POST['codigo_unico'] ?? '',
            'tipo_servicio' => $_POST['tipo_servicio'] ?? 'UNICO',
            'detalle_tecnico' => $_POST['detalle_tecnico'] ?? '',
            'monto_unitario' => $_POST['monto_unitario'] ?? 0,
        ]);
        if (isset($res['__error'])) self::fail($res['__error']);
        self::ok($res);
    }

    public static function delete(): void {
        if (!self::can_access()) self::fail('No tienes permisos.');
        if (!self::nonce_ok()) self::fail('Nonce inválido.');
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $repo = new Repository();
        $res = $repo->delete($id);
        if (isset($res['__error'])) self::fail($res['__error']);
        self::ok($res);
    }
}

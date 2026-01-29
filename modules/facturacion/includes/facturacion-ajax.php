<?php

namespace CMBERP\Modules\Facturacion;

if (!defined('ABSPATH')) exit;

final class Ajax {
    public static function register(): void {
        add_action('wp_ajax_cmb_invoicing_save', [__CLASS__, 'save']);
        add_action('wp_ajax_cmb_invoicing_delete', [__CLASS__, 'delete']);
    }

    private static function can_access(): bool {
        return current_user_can('administrator') || current_user_can('edit_posts');
    }

    private static function nonce_ok(): bool {
        $nonce = $_POST['nonce'] ?? '';
        $nonce = is_string($nonce) ? $nonce : '';
        $action = defined('CMB_ERP_NONCE_ACTION') ? CMB_ERP_NONCE_ACTION : 'crm_erp_nonce';
        return ($nonce !== '') && wp_verify_nonce($nonce, $action);
    }

    private static function fail(string $msg): void { wp_send_json_error($msg); }
    private static function ok(array $data = []): void { wp_send_json_success($data); }

    public static function save(): void {
        if (!self::can_access()) self::fail('No tienes permisos para Facturación.');
        if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.');

        $repo = new Repository();

        $venta_id = (int)($_POST['v_id'] ?? 0);
        $tipo = sanitize_text_field((string)($_POST['tipo'] ?? ''));
        $nro  = sanitize_text_field((string)($_POST['nro'] ?? ''));
        $fec  = $repo->safe_date((string)($_POST['fec'] ?? ''));
        $mon  = (float)($_POST['mon'] ?? 0);

        if ($venta_id <= 0) self::fail('ID de venta inválido.');
        if ($tipo !== 'Factura' && $tipo !== 'Recibo') self::fail('Tipo de documento inválido.');
        if (!$fec) self::fail('Fecha inválida (YYYY-MM-DD).');
        if ($mon <= 0) self::fail('Monto inválido.');

        if (!$repo->venta_existe($venta_id)) self::fail('La venta no existe.');
        if ($repo->venta_tiene_doc($venta_id)) self::fail('Esta venta ya tiene documento. Elimina el registro primero si deseas cambiarlo.');

        if ($tipo === 'Recibo') {
            $nro = 'SIN FACTURA';
        } else {
            if (trim($nro) === '') self::fail('Para Factura debes ingresar un número.');
            if ($repo->factura_duplicada_en_anio($nro, $fec)) {
                $anio = (int) date('Y', strtotime($fec));
                self::fail("Número de factura duplicado en el año {$anio}.");
            }
        }

        $res = $repo->insert_doc($venta_id, $tipo, $nro, $fec, $mon);
        if (isset($res['__error'])) self::fail($res['__error']);
        self::ok($res);
    }

    public static function delete(): void {
        if (!self::can_access()) self::fail('No tienes permisos.');
        if (!self::nonce_ok()) self::fail('Nonce inválido.');

        $repo = new Repository();
        $id = (int)($_POST['f_id'] ?? 0);
        if ($id <= 0) self::fail('ID inválido.');

        $res = $repo->delete_doc($id);
        if (isset($res['__error'])) self::fail($res['__error']);
        self::ok();
    }
}

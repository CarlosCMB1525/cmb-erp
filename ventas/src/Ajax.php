<?php
namespace CMBERP\Modules\Ventas;

use CMBERP\Modules\Ventas\Domain\PeriodService;
use CMBERP\Modules\Ventas\Domain\SaleItemsService;
use CMBERP\Modules\Ventas\Repositories\ClientsRepository;
use CMBERP\Modules\Ventas\Repositories\ServicesRepository;
use CMBERP\Modules\Ventas\Repositories\SalesRepository;
use CMBERP\Modules\Ventas\Repositories\RelationsRepository;
use CMBERP\Modules\Ventas\Repositories\GuardsRepository;
use CMBERP\Modules\Ventas\Repositories\QuotesRepository;

if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/Domain/PeriodService.php';
require_once __DIR__ . '/Domain/SaleItemsService.php';
require_once __DIR__ . '/Repositories/ClientsRepository.php';
require_once __DIR__ . '/Repositories/ServicesRepository.php';
require_once __DIR__ . '/Repositories/SalesRepository.php';
require_once __DIR__ . '/Repositories/RelationsRepository.php';
require_once __DIR__ . '/Repositories/GuardsRepository.php';
require_once __DIR__ . '/Repositories/QuotesRepository.php';

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
        add_action('wp_ajax_cmb_sales_search_clients', [__CLASS__, 'search_clients']);
        add_action('wp_ajax_cmb_sales_list_services', [__CLASS__, 'list_services']);
        add_action('wp_ajax_cmb_sales_list_emitted_quotes', [__CLASS__, 'list_emitted_quotes']);
        add_action('wp_ajax_cmb_sales_get_quote_payload', [__CLASS__, 'get_quote_payload']);
        add_action('wp_ajax_cmb_sales_save', [__CLASS__, 'save']);
        add_action('wp_ajax_cmb_sales_get', [__CLASS__, 'get_sale']);
        add_action('wp_ajax_cmb_sales_delete', [__CLASS__, 'delete_sale']);
        add_action('wp_ajax_cmb_sales_clone_manual', [__CLASS__, 'clone_manual']);
        add_action('wp_ajax_cmb_sales_set_recurrence', [__CLASS__, 'set_recurrence']);
        add_action('wp_ajax_cmb_sales_history', [__CLASS__, 'history']);
    }

    public static function search_clients(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.', 403);
        $q = (string)($_POST['q'] ?? '');
        $page = (int)($_POST['page'] ?? 1);
        $per = (int)($_POST['per_page'] ?? 20);
        self::ok(ClientsRepository::search($q, $page, $per));
    }

    public static function list_services(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.', 403);
        $q = (string)($_POST['q'] ?? '');
        $page = (int)($_POST['page'] ?? 1);
        $per = (int)($_POST['per_page'] ?? 20);
        self::ok(ServicesRepository::search($q, $page, $per));
    }

    public static function list_emitted_quotes(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.', 403);
        $q = (string)($_POST['q'] ?? '');
        $page = (int)($_POST['page'] ?? 1);
        $per = (int)($_POST['per_page'] ?? 20);
        self::ok(QuotesRepository::list_emitted($q, $page, $per));
    }

    public static function get_quote_payload(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.', 403);
        $id = absint($_POST['id'] ?? 0);
        if ($id <= 0) self::fail('ID inválido.');
        $payload = QuotesRepository::get_payload($id);
        if (!$payload) self::fail('Cotización no encontrada.', 404);
        // Convert items to sales format (legacy)
        $salesItems = SaleItemsService::from_quote_items($payload['items']);
        $calc = SaleItemsService::sanitize_and_total($salesItems);
        self::ok([
            'quote' => $payload['quote'],
            'client' => $payload['client'],
            'items' => $calc['items'],
            'total' => $calc['total'],
        ]);
    }

    public static function save(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.', 403);

        $id = absint($_POST['id'] ?? 0);
        $cliente_id = absint($_POST['cliente_id'] ?? 0);
        if ($cliente_id <= 0) self::fail('Cliente inválido.');

        $tipo = strtoupper(sanitize_text_field((string)($_POST['tipo_contrato'] ?? 'UNICO')));
        if (!in_array($tipo, ['UNICO','MENSUAL'], true)) $tipo = 'UNICO';
        $meses = absint($_POST['meses'] ?? 1);
        if ($meses < 1) $meses = 1;

        $items_raw = $_POST['items'] ?? '[]';
        $items_res = SaleItemsService::sanitize_and_total($items_raw);
        if (!$items_res['ok'] || empty($items_res['items'])) self::fail($items_res['error'] ?: 'Agrega al menos un ítem.');

        $total = (float)$items_res['total'];

        // Si editamos, preservar estado si estaba PAGADO/PARCIAL
        $estado_final = 'Pendiente';
        if ($id > 0) {
            $prev = SalesRepository::get($id);
            if (!$prev) self::fail('Venta no encontrada.', 404);
            $ea = strtoupper(trim((string)($prev['estado'] ?? '')));
            if ($ea === 'PAGADO' || $ea === 'PAGADO') $estado_final = 'Pagado';
            elseif ($ea === 'PARCIAL') $estado_final = 'Parcial';
        }

        $now = current_time('mysql');
        $periodo = PeriodService::periodo_literal($now);

        $data = [
            'cliente_id' => $cliente_id,
            'total_bs' => round($total, 2),
            'estado' => $estado_final,
            'tipo_contrato' => $tipo,
            'meses' => $meses,
            'detalles' => $items_res['json'],
        ];

        if ($id > 0) {
            $ok = SalesRepository::update($id, $data);
            if (!$ok) self::fail('DB: no se pudo actualizar.');
            $venta_id = $id;
        } else {
            $data['fecha_venta'] = $now;
            $data['mes_correspondiente'] = $periodo;
            $data['estado'] = 'Pendiente';
            $venta_id = SalesRepository::insert($data);
            if ($venta_id <= 0) self::fail('DB: no se pudo insertar.');
        }

        // Link optional to quote
        $quote_id = absint($_POST['quote_id'] ?? 0);
        $quote_code = sanitize_text_field((string)($_POST['quote_code'] ?? ''));
        if ($quote_id > 0 || $quote_code !== '') {
            RelationsRepository::upsert($venta_id, $quote_id > 0 ? $quote_id : null, $quote_code !== '' ? $quote_code : null);
        } else {
            // If user cleared link
            RelationsRepository::delete_by_sale($venta_id);
        }

        self::ok(['id'=>$venta_id, 'total'=>round($total,2)]);
    }

    public static function get_sale(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.', 403);
        $id = absint($_POST['id'] ?? 0);
        if ($id<=0) self::fail('ID inválido.');
        $row = SalesRepository::get($id);
        if (!$row) self::fail('Venta no encontrada.', 404);
        $link = RelationsRepository::get_by_sale($id);
        $items = [];
        if (!empty($row['detalles'])) {
            $items_res = SaleItemsService::sanitize_and_total((string)$row['detalles']);
            if ($items_res['ok']) $items = $items_res['items'];
        }
        self::ok([
            'sale' => $row,
            'items' => $items,
            'link' => $link,
            'client' => !empty($row['cliente_id']) ? ClientsRepository::get((int)$row['cliente_id']) : null,
        ]);
    }

    public static function delete_sale(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.', 403);
        $id = absint($_POST['id'] ?? 0);
        if ($id<=0) self::fail('ID inválido.');
        if (GuardsRepository::has_payments($id) || GuardsRepository::has_invoices($id)) {
            self::fail('No se puede eliminar: la venta tiene pagos o documentos asociados.');
        }
        RelationsRepository::delete_by_sale($id);
        $ok = SalesRepository::delete($id);
        if (!$ok) self::fail('DB: no se pudo eliminar.');
        self::ok(['deleted'=>true,'id'=>$id]);
    }

    public static function clone_manual(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.', 403);

        $id_original = absint($_POST['id'] ?? 0);
        $fecha = sanitize_text_field((string)($_POST['fecha'] ?? ''));
        if ($id_original<=0) self::fail('ID de venta inválido.');
        if (!PeriodService::validate_date_ymd($fecha)) self::fail('Fecha inválida. Usa YYYY-MM-DD.');

        $original = SalesRepository::get($id_original);
        if (!$original) self::fail('La venta original no existe.', 404);
        $cliente_id = (int)($original['cliente_id'] ?? 0);
        if ($cliente_id<=0) self::fail('Venta original sin cliente.');

        $mes_sel = (int) date('m', strtotime($fecha));
        $anio_sel = (int) date('Y', strtotime($fecha));
        $existe = SalesRepository::exists_sale_for_client_month($cliente_id, $mes_sel, $anio_sel);
        if ($existe > 0) self::fail("Ya existe una venta para este cliente en el periodo {$mes_sel}/{$anio_sel}.");

        $parent_id = !empty($original['venta_maestra_id']) ? (int)$original['venta_maestra_id'] : (int)$original['id'];
        $fecha_final = $fecha . ' ' . current_time('H:i:s');
        $periodo = PeriodService::periodo_literal($fecha);

        $data = [
            'cliente_id' => $cliente_id,
            'total_bs' => (float)($original['total_bs'] ?? 0),
            'estado' => 'Pendiente',
            'tipo_contrato' => sanitize_text_field((string)($original['tipo_contrato'] ?? 'UNICO')),
            'dia_facturacion' => !empty($original['dia_facturacion']) ? (int)$original['dia_facturacion'] : 0,
            'meses' => !empty($original['meses']) ? (int)$original['meses'] : 1,
            'detalles' => (string)($original['detalles'] ?? '[]'),
            'fecha_venta' => $fecha_final,
            'venta_maestra_id' => $parent_id,
            'DIA_RECURRENTE_CLON_VENTA' => !empty($original['dia_facturacion']) ? (int)$original['dia_facturacion'] : 0,
            'mes_correspondiente' => $periodo,
        ];

        $new_id = SalesRepository::insert($data);
        if ($new_id<=0) self::fail('DB: error al clonar.');

        // Clonar link si existía
        $link = RelationsRepository::get_by_sale($id_original);
        if ($link) {
            RelationsRepository::upsert($new_id, !empty($link['cotizacion_id']) ? (int)$link['cotizacion_id'] : null, !empty($link['cot_codigo']) ? (string)$link['cot_codigo'] : null);
        }

        self::ok(['id'=>$new_id,'venta_maestra_id'=>$parent_id]);
    }

    public static function set_recurrence(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.', 403);
        $id = absint($_POST['id'] ?? 0);
        $dia = absint($_POST['dia'] ?? 0);
        if ($id<=0) self::fail('ID inválido.');
        if ($dia<1 || $dia>28) self::fail('Día inválido (1-28).');
        $ok = SalesRepository::update_recurrence($id, $dia);
        if (!$ok) self::fail('DB: no se pudo actualizar recurrencia.');
        self::ok(['id'=>$id,'dia'=>$dia]);
    }

    public static function history(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.', 403);
        $limit = absint($_POST['limit'] ?? 50);
        $rows = SalesRepository::list_recent($limit);
        // attach links
        $out = [];
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            $link = $id>0 ? RelationsRepository::get_by_sale($id) : null;
            $r['quote_code'] = $link['cot_codigo'] ?? '';
            $r['quote_id'] = isset($link['cotizacion_id']) ? (int)$link['cotizacion_id'] : 0;
            $out[] = $r;
        }
        self::ok(['rows'=>$out]);
    }
}

<?php
namespace CMBERP\Modules\Cotizaciones\Domain;

use CMBERP\Modules\Cotizaciones\Installer;
use CMBERP\Modules\Cotizaciones\Repositories\QuotesRepository;
use CMBERP\Modules\Cotizaciones\Repositories\ClientsRepository;
use CMBERP\Modules\Cotizaciones\Repositories\QuoteGroupsRepository;
use CMBERP\Modules\Cotizaciones\Repositories\QuoteItemsRepository;

if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/../Installer.php';
require_once __DIR__ . '/Validators.php';
require_once __DIR__ . '/QuoteTotalsService.php';
require_once __DIR__ . '/QuoteVersionService.php';
require_once __DIR__ . '/PdfPayloadService.php';
require_once __DIR__ . '/../Repositories/QuotesRepository.php';
require_once __DIR__ . '/../Repositories/ClientsRepository.php';
require_once __DIR__ . '/../Repositories/QuoteGroupsRepository.php';
require_once __DIR__ . '/../Repositories/QuoteItemsRepository.php';

final class QuoteService {

    public static function save_draft(array $payload): array {
        Installer::maybe_install();

        $id = absint($payload['id'] ?? 0);
        $cliente_id = absint($payload['cliente_id'] ?? 0);
        $contacto_id = absint($payload['contacto_id'] ?? 0);
        if ($cliente_id <= 0) {
            return ['error' => 'Cliente inválido.'];
        }

        $fecha = Validators::date_or_today((string)($payload['fecha'] ?? ''));
        $moneda = Validators::currency((string)($payload['moneda'] ?? 'BOB'));
        $validez_sel = Validators::text((string)($payload['validez_sel'] ?? '15'), 50);
        $pago_sel = Validators::text((string)($payload['pago_sel'] ?? '50_50'), 50);
        $condiciones = wp_kses_post((string)($payload['condiciones'] ?? ''));

        $items = $payload['items'] ?? [];
        if (is_string($items)) {
            $items = json_decode(wp_unslash($items), true);
        }
        if (!is_array($items) || empty($items)) {
            return ['error' => 'Agrega al menos un ítem.'];
        }

        $groups = $payload['groups'] ?? [];
        if (is_string($groups)) {
            $groups = json_decode(wp_unslash($groups), true);
        }
        if (!is_array($groups)) $groups = [];

        // Si no hay grupos, crear uno default y asignar group_key
        if (empty($groups)) {
            $groups = [[ 'id' => 0, 'key' => 'g_default', 'tipo' => 'UNICO', 'titulo' => 'Únicos', 'orden' => 1 ]];
            foreach ($items as &$it) {
                if (is_array($it) && empty($it['group_key'])) $it['group_key'] = 'g_default';
            }
            unset($it);
        }

        $calc = QuoteTotalsService::sanitize_items($items);
        $safe_items = $calc['items'];
        if (empty($safe_items)) {
            return ['error' => 'Agrega al menos un ítem válido.'];
        }

        $emit_dt = $fecha . ' ' . current_time('H:i:s');

        $data = [
            'fecha_emision' => $emit_dt,
            'cliente_id' => $cliente_id,
            'contacto_id' => ($contacto_id > 0) ? $contacto_id : null,
            'validez_sel' => $validez_sel,
            'pago_sel' => $pago_sel,
            'condiciones' => $condiciones,
            'moneda' => $moneda,
            'subtotal' => $calc['subtotal'],
            'descuento' => 0,
            'impuestos' => 0,
            'total' => $calc['total'],
            'estado' => 'BORRADOR',
        ];

        $new_id = QuotesRepository::upsert_draft($data, $id);
        if ($new_id <= 0) {
            return ['error' => 'DB: error al guardar cabecera.'];
        }

        // Sincronizar grupos y obtener mapa key=>id
        $map = QuoteGroupsRepository::sync_groups($new_id, $groups);
        if (empty($map)) {
            return ['error' => 'DB: no se pudo crear grupo.'];
        }

        // Preparar items con grupo_id
        $final_items = [];
        foreach ($safe_items as $it) {
            $gkey = (string)($it['group_key'] ?? '');
            $gid = absint($it['grupo_id'] ?? 0);
            if ($gid <= 0 && $gkey !== '' && isset($map[$gkey])) {
                $gid = (int)$map[$gkey];
            }
            if ($gid <= 0) {
                // fallback: primer grupo
                $gid = (int) reset($map);
            }
            $it['grupo_id'] = $gid;
            $final_items[] = $it;
        }

        QuoteItemsRepository::delete_by_quote($new_id);
        $ok = QuoteItemsRepository::insert_many($new_id, $final_items);
        if (!$ok) {
            return ['error' => 'DB: error al guardar ítems.'];
        }

        return ['id' => $new_id, 'subtotal' => $calc['subtotal'], 'total' => $calc['total']];
    }

    public static function get_quote(int $id): array {
        Installer::maybe_install();
        $id = absint($id);
        if ($id <= 0) return ['error' => 'ID inválido.'];

        $cot = QuotesRepository::get($id);
        if (!$cot) return ['error' => 'Cotización no encontrada.'];

        $cliente = !empty($cot['cliente_id']) ? ClientsRepository::get((int)$cot['cliente_id']) : null;
        $groups = QuoteGroupsRepository::list_by_quote($id);
        if (empty($groups)) {
            $gid = QuoteGroupsRepository::ensure_default_group($id);
            if ($gid > 0) $groups = QuoteGroupsRepository::list_by_quote($id);
        }

        $items_rows = QuoteItemsRepository::list_by_quote($id);
        $items = QuoteItemsRepository::normalize_for_js($items_rows);

        return [
            'cotizacion' => $cot,
            'cliente' => $cliente,
            'groups' => $groups,
            'items' => $items,
        ];
    }

    public static function list_versions(string $base, int $limit): array {
        Installer::maybe_install();
        return QuotesRepository::list_versions($base, $limit);
    }

    public static function emit(int $id): array {
        return QuoteVersionService::emit($id);
    }

    public static function delete(int $id): array {
        Installer::maybe_install();
        $id = absint($id);
        if ($id <= 0) return ['error' => 'ID inválido.'];

        $cot = QuotesRepository::get($id);
        if (!$cot) return ['error' => 'Cotización no encontrada.'];

        QuoteItemsRepository::delete_by_quote($id);
        QuoteGroupsRepository::delete_by_quote($id);
        $ok = QuotesRepository::delete($id);
        if (!$ok) return ['error' => 'DB: no se pudo eliminar.'];

        return ['id' => $id, 'deleted' => true];
    }

    public static function pdf_payload(int $id): array {
        $res = PdfPayloadService::build($id);
        if (!empty($res['error'])) return $res;
        return $res;
    }
}

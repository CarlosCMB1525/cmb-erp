<?php
namespace CMBERP\Modules\Cotizaciones\Domain;

use CMBERP\Modules\Cotizaciones\Installer;
use CMBERP\Modules\Cotizaciones\Repositories\ClientsRepository;
use CMBERP\Modules\Cotizaciones\Repositories\ContactsRepository;
use CMBERP\Modules\Cotizaciones\Repositories\QuotesRepository;
use CMBERP\Modules\Cotizaciones\Repositories\QuoteItemsRepository;
use CMBERP\Modules\Cotizaciones\Repositories\QuoteGroupsRepository;

if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/../Installer.php';
require_once __DIR__ . '/../Repositories/QuotesRepository.php';
require_once __DIR__ . '/../Repositories/ClientsRepository.php';
require_once __DIR__ . '/../Repositories/ContactsRepository.php';
require_once __DIR__ . '/../Repositories/QuoteItemsRepository.php';
require_once __DIR__ . '/../Repositories/QuoteGroupsRepository.php';

final class PdfPayloadService {

    public static function build(int $id): array {
        Installer::maybe_install();

        $id = absint($id);
        if ($id <= 0) return ['error' => 'ID inválido.'];

        $cot = QuotesRepository::get($id);
        if (!$cot) return ['error' => 'Cotización no encontrada.'];

        $cliente = !empty($cot['cliente_id']) ? ClientsRepository::get((int)$cot['cliente_id']) : null;
        $contactos = (!empty($cot['cliente_id'])) ? ContactsRepository::by_company((int)$cot['cliente_id']) : [];
        $contacto = null;
        if (!empty($cot['contacto_id'])) {
            $cid = (int)$cot['contacto_id'];
            foreach ($contactos as $c) {
                if ((int)($c['id'] ?? 0) === $cid) { $contacto = $c; break; }
            }
        }

        $groups = QuoteGroupsRepository::list_by_quote($id);
        $items_rows = QuoteItemsRepository::list_by_quote($id);

        $items = [];
        foreach ($items_rows as $it) {
            $items[] = [
                'grupo_id' => !empty($it['grupo_id']) ? (int)$it['grupo_id'] : 0,
                'codigo' => (string)($it['codigo_servicio'] ?? ''),
                // Mantener descripción completa (puede venir con HTML de detalle técnico)
                'descripcion' => (string)($it['descripcion'] ?? $it['nombre_servicio'] ?? ''),
                'cantidad' => isset($it['cantidad']) ? (float)$it['cantidad'] : 1,
                'precio_unitario' => isset($it['precio_unitario']) ? (float)$it['precio_unitario'] : 0,
                'subtotal' => isset($it['subtotal_item']) ? (float)$it['subtotal_item'] : ((float)($it['cantidad'] ?? 1) * (float)($it['precio_unitario'] ?? 0)),
            ];
        }

        $company = [
            'nombre' => (string) apply_filters('cmb_erp_company_name', 'Empresa'),
            'direccion' => (string) apply_filters('cmb_erp_company_address', ''),
            'telefono' => (string) apply_filters('cmb_erp_company_phone', ''),
            'email' => (string) apply_filters('cmb_erp_company_email', ''),
            'logo_url' => (string) apply_filters('cmb_erp_company_logo_url', ''),

            // Footer 3 bloques
            'footer_block1_html' => (string) apply_filters('cmb_erp_company_footer_block1_html', ''),
            'footer_block2_html' => (string) apply_filters('cmb_erp_company_footer_block2_html', ''),
            'footer_image_url'   => (string) apply_filters('cmb_erp_company_footer_image_url', ''),
        ];

        return [
            'meta' => [
                'generated_at' => current_time('mysql'),
                'plugin' => defined('CMB_ERP_VERSION') ? CMB_ERP_VERSION : '1.0.0',
            ],
            'company' => $company,
            'quote' => [
                'id' => (int)($cot['id'] ?? $id),
                'codigo' => (string)($cot['cot_codigo'] ?? ''),
                'fecha' => (string) substr((string)($cot['fecha_emision'] ?? ''), 0, 10),
                'moneda' => (string)($cot['moneda'] ?? 'BOB'),
                'validez_sel' => (string)($cot['validez_sel'] ?? ''),
                'pago_sel' => (string)($cot['pago_sel'] ?? ''),
                'condiciones' => (string)($cot['condiciones'] ?? ''),
                'subtotal' => isset($cot['subtotal']) ? (float)$cot['subtotal'] : null,
                'total' => isset($cot['total']) ? (float)$cot['total'] : null,
            ],
            'client' => $cliente ?: null,
            'contact' => $contacto ?: null,
            'groups' => $groups,
            'items' => $items,
        ];
    }
}

<?php
/**
 * Módulo: Registro de Facturación
 * Shortcode: [cmb_invoicing]
 * Compat: [registro_facturacion] (solo si existía)
 * AJAX:
 *  - cmb_invoicing_save
 *  - cmb_invoicing_delete
 */

namespace CMBERP\Modules\Facturacion;

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/src/facturacion-repository.php';
require_once __DIR__ . '/src/facturacion-shortcode.php';
require_once __DIR__ . '/includes/facturacion-ajax.php';

final class Module {
    public static function register(): void {
        add_action('init', [Ajax::class, 'register'], 21);
        add_action('init', [Shortcode::class, 'register'], 21);
    }
}

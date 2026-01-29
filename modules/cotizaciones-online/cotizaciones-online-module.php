<?php
/**
 * Módulo: Cotizaciones Online (Listado + PDF)
 *
 * Este módulo es independiente del módulo core de Cotizaciones.
 * Expone:
 *  - Shortcode: [cmb_quotes_table]
 *  - AJAX: wp_ajax_cmb_quotes_online_get
 */

namespace CMBERP\Modules\CotizacionesOnline;

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/src/cotizaciones-online-repository.php';
require_once __DIR__ . '/src/cotizaciones-online-shortcode.php';
require_once __DIR__ . '/includes/cotizaciones-online-ajax.php';

final class Module {
    public static function register(): void {
        // Shortcode
        add_action('init', [Shortcode::class, 'register'], 21);
        // AJAX endpoints
        add_action('init', [Ajax::class, 'register'], 21);
    }
}

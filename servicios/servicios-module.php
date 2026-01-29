<?php
/**
 * Módulo: Servicios (Catálogo)
 * Shortcode: [cmb_services]
 * AJAX: wp_ajax_cmb_services_*
 */
namespace CMBERP\Modules\Servicios;
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/src/servicios-repository.php';
require_once __DIR__ . '/src/servicios-shortcode.php';
require_once __DIR__ . '/includes/servicios-ajax.php';
final class Module {
    public static function register(): void {
        add_action('init', [Ajax::class, 'register'], 21);
        add_action('init', [Shortcode::class, 'register'], 21);
    }
}

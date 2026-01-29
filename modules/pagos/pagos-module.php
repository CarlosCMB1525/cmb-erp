<?php
/**
 * Módulo: Pagos (Registro de Pagos)
 * Shortcode final: [cmb_payments] (registrado por Core\Plugin)
 * Mantiene acciones AJAX legacy:
 *  - crm_pago_v83
 *  - crm_borrar_pago_v83
 *  - crm_editar_pago_v83
 */
namespace CMBERP\Modules\Pagos;

if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/src/Logic.php';
require_once __DIR__ . '/src/Ajax.php';
require_once __DIR__ . '/src/Shortcode.php';

final class Module {
    public static function register(): void {
        add_action('init', [Ajax::class, 'register'], 21);
        // Shortcode lo registra Core\Plugin.
    }
}

<?php
/**
 * Módulo: Auditoría (SQL Master)
 * Shortcodes:
 *  - [cmb_audit] (principal)
 *  - [sistema_auditoria] (compat)
 */
namespace CMBERP\Modules\Auditoria;

if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/src/auditoria-logic.php';
require_once __DIR__ . '/src/auditoria-shortcode.php';
require_once __DIR__ . '/includes/auditoria-ajax.php';

final class Module {
    public static function register(): void {
        add_action('init', [Ajax::class, 'register'], 21);
        add_action('init', [Shortcode::class, 'register'], 21);
    }
}

<?php
/**
 * Entry point del módulo Cotizaciones.
 */
namespace CMBERP\Modules\Cotizaciones;

if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/src/Installer.php';
require_once __DIR__ . '/src/Ajax.php';
require_once __DIR__ . '/src/Shortcode.php';

final class Module {
    public static function register(): void {
        // Installer versionado: se ejecuta temprano y solo cuando cambia la versión.
        add_action('init', [Installer::class, 'maybe_install'], 5);
        // Endpoints AJAX del módulo.
        add_action('init', [Ajax::class, 'register'], 21);
    }
}

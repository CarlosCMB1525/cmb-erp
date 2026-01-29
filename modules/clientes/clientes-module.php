<?php
namespace CMBERP\Modules\Clientes;

if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/src/Installer.php';
require_once __DIR__ . '/src/Ajax.php';
require_once __DIR__ . '/src/Shortcode.php';

final class Module {
    public static function register(): void {
        add_action('init', [Installer::class, 'maybe_install'], 5);
        add_action('init', [Ajax::class, 'register'], 21);
    }
}

<?php
/**
 * Módulo: Dashboard
 * Shortcode: [cmb_dashboard]
 * AJAX: wp_ajax_cmb_dashboard_filter
 */
namespace CMBERP\Modules\Dashboard;

if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/src/DashboardService.php';
require_once __DIR__ . '/src/Ajax.php';
require_once __DIR__ . '/src/Shortcode.php';

final class Module {
    public static function register(): void {
        add_action('init', [Ajax::class, 'register'], 21);
        add_action('init', [Shortcode::class, 'register'], 21);
    }
}

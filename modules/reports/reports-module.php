<?php
declare(strict_types=1);
/**
 * Entry point del módulo Reports/Finanzas.
 * Genera reportes (P&L Mensual/Anual) desde Dashboard via AJAX.
 */
namespace CMBERP\Modules\Reports;

if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/src/Installer.php';
require_once __DIR__ . '/src/Cpt/AccountingCategoryCpt.php';
require_once __DIR__ . '/src/Cpt/TaxProfileCpt.php';
require_once __DIR__ . '/src/Services/CategorySyncService.php';
require_once __DIR__ . '/src/Services/PnlReportService.php';
require_once __DIR__ . '/src/Ajax.php';

final class Module {
    public static function register(): void {
        add_action('init', [Cpt\AccountingCategoryCpt::class, 'register'], 9);
        add_action('init', [Cpt\TaxProfileCpt::class, 'register'], 9);

        // Installer + precarga inicial (idempotente)
        add_action('init', [Installer::class, 'maybe_seed'], 11);

        // Sincronización categorías (CPT -> Cashflow options)
        Services\CategorySyncService::register_hooks();

        // Endpoints de reportes
        add_action('init', [Ajax::class, 'register'], 21);
    }
}

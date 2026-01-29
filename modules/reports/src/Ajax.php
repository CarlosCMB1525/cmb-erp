<?php
declare(strict_types=1);
namespace CMBERP\Modules\Reports;

use CMBERP\Modules\Reports\Services\PnlReportService;

if (!defined('ABSPATH')) { exit; }

final class Ajax {
    private static function can_access(): bool {
        return current_user_can('administrator') || current_user_can('edit_posts');
    }

    private static function nonce_ok(): bool {
        $nonce = $_POST['nonce'] ?? '';
        if (!is_string($nonce) || $nonce === '') return false;
        $action = defined('CMB_ERP_NONCE_ACTION') ? CMB_ERP_NONCE_ACTION : 'cmb_erp_nonce';
        return (bool) wp_verify_nonce($nonce, $action);
    }

    private static function fail(string $msg, int $code = 400): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[CMBERP][Reports][Ajax] ' . $msg);
        }
        wp_send_json_error($msg, $code);
    }

    private static function ok(array $data = []): void {
        wp_send_json_success($data);
    }

    public static function register(): void {
        add_action('wp_ajax_cmb_reports_pnl_payload', [__CLASS__, 'pnl_payload']);
    }

    /**
     * Retorna payload del P&L (mensual o anual)
     * POST: period_type=MONTH|YEAR, period=YYYY-MM|YYYY
     */
    public static function pnl_payload(): void {
        if (!self::can_access()) self::fail('No tienes permisos.', 403);
        if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.', 403);

        $type = isset($_POST['period_type']) ? sanitize_text_field((string)$_POST['period_type']) : 'MONTH';
        $period = isset($_POST['period']) ? sanitize_text_field((string)$_POST['period']) : '';

        try {
            if ($type === 'YEAR') {
                $res = PnlReportService::build_annual($period);
            } else {
                $type = 'MONTH';
                $res = PnlReportService::build_monthly($period);
            }

            if (!empty($res['error'])) {
                self::fail((string)$res['error']);
            }

            self::ok(['payload' => $res['payload']]);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[CMBERP][Reports][Ajax] ' . $e->getMessage());
            }
            self::fail('Error interno al generar el reporte.', 500);
        }
    }
}

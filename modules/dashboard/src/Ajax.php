<?php
namespace CMBERP\Modules\Dashboard;

if (!defined('ABSPATH')) { exit; }

// Asegura que DashboardService esté disponible también en llamadas AJAX.
require_once __DIR__ . '/DashboardService.php';

final class Ajax {
    public static function register(): void {
        add_action('wp_ajax_cmb_dashboard_filter', [__CLASS__, 'filter']);
    }

    public static function filter(): void {
        if (!DashboardService::can_access()) {
            wp_send_json_error('No tienes permisos.');
        }
        if (!DashboardService::nonce_ok()) {
            wp_send_json_error('Nonce inválido. Recarga la página.');
        }

        $inicio = isset($_POST['inicio']) ? DashboardService::safe_date((string)$_POST['inicio']) : '';
        $fin    = isset($_POST['fin'])    ? DashboardService::safe_date((string)$_POST['fin'])    : '';

        if (($inicio && !$fin) || (!$inicio && $fin)) {
            wp_send_json_error('Selecciona ambas fechas (Desde/Hasta).');
        }
        if ($inicio && $fin && strtotime($inicio) > strtotime($fin)) {
            wp_send_json_error('La fecha Desde no puede ser mayor que Hasta.');
        }

        $categoria   = isset($_POST['categoria'])    ? DashboardService::normalize_categoria($_POST['categoria']) : 'TODAS';
        $pago_estado = isset($_POST['pago_estado']) ? DashboardService::normalize_pay_status($_POST['pago_estado']) : 'TODOS';
        $doc_tipo    = isset($_POST['doc_tipo'])    ? DashboardService::normalize_doc_tipo($_POST['doc_tipo']) : 'TODOS';
        $q_quick     = isset($_POST['q_quick'])     ? DashboardService::normalize_query($_POST['q_quick']) : '';
        $q_adv       = isset($_POST['q_adv'])       ? DashboardService::normalize_query($_POST['q_adv']) : '';

        $metrics = DashboardService::get_metrics($inicio, $fin, $categoria, $pago_estado, $doc_tipo, $q_quick, $q_adv);
        $extra   = DashboardService::get_extra_metrics($inicio, $fin, $categoria, $q_quick, $q_adv);
        $rows    = DashboardService::get_rows($inicio, $fin, $categoria, $pago_estado, $doc_tipo, $q_quick, $q_adv);

        ob_start();
        $data = ['rows' => $rows];
        require __DIR__ . '/../templates/partials/tbody.php';
        $tbody = (string) ob_get_clean();

        wp_send_json_success([
            'metrics' => array_merge($metrics, $extra),
            'tbody'   => $tbody,
        ]);
    }
}

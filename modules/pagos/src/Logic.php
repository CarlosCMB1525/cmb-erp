<?php
namespace CMBERP\Modules\Pagos;

if (!defined('ABSPATH')) { exit; }

final class Logic {
    public static function can_access(): bool {
        return current_user_can('administrator') || current_user_can('edit_posts');
    }

    /**
     * Acepta nonce legacy crm_erp_nonce (prioridad) y el nonce action del core.
     */
    public static function nonce_ok(): bool {
        $nonce = $_POST['nonce'] ?? '';
        $nonce = is_string($nonce) ? $nonce : '';
        if ($nonce === '') return false;
        if (wp_verify_nonce($nonce, 'crm_erp_nonce')) return true;
        $modern = defined('CMB_ERP_NONCE_ACTION') ? (string) CMB_ERP_NONCE_ACTION : 'cmb_erp_nonce';
        return wp_verify_nonce($nonce, $modern);
    }

    public static function safe_date_ymd($d): string {
        $d = sanitize_text_field((string)$d);
        $dt = \DateTime::createFromFormat('Y-m-d', $d);
        if (!$dt || $dt->format('Y-m-d') !== $d) return '';
        return $d;
    }

    public static function recalcular_estado_venta(int $venta_id): string {
        global $wpdb;
        $t_ventas = $wpdb->prefix . 'vn_ventas';
        $t_pagos = $wpdb->prefix . 'vn_pagos';
        $total = (float)$wpdb->get_var($wpdb->prepare("SELECT total_bs FROM $t_ventas WHERE id=%d", $venta_id));
        $pagado = (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(monto_pagado),0) FROM $t_pagos WHERE venta_id=%d", $venta_id));
        $tol = 0.05;
        if ($pagado <= $tol) $estado = 'Pendiente';
        elseif ($pagado >= ($total - $tol)) $estado = 'Pagado';
        else $estado = 'Parcial';
        $wpdb->update($t_ventas, ['estado' => $estado], ['id' => $venta_id]);
        return $estado;
    }
}

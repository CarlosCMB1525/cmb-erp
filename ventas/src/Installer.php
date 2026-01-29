<?php
namespace CMBERP\Modules\Ventas;

if (!defined('ABSPATH')) { exit; }

/**
 * Installer del módulo Ventas.
 * - NO altera tablas existentes vn_ventas, vn_pagos, vn_facturas.
 * - Crea tabla de enlace venta<->cotización emitida.
 */
final class Installer {
    public const T_SALES = 'vn_ventas';
    public const T_PAYMENTS = 'vn_pagos';
    public const T_INVOICES = 'vn_facturas';
    public const T_LINKS = 'vn_ventas_quotes';

    public static function maybe_install(): void {
        global $wpdb;
        if (!$wpdb) { return; }
        $charset = $wpdb->get_charset_collate();
        $t = $wpdb->prefix . self::T_LINKS;
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t));
        if (empty($exists)) {
            $sql = "CREATE TABLE {$t} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                venta_id BIGINT UNSIGNED NOT NULL,
                cotizacion_id BIGINT UNSIGNED NULL,
                cot_codigo VARCHAR(60) NULL,
                created_at DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_venta (venta_id),
                KEY idx_cot (cotizacion_id),
                KEY idx_codigo (cot_codigo)
            ) {$charset};";
            $wpdb->query($sql);
        }
    }
}

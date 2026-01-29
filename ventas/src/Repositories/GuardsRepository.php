<?php
namespace CMBERP\Modules\Ventas\Repositories;

use CMBERP\Modules\Ventas\Installer;

if (!defined('ABSPATH')) { exit; }

final class GuardsRepository {
    public static function has_payments(int $venta_id): bool {
        global $wpdb;
        $t = $wpdb->prefix . Installer::T_PAYMENTS;
        $venta_id = absint($venta_id);
        if ($venta_id<=0) return false;
        $cnt = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE venta_id=%d", $venta_id));
        return $cnt > 0;
    }

    public static function has_invoices(int $venta_id): bool {
        global $wpdb;
        $t = $wpdb->prefix . Installer::T_INVOICES;
        $venta_id = absint($venta_id);
        if ($venta_id<=0) return false;
        $cnt = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE venta_id=%d", $venta_id));
        return $cnt > 0;
    }
}

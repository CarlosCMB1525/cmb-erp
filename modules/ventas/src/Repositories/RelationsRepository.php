<?php
namespace CMBERP\Modules\Ventas\Repositories;

use CMBERP\Modules\Ventas\Installer;

if (!defined('ABSPATH')) { exit; }

final class RelationsRepository {
    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . Installer::T_LINKS;
    }

    public static function upsert(int $venta_id, ?int $cotizacion_id, ?string $cot_codigo): bool {
        global $wpdb;
        $t = self::table();
        $venta_id = absint($venta_id);
        if ($venta_id <= 0) return false;
        $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE venta_id=%d", $venta_id));
        $now = current_time('mysql');
        $data = [
            'venta_id' => $venta_id,
            'cotizacion_id' => $cotizacion_id ? absint($cotizacion_id) : null,
            'cot_codigo' => $cot_codigo ? sanitize_text_field($cot_codigo) : null,
            'created_at' => $now,
        ];
        if ($exists > 0) {
            unset($data['created_at']);
            $ok = $wpdb->update($t, $data, ['venta_id'=>$venta_id]);
            return ($ok !== false);
        }
        $ok = $wpdb->insert($t, $data);
        return ($ok !== false);
    }

    public static function get_by_sale(int $venta_id): ?array {
        global $wpdb;
        $t = self::table();
        $venta_id = absint($venta_id);
        if ($venta_id<=0) return null;
        $row = $wpdb->get_row($wpdb->prepare("SELECT venta_id, cotizacion_id, cot_codigo FROM {$t} WHERE venta_id=%d", $venta_id), ARRAY_A);
        return $row ?: null;
    }

    public static function delete_by_sale(int $venta_id): void {
        global $wpdb;
        $t = self::table();
        $wpdb->delete($t, ['venta_id'=>absint($venta_id)], ['%d']);
    }
}

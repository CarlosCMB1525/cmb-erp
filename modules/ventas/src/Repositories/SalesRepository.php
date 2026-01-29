<?php
   namespace CMBERP\Modules\Ventas\Repositories;

   use CMBERP\Modules\Ventas\Installer;

   if (!defined('ABSPATH')) { exit; }

   final class SalesRepository {
       private static function table(): string {
           global $wpdb;
           return $wpdb->prefix . Installer::T_SALES;
       }

       public static function get(int $id): ?array {
           global $wpdb;
           $t = self::table();
           $id = absint($id);
           if ($id <= 0) return null;
           $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d", $id), ARRAY_A);
           return $row ?: null;
       }

       public static function insert(array $data): int {
           global $wpdb;
           $t = self::table();
           $ok = $wpdb->insert($t, $data);
           if ($ok === false) return 0;
           return (int)$wpdb->insert_id;
       }

       public static function update(int $id, array $data): bool {
           global $wpdb;
           $t = self::table();
           $ok = $wpdb->update($t, $data, ['id'=>absint($id)]);
           return ($ok !== false);
       }

       public static function delete(int $id): bool {
           global $wpdb;
           $t = self::table();
           $ok = $wpdb->delete($t, ['id'=>absint($id)], ['%d']);
           return ($ok !== false);
       }

       public static function list_recent(int $limit = 50): array {
           global $wpdb;
           $t = self::table();
           $limit = absint($limit);
           if ($limit < 1) $limit = 50;
           if ($limit > 500) $limit = 500;
           $sql = $wpdb->prepare(
               "SELECT v.*, c.nombre_legal
FROM {$t} v
LEFT JOIN {$wpdb->prefix}cl_empresas c ON v.cliente_id=c.id
ORDER BY v.fecha_venta DESC, v.id DESC
LIMIT %d",
               $limit
           );
           $rows = $wpdb->get_results($sql, ARRAY_A);
           return $rows ?: [];
       }

       public static function exists_sale_for_client_month(int $cliente_id, int $month, int $year): int {
           global $wpdb;
           $t = self::table();
           $cliente_id = absint($cliente_id);
           $month = absint($month);
           $year = absint($year);
           if ($cliente_id<=0 || $month<=0 || $year<=0) return 0;
           return (int)$wpdb->get_var($wpdb->prepare(
               "SELECT COUNT(*) FROM {$t} WHERE cliente_id=%d AND MONTH(fecha_venta)=%d AND YEAR(fecha_venta)=%d",
               $cliente_id, $month, $year
           ));
       }

       public static function update_recurrence(int $id, int $dia): bool {
           global $wpdb;
           $t = self::table();
           $id = absint($id);
           $dia = absint($dia);
           if ($id<=0) return false;
           $data = [
               'dia_facturacion' => $dia,
               'tipo_contrato' => 'MENSUAL',
               'DIA_RECURRENTE_CLON_VENTA' => $dia,
           ];
           $ok = $wpdb->update($t, $data, ['id'=>$id], ['%d','%s','%d'], ['%d']);
           return ($ok !== false);
       }
   }

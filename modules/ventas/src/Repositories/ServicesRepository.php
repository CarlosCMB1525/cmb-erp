<?php
   namespace CMBERP\Modules\Ventas\Repositories;

   if (!defined('ABSPATH')) { exit; }

   final class ServicesRepository {
       public static function search(string $q, int $page, int $per_page): array {
           global $wpdb;
           $t = $wpdb->prefix . 'sv_servicios';
           $q = sanitize_text_field(wp_unslash($q));
           $q = trim($q);
           $page = max(1, absint($page));
           $per_page = absint($per_page);
           if ($per_page < 1) $per_page = 20;
           if ($per_page > 50) $per_page = 50;
           $offset = ($page - 1) * $per_page;

           if ($q === '') {
               $sql = $wpdb->prepare(
                   "SELECT SQL_CALC_FOUND_ROWS id, codigo_unico, nombre_servicio, detalle_tecnico, monto_unitario
FROM {$t}
ORDER BY nombre_servicio ASC
LIMIT %d OFFSET %d",
                   $per_page, $offset
               );
           } else {
               $like = '%' . $wpdb->esc_like($q) . '%';
               $sql = $wpdb->prepare(
                   "SELECT SQL_CALC_FOUND_ROWS id, codigo_unico, nombre_servicio, detalle_tecnico, monto_unitario
FROM {$t}
WHERE codigo_unico LIKE %s OR nombre_servicio LIKE %s OR detalle_tecnico LIKE %s
ORDER BY nombre_servicio ASC
LIMIT %d OFFSET %d",
                   $like, $like, $like,
                   $per_page, $offset
               );
           }
           $rows = $wpdb->get_results($sql, ARRAY_A);
           $total = (int) $wpdb->get_var('SELECT FOUND_ROWS()');
           return [
               'rows' => $rows ?: [],
               'page' => $page,
               'per_page' => $per_page,
               'total' => $total,
               'has_more' => ($offset + $per_page) < $total,
               'q' => $q,
           ];
       }
   }

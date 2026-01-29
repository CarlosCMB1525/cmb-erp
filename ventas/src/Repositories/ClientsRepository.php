<?php
   namespace CMBERP\Modules\Ventas\Repositories;

   if (!defined('ABSPATH')) { exit; }

   final class ClientsRepository {
       public static function search(string $q, int $page, int $per_page): array {
           global $wpdb;
           $t = $wpdb->prefix . 'cl_empresas';
           $q = sanitize_text_field(wp_unslash($q));
           $q = trim($q);
           $page = max(1, absint($page));
           $per_page = absint($per_page);
           if ($per_page < 1) $per_page = 20;
           if ($per_page > 50) $per_page = 50;
           $offset = ($page - 1) * $per_page;

           if ($q === '') {
               $sql = $wpdb->prepare(
                   "SELECT SQL_CALC_FOUND_ROWS id, nombre_legal, nit_id, razon_social, tipo_cliente
FROM {$t}
ORDER BY nombre_legal ASC
LIMIT %d OFFSET %d",
                   $per_page,
                   $offset
               );
           } else {
               $like = '%' . $wpdb->esc_like($q) . '%';
               $sql = $wpdb->prepare(
                   "SELECT SQL_CALC_FOUND_ROWS id, nombre_legal, nit_id, razon_social, tipo_cliente
FROM {$t}
WHERE nombre_legal LIKE %s OR nit_id LIKE %s OR razon_social LIKE %s
ORDER BY nombre_legal ASC
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

       public static function get(int $id): ?array {
           global $wpdb;
           $t = $wpdb->prefix . 'cl_empresas';
           $id = absint($id);
           if ($id <= 0) return null;
           $row = $wpdb->get_row($wpdb->prepare(
               "SELECT id, nombre_legal, nit_id, razon_social, tipo_cliente FROM {$t} WHERE id=%d",
               $id
           ), ARRAY_A);
           return $row ?: null;
       }
   }

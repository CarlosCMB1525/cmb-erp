<?php
   namespace CMBERP\Modules\Ventas\Repositories;

   use CMBERP\Modules\Cotizaciones\Installer as QuotesInstaller;

   if (!defined('ABSPATH')) { exit; }

   /**
    * Adaptador para leer cotizaciones emitidas del m¨®dulo Cotizaciones.
    */
   final class QuotesRepository {
       private static function table_quotes(): string {
           global $wpdb;
           // Tabla legacy de cotizaciones: qt_cotizaciones
           return $wpdb->prefix . 'qt_cotizaciones';
       }

       private static function table_items(): string {
           global $wpdb;
           return $wpdb->prefix . 'qt_cotizacion_items';
       }

       public static function list_emitted(string $q, int $page, int $per_page): array {
           global $wpdb;
           $t = self::table_quotes();
           $q = sanitize_text_field(wp_unslash($q));
           $q = trim($q);
           $page = max(1, absint($page));
           $per_page = absint($per_page);
           if ($per_page < 1) $per_page = 20;
           if ($per_page > 50) $per_page = 50;
           $offset = ($page - 1) * $per_page;

           $where = "WHERE qt.estado='EMITIDA' AND qt.cot_codigo IS NOT NULL AND qt.cot_codigo<>''";
           $params = [];
           if ($q !== '') {
               $like = '%' . $wpdb->esc_like($q) . '%';
               $where .= " AND (qt.cot_codigo LIKE %s OR CAST(qt.id AS CHAR) LIKE %s OR c.nombre_legal LIKE %s)";
               $params = [$like, $like, $like];
           }

           $sql = "SELECT SQL_CALC_FOUND_ROWS qt.id, qt.cot_codigo, qt.fecha_emision, qt.cliente_id, qt.total, c.nombre_legal
                   FROM {$t} qt
                   LEFT JOIN {$wpdb->prefix}cl_empresas c ON qt.cliente_id=c.id
                   {$where}
                   ORDER BY qt.fecha_emision DESC, qt.id DESC
                   LIMIT %d OFFSET %d";

           $params[] = $per_page;
           $params[] = $offset;
           $prepared = $wpdb->prepare($sql, ...$params);
           $rows = $wpdb->get_results($prepared, ARRAY_A);
           $total = (int)$wpdb->get_var('SELECT FOUND_ROWS()');

           return [
               'rows' => $rows ?: [],
               'page' => $page,
               'per_page' => $per_page,
               'total' => $total,
               'has_more' => ($offset + $per_page) < $total,
               'q' => $q,
           ];
       }

       public static function get_payload(int $id): ?array {
           global $wpdb;
           $tq = self::table_quotes();
           $ti = self::table_items();
           $id = absint($id);
           if ($id <= 0) return null;
           $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tq} WHERE id=%d", $id), ARRAY_A);
           if (!$quote) return null;
           $items = $wpdb->get_results($wpdb->prepare(
               "SELECT grupo_id, codigo_servicio, nombre_servicio, descripcion, cantidad, precio_unitario, subtotal_item
FROM {$ti} WHERE cotizacion_id=%d ORDER BY orden ASC, id ASC",
               $id
           ), ARRAY_A);
           $client = null;
           if (!empty($quote['cliente_id'])) {
               $client = ClientsRepository::get((int)$quote['cliente_id']);
           }
           return [
               'quote' => [
                   'id' => (int)$quote['id'],
                   'codigo' => (string)($quote['cot_codigo'] ?? ''),
                   'fecha' => (string)substr((string)($quote['fecha_emision'] ?? ''), 0, 10),
                   'cliente_id' => (int)($quote['cliente_id'] ?? 0),
                   'total' => isset($quote['total']) ? (float)$quote['total'] : null,
               ],
               'client' => $client,
               'items' => $items ?: [],
           ];
       }
   }

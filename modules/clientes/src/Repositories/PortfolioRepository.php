<?php
namespace CMBERP\Modules\Clientes\Repositories;

if (!defined('ABSPATH')) { exit; }

/**
 * Buscador avanzado (SQL LIKE + OR) por:
 * - nombre_legal
 * - nit_id
 * - contacto principal (primer contacto por empresa por MIN(id))
 */
final class PortfolioRepository {

    public static function search(string $q, int $limit=200): array {
        global $wpdb;
        $t_emp = $wpdb->prefix . 'cl_empresas';
        $t_con = $wpdb->prefix . 'cl_contactos';

        $q = sanitize_text_field(wp_unslash($q));
        $q = trim($q);
        $limit = absint($limit);
        if ($limit < 1) $limit = 200;
        if ($limit > 500) $limit = 500;

        $like = '%' . $wpdb->esc_like($q) . '%';

        $sql = $wpdb->prepare(
            "SELECT e.*, pc.nombre_contacto AS contacto_principal
             FROM {$t_emp} e
             LEFT JOIN (
               SELECT c1.empresa_id, c1.nombre_contacto
               FROM {$t_con} c1
               INNER JOIN (
                 SELECT empresa_id, MIN(id) AS min_id
                 FROM {$t_con}
                 GROUP BY empresa_id
               ) x ON x.empresa_id = c1.empresa_id AND x.min_id = c1.id
             ) pc ON pc.empresa_id = e.id
             WHERE e.nombre_legal LIKE %s OR e.nit_id LIKE %s OR pc.nombre_contacto LIKE %s
             ORDER BY e.id DESC
             LIMIT {$limit}",
            $like, $like, $like
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);
        return $rows ?: [];
    }
}

<?php
namespace CMBERP\Modules\Clientes\Repositories;

if (!defined('ABSPATH')) { exit; }

final class CompaniesRepository {
    private static function t(): string { global $wpdb; return $wpdb->prefix . 'cl_empresas'; }

    public static function get(int $id): ?array {
        global $wpdb;
        $id = absint($id);
        if ($id <= 0) return null;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::t() . ' WHERE id=%d', $id), ARRAY_A);
        return $row ?: null;
    }

    public static function save(int $id, array $data): int {
        global $wpdb;
        $t = self::t();
        $id = absint($id);
        if ($id > 0) {
            $ok = $wpdb->update($t, $data, ['id'=>$id]);
            return ($ok === false) ? 0 : $id;
        }
        $ok = $wpdb->insert($t, $data);
        return ($ok === false) ? 0 : (int)$wpdb->insert_id;
    }

    public static function delete(int $id): bool {
        global $wpdb;
        $id = absint($id);
        if ($id <= 0) return false;
        $ok = $wpdb->delete(self::t(), ['id'=>$id], ['%d']);
        return ($ok !== false);
    }

    public static function list_recent(int $limit=200): array {
        global $wpdb;
        $limit = absint($limit);
        if ($limit < 1) $limit = 200;
        if ($limit > 500) $limit = 500;
        $rows = $wpdb->get_results('SELECT * FROM ' . self::t() . ' ORDER BY id DESC LIMIT ' . $limit, ARRAY_A);
        return $rows ?: [];
    }
}

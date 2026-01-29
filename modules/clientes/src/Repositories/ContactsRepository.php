<?php
namespace CMBERP\Modules\Clientes\Repositories;

if (!defined('ABSPATH')) { exit; }

final class ContactsRepository {
    private static function t(): string { global $wpdb; return $wpdb->prefix . 'cl_contactos'; }

    public static function get(int $id): ?array {
        global $wpdb;
        $id = absint($id);
        if ($id <= 0) return null;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::t() . ' WHERE id=%d', $id), ARRAY_A);
        return $row ?: null;
    }

    public static function list_by_company(int $empresa_id): array {
        global $wpdb;
        $empresa_id = absint($empresa_id);
        if ($empresa_id <= 0) return [];
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::t() . ' WHERE empresa_id=%d ORDER BY nombre_contacto ASC, id ASC',
            $empresa_id
        ), ARRAY_A);
        return $rows ?: [];
    }

    public static function count_by_company(int $empresa_id): int {
        global $wpdb;
        $empresa_id = absint($empresa_id);
        if ($empresa_id <= 0) return 0;
        return (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::t() . ' WHERE empresa_id=%d', $empresa_id));
    }

    public static function email_exists(string $email, int $exclude_id=0): bool {
        global $wpdb;
        $email = sanitize_email($email);
        if ($email === '') return false;
        $exclude_id = absint($exclude_id);
        $id = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . self::t() . ' WHERE correo_electronico=%s AND id<>%d LIMIT 1',
            $email, $exclude_id
        ));
        return !empty($id);
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
}

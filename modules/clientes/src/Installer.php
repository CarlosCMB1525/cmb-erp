<?php
namespace CMBERP\Modules\Clientes;

if (!defined('ABSPATH')) { exit; }

/**
 * DB fix: remover UNIQUE sobre cl_empresas.nit_id si existe.
 * - No borra datos
 * - Se ejecuta una sola vez (option flag)
 */
final class Installer {
    private const OPT_FLAG = 'crm_erp_nit_unique_removed';

    public static function maybe_install(): void {
        if (!(current_user_can('administrator') || current_user_can('edit_posts'))) return;
        if (get_option(self::OPT_FLAG) === '1') return;

        global $wpdb;
        if (!$wpdb) return;

        $table = $wpdb->prefix . 'cl_empresas';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if (!$exists) return;

        $idx = $wpdb->get_results("SHOW INDEX FROM `{$table}`");
        if (!is_array($idx) || empty($idx)) {
            update_option(self::OPT_FLAG, '1');
            return;
        }

        $unique_keys = [];
        foreach ($idx as $row) {
            if ((int)($row->Non_unique ?? 1) !== 0) continue; // 0 => UNIQUE
            $key = (string)($row->Key_name ?? '');
            $col = (string)($row->Column_name ?? '');
            if ($key === '' || $col === '') continue;
            if (!isset($unique_keys[$key])) $unique_keys[$key] = [];
            $unique_keys[$key][] = $col;
        }

        $to_drop = '';
        foreach ($unique_keys as $k => $cols) {
            if (in_array('nit_id', $cols, true)) { $to_drop = $k; break; }
        }

        if ($to_drop) {
            $ok = $wpdb->query("ALTER TABLE `{$table}` DROP INDEX `{$to_drop}`");
            if ($ok !== false) update_option(self::OPT_FLAG, '1');
        } else {
            update_option(self::OPT_FLAG, '1');
        }
    }
}

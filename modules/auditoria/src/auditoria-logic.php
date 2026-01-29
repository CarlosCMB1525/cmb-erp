<?php
namespace CMBERP\Modules\Auditoria;

if (!defined('ABSPATH')) { exit; }

final class Logic {
    public static function can_access(): bool {
        return current_user_can('administrator');
    }

    /**
     * Compatibilidad total: acepta nonce del core nuevo o del legacy.
     */
    public static function nonce_ok(): bool {
        $nonce = $_POST['nonce'] ?? '';
        $nonce = is_string($nonce) ? $nonce : '';
        if ($nonce === '') return false;

        // Nuevo
        $modern = defined('CMB_ERP_NONCE_ACTION') ? (string) CMB_ERP_NONCE_ACTION : 'cmb_erp_nonce';
        if (wp_verify_nonce($nonce, $modern)) return true;

        // Legacy
        return wp_verify_nonce($nonce, 'crm_erp_nonce');
    }

    public static function allowed_tables_map(): array {
        global $wpdb;
        $cache_key = 'cmb_erp_allowed_tables_map';
        $cached = get_transient($cache_key);
        if (is_array($cached) && !empty($cached)) return $cached;

        $prefix = $wpdb->prefix;
        $like = $wpdb->esc_like($prefix) . '%';
        $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));
        if (!is_array($tables)) $tables = [];

        $map = [];
        foreach ($tables as $t) $map[strtolower((string)$t)] = (string)$t;
        set_transient($cache_key, $map, 120);
        return $map;
    }

    public static function validate_table($tabla_input): string {
        global $wpdb;
        $tabla_input = sanitize_text_field((string)$tabla_input);
        $tabla_input = trim($tabla_input);
        if ($tabla_input === '') return '';

        $prefix = $wpdb->prefix;
        $t = $tabla_input;
        if (stripos($t, $prefix) !== 0) {
            $t = $prefix . strtolower($t);
        } else {
            $t = strtolower($t);
        }

        $map = self::allowed_tables_map();
        return isset($map[$t]) ? $map[$t] : '';
    }

    public static function get_columns(string $tabla_full): array {
        global $wpdb;
        $cols = $wpdb->get_results("SHOW COLUMNS FROM `{$tabla_full}`");
        return is_array($cols) ? $cols : [];
    }

    public static function pick_key_column(array $cols): string {
        foreach ($cols as $c) {
            if (($c->Field ?? '') === 'id') return 'id';
        }
        return !empty($cols) ? (string)($cols[0]->Field ?? '') : '';
    }

    public static function truncate_value($val, int $len = 80): string {
        $val = (string)$val;
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return (mb_strlen($val) > $len) ? (mb_substr($val, 0, $len) . '…') : $val;
        }
        return (strlen($val) > $len) ? (substr($val, 0, $len) . '…') : $val;
    }

    public static function filter_update_data($datos, array $cols, string $key_col): array {
        $allowed = [];
        foreach ($cols as $c) $allowed[(string)$c->Field] = true;

        $clean = [];
        foreach ((array)$datos as $k => $v) {
            $k = sanitize_key($k);
            if ($k === $key_col) continue;
            if (!isset($allowed[$k])) continue;
            $clean[$k] = wp_unslash($v);
        }
        return $clean;
    }
}

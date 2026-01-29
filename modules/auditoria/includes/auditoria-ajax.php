<?php
namespace CMBERP\Modules\Auditoria;

if (!defined('ABSPATH')) { exit; }

final class Ajax {
    public static function register(): void {
        // Nuevos actions (cmb_*)
        add_action('wp_ajax_cmb_audit_load_table', [__CLASS__, 'load_table']);
        add_action('wp_ajax_cmb_audit_test_table', [__CLASS__, 'test_table']);
        add_action('wp_ajax_cmb_audit_save_row', [__CLASS__, 'save_row']);
        add_action('wp_ajax_cmb_audit_delete_row', [__CLASS__, 'delete_row']);
        add_action('wp_ajax_cmb_audit_truncate', [__CLASS__, 'truncate']);
        add_action('wp_ajax_cmb_audit_import_json', [__CLASS__, 'import_json']);
        add_action('wp_ajax_cmb_audit_export_all', [__CLASS__, 'export_all']);

        // Alias legacy: el original usa auditoria_*.
        add_action('wp_ajax_auditoria_cargar_tabla', [__CLASS__, 'load_table']);
        add_action('wp_ajax_auditoria_guardar_fila', [__CLASS__, 'save_row']);
        add_action('wp_ajax_auditoria_eliminar_fila', [__CLASS__, 'delete_row']);
        add_action('wp_ajax_auditoria_reset_id', [__CLASS__, 'truncate']);
        add_action('wp_ajax_auditoria_importar_json', [__CLASS__, 'import_json']);
        add_action('wp_ajax_auditoria_exportar_todo', [__CLASS__, 'export_all']);
    }

    private static function fail(string $msg, array $extra = []): void {
        wp_send_json_error(array_merge(['message' => $msg], $extra));
    }
    private static function ok(array $data = []): void { wp_send_json_success($data); }

    public static function load_table(): void {
        if (!Logic::can_access()) self::fail('No tienes permisos (solo admin).');
        if (!Logic::nonce_ok()) self::fail('Nonce inválido. Recarga la página.');

        global $wpdb;
        $tabla_in = $_POST['tabla'] ?? '';
        $tabla = Logic::validate_table($tabla_in);
        if (!$tabla) self::fail('Tabla inválida/no permitida.', ['received' => (string)$tabla_in]);

        $pagina = max(1, (int)($_POST['pagina'] ?? 1));
        $por_pagina = 50;
        $offset = ($pagina - 1) * $por_pagina;

        $cols = Logic::get_columns($tabla);
        if (empty($cols)) self::fail('No se pudieron obtener columnas.', ['tabla' => $tabla]);
        $key_col = Logic::pick_key_column($cols);
        if ($key_col === '') self::fail('No se pudo determinar columna clave.', ['tabla' => $tabla]);

        $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$tabla}`");
        if ($wpdb->last_error) self::fail('Error COUNT: ' . $wpdb->last_error);
        $total_paginas = max(1, (int)ceil($total / $por_pagina));

        $sql = $wpdb->prepare(
            "SELECT * FROM `{$tabla}` ORDER BY `{$key_col}` DESC LIMIT %d OFFSET %d",
            $por_pagina,
            $offset
        );
        $rows = $wpdb->get_results($sql);
        if ($wpdb->last_error) self::fail('Error SELECT: ' . $wpdb->last_error);

        // Render template protegido: si hay Error/Throwable, devolvemos JSON error en vez de 500/HTML.
        $html = '';
        try {
            ob_start();
            $data = [
                'tabla' => $tabla,
                'pagina' => $pagina,
                'total' => $total,
                'total_paginas' => $total_paginas,
                'cols' => $cols,
                'rows' => $rows,
                'key_col' => $key_col,
                'prefix' => $wpdb->prefix,
            ];
            require __DIR__ . '/../templates/auditoria-table.php';
            $html = (string) ob_get_clean();
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) { @ob_end_clean(); }
            self::fail('Error al renderizar la tabla (template).', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);
        }

        self::ok([
            'html' => $html,
            'tabla' => $tabla,
            'pagina' => $pagina,
            'total_paginas' => $total_paginas,
        ]);
    }

    public static function test_table(): void {
        if (!Logic::can_access()) self::fail('No tienes permisos (solo admin).');
        if (!Logic::nonce_ok()) self::fail('Nonce inválido.');

        global $wpdb;
        $tabla_in = $_POST['tabla'] ?? '';
        $tabla = Logic::validate_table($tabla_in);
        if (!$tabla) self::fail('Tabla inválida/no permitida.');

        $cols = Logic::get_columns($tabla);
        if (empty($cols)) self::fail('No se pudieron leer columnas.');
        $key_col = Logic::pick_key_column($cols);

        $probe = $wpdb->get_var("SELECT 1 FROM `{$tabla}` LIMIT 1");
        if ($wpdb->last_error) self::fail('Error SQL: ' . $wpdb->last_error);

        $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$tabla}`");
        if ($wpdb->last_error) self::fail('Error COUNT: ' . $wpdb->last_error);

        self::ok([
            'tabla' => $tabla,
            'cols' => count($cols),
            'key_col' => $key_col,
            'rows' => $total,
            'probe' => $probe === null ? 0 : (int)$probe,
        ]);
    }

    public static function save_row(): void {
        if (!Logic::can_access()) self::fail('No tienes permisos.');
        if (!Logic::nonce_ok()) self::fail('Nonce inválido.');

        global $wpdb;
        $tabla = Logic::validate_table($_POST['tabla'] ?? '');
        if (!$tabla) self::fail('Tabla inválida.');

        $cols = Logic::get_columns($tabla);
        $key_col = Logic::pick_key_column($cols);
        if ($key_col === '') self::fail('No hay columna clave.');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) self::fail('ID inválido.');

        $datos = (array)($_POST['datos'] ?? []);
        $clean = Logic::filter_update_data($datos, $cols, $key_col);
        if (empty($clean)) self::fail('No hay cambios.');

        $ok = $wpdb->update($tabla, $clean, [$key_col => $id]);
        if ($ok === false) self::fail('Error DB: ' . $wpdb->last_error);
        self::ok();
    }

    public static function delete_row(): void {
        if (!Logic::can_access()) self::fail('No tienes permisos.');
        if (!Logic::nonce_ok()) self::fail('Nonce inválido.');

        global $wpdb;
        $tabla = Logic::validate_table($_POST['tabla'] ?? '');
        if (!$tabla) self::fail('Tabla inválida.');

        $cols = Logic::get_columns($tabla);
        $key_col = Logic::pick_key_column($cols);
        if ($key_col === '') self::fail('No hay columna clave.');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) self::fail('ID inválido.');

        $ok = $wpdb->delete($tabla, [$key_col => $id]);
        if ($ok === false) self::fail('Error DB: ' . $wpdb->last_error);
        self::ok();
    }

    public static function truncate(): void {
        if (!Logic::can_access()) self::fail('No tienes permisos.');
        if (!Logic::nonce_ok()) self::fail('Nonce inválido.');

        global $wpdb;
        $tabla = Logic::validate_table($_POST['tabla'] ?? '');
        if (!$tabla) self::fail('Tabla inválida.');

        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');
        $ok = $wpdb->query("TRUNCATE TABLE `{$tabla}`");
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');

        if ($ok === false) self::fail('Error DB: ' . $wpdb->last_error);
        self::ok();
    }

    public static function import_json(): void {
        if (!Logic::can_access()) self::fail('No tienes permisos.');
        if (!Logic::nonce_ok()) self::fail('Nonce inválido.');

        global $wpdb;
        $tabla = Logic::validate_table($_POST['tabla'] ?? '');
        if (!$tabla) self::fail('Tabla inválida.');

        $payload = $_POST['payload'] ?? [];
        if (!is_array($payload) || empty($payload)) self::fail('No hay datos recibidos.');

        $cols = Logic::get_columns($tabla);
        if (empty($cols)) self::fail('No se pudieron obtener columnas.');

        $allowed = [];
        foreach ($cols as $c) $allowed[(string)$c->Field] = true;

        $insertados = 0;
        $errores = [];
        foreach ($payload as $i => $fila) {
            if (!is_array($fila)) continue;
            unset($fila['id'], $fila['ID']);
            $clean = [];
            foreach ($fila as $k => $v) {
                $k2 = sanitize_key($k);
                if (!isset($allowed[$k2])) continue;
                $clean[$k2] = wp_unslash($v);
            }
            if (empty($clean)) { $errores[] = 'Fila ' . ($i+1) . ': sin columnas válidas.'; continue; }
            $ok = $wpdb->insert($tabla, $clean);
            if ($ok === false) $errores[] = 'Fila ' . ($i+1) . ': ' . $wpdb->last_error;
            else $insertados++;
        }

        self::ok(['total' => count($payload), 'insertados' => $insertados, 'errores' => $errores]);
    }

    public static function export_all(): void {
        if (!Logic::can_access()) self::fail('No tienes permisos.');
        if (!Logic::nonce_ok()) self::fail('Nonce inválido.');

        global $wpdb;
        $tabla = Logic::validate_table($_POST['tabla'] ?? '');
        if (!$tabla) self::fail('Tabla inválida.');

        $limit = min(20000, max(1, (int)($_POST['limit'] ?? 5000)));
        $cols = Logic::get_columns($tabla);
        $key_col = Logic::pick_key_column($cols);
        if ($key_col === '') self::fail('No hay columna clave.');

        $rows = $wpdb->get_results("SELECT * FROM `{$tabla}` ORDER BY `{$key_col}` DESC LIMIT {$limit}", ARRAY_A);
        if ($wpdb->last_error) self::fail('Error DB: ' . $wpdb->last_error);

        self::ok(['rows' => $rows ?: [], 'limit' => $limit]);
    }
}

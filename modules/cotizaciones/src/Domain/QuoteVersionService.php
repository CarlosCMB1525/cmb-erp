<?php
namespace CMBERP\Modules\Cotizaciones\Domain;

use CMBERP\Modules\Cotizaciones\Installer;
use CMBERP\Modules\Cotizaciones\Repositories\QuotesRepository;
use CMBERP\Modules\Cotizaciones\Repositories\QuoteGroupsRepository;
use CMBERP\Modules\Cotizaciones\Repositories\QuoteItemsRepository;

if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/../Installer.php';
require_once __DIR__ . '/../Repositories/QuotesRepository.php';
require_once __DIR__ . '/../Repositories/QuoteGroupsRepository.php';
require_once __DIR__ . '/../Repositories/QuoteItemsRepository.php';

/**
 * Emitir cotización con esquema de códigos:
 * - Base: %04dCDG%02d
 * - Ediciones: %04dCDG%02d-VN (N inicia en 1)
 */
final class QuoteVersionService {

    private static function get_columns(string $table): array {
        global $wpdb;
        return $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0) ?: [];
    }

    private static function has_col(string $col, array $cols): bool {
        return in_array($col, $cols, true);
    }

    private static function ensure_correlativos(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $t_corr = $wpdb->prefix . Installer::T_CORR;
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t_corr));
        if (empty($exists)) {
            $wpdb->query("CREATE TABLE {$t_corr} (anio INT NOT NULL, ultimo_numero INT NOT NULL DEFAULT 0, PRIMARY KEY (anio)) {$charset};");
        }
    }

    private static function build_base_code(int $numero, int $anio): string {
        $yy = $anio % 100;
        return sprintf('%04d', $numero) . 'CDG' . sprintf('%02d', $yy);
    }

    private static function parse_base_parts(string $code): array {
        // Devuelve [numero, anioYY] o [0,0]
        $code = trim($code);
        if (preg_match('/^(\d{4})CDG(\d{2})/', $code, $m)) {
            return [(int)$m[1], (int)$m[2]];
        }
        return [0, 0];
    }

    private static function next_version_n(string $base): int {
        global $wpdb;
        $t_cot = $wpdb->prefix . Installer::T_COT;
        $like = $wpdb->esc_like($base . '-V') . '%';
        $rows = $wpdb->get_col($wpdb->prepare("SELECT cot_codigo FROM {$t_cot} WHERE cot_codigo LIKE %s", $like));
        $max = 0;
        foreach (($rows ?: []) as $c) {
            if (preg_match('/-V(\d+)$/', (string)$c, $m)) {
                $n = (int)$m[1];
                if ($n > $max) $max = $n;
            }
        }
        return $max + 1; // inicia en 1
    }

    public static function emit(int $id): array {
        Installer::maybe_install();

        global $wpdb;
        $id = absint($id);
        if ($id <= 0) return ['error' => 'ID inválido.'];

        $t_cot = $wpdb->prefix . Installer::T_COT;
        $cols = self::get_columns($t_cot);

        $row = QuotesRepository::get($id);
        if (!$row) return ['error' => 'Cotización no encontrada.'];

        $now = current_time('mysql');

        $current_code = (string)($row['cot_codigo'] ?? '');

        // 1) Primera emisión: si no tiene código base, asignar correlativo
        if ($current_code === '') {
            self::ensure_correlativos();
            $t_corr = $wpdb->prefix . Installer::T_CORR;

            $anio = (int) date('Y');

            $wpdb->query('START TRANSACTION');
            $corr = $wpdb->get_row($wpdb->prepare("SELECT ultimo_numero FROM {$t_corr} WHERE anio=%d FOR UPDATE", $anio), ARRAY_A);
            if (!$corr) {
                $wpdb->insert($t_corr, ['anio' => $anio, 'ultimo_numero' => 0]);
                $corr = ['ultimo_numero' => 0];
            }

            $next = ((int)$corr['ultimo_numero']) + 1;
            $wpdb->update($t_corr, ['ultimo_numero' => $next], ['anio' => $anio]);

            $base = self::build_base_code($next, $anio);

            $data = [];
            if (self::has_col('cot_codigo', $cols)) $data['cot_codigo'] = $base;
            if (self::has_col('numero', $cols)) $data['numero'] = $next;
            if (self::has_col('anio', $cols)) $data['anio'] = $anio;
            if (self::has_col('estado', $cols)) $data['estado'] = 'EMITIDA';
            if (self::has_col('updated_at', $cols)) $data['updated_at'] = $now;

            $ok = $wpdb->update($t_cot, $data, ['id' => $id]);
            if ($ok === false) {
                $wpdb->query('ROLLBACK');
                return ['error' => 'DB: ' . $wpdb->last_error];
            }

            $wpdb->query('COMMIT');
            return ['id' => $id, 'cot_codigo' => $base, 'mode' => 'base'];
        }

        // 2) Edición: NO generar nuevo correlativo. Crear versión BASE-VN
        $numero = (int)($row['numero'] ?? 0);
        $anio = (int)($row['anio'] ?? 0);

        if ($numero > 0 && $anio > 0) {
            $base = self::build_base_code($numero, $anio);
        } else {
            // fallback a parseo desde código existente
            [$n4, $yy] = self::parse_base_parts($current_code);
            if ($n4 > 0 && $yy > 0) {
                $base = sprintf('%04d', $n4) . 'CDG' . sprintf('%02d', $yy);
            } else {
                // último fallback: usar código existente sin sufijo
                $base = preg_replace('/-V\d+$/', '', $current_code);
            }
        }

        $vn = self::next_version_n($base);
        $new_code = $base . '-V' . $vn;

        // Clonar cabecera
        $new = $row;
        unset($new['id']);

        if (self::has_col('cot_codigo', $cols)) $new['cot_codigo'] = $new_code;
        if (self::has_col('estado', $cols)) $new['estado'] = 'EMITIDA';
        if (self::has_col('created_at', $cols)) $new['created_at'] = $now;
        if (self::has_col('updated_at', $cols)) $new['updated_at'] = $now;

        if (self::has_col('cotizacion_padre_id', $cols)) {
            $new['cotizacion_padre_id'] = (int)$id;
        }

        if (!self::has_col('version', $cols) && isset($new['version'])) unset($new['version']);

        $ok = $wpdb->insert($t_cot, $new);
        if ($ok === false) return ['error' => 'DB: ' . $wpdb->last_error];
        $new_id = (int) $wpdb->insert_id;

        // Clonar grupos + items
        $group_map = QuoteGroupsRepository::clone_groups($id, $new_id);
        $ok2 = QuoteItemsRepository::clone_items($id, $new_id, $group_map);
        if (!$ok2) return ['error' => 'DB: no se pudieron clonar ítems.'];

        return ['id' => $new_id, 'cot_codigo' => $new_code, 'mode' => 'version', 'version_n' => $vn];
    }
}

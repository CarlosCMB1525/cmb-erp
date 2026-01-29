<?php
declare(strict_types=1);
namespace CMBERP\Modules\Reports\Services;

use CMBERP\Modules\Cashflow\Settings as CashflowSettings;

if (!defined('ABSPATH')) { exit; }

/**
 * Sincroniza categorías del CPT con el dropdown de Cashflow.
 * Fuente de verdad: CPT.
 */
final class CategorySyncService {
    public const CPT = 'cmb_fin_account_map';
    public const META_TYPE = '_cmb_fin_cat_type'; // COGS|OPEX|OTROS
    public const META_ORDER = '_cmb_fin_cat_order';

    public static function register_hooks(): void {
        add_action('save_post_' . self::CPT, [__CLASS__, 'on_changed'], 20, 2);
        add_action('trash_' . self::CPT, [__CLASS__, 'on_changed_any'], 20);
        add_action('untrash_' . self::CPT, [__CLASS__, 'on_changed_any'], 20);
    }

    public static function on_changed(int $post_id, \WP_Post $post): void {
        // Solo cuando está publish/draft
        self::sync_to_cashflow_options();
    }

    public static function on_changed_any(int $post_id): void {
        self::sync_to_cashflow_options();
    }

    /**
     * Escribe las categorías publicadas al option de cashflow.
     */
    public static function sync_to_cashflow_options(): void {
        // Si Cashflow no existe, no hacemos nada.
        if (!class_exists(CashflowSettings::class)) {
            return;
        }

        $posts = get_posts([
            'post_type' => self::CPT,
            'post_status' => 'publish',
            'posts_per_page' => 500,
            'orderby' => 'meta_value_num',
            'meta_key' => self::META_ORDER,
            'order' => 'ASC',
            'no_found_rows' => true,
        ]);

        $names = [];
        foreach ($posts as $p) {
            $t = trim((string)$p->post_title);
            if ($t !== '') $names[] = $t;
        }

        // Mantener compatibilidad: si hay movimientos históricos con 'Impuestos'
        // añadimos también esa etiqueta como opción secundaria.
        if (!in_array('Impuestos', $names, true)) {
            $names[] = 'Impuestos';
        }

        $names = array_values(array_unique(array_filter($names)));
        if (empty($names)) {
            return;
        }

        update_option(CashflowSettings::OPT_EGRESO_CATS, $names, false);
    }

    /**
     * Devuelve mapa name => type
     */
    public static function get_category_type_map(): array {
        $posts = get_posts([
            'post_type' => self::CPT,
            'post_status' => 'publish',
            'posts_per_page' => 1000,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        $map = [];
        foreach ($posts as $id) {
            $title = get_the_title((int)$id);
            $title = is_string($title) ? trim($title) : '';
            if ($title === '') continue;
            $type = (string)get_post_meta((int)$id, self::META_TYPE, true);
            if (!in_array($type, ['COGS','OPEX','OTROS'], true)) $type = 'OPEX';
            $map[$title] = $type;
        }
        // Compat: 'Impuestos' histórico lo tratamos como OTROS (IVA/IT pagado)
        if (!isset($map['Impuestos'])) {
            $map['Impuestos'] = 'OTROS';
        }
        return $map;
    }
}

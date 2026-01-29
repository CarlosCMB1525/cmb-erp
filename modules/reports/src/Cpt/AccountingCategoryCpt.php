<?php
declare(strict_types=1);
namespace CMBERP\Modules\Reports\Cpt;

use CMBERP\Modules\Reports\Services\CategorySyncService;

if (!defined('ABSPATH')) { exit; }

/**
 * CPT: Categorías contables (mapeo COGS/OPEX/OTROS)
 * - Título del post = nombre de categoría
 * - Meta: tipo
 */
final class AccountingCategoryCpt {
    public static function register(): void {
        register_post_type(CategorySyncService::CPT, [
            'labels' => [
                'name' => 'Categorías P&L',
                'singular_name' => 'Categoría P&L',
                'add_new' => 'Agregar categoría',
                'add_new_item' => 'Agregar categoría',
                'edit_item' => 'Editar categoría',
                'new_item' => 'Nueva categoría',
                'view_item' => 'Ver categoría',
                'search_items' => 'Buscar categorías',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 58,
            'menu_icon' => 'dashicons-chart-line',
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);

        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post_' . CategorySyncService::CPT, [__CLASS__, 'save_meta'], 10, 2);
    }

    public static function add_meta_boxes(): void {
        add_meta_box(
            'cmb_fin_cat_type',
            'Clasificación',
            [__CLASS__, 'render_box_type'],
            CategorySyncService::CPT,
            'side',
            'high'
        );
        add_meta_box(
            'cmb_fin_cat_help',
            'Ayuda',
            [__CLASS__, 'render_box_help'],
            CategorySyncService::CPT,
            'normal',
            'default'
        );
    }

    public static function render_box_type(\WP_Post $post): void {
        wp_nonce_field('cmb_fin_cat_save', 'cmb_fin_cat_nonce');
        $type = (string) get_post_meta($post->ID, CategorySyncService::META_TYPE, true);
        if ($type === '') $type = 'OPEX';
        echo '<p><label for="cmb_fin_cat_type_select" style="font-weight:800;">Tipo</label></p>';
        echo '<select id="cmb_fin_cat_type_select" name="cmb_fin_cat_type" style="width:100%;">';
        foreach (['COGS' => 'COGS', 'OPEX' => 'OPEX', 'OTROS' => 'OTROS'] as $k => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($type, $k, false), esc_html($label));
        }
        echo '</select>';
        echo '<p class="description" style="margin-top:8px;">COGS = costo directo · OPEX = gasto operativo · OTROS = no operativo (CAPEX/capital/impuestos pagados).</p>';
    }

    public static function render_box_help(): void {
        echo '<div style="max-width:720px">';
        echo '<p><strong>Reglas Enterprise:</strong></p>';
        echo '<ul style="margin-left:18px;">';
        echo '<li>COGS: costos directos para entregar ventas.</li>';
        echo '<li>OPEX: gastos operativos del negocio.</li>';
        echo '<li>OTROS: pagos de capital, compras de equipos, IVA/IT pagado (se concilia, no va a OPEX).</li>';
        echo '</ul>';
        echo '</div>';
    }

    public static function save_meta(int $post_id, \WP_Post $post): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['cmb_fin_cat_nonce']) || !wp_verify_nonce((string)$_POST['cmb_fin_cat_nonce'], 'cmb_fin_cat_save')) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $type = isset($_POST['cmb_fin_cat_type']) ? sanitize_text_field((string)$_POST['cmb_fin_cat_type']) : 'OPEX';
        if (!in_array($type, ['COGS','OPEX','OTROS'], true)) $type = 'OPEX';
        update_post_meta($post_id, CategorySyncService::META_TYPE, $type);

        // Orden opcional: si no existe, autogenerar por ID
        $order = (int) get_post_meta($post_id, CategorySyncService::META_ORDER, true);
        if ($order <= 0) {
            update_post_meta($post_id, CategorySyncService::META_ORDER, (int)$post_id);
        }
    }
}

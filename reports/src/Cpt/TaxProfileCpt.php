<?php
declare(strict_types=1);
namespace CMBERP\Modules\Reports\Cpt;

if (!defined('ABSPATH')) { exit; }

/**
 * CPT: Perfil fiscal por periodo (mensual/anual)
 * Permite editar tasas mes a mes y anual.
 */
final class TaxProfileCpt {
    public const CPT = 'cmb_fin_tax_profile';

    public const META_PERIOD_TYPE = '_cmb_fin_period_type'; // MONTH|YEAR
    public const META_PERIOD_KEY  = '_cmb_fin_period_key';  // YYYY-MM | YYYY
    public const META_SALES_TAX_RATE  = '_cmb_fin_sales_tax_rate'; // 0.16
    public const META_ANNUAL_TAX_RATE = '_cmb_fin_annual_tax_rate'; // 0.25
    public const META_ANNUAL_TAX_MODE = '_cmb_fin_annual_tax_mode'; // PRORRATED|YEAR_END
    public const META_ANNUAL_TAX_BASE = '_cmb_fin_annual_tax_base'; // GROSS_PROFIT

    public static function register(): void {
        register_post_type(self::CPT, [
            'labels' => [
                'name' => 'Perfiles Fiscales',
                'singular_name' => 'Perfil Fiscal',
                'add_new' => 'Agregar perfil',
                'add_new_item' => 'Agregar perfil fiscal',
                'edit_item' => 'Editar perfil fiscal',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 59,
            'menu_icon' => 'dashicons-calculator',
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);

        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post_' . self::CPT, [__CLASS__, 'save_meta'], 10, 2);
    }

    public static function add_meta_boxes(): void {
        add_meta_box('cmb_fin_tax_profile_box', 'Configuración', [__CLASS__, 'render_box'], self::CPT, 'normal', 'high');
    }

    public static function render_box(\WP_Post $post): void {
        wp_nonce_field('cmb_fin_tax_save', 'cmb_fin_tax_nonce');
        $type = (string)get_post_meta($post->ID, self::META_PERIOD_TYPE, true);
        $key  = (string)get_post_meta($post->ID, self::META_PERIOD_KEY, true);
        $sales = (string)get_post_meta($post->ID, self::META_SALES_TAX_RATE, true);
        $annual = (string)get_post_meta($post->ID, self::META_ANNUAL_TAX_RATE, true);
        $mode = (string)get_post_meta($post->ID, self::META_ANNUAL_TAX_MODE, true);
        $base = (string)get_post_meta($post->ID, self::META_ANNUAL_TAX_BASE, true);

        if ($type === '') $type = 'MONTH';
        if ($sales === '') $sales = '0.16';
        if ($annual === '') $annual = '0.25';
        if ($mode === '') $mode = 'PRORRATED';
        if ($base === '') $base = 'GROSS_PROFIT';

        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;max-width:900px">';

        echo '<div><label style="font-weight:800">Tipo de periodo</label><br>';
        echo '<select name="cmb_fin_period_type" style="width:100%">';
        foreach (['MONTH' => 'Mensual (YYYY-MM)', 'YEAR' => 'Anual (YYYY)'] as $k2 => $lbl) {
            printf('<option value="%s" %s>%s</option>', esc_attr($k2), selected($type, $k2, false), esc_html($lbl));
        }
        echo '</select></div>';

        echo '<div><label style="font-weight:800">Clave</label><br>';
        echo '<input type="text" name="cmb_fin_period_key" value="' . esc_attr($key) . '" placeholder="2026-01 o 2026" style="width:100%" />';
        echo '<p class="description">Usa YYYY-MM para mensual o YYYY para anual.</p></div>';

        echo '<div><label style="font-weight:800">Impuesto ventas (sobre facturado)</label><br>';
        echo '<input type="number" step="0.0001" min="0" max="1" name="cmb_fin_sales_tax_rate" value="' . esc_attr($sales) . '" style="width:100%" />';
        echo '<p class="description">Ej: 0.16 para 16%.</p></div>';

        echo '<div><label style="font-weight:800">Impuesto anual</label><br>';
        echo '<input type="number" step="0.0001" min="0" max="1" name="cmb_fin_annual_tax_rate" value="' . esc_attr($annual) . '" style="width:100%" />';
        echo '<p class="description">Ej: 0.25 para 25%.</p></div>';

        echo '<div><label style="font-weight:800">Modo anual</label><br>';
        echo '<select name="cmb_fin_annual_tax_mode" style="width:100%">';
        foreach (['PRORRATED' => 'Prorrateado (25%/12)', 'YEAR_END' => 'Cierre anual'] as $k3 => $lbl) {
            printf('<option value="%s" %s>%s</option>', esc_attr($k3), selected($mode, $k3, false), esc_html($lbl));
        }
        echo '</select></div>';

        echo '<div><label style="font-weight:800">Base impuesto anual</label><br>';
        echo '<select name="cmb_fin_annual_tax_base" style="width:100%">';
        foreach (['GROSS_PROFIT' => 'Utilidad Bruta'] as $k4 => $lbl) {
            printf('<option value="%s" %s>%s</option>', esc_attr($k4), selected($base, $k4, false), esc_html($lbl));
        }
        echo '</select></div>';

        echo '</div>';
    }

    public static function save_meta(int $post_id, \WP_Post $post): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['cmb_fin_tax_nonce']) || !wp_verify_nonce((string)$_POST['cmb_fin_tax_nonce'], 'cmb_fin_tax_save')) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $type = sanitize_text_field((string)($_POST['cmb_fin_period_type'] ?? 'MONTH'));
        if (!in_array($type, ['MONTH','YEAR'], true)) $type = 'MONTH';

        $key = sanitize_text_field((string)($_POST['cmb_fin_period_key'] ?? ''));
        if ($type === 'MONTH') {
            if (!preg_match('/^\d{4}-\d{2}$/', $key)) $key = '';
        } else {
            if (!preg_match('/^\d{4}$/', $key)) $key = '';
        }

        $sales = (float)($_POST['cmb_fin_sales_tax_rate'] ?? 0.16);
        $annual = (float)($_POST['cmb_fin_annual_tax_rate'] ?? 0.25);
        $sales = max(0.0, min(1.0, $sales));
        $annual = max(0.0, min(1.0, $annual));

        $mode = sanitize_text_field((string)($_POST['cmb_fin_annual_tax_mode'] ?? 'PRORRATED'));
        if (!in_array($mode, ['PRORRATED','YEAR_END'], true)) $mode = 'PRORRATED';

        $base = sanitize_text_field((string)($_POST['cmb_fin_annual_tax_base'] ?? 'GROSS_PROFIT'));
        if ($base !== 'GROSS_PROFIT') $base = 'GROSS_PROFIT';

        update_post_meta($post_id, self::META_PERIOD_TYPE, $type);
        update_post_meta($post_id, self::META_PERIOD_KEY, $key);
        update_post_meta($post_id, self::META_SALES_TAX_RATE, (string)$sales);
        update_post_meta($post_id, self::META_ANNUAL_TAX_RATE, (string)$annual);
        update_post_meta($post_id, self::META_ANNUAL_TAX_MODE, $mode);
        update_post_meta($post_id, self::META_ANNUAL_TAX_BASE, $base);
    }
}

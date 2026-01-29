<?php
namespace CMBERP\Modules\Cotizaciones\Admin;

use CMBERP\Modules\Cotizaciones\Domain\CompanyProfileService;

if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/../Domain/CompanyProfileService.php';

/**
 * Página de configuración del Perfil de Empresa para PDF.
 *
 * - Submenu dentro de CMB ERP.
 * - Footer PDF dividido en 3 bloques + URL de imagen.
 */
final class CompanyProfilePage {

    private const PARENT_SLUG = 'cmb-erp';

    public static function register(): void {
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 99);
        add_action('admin_init', [__CLASS__, 'admin_init']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        add_filter('cmb_erp_company_name', [__CLASS__, 'filter_company_name']);
        add_filter('cmb_erp_company_address', [__CLASS__, 'filter_company_address']);
        add_filter('cmb_erp_company_phone', [__CLASS__, 'filter_company_phone']);
        add_filter('cmb_erp_company_email', [__CLASS__, 'filter_company_email']);
        add_filter('cmb_erp_company_logo_url', [__CLASS__, 'filter_company_logo_url']);

        // Footer 3 bloques
        add_filter('cmb_erp_company_footer_block1_html', [__CLASS__, 'filter_footer_b1']);
        add_filter('cmb_erp_company_footer_block2_html', [__CLASS__, 'filter_footer_b2']);
        add_filter('cmb_erp_company_footer_image_url', [__CLASS__, 'filter_footer_img']);

        // Back-compat: mantener filtro anterior si existe (lo redirigimos al bloque 1)
        add_filter('cmb_erp_company_footer_html', [__CLASS__, 'filter_footer_legacy']);
    }

    public static function admin_menu(): void {
        add_submenu_page(
            self::PARENT_SLUG,
            'CMB ERP · Empresa (PDF Cotizaciones)',
            'Empresa (PDF)',
            'manage_options',
            'cmb-erp-company-profile',
            [__CLASS__, 'render']
        );
    }

    public static function admin_init(): void {
        register_setting('cmb_erp_company_profile_group', CompanyProfileService::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize'],
            'default' => CompanyProfileService::defaults(),
        ]);

        add_settings_section('cmb_erp_company_profile_section', 'Datos de Empresa', function () {
            echo '<p>Estos datos se usarán en el encabezado del PDF.</p>';
        }, 'cmb-erp-company-profile');

        add_settings_field('nombre', 'Nombre', [__CLASS__, 'field_nombre'], 'cmb-erp-company-profile', 'cmb_erp_company_profile_section');
        add_settings_field('direccion', 'Dirección', [__CLASS__, 'field_direccion'], 'cmb-erp-company-profile', 'cmb_erp_company_profile_section');
        add_settings_field('telefono', 'Teléfono', [__CLASS__, 'field_telefono'], 'cmb-erp-company-profile', 'cmb_erp_company_profile_section');
        add_settings_field('email', 'Email', [__CLASS__, 'field_email'], 'cmb-erp-company-profile', 'cmb_erp_company_profile_section');
        add_settings_field('logo_url', 'Logo (URL)', [__CLASS__, 'field_logo'], 'cmb-erp-company-profile', 'cmb_erp_company_profile_section');

        add_settings_section('cmb_erp_company_footer_section', 'Pie de página (PDF)', function () {
            echo '<p>El pie del PDF está dividido en 3 bloques: (1) Datos/Legal, (2) Redes, (3) Imagen + paginación.</p>';
        }, 'cmb-erp-company-profile');

        add_settings_field('footer_block1_html', 'Bloque 1 · Datos/Legal', [__CLASS__, 'field_footer_b1'], 'cmb-erp-company-profile', 'cmb_erp_company_footer_section');
        add_settings_field('footer_block2_html', 'Bloque 2 · Redes sociales', [__CLASS__, 'field_footer_b2'], 'cmb-erp-company-profile', 'cmb_erp_company_footer_section');
        add_settings_field('footer_image_url', 'Bloque 3 · Imagen (URL)', [__CLASS__, 'field_footer_img'], 'cmb-erp-company-profile', 'cmb_erp_company_footer_section');
    }

    public static function sanitize($input): array {
        $in = is_array($input) ? $input : [];
        return [
            'nombre' => sanitize_text_field((string)($in['nombre'] ?? '')),
            'direccion' => sanitize_text_field((string)($in['direccion'] ?? '')),
            'telefono' => sanitize_text_field((string)($in['telefono'] ?? '')),
            'email' => sanitize_email((string)($in['email'] ?? '')),
            'logo_url' => esc_url_raw((string)($in['logo_url'] ?? '')),
            'footer_block1_html' => wp_kses_post((string)($in['footer_block1_html'] ?? '')),
            'footer_block2_html' => wp_kses_post((string)($in['footer_block2_html'] ?? '')),
            'footer_image_url' => esc_url_raw((string)($in['footer_image_url'] ?? '')),
        ];
    }

    public static function enqueue_assets(string $hook): void {
        if ($hook !== 'cmb-erp_page_cmb-erp-company-profile') return;
        wp_enqueue_media();
        wp_enqueue_editor();
        if (defined('CMB_ERP_URL')) {
            $rel = 'modules/cotizaciones/assets/js/company-profile-admin.js';
            if (defined('CMB_ERP_DIR') && is_file(CMB_ERP_DIR . $rel)) {
                wp_enqueue_script('cmb-erp-company-profile-admin', CMB_ERP_URL . $rel, ['jquery'], defined('CMB_ERP_VERSION') ? CMB_ERP_VERSION : '1.0.0', true);
            }
        }
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) wp_die('No autorizado');
        $val = CompanyProfileService::get();

        echo '<div class="wrap">';
        echo '<h1>CMB ERP · Empresa (PDF Cotizaciones)</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('cmb_erp_company_profile_group');
        do_settings_sections('cmb-erp-company-profile');
        submit_button('Guardar');
        echo '</form>';

        echo '<hr />';
        echo '<h2>Vista rápida</h2>';
        echo '<p><strong>' . esc_html($val['nombre']) . '</strong></p>';
        if (!empty($val['direccion'])) echo '<p>' . esc_html($val['direccion']) . '</p>';
        $line = implode(' · ', array_filter([$val['telefono'], $val['email']]));
        if ($line) echo '<p>' . esc_html($line) . '</p>';
        if (!empty($val['logo_url'])) {
            echo '<p><img src="' . esc_url($val['logo_url']) . '" style="max-width:240px;height:auto;border:1px solid #ddd;padding:6px;background:#fff;" /></p>';
        }
        echo '<h3>Pie de página</h3>';
        echo '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;max-width:960px;">';
        echo '<div style="background:#fff;border:1px solid #ddd;padding:12px;">' . wp_kses_post($val['footer_block1_html']) . '</div>';
        echo '<div style="background:#fff;border:1px solid #ddd;padding:12px;">' . wp_kses_post($val['footer_block2_html']) . '</div>';
        echo '<div style="background:#fff;border:1px solid #ddd;padding:12px;">';
        if (!empty($val['footer_image_url'])) echo '<img src="' . esc_url($val['footer_image_url']) . '" style="max-width:100%;height:auto;" />';
        echo '<p style="margin-top:8px;color:#666;">Paginación se mostrará debajo de la imagen en el PDF.</p>';
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }

    private static function opt_name(string $key): string {
        return CompanyProfileService::OPTION_KEY . '[' . $key . ']';
    }

    public static function field_nombre(): void {
        $v = CompanyProfileService::get();
        printf('<input type="text" class="regular-text" name="%s" value="%s" />', esc_attr(self::opt_name('nombre')), esc_attr($v['nombre']));
    }
    public static function field_direccion(): void {
        $v = CompanyProfileService::get();
        printf('<input type="text" class="regular-text" name="%s" value="%s" />', esc_attr(self::opt_name('direccion')), esc_attr($v['direccion']));
    }
    public static function field_telefono(): void {
        $v = CompanyProfileService::get();
        printf('<input type="text" class="regular-text" name="%s" value="%s" />', esc_attr(self::opt_name('telefono')), esc_attr($v['telefono']));
    }
    public static function field_email(): void {
        $v = CompanyProfileService::get();
        printf('<input type="email" class="regular-text" name="%s" value="%s" />', esc_attr(self::opt_name('email')), esc_attr($v['email']));
    }

    public static function field_logo(): void {
        $v = CompanyProfileService::get();
        $name = esc_attr(self::opt_name('logo_url'));
        $value = esc_attr($v['logo_url']);
        echo '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
        echo '<input type="url" class="regular-text" id="cmb_erp_logo_url" name="' . $name . '" value="' . $value . '" placeholder="https://..." />';
        echo '<button type="button" class="button" id="cmb_erp_pick_logo">Seleccionar…</button>';
        echo '<button type="button" class="button" id="cmb_erp_clear_logo">Quitar</button>';
        echo '</div>';
    }

    public static function field_footer_b1(): void {
        $v = CompanyProfileService::get();
        $content = (string)($v['footer_block1_html'] ?? '');
        wp_editor($content, 'cmb_erp_footer_b1', [
            'textarea_name' => self::opt_name('footer_block1_html'),
            'textarea_rows' => 5,
            'media_buttons' => false,
            'teeny' => true,
            'quicktags' => true,
        ]);
    }

    public static function field_footer_b2(): void {
        $v = CompanyProfileService::get();
        $content = (string)($v['footer_block2_html'] ?? '');
        wp_editor($content, 'cmb_erp_footer_b2', [
            'textarea_name' => self::opt_name('footer_block2_html'),
            'textarea_rows' => 5,
            'media_buttons' => false,
            'teeny' => true,
            'quicktags' => true,
        ]);
    }

    public static function field_footer_img(): void {
        $v = CompanyProfileService::get();
        $name = esc_attr(self::opt_name('footer_image_url'));
        $value = esc_attr($v['footer_image_url']);
        echo '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
        echo '<input type="url" class="regular-text" id="cmb_erp_footer_image_url" name="' . $name . '" value="' . $value . '" placeholder="https://..." />';
        echo '<button type="button" class="button" id="cmb_erp_pick_footer_image">Seleccionar…</button>';
        echo '<button type="button" class="button" id="cmb_erp_clear_footer_image">Quitar</button>';
        echo '</div>';
        echo '<p class="description">Pega la URL o usa el selector de medios. Ideal: PNG/JPG con ancho 300-600px.</p>';
    }

    // Filters
    public static function filter_company_name($v) { $p = CompanyProfileService::get(); return $p['nombre'] ?: $v; }
    public static function filter_company_address($v) { $p = CompanyProfileService::get(); return $p['direccion'] ?: $v; }
    public static function filter_company_phone($v) { $p = CompanyProfileService::get(); return $p['telefono'] ?: $v; }
    public static function filter_company_email($v) { $p = CompanyProfileService::get(); return $p['email'] ?: $v; }
    public static function filter_company_logo_url($v) { $p = CompanyProfileService::get(); return $p['logo_url'] ?: $v; }

    public static function filter_footer_b1($v) { $p = CompanyProfileService::get(); return $p['footer_block1_html'] ?: $v; }
    public static function filter_footer_b2($v) { $p = CompanyProfileService::get(); return $p['footer_block2_html'] ?: $v; }
    public static function filter_footer_img($v) { $p = CompanyProfileService::get(); return $p['footer_image_url'] ?: $v; }

    public static function filter_footer_legacy($v) {
        $p = CompanyProfileService::get();
        return $p['footer_block1_html'] ?: $v;
    }
}

<?php
namespace CMBERP\Modules\Cotizaciones\Domain;

if (!defined('ABSPATH')) { exit; }

/**
 * Perfil de empresa (persistido) para cotizaciones y PDF.
 * Opción: cmb_erp_company_profile
 */
final class CompanyProfileService {

    public const OPTION_KEY = 'cmb_erp_company_profile';

    public static function defaults(): array {
        return [
            'nombre' => 'Empresa',
            'direccion' => '',
            'telefono' => '',
            'email' => '',
            'logo_url' => '',

            // Footer PDF (3 bloques)
            'footer_block1_html' => '', // Datos empresa / legal
            'footer_block2_html' => '', // Redes sociales
            'footer_image_url'   => '', // URL imagen (derecha)
        ];
    }

    public static function get(): array {
        $raw = get_option(self::OPTION_KEY, []);
        if (!is_array($raw)) $raw = [];
        $d = self::defaults();
        $val = array_merge($d, $raw);

        $val['nombre'] = sanitize_text_field((string)($val['nombre'] ?? $d['nombre']));
        $val['direccion'] = sanitize_text_field((string)($val['direccion'] ?? ''));
        $val['telefono'] = sanitize_text_field((string)($val['telefono'] ?? ''));
        $val['email'] = sanitize_email((string)($val['email'] ?? ''));
        $val['logo_url'] = esc_url_raw((string)($val['logo_url'] ?? ''));

        $val['footer_block1_html'] = wp_kses_post((string)($val['footer_block1_html'] ?? ''));
        $val['footer_block2_html'] = wp_kses_post((string)($val['footer_block2_html'] ?? ''));
        $val['footer_image_url']   = esc_url_raw((string)($val['footer_image_url'] ?? ''));

        return $val;
    }

    public static function update(array $data): bool {
        $d = self::defaults();
        $val = array_merge($d, $data);

        $save = [
            'nombre' => sanitize_text_field((string)$val['nombre']),
            'direccion' => sanitize_text_field((string)$val['direccion']),
            'telefono' => sanitize_text_field((string)$val['telefono']),
            'email' => sanitize_email((string)$val['email']),
            'logo_url' => esc_url_raw((string)$val['logo_url']),

            'footer_block1_html' => wp_kses_post((string)$val['footer_block1_html']),
            'footer_block2_html' => wp_kses_post((string)$val['footer_block2_html']),
            'footer_image_url'   => esc_url_raw((string)$val['footer_image_url']),
        ];

        return update_option(self::OPTION_KEY, $save, false);
    }
}

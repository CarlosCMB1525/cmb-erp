<?php
namespace CMBERP\Core;

if (!defined('ABSPATH')) exit;

require_once dirname(__DIR__) . '/Core/Assets.php';
require_once dirname(__DIR__) . '/Core/AdminMenu.php';

/**
 * Plugin core sin LegacyBridge.
 * - No carga archivos legacy autom¨¢ticamente.
 * - Mantiene compatibilidad opcional con funciones legacy (si existen) sin depender de LegacyBridge.
 * - Permite mapear shortcodes legacy a m¨®dulos modernos para no romper p¨¢ginas antiguas.
 */
function cmb_erp_load_module(string $relative, string $fqcn): void {
    if (!defined('CMB_ERP_DIR')) return;
    $file = CMB_ERP_DIR . ltrim($relative, '/');
    if (file_exists($file)) {
        require_once $file;
        if (class_exists($fqcn)) {
            $fqcn::register();
        }
    }
}

// =========================
// M¨®dulos (entrypoints)
// =========================
// Nota: ya NO se cargan m¨®dulos legacy v¨ªa bridge.

cmb_erp_load_module('modules/dashboard/dashboard-module.php', '\CMBERP\Modules\Dashboard\Module');
cmb_erp_load_module('modules/servicios/servicios-module.php', '\CMBERP\Modules\Servicios\Module');
cmb_erp_load_module('modules/facturacion/facturacion-module.php', '\CMBERP\Modules\Facturacion\Module');
cmb_erp_load_module('modules/auditoria/auditoria-module.php', '\CMBERP\Modules\Auditoria\Module');
cmb_erp_load_module('modules/cotizaciones-online/cotizaciones-online-module.php', '\CMBERP\Modules\CotizacionesOnline\Module');
cmb_erp_load_module('modules/cotizaciones/cotizaciones-module.php', '\CMBERP\Modules\Cotizaciones\Module');
cmb_erp_load_module('modules/ventas/ventas-module.php', '\CMBERP\Modules\Ventas\Module');
cmb_erp_load_module('modules/cashflow/cashflow-module.php', '\CMBERP\Modules\Cashflow\Module');
cmb_erp_load_module('modules/pagos/pagos-module.php', '\CMBERP\Modules\Pagos\Module');
cmb_erp_load_module('modules/clientes/clientes-module.php', '\CMBERP\Modules\Clientes\Module');
cmb_erp_load_module('modules/reports/reports-module.php', '\CMBERP\Modules\Reports\Module');

class Plugin {
    private static ?Plugin $instance = null;

    public static function instance(): Plugin {
        return self::$instance ??= new self();
    }

    public function boot(): void {
        add_action('init', [$this, 'register_shortcodes'], 20);
        Assets::register();
        AdminMenu::register();
    }

    /**
     * Registro de shortcodes modernos (cmb_*) y alias legacy opcionales.
     * Si un m¨®dulo moderno existe, se usa. Si no, se intenta usar funci¨®n legacy (si existe).
     */
    public function register_shortcodes(): void {

        // -------------------------
        // Modernos (cmb_*)
        // -------------------------

        add_shortcode('cmb_dashboard', function () {
            if (class_exists('\CMBERP\Modules\Dashboard\Shortcode')) {
                return \CMBERP\Modules\Dashboard\Shortcode::render();
            }
            // fallback legacy (si existe)
            if (function_exists('tablero_contabilidad')) {
                return (string) tablero_contabilidad();
            }
            return '<div class="cmb-erp-card"><p style="color:#ef4444;font-weight:800;">7²215 Dashboard no disponible.</p></div>';
        });

        add_shortcode('cmb_customers', function () {
    if (class_exists('\\CMBERP\\Modules\\Clientes\\Shortcode')) {
        return \CMBERP\Modules\Clientes\Shortcode::render();
    }
    return '<div class="cmb-erp-card"><p style="color:#ef4444;font-weight:800;">⚠️ Módulo de Clientes no disponible. Verifica /modules/clientes/.</p></div>';
});

add_shortcode('cmb_services', function () {
            if (class_exists('\CMBERP\Modules\Servicios\Shortcode')) {
                return \CMBERP\Modules\Servicios\Shortcode::render();
            }
            if (function_exists('sv_render_catalogo_servicios')) {
                return (string) sv_render_catalogo_servicios();
            }
            return do_shortcode('[catalogo_servicios]');
        });

        add_shortcode('cmb_sales', function () {
            if (class_exists('\CMBERP\Modules\Ventas\Shortcode')) {
                return \CMBERP\Modules\Ventas\Shortcode::render();
            }
            if (function_exists('vn_render_modulo_ventas')) {
                return (string) vn_render_modulo_ventas();
            }
            return do_shortcode('[modulo_ventas]');
        });

        add_shortcode('cmb_quotes', function ($atts = [], $content = null, $tag = '') {
            if (class_exists('\CMBERP\Modules\Cotizaciones\Shortcode')) {
                return \CMBERP\Modules\Cotizaciones\Shortcode::render($atts, $content, $tag);
            }
            if (function_exists('qt_render_modulo_cotizaciones')) {
                return (string) qt_render_modulo_cotizaciones();
            }
            return do_shortcode('[modulo_cotizaciones]');
        });

        add_shortcode('cmb_quotes_table', function ($atts = [], $content = null, $tag = '') {
            if (class_exists('\CMBERP\Modules\CotizacionesOnline\Shortcode')) {
                return \CMBERP\Modules\CotizacionesOnline\Shortcode::render($atts, $content, $tag);
            }
            return do_shortcode('[cotizaciones_tabla]');
        });

        add_shortcode('cmb_invoicing', function () {
            if (class_exists('\CMBERP\Modules\Facturacion\Shortcode')) {
                return \CMBERP\Modules\Facturacion\Shortcode::render();
            }
            return do_shortcode('[registro_facturacion]');
        });

        add_shortcode('cmb_payments', function () {
            if (class_exists('\CMBERP\Modules\Pagos\Shortcode')) {
                return \CMBERP\Modules\Pagos\Shortcode::render();
            }
            return '<div class="cmb-erp-card"><p style="color:#ef4444;font-weight:800;">7²215 M¨®dulo de Pagos no disponible.</p></div>';
        });

        add_shortcode('cmb_audit', function () {
            if (class_exists('\CMBERP\Modules\Auditoria\Shortcode')) {
                return \CMBERP\Modules\Auditoria\Shortcode::render();
            }
            return do_shortcode('[sistema_auditoria]');
        });

        add_shortcode('cmb_vendor_payments', function ($atts = [], $content = null, $tag = '') {
            if (class_exists('\CMBERP\Modules\Cashflow\Shortcode')) {
                return \CMBERP\Modules\Cashflow\Shortcode::render($atts, $content, $tag);
            }
            return '<div class="cmb-erp-card"><p style="color:#ef4444;font-weight:800;">7²215 Cashflow no est¨¢ cargado. Verifica /modules/cashflow/.</p></div>';
        });

        // -------------------------
        // Alias legacy (opcional)
        // -------------------------
        // Si est¨¢s migrando ¡°de golpe¡± y quieres eliminar compatibilidad, puedes borrar este bloque.

        add_shortcode('modulo_ventas', function ($atts = [], $content = null, $tag = '') {
            if (class_exists('\CMBERP\Modules\Ventas\Shortcode')) {
                return \CMBERP\Modules\Ventas\Shortcode::render($atts, $content, $tag);
            }
            if (function_exists('vn_render_modulo_ventas')) {
                return (string) vn_render_modulo_ventas();
            }
            return '<div class="cmb-erp-card"><p style="color:#ef4444;font-weight:800;">7²215 Ventas no disponible.</p></div>';
        });

        add_shortcode('modulo_cotizaciones', function ($atts = [], $content = null, $tag = '') {
            if (class_exists('\CMBERP\Modules\Cotizaciones\Shortcode')) {
                return \CMBERP\Modules\Cotizaciones\Shortcode::render($atts, $content, $tag);
            }
            return '<div class="cmb-erp-card"><p style="color:#ef4444;font-weight:800;">7²215 Cotizaciones no disponible.</p></div>';
        });

        add_shortcode('cotizaciones_tabla', function ($atts = [], $content = null, $tag = '') {
            if (class_exists('\CMBERP\Modules\CotizacionesOnline\Shortcode')) {
                return \CMBERP\Modules\CotizacionesOnline\Shortcode::render($atts, $content, $tag);
            }
            return '<div class="cmb-erp-card"><p style="color:#ef4444;font-weight:800;">7²215 Cotizaciones online no disponible.</p></div>';
        });

    }
}

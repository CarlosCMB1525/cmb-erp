<?php
namespace CMBERP\Core;
if (!defined('ABSPATH')) exit;

class AdminMenu {
  public static function register(): void {
    add_action('admin_menu', [__CLASS__, 'menu']);
  }

  public static function menu(): void {
    add_menu_page('CMB ERP','CMB ERP','edit_posts','cmb-erp',[__CLASS__,'page_home'],'dashicons-chart-line',26);
    add_submenu_page('cmb-erp','Shortcodes','Shortcodes','edit_posts','cmb-erp-shortcodes',[__CLASS__,'page_shortcodes']);
    add_submenu_page('cmb-erp','Ajustes Flujo de Caja','Flujo de Caja (Ajustes)','manage_options','cmb-erp-cashflow-settings',[\CMBERP\Cashflow\Admin::class,'page_settings']);
  }

  public static function page_home(): void {
    echo '<div class="wrap"><h1>CMB ERP</h1><p>Plugin modular por shortcodes <code>cmb_*</code>.</p></div>';
  }

  public static function page_shortcodes(): void {
    echo '<div class="wrap"><h1>Shortcodes</h1><ul style="list-style:disc;padding-left:18px;">'
      . '<li><code>[cmb_dashboard]</code></li>'
      . '<li><code>[cmb_customers]</code></li>'
      . '<li><code>[cmb_services]</code></li>'
      . '<li><code>[cmb_sales]</code></li>'
      . '<li><code>[cmb_quotes]</code></li>'
      . '<li><code>[cmb_quotes_table]</code> <em>(antes: [cotizaciones_tabla])</em></li>'
      . '<li><code>[cmb_invoicing]</code></li>'
      . '<li><code>[cmb_payments]</code></li>'
      . '<li><code>[cmb_audit]</code></li>'
      . '<li><code>[cmb_vendor_payments]</code> <em>(Flujo de Caja)</em></li>'
      . '</ul></div>';
  }
}

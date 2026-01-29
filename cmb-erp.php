<?php
/**
 * Plugin Name: CMB ERP
 * Description: CMB ERP modular (shortcodes cmb_*). Incluye Control de Flujo de Caja en [cmb_vendor_payments].
 * Version: 1.0.2
 * Author: Carlos De Gumucio
 * Text Domain: cmb-erp
 */

if (!defined('ABSPATH')) exit;

define('CMB_ERP_VERSION', '1.0.2');
define('CMB_ERP_SLUG', 'cmb-erp');
define('CMB_ERP_DIR', plugin_dir_path(__FILE__));
define('CMB_ERP_URL', plugin_dir_url(__FILE__));
define('CMB_ERP_NONCE_ACTION', 'cmb_erp_nonce');

require_once CMB_ERP_DIR.'src/Core/Plugin.php';
require_once CMB_ERP_DIR.'src/Database/Installer.php';

register_activation_hook(__FILE__, ['\CMBERP\Database\Installer', 'activate']);

add_action('plugins_loaded', function(){
  \CMBERP\Core\Plugin::instance()->boot();
}, 1);

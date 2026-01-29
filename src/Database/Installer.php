<?php
namespace CMBERP\Database;
if (!defined('ABSPATH')) exit;

class Installer {
  public static function activate(): void {
    // No tocar tablas existentes aquí para evitar romper instalaciones.
    update_option('cmb_erp_db_version', '1.0.2');
  }
}

<?php
namespace CMBERP\Modules\Cashflow;
if (!defined('ABSPATH')) exit;

final class Installer {
  public static function maybe_install(): void {
    // Ejecuta cuando cambia la versión o cuando faltan columnas clave.
    $cur = get_option(Settings::DB_VER_OPT, '');

    global $wpdb;
    $table = $wpdb->prefix . Settings::TABLE_NAME;
    $table_p = $wpdb->prefix . Settings::TABLE_PAYMENTS;
    $charset = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Movimientos
    $sql = "CREATE TABLE {$table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      tipo VARCHAR(20) NOT NULL,
      estado VARCHAR(30) NOT NULL DEFAULT 'Pendiente',
      detalle TEXT NOT NULL,
      beneficiario VARCHAR(255) NULL,
      categoria_egreso VARCHAR(255) NULL,
      categoria_ingreso VARCHAR(255) NULL,
      metodo_pago VARCHAR(100) NULL,
      monto_bs DECIMAL(18,2) NOT NULL DEFAULT 0,
      monto_usd DECIMAL(18,2) NULL,
      tasa_cambio DECIMAL(18,6) NULL,
      adjunto TEXT NULL,
      adjuntos LONGTEXT NULL,
      creado_en DATETIME NOT NULL,
      pagado_en DATETIME NULL,
      created_by BIGINT UNSIGNED NULL,
      PRIMARY KEY (id),
      KEY tipo (tipo),
      KEY estado (estado),
      KEY creado_en (creado_en),
      KEY pagado_en (pagado_en)
    ) {$charset};";
    dbDelta($sql);

    // Pagos parciales (historial)
    $sql2 = "CREATE TABLE {$table_p} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      movimiento_id BIGINT UNSIGNED NOT NULL,
      monto_bs DECIMAL(18,2) NOT NULL DEFAULT 0,
      monto_usd DECIMAL(18,2) NULL,
      tasa_cambio DECIMAL(18,6) NULL,
      metodo_pago VARCHAR(100) NULL,
      comprobante VARCHAR(255) NULL,
      fecha_comprobante DATE NULL,
      adjunto TEXT NULL,
      adjuntos LONGTEXT NULL,
      pagado_en DATETIME NOT NULL,
      created_by BIGINT UNSIGNED NULL,
      PRIMARY KEY (id),
      KEY movimiento_id (movimiento_id),
      KEY pagado_en (pagado_en)
    ) {$charset};";
    dbDelta($sql2);

    // Hardening: asegurar columna adjuntos en movimientos (instalaciones antiguas)
    $has_adjuntos = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'adjuntos'));
    if (empty($has_adjuntos)) {
      $wpdb->query("ALTER TABLE {$table} ADD COLUMN adjuntos LONGTEXT NULL AFTER adjunto");
    }

    // Guardar versión (mantiene el nombre y la opción existente)
    if ($cur !== Settings::DB_VER) {
      update_option(Settings::DB_VER_OPT, Settings::DB_VER);
    }
  }
}

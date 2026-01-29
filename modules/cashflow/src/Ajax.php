<?php
namespace CMBERP\Modules\Cashflow;
if (!defined('ABSPATH')) exit;

final class Ajax {
  private static function can_access(): bool {
    return current_user_can('administrator') || current_user_can('edit_posts');
  }
  private static function nonce_ok(): bool {
    return isset($_POST['nonce']) && defined('CMB_ERP_NONCE_ACTION') && wp_verify_nonce($_POST['nonce'], CMB_ERP_NONCE_ACTION);
  }
  private static function fail(string $msg): void { wp_send_json_error($msg); }
  private static function ok(array $data = []): void { wp_send_json_success($data); }

  public static function register(): void {
    add_action('wp_ajax_cmb_cashflow_create', [__CLASS__, 'create']);
    add_action('wp_ajax_cmb_cashflow_list', [__CLASS__, 'list_items']);
    add_action('wp_ajax_cmb_cashflow_get', [__CLASS__, 'get']);
    add_action('wp_ajax_cmb_cashflow_pay', [__CLASS__, 'pay']);
    add_action('wp_ajax_cmb_cashflow_history', [__CLASS__, 'history']);
  }

  private static function ensure(): void { Installer::maybe_install(); }

  private static function normalize_tipo($t): string {
    $t = strtoupper(trim((string)$t));
    return ($t === 'INGRESO') ? 'Ingreso' : (($t === 'EGRESO') ? 'Egreso' : (ucfirst(strtolower($t)) ?: 'Egreso'));
  }

  private static function safe_decimal($v, int $dec = 2): float { return round((float)$v, $dec); }

  private static function parse_adjuntos_ids(string $raw): array {
    $parts = array_filter(array_map('trim', preg_split('/[\s,]+/', (string)$raw)));
    $ids = [];
    foreach ($parts as $p) {
      $aid = (int)$p;
      if ($aid > 0) $ids[] = $aid;
    }
    return array_values(array_unique($ids));
  }

  private static function upload_adjunto(string $field): string {
    // Legacy upload (compat). UI principal usa biblioteca de medios.
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) return '';
    $f = $_FILES[$field];
    if (!isset($f['error']) || $f['error'] === UPLOAD_ERR_NO_FILE) return '';
    if ($f['error'] !== UPLOAD_ERR_OK) return '';
    if (!current_user_can('upload_files')) return '';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $overrides = [
      'test_form' => false,
      'mimes' => [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
      ],
    ];
    $res = wp_handle_upload($f, $overrides);
    if (is_array($res) && empty($res['error']) && !empty($res['url'])) return (string)$res['url'];
    return '';
  }

  /** Crear movimiento */
  public static function create(): void {
    if (!self::can_access()) self::fail('No tienes permisos.');
    if (!self::nonce_ok()) self::fail('Nonce inválido. Recarga la página.');
    self::ensure();

    global $wpdb;
    $table = $wpdb->prefix . Settings::TABLE_NAME;

    $tipo = self::normalize_tipo($_POST['tipo'] ?? 'Egreso');
    $detalle = sanitize_text_field((string)($_POST['detalle'] ?? ''));
    $benef = sanitize_text_field((string)($_POST['beneficiario'] ?? ''));
    $cat_e = sanitize_text_field((string)($_POST['categoria_egreso'] ?? ''));
    $cat_i = sanitize_text_field((string)($_POST['categoria_ingreso'] ?? ''));
    $metodo = sanitize_text_field((string)($_POST['metodo_pago'] ?? ''));
    $monto_bs = self::safe_decimal($_POST['monto_bs'] ?? 0, 2);
    $monto_usd = self::safe_decimal($_POST['monto_usd'] ?? 0, 2);
    $tasa = self::safe_decimal($_POST['tasa_cambio'] ?? 0, 6);

    if ($detalle === '') self::fail('Detalle es obligatorio.');
    if ($monto_bs <= 0) self::fail('Monto Bs inválido.');

    $mets = Settings::mets();
    if ($metodo === '' || !in_array($metodo, $mets, true)) $metodo = $mets[0];
    $cats = Settings::cats();
    if ($cat_e === '' || !in_array($cat_e, $cats, true)) $cat_e = $cats[0];

    // Adjuntos opcionales (biblioteca de medios): IDs en JSON
    $adj_ids = [];
    if (isset($_POST['adjuntos_ids'])) {
      $adj_ids = self::parse_adjuntos_ids((string)$_POST['adjuntos_ids']);
    }

    // Legacy single upload (opcional)
    $adj_legacy = self::upload_adjunto('adjunto');

    $data = [
      'tipo' => $tipo,
      'estado' => 'Pendiente',
      'detalle' => $detalle,
      'beneficiario' => $benef,
      'categoria_egreso' => $tipo === 'Egreso' ? $cat_e : null,
      'categoria_ingreso' => $tipo === 'Ingreso' ? ($cat_i ?: 'Inyección de Capital') : null,
      'metodo_pago' => $metodo,
      'monto_bs' => $monto_bs,
      'monto_usd' => ($metodo === 'Tarjeta Internacional') ? $monto_usd : null,
      'tasa_cambio' => ($metodo === 'Tarjeta Internacional') ? $tasa : null,
      'adjunto' => $adj_legacy ?: null,
      'adjuntos' => !empty($adj_ids) ? wp_json_encode($adj_ids) : null,
      'creado_en' => current_time('mysql'),
      'pagado_en' => null,
      'created_by' => get_current_user_id(),
    ];

    $ok = $wpdb->insert($table, $data);
    if (!$ok) self::fail('DB: ' . $wpdb->last_error);
    self::ok(['id' => (int)$wpdb->insert_id]);
  }

  /** Listado de movimientos (tbody HTML) */
  public static function list_items(): void {
    if (!self::can_access()) self::fail('No tienes permisos.');
    if (!self::nonce_ok()) self::fail('Nonce inválido.');
    self::ensure();

    global $wpdb;
    $table_m = $wpdb->prefix . Settings::TABLE_NAME;
    $table_p = $wpdb->prefix . Settings::TABLE_PAYMENTS;

    $rows = $wpdb->get_results("SELECT * FROM {$table_m} ORDER BY id DESC LIMIT 200");

    ob_start();
    foreach ((array)$rows as $r) {
      $id = (int)$r->id;
      $tipo = esc_html((string)$r->tipo);
      $detalle = esc_html((string)$r->detalle);
      $benef = esc_html((string)$r->beneficiario);
      $cat_raw = ((string)$r->tipo === 'Ingreso') ? (string)$r->categoria_ingreso : (string)$r->categoria_egreso;
      $cat = esc_html($cat_raw);

      $total = (float)$r->monto_bs;
      $paid = (float)$wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(monto_bs),0) FROM {$table_p} WHERE movimiento_id=%d",
        $id
      ));
      $saldo = max(0, round($total - $paid, 2));
      $estado = ($saldo <= 0.00) ? 'Pagado' : (($paid > 0) ? 'Parcial' : 'Pendiente');

      $total_f = number_format($total, 2);
      $paid_f = number_format($paid, 2);
      $saldo_f = number_format($saldo, 2);
      $fecha = esc_html(date('d/m/Y H:i', strtotime((string)$r->creado_en)));

      // Adjuntos: IDs (JSON) + legacy URL
      $links = [];
      $adj_json = (string)($r->adjuntos ?? '');
      if ($adj_json) {
        $ids = json_decode($adj_json, true);
        if (is_array($ids)) {
          foreach ($ids as $aid) {
            $aid = (int)$aid;
            if ($aid > 0) {
              $u = wp_get_attachment_url($aid);
              if ($u) $links[] = $u;
            }
          }
        }
      }
      $adj_legacy = (string)($r->adjunto ?? '');
      if ($adj_legacy) $links[] = $adj_legacy;
      $links = array_values(array_unique(array_filter($links)));

      if (!empty($links)) {
        $adj_html = '';
        foreach ($links as $u) {
          $adj_html .= '<a href="' . esc_url($u) . '" target="_blank" rel="noopener">📎</a> ';
        }
        $adj_html = trim($adj_html);
      } else {
        $adj_html = '<span class="cmb-erp-text-muted">—</span>';
      }

      $pay_disabled = ($saldo <= 0.00) ? 'disabled aria-disabled="true"' : '';

      echo '<tr>';
      echo '<td><strong>#' . $id . '</strong></td>';
      echo '<td>' . $tipo . '</td>';
      echo '<td>' . $detalle . '</td>';
      echo '<td>' . $benef . '</td>';
      echo '<td>' . $cat . '</td>';
      echo '<td><strong>' . $total_f . ' Bs</strong></td>';
      echo '<td class="cmb-erp-text-muted">' . $paid_f . ' Bs</td>';
      echo '<td><strong>' . $saldo_f . ' Bs</strong></td>';
      echo '<td>' . esc_html($estado) . '</td>';
      echo '<td>' . $adj_html . '</td>';
      echo '<td>' . $fecha . '</td>';
      echo '<td class="cmb-erp-text-right">';
      echo '<button type="button" class="cmb-erp-btn cmb-erp-btn--ghost cmb-erp-btn--sm" data-cf-action="view" data-id="' . $id . '">Ver</button> ';
      echo '<button type="button" class="cmb-erp-btn cmb-erp-btn--sm" data-cf-action="pay" data-id="' . $id . '" data-saldo="' . esc_attr((string)$saldo) . '" ' . $pay_disabled . '>Pagar</button> ';
      echo '<button type="button" class="cmb-erp-btn cmb-erp-btn--ghost cmb-erp-btn--sm" data-cf-action="history" data-id="' . $id . '">Histórico</button>';
      echo '</td>';
      echo '</tr>';
    }

    $tbody = ob_get_clean();
    self::ok(['tbody' => $tbody]);
  }

  public static function get(): void {
    if (!self::can_access()) self::fail('No tienes permisos.');
    if (!self::nonce_ok()) self::fail('Nonce inválido.');
    self::ensure();

    global $wpdb;
    $table = $wpdb->prefix . Settings::TABLE_NAME;
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) self::fail('ID inválido.');
    $r = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id));
    if (!$r) self::fail('No encontrado.');

    // Resolver URLs para adjuntos IDs
    $urls = [];
    $adj_json = (string)($r->adjuntos ?? '');
    if ($adj_json) {
      $ids = json_decode($adj_json, true);
      if (is_array($ids)) {
        foreach ($ids as $aid) {
          $aid = (int)$aid;
          if ($aid > 0) {
            $u = wp_get_attachment_url($aid);
            if ($u) $urls[] = $u;
          }
        }
      }
    }
    $adj_legacy = (string)($r->adjunto ?? '');
    if ($adj_legacy) $urls[] = $adj_legacy;
    $urls = array_values(array_unique(array_filter($urls)));

    self::ok(['row' => $r, 'attachments' => $urls]);
  }

  /** Registrar pago parcial */
  public static function pay(): void {
    if (!self::can_access()) self::fail('No tienes permisos.');
    if (!self::nonce_ok()) self::fail('Nonce inválido.');
    self::ensure();

    global $wpdb;
    $table_m = $wpdb->prefix . Settings::TABLE_NAME;
    $table_p = $wpdb->prefix . Settings::TABLE_PAYMENTS;

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) self::fail('ID inválido.');

    $mov = $wpdb->get_row($wpdb->prepare("SELECT id, monto_bs FROM {$table_m} WHERE id=%d", $id));
    if (!$mov) self::fail('Movimiento no encontrado.');

    $total = (float)$mov->monto_bs;
    $paid = (float)$wpdb->get_var($wpdb->prepare(
      "SELECT COALESCE(SUM(monto_bs),0) FROM {$table_p} WHERE movimiento_id=%d",
      $id
    ));
    $saldo = round($total - $paid, 2);
    if ($saldo <= 0) self::fail('Este movimiento ya está pagado.');

    $metodo = sanitize_text_field((string)($_POST['metodo_pago'] ?? ''));
    $fecha = sanitize_text_field((string)($_POST['fecha_pago'] ?? ''));
    if ($fecha === '') self::fail('Fecha de pago obligatoria.');

    $comprobante = sanitize_text_field((string)($_POST['comprobante'] ?? ''));
    $fecha_comp = sanitize_text_field((string)($_POST['fecha_comprobante'] ?? ''));
    $fecha_comp = ($fecha_comp !== '') ? $fecha_comp : null;

    $monto_bs = self::safe_decimal($_POST['monto_bs'] ?? 0, 2);
    $monto_usd = null;
    $tasa_cambio = null;

    if ($metodo === 'Tarjeta Internacional') {
      $usd = self::safe_decimal($_POST['monto_usd'] ?? 0, 2);
      $tasa = self::safe_decimal($_POST['tasa_cambio'] ?? 0, 6);
      if ($usd > 0 && $tasa > 0) {
        $monto_usd = $usd;
        $tasa_cambio = $tasa;
        $monto_bs = round($usd * $tasa, 2);
      }
    }

    if ($monto_bs <= 0) self::fail('Monto Bs inválido.');
    if ($monto_bs > $saldo) self::fail('El monto excede el saldo pendiente.');

    $adj_ids = [];
    if (isset($_POST['adjuntos_ids'])) {
      $adj_ids = self::parse_adjuntos_ids((string)$_POST['adjuntos_ids']);
    }

    $adj_legacy = self::upload_adjunto('adjunto');

    $ok = $wpdb->insert($table_p, [
      'movimiento_id' => $id,
      'monto_bs' => $monto_bs,
      'monto_usd' => $monto_usd,
      'tasa_cambio' => $tasa_cambio,
      'metodo_pago' => $metodo,
      'comprobante' => $comprobante ?: null,
      'fecha_comprobante' => $fecha_comp,
      'adjunto' => $adj_legacy ?: null,
      'adjuntos' => !empty($adj_ids) ? wp_json_encode($adj_ids) : null,
      'pagado_en' => $fecha . ' ' . current_time('H:i:s'),
      'created_by' => get_current_user_id(),
    ]);

    if (!$ok) self::fail('DB pago: ' . $wpdb->last_error);

    $paid2 = (float)$wpdb->get_var($wpdb->prepare(
      "SELECT COALESCE(SUM(monto_bs),0) FROM {$table_p} WHERE movimiento_id=%d",
      $id
    ));
    $saldo2 = round($total - $paid2, 2);
    $estado = ($saldo2 <= 0.00) ? 'Pagado' : 'Parcial';
    $pagado_en = ($saldo2 <= 0.00) ? ($fecha . ' ' . current_time('H:i:s')) : null;

    $wpdb->update($table_m, [
      'estado' => $estado,
      'metodo_pago' => $metodo,
      'pagado_en' => $pagado_en,
    ], ['id' => $id]);

    self::ok(['id' => $id, 'estado' => $estado, 'paid' => $paid2, 'saldo' => max(0, $saldo2)]);
  }

  /** Histórico de pagos */
  public static function history(): void {
    if (!self::can_access()) self::fail('No tienes permisos.');
    if (!self::nonce_ok()) self::fail('Nonce inválido.');
    self::ensure();

    global $wpdb;
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) self::fail('ID inválido.');

    $table_m = $wpdb->prefix . Settings::TABLE_NAME;
    $table_p = $wpdb->prefix . Settings::TABLE_PAYMENTS;

    $mov = $wpdb->get_row($wpdb->prepare("SELECT id, monto_bs FROM {$table_m} WHERE id=%d", $id));
    if (!$mov) self::fail('Movimiento no encontrado.');

    $total = (float)$mov->monto_bs;
    $paid = (float)$wpdb->get_var($wpdb->prepare(
      "SELECT COALESCE(SUM(monto_bs),0) FROM {$table_p} WHERE movimiento_id=%d",
      $id
    ));
    $saldo = max(0, round($total - $paid, 2));

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT id, monto_bs, monto_usd, tasa_cambio, metodo_pago, comprobante, fecha_comprobante, adjunto, adjuntos, pagado_en FROM {$table_p} WHERE movimiento_id=%d ORDER BY id DESC",
      $id
    ));

    ob_start();
    if (empty($rows)) {
      echo '<span class="cmb-erp-text-muted">--- Sin pagos registrados ---</span>';
    } else {
      foreach ($rows as $p) {
        $monto = number_format((float)$p->monto_bs, 2);
        $met = esc_html((string)$p->metodo_pago);
        $comp = esc_html((string)$p->comprobante);
        $fcomp = $p->fecha_comprobante ? esc_html(date('d/m/Y', strtotime((string)$p->fecha_comprobante))) : '';
        $fecha = esc_html(date('d/m/Y', strtotime((string)$p->pagado_en)));

        $links = [];
        $adj_json = (string)$p->adjuntos;
        if ($adj_json) {
          $ids = json_decode($adj_json, true);
          if (is_array($ids)) {
            foreach ($ids as $aid) {
              $aid = (int)$aid;
              if ($aid > 0) {
                $url = wp_get_attachment_url($aid);
                if ($url) $links[] = '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">📎 Archivo</a>';
              }
            }
          }
        }
        $adj_legacy = (string)$p->adjunto;
        if ($adj_legacy) $links[] = '<a href="' . esc_url($adj_legacy) . '" target="_blank" rel="noopener">📎 Archivo</a>';

        echo '<div class="rp-box">';
        echo '<div style="display:flex; justify-content:space-between; gap:10px; align-items:flex-start;">';
        echo '<div>';
        echo '<strong>' . esc_html($monto) . ' Bs</strong> ';
        echo '<span class="cmb-erp-text-muted" style="font-size:12px;">— ' . $met . '</span>';
        if ($comp !== '') {
          echo '<div style="font-size:12px; margin-top:4px;">Comprobante: <code>' . $comp . '</code>';
          if ($fcomp !== '') echo ' <span class="cmb-erp-text-muted">(' . $fcomp . ')</span>';
          echo '</div>';
        }
        echo '<div style="font-size:12px; margin-top:4px;" class="cmb-erp-text-muted">Fecha pago: ' . $fecha . '</div>';
        if (!empty($links)) {
          echo '<div style="font-size:12px; margin-top:6px;">' . implode(' ', $links) . '</div>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
      }
    }

    $html = ob_get_clean();
    self::ok(['html' => $html, 'total' => $total, 'paid' => $paid, 'saldo' => $saldo]);
  }
}

<?php
namespace CMBERP\Modules\Pagos;

if (!defined('ABSPATH')) { exit; }

final class Ajax {
    public static function register(): void {
        // Legacy actions (requisito)
        add_action('wp_ajax_crm_pago_v83', [__CLASS__, 'save_payment']);
        add_action('wp_ajax_crm_borrar_pago_v83', [__CLASS__, 'delete_payment']);
        add_action('wp_ajax_crm_editar_pago_v83', [__CLASS__, 'edit_payment']);
        // Nuevo: filtros avanzados (server-side)
        add_action('wp_ajax_cmb_pagos_list', [__CLASS__, 'list_sales']);
    }

    private static function fail(string $msg): void { wp_send_json_error($msg); }

    public static function save_payment(): void {
        if (!Logic::can_access()) self::fail('No tienes permisos para registrar pagos.');
        if (!Logic::nonce_ok()) self::fail('Nonce inválido. Recarga la página.');
        global $wpdb;
        $t_ventas = $wpdb->prefix . 'vn_ventas';
        $t_pagos  = $wpdb->prefix . 'vn_pagos';
        $v_id = (int)($_POST['v_id'] ?? 0);
        $monto = (float)($_POST['monto'] ?? 0);
        $metodo = sanitize_text_field((string)($_POST['metodo'] ?? ''));
        $ref = sanitize_text_field((string)($_POST['ref'] ?? ''));
        $adj_id = (int)($_POST['adjunto_id'] ?? 0);
        $fec = Logic::safe_date_ymd((string)($_POST['fecha_pago'] ?? ''));
        if ($fec === '') self::fail('Fecha de pago inválida (usa YYYY-MM-DD).');
        $fecha_dt = $fec . ' ' . current_time('H:i:s');
        if ($v_id <= 0) self::fail('Venta inválida.');
        if ($monto <= 0) self::fail('Monto inválido.');
        if ($metodo === '') self::fail('Método inválido.');
        if ($ref === '') self::fail('Referencia obligatoria.');
        $total_v = $wpdb->get_var($wpdb->prepare("SELECT total_bs FROM $t_ventas WHERE id=%d", $v_id));
        if ($total_v === null) self::fail('La venta no existe.');
        $total_v = (float)$total_v;
        $pagado_v = (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(monto_pagado),0) FROM $t_pagos WHERE venta_id=%d", $v_id));
        $saldo = round($total_v - $pagado_v, 2);
        if ($saldo < 0) $saldo = 0;
        if ($monto > ($saldo + 0.05)) self::fail('El monto excede el saldo pendiente de ' . number_format($saldo, 2) . ' Bs.');
        $url_adjunto = '';
        if ($adj_id > 0) {
            $u = wp_get_attachment_url($adj_id);
            $url_adjunto = $u ? $u : '';
        }
        $sql = $wpdb->prepare(
            "INSERT INTO $t_pagos (venta_id, monto_pagado, metodo_pago, referencia, url_adjunto, fecha_pago) VALUES (%d, %f, %s, %s, %s, %s)",
            $v_id, round($monto,2), $metodo, $ref, $url_adjunto, $fecha_dt
        );
        $ok = $wpdb->query($sql);
        if ($ok === false) self::fail('Error DB: ' . $wpdb->last_error);
        $estado = Logic::recalcular_estado_venta($v_id);
        wp_send_json_success(['estado' => $estado]);
    }

    public static function delete_payment(): void {
        if (!Logic::can_access()) self::fail('No tienes permisos para eliminar pagos.');
        if (!Logic::nonce_ok()) self::fail('Nonce inválido. Recarga la página.');
        global $wpdb;
        $t_pagos = $wpdb->prefix . 'vn_pagos';
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) self::fail('ID inválido.');
        $venta_id = $wpdb->get_var($wpdb->prepare("SELECT venta_id FROM $t_pagos WHERE id=%d", $id));
        if (!$venta_id) self::fail('Registro no existe.');
        $ok = $wpdb->delete($t_pagos, ['id'=>$id], ['%d']);
        if ($ok === false) self::fail('Error DB: ' . $wpdb->last_error);
        $estado = Logic::recalcular_estado_venta((int)$venta_id);
        wp_send_json_success(['estado'=>$estado]);
    }

    public static function edit_payment(): void {
        if (!Logic::can_access()) self::fail('No tienes permisos para editar pagos.');
        if (!Logic::nonce_ok()) self::fail('Nonce inválido. Recarga la página.');
        global $wpdb;
        $t_pagos = $wpdb->prefix . 'vn_pagos';
        $id = (int)($_POST['id'] ?? 0);
        $monto = (float)($_POST['monto'] ?? 0);
        $metodo = sanitize_text_field((string)($_POST['metodo'] ?? ''));
        $ref = sanitize_text_field((string)($_POST['ref'] ?? ''));
        $adj_id = (int)($_POST['adjunto_id'] ?? 0);
        if ($id <= 0) self::fail('ID inválido.');
        if ($monto <= 0) self::fail('Monto inválido.');
        if ($metodo === '') self::fail('Método inválido.');
        if ($ref === '') self::fail('Referencia obligatoria.');
        $venta_id = $wpdb->get_var($wpdb->prepare("SELECT venta_id FROM $t_pagos WHERE id=%d", $id));
        if (!$venta_id) self::fail('Pago no encontrado.');
        $url_adjunto = null;
        if ($adj_id > 0) {
            $u = wp_get_attachment_url($adj_id);
            $url_adjunto = $u ? $u : '';
        }
        $data = ['monto_pagado'=>round($monto,2), 'metodo_pago'=>$metodo, 'referencia'=>$ref];
        if ($url_adjunto !== null) $data['url_adjunto'] = $url_adjunto;
        $ok = $wpdb->update($t_pagos, $data, ['id'=>$id]);
        if ($ok === false) self::fail('Error DB: ' . $wpdb->last_error);
        $estado = Logic::recalcular_estado_venta((int)$venta_id);
        wp_send_json_success(['estado'=>$estado]);
    }

    /**
     * Filtros avanzados + orden (tabla principal) - devuelve tbody HTML.
     */
    public static function list_sales(): void {
        if (!Logic::can_access()) self::fail('No tienes permisos.');
        if (!Logic::nonce_ok()) self::fail('Nonce inválido.');

        global $wpdb;
        $t_ventas   = $wpdb->prefix . 'vn_ventas';
        $t_empresas = $wpdb->prefix . 'cl_empresas';
        $t_facturas = $wpdb->prefix . 'vn_facturas';
        $t_pagos    = $wpdb->prefix . 'vn_pagos';

        $ff_from = Logic::safe_date_ymd($_POST['ff_from'] ?? '');
        $ff_to   = Logic::safe_date_ymd($_POST['ff_to'] ?? '');
        $fp_from = Logic::safe_date_ymd($_POST['fp_from'] ?? '');
        $fp_to   = Logic::safe_date_ymd($_POST['fp_to'] ?? '');

        $order_by  = sanitize_text_field((string)($_POST['order_by'] ?? 'venta_id'));
        $order_dir = strtolower(sanitize_text_field((string)($_POST['order_dir'] ?? 'desc')));
        if (!in_array($order_dir, ['asc','desc'], true)) $order_dir = 'desc';

        $allowed_order = [
            'venta_id' => 'v.id',
            'cliente' => 'c.nombre_legal',
            'saldo' => 'saldo',
            'fecha_facturacion' => 'fecha_facturacion',
            'fecha_pago' => 'ultima_fecha_pago',
        ];
        $order_sql = $allowed_order[$order_by] ?? 'v.id';

        $where = [];
        $params = [];
        if ($ff_from !== '') { $where[] = 'COALESCE(f.fecha_emision, DATE(v.fecha_venta)) >= %s'; $params[] = $ff_from; }
        if ($ff_to !== '')   { $where[] = 'COALESCE(f.fecha_emision, DATE(v.fecha_venta)) <= %s'; $params[] = $ff_to; }
        if ($fp_from !== '') { $where[] = 'px.ultima_fecha_pago >= %s'; $params[] = $fp_from; }
        if ($fp_to !== '')   { $where[] = 'px.ultima_fecha_pago <= %s'; $params[] = $fp_to; }
        $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "
            SELECT
                v.id,
                v.total_bs,
                c.nombre_legal,
                f.nro_comprobante,
                f.fecha_emision AS fecha_facturacion,
                COALESCE(px.total_pagado,0) AS total_pagado,
                px.ultima_fecha_pago AS ultima_fecha_pago,
                (v.total_bs - COALESCE(px.total_pagado,0)) AS saldo
            FROM $t_ventas v
            JOIN $t_empresas c ON v.cliente_id = c.id
            LEFT JOIN $t_facturas f ON v.id = f.venta_id
            LEFT JOIN (
                SELECT p.venta_id, COALESCE(SUM(p.monto_pagado),0) AS total_pagado, MAX(p.fecha_pago) AS ultima_fecha_pago
                FROM $t_pagos p
                GROUP BY p.venta_id
            ) px ON px.venta_id = v.id
            $where_sql
            ORDER BY $order_sql $order_dir, v.id DESC
            LIMIT 400
        ";
        if (!empty($params)) $sql = $wpdb->prepare($sql, $params);
        $ventas = $wpdb->get_results($sql);

        // Prefetch pagos
        $pagos_por_venta = [];
        $ids = [];
        foreach ((array)$ventas as $vv) $ids[] = (int)$vv->id;
        $ids = array_values(array_unique(array_filter($ids)));
        if (!empty($ids)) {
            $chunks = array_chunk($ids, 200);
            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
                $sqlp = $wpdb->prepare("SELECT * FROM $t_pagos WHERE venta_id IN ($placeholders) ORDER BY venta_id ASC, fecha_pago ASC", $chunk);
                $rows = $wpdb->get_results($sqlp);
                foreach ((array)$rows as $p) {
                    $vid = (int)$p->venta_id;
                    if (!isset($pagos_por_venta[$vid])) $pagos_por_venta[$vid] = [];
                    $pagos_por_venta[$vid][] = $p;
                }
            }
        }

        $tol = 0.05;
        ob_start();
        foreach ((array)$ventas as $v) {
            $venta_id = (int)$v->id;
            $abonos = $pagos_por_venta[$venta_id] ?? [];
            $total_abonado = (float)($v->total_pagado ?? 0);
            $total_bs = (float)$v->total_bs;
            $saldo = round($total_bs - $total_abonado, 2);
            if ($saldo < 0) $saldo = 0;
            $doc_urls = [];
            foreach ((array)$abonos as $pp) { if (!empty($pp->url_adjunto)) $doc_urls[] = $pp->url_adjunto; }
            $doc_urls = array_values(array_unique($doc_urls));
            $doc_json = esc_attr(wp_json_encode($doc_urls));
            $doc_num = trim((string)($v->nro_comprobante ?? ''));
            if ($doc_num === '') $doc_num = '---';
            $ff = !empty($v->fecha_facturacion) ? date('d/m/Y', strtotime($v->fecha_facturacion)) : '---';
            $fp = !empty($v->ultima_fecha_pago) ? date('d/m/Y', strtotime($v->ultima_fecha_pago)) : '---';
            $search = strtolower($venta_id . ' ' . (string)$v->nombre_legal . ' ' . $doc_num);
            ?>
<tr class="rp-row" data-search="<?php echo esc_attr($search); ?>"
    data-total="<?php echo esc_attr(number_format($total_bs,2,'.','')); ?>"
    data-pagado="<?php echo esc_attr(number_format($total_abonado,2,'.','')); ?>"
    data-saldo="<?php echo esc_attr(number_format($saldo,2,'.','')); ?>"
    data-days="0">
  <td data-label="VENTA"><strong>#<?php echo esc_html($venta_id); ?></strong></td>
  <td data-label="CLIENTE"><strong><?php echo esc_html($v->nombre_legal); ?></strong></td>
  <td data-label="DOC"><span class="cmb-crm-badge cmb-crm-badge-info"><?php echo esc_html($doc_num); ?></span></td>
  <td data-label="TOTAL"><strong><?php echo esc_html(number_format($total_bs,2)); ?> Bs</strong></td>
  <td data-label="PAGADO" class="cmb-crm-text-success cmb-crm-font-bold"><?php echo esc_html(number_format($total_abonado,2)); ?> Bs</td>
  <td data-label="SALDO" class="cmb-crm-text-danger cmb-crm-font-bold"><?php echo esc_html(number_format($saldo,2)); ?> Bs</td>
  <td data-label="FECHA FACTURACIÓN"><?php echo esc_html($ff); ?></td>
  <td data-label="FECHA PAGO"><?php echo esc_html($fp); ?></td>
  <td data-label="HISTORIAL" style="min-width:260px;">
    <?php if (!empty($abonos)): foreach ($abonos as $p): ?>
      <div class="rp-box">
        <strong><?php echo esc_html(number_format((float)$p->monto_pagado,2)); ?> Bs</strong>
        <span class="cmb-crm-text-muted" style="font-size:12px;">— <?php echo esc_html($p->metodo_pago); ?></span>
        <div style="font-size:12px;margin-top:4px;">Ref: <code><?php echo esc_html($p->referencia); ?></code></div>
        <div style="display:flex; gap:6px; margin-top:8px;">
          <button class="cmb-crm-btn cmb-crm-btn-3 cmb-crm-btn-sm" type="button" onclick="abrirEditarPago(<?php echo esc_js($p->id); ?>, <?php echo esc_js((float)$p->monto_pagado); ?>, '<?php echo esc_js($p->metodo_pago); ?>', '<?php echo esc_js($p->referencia); ?>')">✏️</button>
          <button class="cmb-crm-btn cmb-crm-btn-danger cmb-crm-btn-sm" type="button" onclick="borrarRegistro(<?php echo esc_js($p->id); ?>)">ELIMINAR</button>
        </div>
      </div>
    <?php endforeach; else: ?><span class="cmb-crm-text-muted">--- Sin pagos registrados ---</span><?php endif; ?>
  </td>
  <td data-label="ADJUNTOS">
    <?php if (!empty($doc_urls)): ?>
      <button class="cmb-crm-btn cmb-crm-btn-3 cmb-crm-btn-sm" type="button" onclick="abrirComprobantes('<?php echo esc_js($v->nombre_legal); ?>', '<?php echo $doc_json; ?>')">📎 (<?php echo esc_html(count($doc_urls)); ?>)</button>
    <?php else: ?><span class="cmb-crm-text-muted">---</span><?php endif; ?>
  </td>
  <td data-label="ACCIÓN" class="cmb-crm-text-right">
    <?php if ($saldo <= $tol): ?><span class="cmb-crm-badge cmb-crm-badge-success">✓ PAGADO</span>
    <?php else: ?><button class="cmb-crm-btn cmb-crm-btn-1" type="button" onclick="abrirP(<?php echo esc_js($venta_id); ?>, <?php echo esc_js($saldo); ?>, '<?php echo esc_js($v->nombre_legal); ?>')">REGISTRAR PAGO</button><?php endif; ?>
  </td>
</tr>
            <?php
        }
        $tbody = ob_get_clean();
        wp_send_json_success(['tbody' => $tbody]);
    }
}

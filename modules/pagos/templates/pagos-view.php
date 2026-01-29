<?php
/** @var array $ventas */
/** @var array $auditoria */
/** @var array $pagos_por_venta */
/** @var int $default_dias */
/** @var float $tol */
/** @var int $today_ts */
?>
<div class="cmb-crm-root cmb-crm-container-full cmb-crm-rp-root">
  <div class="cmb-crm-card">
    <div class="cmb-crm-header" style="align-items:flex-end;">
      <div>
        <h2 class="cmb-crm-title">💰 Registro de Pagos</h2>
        <p class="cmb-crm-subtitle">Alerta de cobro + trazabilidad por venta</p>
      </div>
      <div class="cmb-crm-header-right" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; justify-content:flex-end;">
        <div style="min-width:280px;">
          <label class="cmb-crm-label" style="margin-bottom:6px;">🔎 Buscar (Cliente / N° Factura / ID Venta)</label>
          <div class="rp-search-row">
            <input type="text" id="rp_search" class="cmb-crm-input" placeholder="Ej: COMMUNITY / 123-ABC / 45">
            <div class="rp-mobile-actions" aria-label="Acciones de búsqueda móvil">
              <button type="button" id="rp_m_search" class="cmb-crm-btn cmb-crm-btn-3 rp-m-btn" aria-label="Buscar">🔍</button>
              <button type="button" id="rp_m_clear" class="cmb-crm-btn cmb-crm-btn-3 rp-m-btn" aria-label="Limpiar">✖</button>
            </div>
          </div>
        </div>
        <div style="width:180px;">
          <label class="cmb-crm-label" style="margin-bottom:6px;">⏱️ Días de Atraso</label>
          <input type="number" id="rp_days" class="cmb-crm-input" min="0" step="1" value="<?php echo esc_attr($default_dias); ?>">
          <small class="cmb-crm-text-muted" style="display:block;margin-top:6px;">0 = mostrar todo</small>
        </div>
        <div style="min-width:180px;">
          <label class="cmb-crm-label" style="margin-bottom:6px;">Filtros</label>
          <button type="button" id="rp_btn_filters" class="cmb-crm-btn cmb-crm-btn-3" style="width:100%;">⚙️ Filtros avanzados</button>
        </div>
      </div>
    </div>

    <div class="cmb-crm-table-wrap cmb-crm-container-full" style="margin-top:10px;">
      <table class="cmb-crm-table" id="rp_table">
        <thead>
          <tr>
            <th>VENTA</th>
            <th>CLIENTE</th>
            <th>DOC</th>
            <th>TOTAL</th>
            <th>PAGADO</th>
            <th>SALDO</th>
            <th>FECHA FACTURACIÓN</th>
            <th>FECHA PAGO</th>
            <th>HISTORIAL</th>
            <th>📎</th>
            <th class="cmb-crm-text-right">ACCIÓN</th>
          </tr>
        </thead>
        <tbody id="rp_tbody">
        <?php foreach ((array)$ventas as $v):
          $venta_id = (int)$v->id;
          $abonos = $pagos_por_venta[$venta_id] ?? [];
          $total_abonado = (float)($v->total_pagado ?? 0);
          $total_bs = (float)$v->total_bs;
          $saldo = round($total_bs - $total_abonado, 2);
          if ($saldo < 0) $saldo = 0;
          $doc_urls = [];
          foreach ((array)$abonos as $p) { if (!empty($p->url_adjunto)) $doc_urls[] = $p->url_adjunto; }
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
        <?php endforeach; ?>
        </tbody>
      </table>
      <div id="rp_no_results" class="cmb-crm-text-center cmb-crm-text-muted" style="display:none;padding:12px;">No se encontraron coincidencias.</div>
    </div>
  </div>

  <!-- Auditoría (sin cambios) -->
  <div class="cmb-crm-card" style="border-top-color:var(--cmb-crm-danger);">
    <h3 class="cmb-crm-title" style="color:var(--cmb-crm-danger);">🧾 Auditoría de Pagos (últimos 25)</h3>
    <p class="cmb-crm-subtitle">Registros recientes, edición y eliminación</p>
    <div class="cmb-crm-table-wrap">
      <table class="cmb-crm-table">
        <thead><tr><th>FECHA PAGO</th><th>VENTA</th><th>CLIENTE</th><th>MONTO</th><th>MÉTODO</th><th>REFERENCIA</th><th>COMPROBANTE</th><th class="cmb-crm-text-right">ACCIÓN</th></tr></thead>
        <tbody>
          <?php foreach ((array)$auditoria as $ap): ?>
            <tr>
              <td><?php echo esc_html(date('d/m/Y', strtotime($ap->fecha_pago))); ?></td>
              <td><strong>#<?php echo esc_html($ap->venta_id); ?></strong></td>
              <td><?php echo esc_html($ap->nombre_legal); ?></td>
              <td><strong class="cmb-crm-text-success"><?php echo esc_html(number_format((float)$ap->monto_pagado,2)); ?> Bs</strong></td>
              <td><span class="cmb-crm-badge cmb-crm-badge-info"><?php echo esc_html($ap->metodo_pago); ?></span></td>
              <td><code><?php echo esc_html($ap->referencia); ?></code></td>
              <td><?php if (!empty($ap->url_adjunto)): ?><a href="<?php echo esc_url($ap->url_adjunto); ?>" target="_blank" rel="noopener">Adjunto</a><?php else: ?><span class="cmb-crm-text-muted">---</span><?php endif; ?></td>
              <td class="cmb-crm-text-right">
                <button class="cmb-crm-btn cmb-crm-btn-3 cmb-crm-btn-sm" type="button" onclick="abrirEditarPago(<?php echo esc_js($ap->id); ?>, <?php echo esc_js((float)$ap->monto_pagado); ?>, '<?php echo esc_js($ap->metodo_pago); ?>', '<?php echo esc_js($ap->referencia); ?>')">✏️</button>
                <button class="cmb-crm-btn cmb-crm-btn-danger cmb-crm-btn-sm" type="button" onclick="borrarRegistro(<?php echo esc_js($ap->id); ?>)">ELIMINAR</button>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($auditoria)): ?><tr><td colspan="8" class="cmb-crm-text-center cmb-crm-text-muted" style="padding:18px;">No hay pagos registrados.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Modal Filtros -->
  <div id="modalFilters" class="cmb-crm-modal" style="display:none;">
    <div class="cmb-crm-modal-content" style="max-width:720px;">
      <h3 class="cmb-crm-title">⚙️ Filtros avanzados</h3>
      <p class="cmb-crm-subtitle">Filtra por rangos de fechas y ordena resultados. (No afecta al buscador superior.)</p>
      <div class="cmb-crm-grid" style="grid-template-columns:repeat(2, minmax(0,1fr)); gap:12px;">
        <div>
          <label class="cmb-crm-label">Facturación desde</label>
          <input type="date" id="rp_ff_from" class="cmb-crm-input" />
        </div>
        <div>
          <label class="cmb-crm-label">Facturación hasta</label>
          <input type="date" id="rp_ff_to" class="cmb-crm-input" />
        </div>
        <div>
          <label class="cmb-crm-label">Pago desde</label>
          <input type="date" id="rp_fp_from" class="cmb-crm-input" />
        </div>
        <div>
          <label class="cmb-crm-label">Pago hasta</label>
          <input type="date" id="rp_fp_to" class="cmb-crm-input" />
        </div>
        <div>
          <label class="cmb-crm-label">Ordenar por</label>
          <select id="rp_order_by" class="cmb-crm-select">
            <option value="venta_id">ID Venta</option>
            <option value="cliente">Cliente</option>
            <option value="saldo">Saldo</option>
            <option value="fecha_facturacion">Fecha facturación</option>
            <option value="fecha_pago">Fecha pago</option>
          </select>
        </div>
        <div>
          <label class="cmb-crm-label">Dirección</label>
          <select id="rp_order_dir" class="cmb-crm-select">
            <option value="desc">DESC</option>
            <option value="asc">ASC</option>
          </select>
        </div>
      </div>
      <div style="display:flex; gap:10px; margin-top:14px; justify-content:flex-end; flex-wrap:wrap;">
        <button type="button" id="rp_filters_reset" class="cmb-crm-btn cmb-crm-btn-3">Limpiar</button>
        <button type="button" id="rp_filters_close" class="cmb-crm-btn cmb-crm-btn-danger">Cerrar</button>
        <button type="button" id="rp_filters_apply" class="cmb-crm-btn cmb-crm-btn-1">Aplicar</button>
      </div>
    </div>
  </div>

  <!-- Modales requeridos por pagos.js (mantener IDs) -->
  <div id="modalP" class="cmb-crm-modal" style="display:none;"><div class="cmb-crm-modal-content">
    <h3 id="m_cli" class="cmb-crm-title"></h3><p class="cmb-crm-subtitle" id="m_sub"></p>
    <input type="hidden" id="m_id">
    <label class="cmb-crm-label">Monto a Pagar (Bs)</label><input type="number" id="m_mnt" step="0.01" class="cmb-crm-input" />
    <label class="cmb-crm-label">Referencia / Número de Operación</label><input type="text" id="m_ref" class="cmb-crm-input" />
    <label class="cmb-crm-label">Método</label>
    <select id="m_met" class="cmb-crm-select"><option value="QR BANCARIO">QR BANCARIO</option><option value="TRANSFERENCIA">TRANSFERENCIA</option><option value="DEPOSITO">DEPÓSITO</option><option value="EFECTIVO">EFECTIVO</option></select>
    <label class="cmb-crm-label">Fecha</label><input type="date" id="m_fec" class="cmb-crm-input" value="<?php echo esc_attr(date('Y-m-d')); ?>" />
    <label class="cmb-crm-label">Comprobante (opcional)</label>
    <button id="m_file" class="cmb-crm-btn cmb-crm-btn-3 cmb-crm-btn-block" style="border-style:dashed;">📂 Seleccionar de biblioteca</button>
    <input type="hidden" id="m_fid"><div id="m_fname" class="cmb-crm-text-muted" style="margin-top:8px;"></div>
    <button id="btn_save" class="cmb-crm-btn cmb-crm-btn-1 cmb-crm-btn-block" type="button">CONFIRMAR</button>
    <button class="cmb-crm-btn cmb-crm-btn-danger cmb-crm-btn-block" type="button" onclick="cerrarModal('modalP')">CANCELAR</button>
  </div></div>

  <div id="modalEditPago" class="cmb-crm-modal" style="display:none;"><div class="cmb-crm-modal-content">
    <h3 class="cmb-crm-title">✏️ Editar Pago</h3>
    <input type="hidden" id="ep_id">
    <label class="cmb-crm-label">Monto (Bs)</label><input type="number" id="ep_mnt" step="0.01" class="cmb-crm-input" />
    <label class="cmb-crm-label">Referencia</label><input type="text" id="ep_ref" class="cmb-crm-input" />
    <label class="cmb-crm-label">Método</label>
    <select id="ep_met" class="cmb-crm-select"><option value="QR BANCARIO">QR BANCARIO</option><option value="TRANSFERENCIA">TRANSFERENCIA</option><option value="DEPOSITO">DEPÓSITO</option><option value="EFECTIVO">EFECTIVO</option></select>
    <label class="cmb-crm-label">Comprobante (opcional)</label>
    <button id="ep_file" class="cmb-crm-btn cmb-crm-btn-3 cmb-crm-btn-block" style="border-style:dashed;">📂 Seleccionar de biblioteca</button>
    <input type="hidden" id="ep_fid"><div id="ep_fname" class="cmb-crm-text-muted" style="margin-top:8px;"></div>
    <button id="ep_save" class="cmb-crm-btn cmb-crm-btn-1 cmb-crm-btn-block" type="button">GUARDAR CAMBIOS</button>
    <button class="cmb-crm-btn cmb-crm-btn-danger cmb-crm-btn-block" type="button" onclick="cerrarModal('modalEditPago')">CANCELAR</button>
  </div></div>

  <div id="modalDocs" class="cmb-crm-modal" style="display:none;"><div class="cmb-crm-modal-content" style="max-width:640px;">
    <h3 class="cmb-crm-title" id="docs_title">📎 Comprobantes</h3>
    <p class="cmb-crm-subtitle">Haz clic para abrir en nueva pestaña.</p>
    <div id="docs_list" style="display:flex;flex-direction:column;gap:10px;"></div>
    <button class="cmb-crm-btn cmb-crm-btn-3 cmb-crm-btn-block" type="button" onclick="cerrarModal('modalDocs')" style="margin-top:10px;">CERRAR</button>
  </div></div>
</div>

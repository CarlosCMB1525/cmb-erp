<?php
if (!defined('ABSPATH')) exit;
$ventas = (array)($data['ventas'] ?? []);
$docs = (array)($data['docs'] ?? []);
/** @var \CMBERP\Modules\Facturacion\Repository $repo */
$repo = $data['repo'];
?>
<div class="crm-root" id="cmb_invoicing_root">
  <div class="crm-card">
    <h2 class="crm-title">📑 Módulo de Facturación</h2>
    <p class="crm-subtitle">Asignación de facturas y recibos a ventas</p>

    <input type="text" id="v17_search" class="crm-input" placeholder="🔍 Buscar...">

    <div class="crm-table-wrapper">
      <table class="crm-table" id="v17_table">
        <thead>
          <tr>
            <th>ID</th><th>PERIODO</th><th>CLIENTE</th><th>DOCUMENTO</th><th>TOTAL</th><th class="text-right">ACCIÓN</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ventas as $v): ?>
            <tr>
              <td><strong>#<?php echo esc_html($v->id); ?></strong></td>
              <td style="font-weight:700;color:#4338ca;">
                <?php echo esc_html($repo->formatear_periodo($v->fecha_venta)); ?>
              </td>
              <td><?php echo esc_html($v->nombre_legal); ?></td>
              <td>
                <?php if (!empty($v->nro_comprobante)): ?>
                  <span class="crm-badge <?php echo ($v->tipo_documento==='Factura')?'bg-success':'bg-warning'; ?>">
                    <?php echo esc_html($v->tipo_documento); ?>: <?php echo esc_html($v->nro_comprobante); ?>
                  </span>
                <?php else: ?>
                  <span class="text-muted">--- SIN DOCUMENTO ---</span>
                <?php endif; ?>
              </td>
              <td><strong class="text-success"><?php echo esc_html(number_format((float)$v->total_bs, 2)); ?> Bs</strong></td>
              <td class="text-right">
                <?php if (empty($v->nro_comprobante)): ?>
                  <button class="crm-btn crm-btn-primary crm-btn-sm" type="button"
                    data-action="open" data-id="<?php echo esc_attr($v->id); ?>"
                    data-mon="<?php echo esc_attr((float)$v->total_bs); ?>"
                    data-cli="<?php echo esc_attr($v->nombre_legal); ?>">
                    ASIGNAR DOCUMENTO
                  </button>
                <?php else: ?>
                  <span class="crm-badge bg-success">✅ ASIGNADO</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="crm-card" style="border-top-color:var(--crm-danger);">
    <h3 class="crm-title" style="color:var(--crm-danger);">🗑️ Auditoría de Documentos</h3>
    <div class="crm-table-wrapper">
      <table class="crm-table" id="v17_docs">
        <thead>
          <tr>
            <th>VENTA</th><th>PERIODO</th><th>TIPO</th><th>NÚMERO</th><th>FECHA</th><th>MONTO</th><th class="text-right">ACCIÓN</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($docs as $f): ?>
            <tr>
              <td><strong>#<?php echo esc_html($f->venta_id); ?></strong></td>
              <td style="font-size:11px;font-weight:700;">
                <?php echo esc_html($repo->formatear_periodo($f->fecha_venta)); ?>
              </td>
              <td>
                <span class="crm-badge <?php echo ($f->tipo_documento==='Factura')?'bg-success':'bg-info'; ?>"><?php echo esc_html($f->tipo_documento); ?></span>
              </td>
              <td><strong><?php echo esc_html($f->nro_comprobante); ?></strong></td>
              <td><?php echo esc_html($f->fecha_emision ? date('d/m/Y', strtotime($f->fecha_emision)) : '---'); ?></td>
              <td><strong class="text-success"><?php echo esc_html(number_format((float)$f->monto_total, 2)); ?> Bs</strong></td>
              <td class="text-right">
                <button type="button" class="crm-btn crm-btn-danger crm-btn-sm" data-action="delete" data-id="<?php echo esc_attr($f->id); ?>">ELIMINAR</button>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (empty($docs)): ?>
            <tr><td colspan="7" class="text-center text-muted" style="padding:18px;">No hay documentos.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- MODAL -->
  <div id="v17_mod" class="crm-modal" style="display:none;" aria-hidden="true">
    <div class="crm-modal-content">
      <h3 id="v17_m_cli" class="crm-title"></h3>
      <p class="crm-subtitle" id="v17_m_sub"></p>
      <input type="hidden" id="v17_v_id">
      <input type="hidden" id="v17_v_mon">

      <label class="crm-label">Tipo de Documento</label>
      <select id="v17_tipo" class="crm-select">
        <option value="Factura">FACTURA</option>
        <option value="Recibo">RECIBO</option>
      </select>

      <label class="crm-label">Número</label>
      <input type="text" id="v17_nro" class="crm-input" placeholder="Ej: 001-000123">

      <label class="crm-label">Fecha de Emisión</label>
      <input type="date" id="v17_fec" class="crm-input" value="<?php echo esc_attr(date('Y-m-d')); ?>">

      <button type="button" class="crm-btn crm-btn-primary crm-btn-block" id="v17_btn_save">CONFIRMAR</button>
      <button type="button" class="crm-btn crm-btn-ghost crm-btn-block" id="v17_btn_cancel">CANCELAR</button>
    </div>
  </div>
</div>

<?php
if (!defined('ABSPATH')) { exit; }
$cats = $view['cats'] ?? [];
$inicio_default = $view['inicio_default'] ?? '';
$fin_default = $view['fin_default'] ?? '';
?>

<div id="cmb_dashboard_modal" class="cmb-erp-modal" style="display:none;" aria-hidden="true">
  <div class="cmb-erp-modal__content">
    <div class="cmb-erp-modal__header">
      <div>
        <h3 class="cmb-erp-title" style="margin:0;">Filtros Avanzados</h3>
        <p class="cmb-erp-subtitle" style="margin:6px 0 0;">Fechas, categor&iacute;a, documento, estado pago y b&uacute;squeda t&eacute;cnica</p>
      </div>
      <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost" id="cmb_dash_modal_close" aria-label="Cerrar">✕</button>
    </div>

    <div class="cmb-erp-modal__body">
      <div class="cmb-dashboard-grid">
        <div class="col-12">
          <label class="cmb-erp-label">B&uacute;squeda T&eacute;cnica</label>
          <input id="cmb_dash_adv" class="cmb-erp-input" placeholder="Incluye NIT y Concepto (detalles)" />
          <div class="cmb-erp-text-muted" style="margin-top:6px;">Incluye NIT y Concepto (detalles).</div>
        </div>

        <div class="col-6">
          <label class="cmb-erp-label">Desde (Fecha Venta)</label>
          <input id="cmb_dash_inicio" class="cmb-erp-input" type="date" value="<?php echo esc_attr($inicio_default); ?>" />
        </div>

        <div class="col-6">
          <label class="cmb-erp-label">Hasta (Fecha Venta)</label>
          <input id="cmb_dash_fin" class="cmb-erp-input" type="date" value="<?php echo esc_attr($fin_default); ?>" />
        </div>

        <div class="col-6">
          <label class="cmb-erp-label">Categor&iacute;a</label>
          <select id="cmb_dash_categoria" class="cmb-erp-select">
            <option value="TODAS">TODAS</option>
            <?php foreach ($cats as $c): ?>
              <option value="<?php echo esc_attr($c); ?>"><?php echo esc_html($c); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-6">
          <label class="cmb-erp-label">Tipo de Documento</label>
          <select id="cmb_dash_doc_tipo" class="cmb-erp-select">
            <option value="TODOS">TODOS</option>
            <option value="FACTURA">FACTURAS</option>
            <option value="RECIBO">RECIBOS</option>
            <option value="OTROS">OTROS / SIN DOC</option>
          </select>
        </div>

        <div class="col-12">
          <label class="cmb-erp-label">Estado de Pago</label>
          <select id="cmb_dash_pago_estado" class="cmb-erp-select">
            <option value="TODOS">TODOS</option>
            <option value="PENDIENTE">PENDIENTE</option>
            <option value="PARCIAL">PAGO PARCIAL</option>
            <option value="PAGADO">PAGADO</option>
          </select>
          <div class="cmb-erp-text-muted" style="margin-top:6px;">Comparaci&oacute;n total vs pagos, tolerancia 0.05 Bs.</div>
        </div>
      </div>
    </div>

    <div class="cmb-erp-modal__footer">
      <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost" id="cmb_dash_modal_clear">Limpiar</button>
      <button type="button" class="cmb-erp-btn cmb-erp-btn--primary" id="cmb_dash_modal_apply">Aplicar</button>
    </div>
  </div>
</div>

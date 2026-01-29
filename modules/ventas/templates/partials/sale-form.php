<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="cmb-erp-grid" style="margin-top:10px;">
  <div>
    <label class="cmb-erp-label">Cliente</label>
    <div style="display:flex;gap:10px;align-items:center;">
      <input type="hidden" id="s_cliente_id" value="0" />
      <input id="s_cliente_nombre" class="cmb-erp-input" placeholder="Selecciona un cliente" readonly />
      <button type="button" id="s_btn_cliente" class="cmb-erp-btn cmb-erp-btn--primary cmb-erp-btn--sm">🔎 Buscar</button>
    </div>
  </div>

  <div>
    <label class="cmb-erp-label">Vincular Cotización Emitida (opcional)</label>
    <div style="display:flex;gap:10px;align-items:center;">
      <input id="s_quote_label" class="cmb-erp-input" placeholder="Selecciona una cotización emitida" readonly />
      <button type="button" id="s_btn_quote" class="cmb-erp-btn cmb-erp-btn--dark cmb-erp-btn--sm">📌 Cotizaciones</button>
      <button type="button" id="s_btn_quote_clear" class="cmb-erp-btn cmb-erp-btn--ghost cmb-erp-btn--sm">Quitar</button>
    </div>
    <small class="cmb-erp-text-muted">Al vincular una cotización, puedes importar sus ítems a la venta.</small>
  </div>

  <div>
    <label class="cmb-erp-label">Contrato</label>
    <select id="s_tipo" class="cmb-erp-select">
      <option value="UNICO">UNICO</option>
      <option value="MENSUAL">MENSUAL (Recurrente)</option>
    </select>
  </div>

  <div>
    <label class="cmb-erp-label">Meses</label>
    <input id="s_meses" type="number" class="cmb-erp-input" min="1" value="1" />
  </div>

  <div class="cmb-erp-span-2">
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <button type="button" id="s_btn_service" class="cmb-erp-btn cmb-erp-btn--primary">🔎 Agregar servicio</button>
      <button type="button" id="s_btn_manual" class="cmb-erp-btn cmb-erp-btn--dark">➕ Ítem manual (descuento/ajuste)</button>
      <button type="button" id="s_btn_import_quote" class="cmb-erp-btn cmb-erp-btn--ghost">⬇️ Importar ítems desde cotización</button>
    </div>
  </div>
</div>

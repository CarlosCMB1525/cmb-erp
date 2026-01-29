<?php if (!defined('ABSPATH')) { exit; } ?>
<div style="margin-top:14px;">
  <h3 class="cmb-erp-title">📦 Ítems</h3>
  <p class="cmb-erp-subtitle">Puedes editar descripción, precio y cantidad. Precio negativo solo para ítems manuales.</p>

  <div class="cmb-erp-table-wrap" style="margin-top:10px;">
    <table class="cmb-erp-table" aria-label="Detalle de venta">
      <thead>
        <tr>
          <th>DESCRIPCIÓN</th>
          <th class="cmb-erp-text-right">PRECIO</th>
          <th class="cmb-erp-text-right">CANT.</th>
          <th class="cmb-erp-text-right">SUBTOTAL</th>
          <th class="cmb-erp-text-right">—</th>
        </tr>
      </thead>
      <tbody id="s_items_tbody">
        <tr><td colspan="5" class="cmb-erp-text-muted" style="padding:14px;">Agrega ítems para comenzar…</td></tr>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="3" class="cmb-erp-text-right">TOTAL:</th>
          <th class="cmb-erp-text-right"><span id="s_total">0.00</span> Bs</th>
          <th></th>
        </tr>
      </tfoot>
    </table>
  </div>

  <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:12px;flex-wrap:wrap;">
    <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost" data-s="delete" style="display:none;" id="s_btn_delete">🗑️ Eliminar</button>
    <button type="button" class="cmb-erp-btn cmb-erp-btn--dark" data-s="save" id="s_btn_save">🧾 Registrar venta</button>
  </div>

</div>

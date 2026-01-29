<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="cmb-erp-modal" id="cmb_sales_quote_modal" role="dialog" aria-modal="true">
  <div class="cmb-erp-modal__content">
    <div class="cmb-erp-modal__header">
      <div>
        <h3 class="cmb-erp-title" style="margin:0;">📌 Cotizaciones emitidas</h3>
        <p class="cmb-erp-subtitle">Busca por código (CDG), cliente o ID</p>
      </div>
      <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost" data-s-close="cmb_sales_quote_modal">✖</button>
    </div>
    <div class="cmb-erp-modal__body">
      <input id="s_q_search" type="search" class="cmb-erp-input" placeholder="Ej: 0001CDG26" />
      <div class="cmb-erp-table-wrap" style="margin-top:10px;">
        <table class="cmb-erp-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>CÓDIGO</th>
              <th>FECHA</th>
              <th>CLIENTE</th>
              <th class="cmb-erp-text-right">TOTAL</th>
              <th class="cmb-erp-text-right">ACCION</th>
            </tr>
          </thead>
          <tbody id="s_q_tbody">
            <tr><td colspan="6" class="cmb-erp-text-muted" style="padding:14px;">Escribe para buscar…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="cmb-erp-modal__footer">
      <button type="button" class="cmb-erp-btn cmb-erp-btn--dark" data-s-close="cmb_sales_quote_modal">Cerrar</button>
    </div>
  </div>
</div>

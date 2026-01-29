<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="cmb-erp-modal" id="cmb_sales_service_modal" role="dialog" aria-modal="true">
  <div class="cmb-erp-modal__content">
    <div class="cmb-erp-modal__header">
      <div>
        <h3 class="cmb-erp-title" style="margin:0;">🔎 Servicios</h3>
        <p class="cmb-erp-subtitle">Busca por Código / Nombre / Detalle técnico</p>
      </div>
      <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost" data-s-close="cmb_sales_service_modal">✖</button>
    </div>
    <div class="cmb-erp-modal__body">
      <input id="s_srv_search" type="search" class="cmb-erp-input" placeholder="Escribe para buscar…" />
      <div class="cmb-erp-table-wrap" style="margin-top:10px;">
        <table class="cmb-erp-table">
          <thead>
            <tr>
              <th>CÓDIGO</th>
              <th>SERVICIO</th>
              <th class="cmb-erp-text-right">PRECIO</th>
              <th class="cmb-erp-text-right">ACCION</th>
            </tr>
          </thead>
          <tbody id="s_srv_tbody">
            <tr><td colspan="4" class="cmb-erp-text-muted" style="padding:14px;">Escribe para buscar…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="cmb-erp-modal__footer">
      <button type="button" class="cmb-erp-btn cmb-erp-btn--dark" data-s-close="cmb_sales_service_modal">Cerrar</button>
    </div>
  </div>
</div>

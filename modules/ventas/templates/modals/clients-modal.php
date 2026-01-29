<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="cmb-erp-modal" id="cmb_sales_client_modal" role="dialog" aria-modal="true">
  <div class="cmb-erp-modal__content">
    <div class="cmb-erp-modal__header">
      <div>
        <h3 class="cmb-erp-title" style="margin:0;">🔎 Clientes</h3>
        <p class="cmb-erp-subtitle">Busca por Empresa / NIT / Razón social</p>
      </div>
      <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost" data-s-close="cmb_sales_client_modal">✖</button>
    </div>
    <div class="cmb-erp-modal__body">
      <input id="s_cli_search" type="search" class="cmb-erp-input" placeholder="Escribe para buscar…" />
      <div class="cmb-erp-table-wrap" style="margin-top:10px;">
        <table class="cmb-erp-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>EMPRESA</th>
              <th>NIT</th>
              <th>TIPO</th>
              <th class="cmb-erp-text-right">ACCION</th>
            </tr>
          </thead>
          <tbody id="s_cli_tbody">
            <tr><td colspan="5" class="cmb-erp-text-muted" style="padding:14px;">Escribe para buscar…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="cmb-erp-modal__footer">
      <button type="button" class="cmb-erp-btn cmb-erp-btn--dark" data-s-close="cmb_sales_client_modal">Cerrar</button>
    </div>
  </div>
</div>

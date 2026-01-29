<?php if (!defined('ABSPATH')) { exit; } ?>
<div style="margin-top:18px;">
  <div class="cmb-erp-header" style="margin-bottom:8px;">
    <div>
      <h3 class="cmb-erp-title">🗂️ Historial</h3>
      <p class="cmb-erp-subtitle">Últimas ventas registradas. Edita, clona o configura recurrencia.</p>
    </div>
    <div class="cmb-erp-actions">
      <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost" id="s_hist_reload">Recargar</button>
    </div>
  </div>
  <div class="cmb-erp-table-wrap">
    <table class="cmb-erp-table" aria-label="Historial de ventas">
      <thead>
        <tr>
          <th>ID</th>
          <th>FECHA</th>
          <th>PERIODO</th>
          <th>CLIENTE</th>
          <th class="cmb-erp-text-right">TOTAL</th>
          <th>COTIZACIÓN</th>
          <th>RECURRENCIA</th>
          <th class="cmb-erp-text-right">ACCIONES</th>
        </tr>
      </thead>
      <tbody id="s_hist_tbody">
        <tr><td colspan="8" class="cmb-erp-text-muted" style="padding:14px;">Cargando…</td></tr>
      </tbody>
    </table>
  </div>
</div>

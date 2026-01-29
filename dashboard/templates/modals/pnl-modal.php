
<?php
if (!defined('ABSPATH')) { exit; }
?>
<div class="cmb-erp-modal" id="cmb_pnl_modal">
  <div class="cmb-erp-modal__content">
    <div class="cmb-erp-modal__header">
      <div>
        <div class="cmb-erp-title">📄 Estado de Resultados (P&amp;L)</div>
        <div class="cmb-erp-subtitle">Genera PDF mensual o anual con conciliación IVA/IT.</div>
      </div>
      <button class="cmb-erp-btn cmb-erp-btn--ghost" id="cmb_pnl_close" type="button">✖</button>
    </div>
    <div class="cmb-erp-modal__body">
      <div class="cmb-erp-grid">
        <div>
          <label class="cmb-erp-label">Tipo de reporte</label>
          <select class="cmb-erp-select" id="cmb_pnl_mode">
            <option value="MONTH">Mensual</option>
            <option value="YEAR">Anual</option>
          </select>
        </div>

        <div id="cmb_pnl_month_wrap">
          <label class="cmb-erp-label">Mes</label>
          <input class="cmb-erp-input" id="cmb_pnl_month" type="month" />
        </div>

        <div id="cmb_pnl_year_wrap">
          <label class="cmb-erp-label">Año</label>
          <input class="cmb-erp-input" id="cmb_pnl_year" type="number" min="2000" max="2100" placeholder="2026" />
        </div>

        <div class="cmb-erp-span-2">
          <div class="cmb-erp-text-muted" style="font-weight:800;">
            Nota: El impuesto de ventas se calcula como <strong>tasa (por periodo)</strong> × <strong>ventas facturadas</strong>. IVA/IT pagado en Cashflow se muestra solo como conciliación.
          </div>
        </div>
      </div>
    </div>
    <div class="cmb-erp-modal__footer">
      <button class="cmb-erp-btn cmb-erp-btn--ghost" type="button" onclick="document.getElementById('cmb_pnl_modal').classList.remove('is-open')">Cancelar</button>
      <button class="cmb-erp-btn cmb-erp-btn--primary" id="cmb_pnl_generate" type="button">Generar PDF</button>
    </div>
  </div>
</div>

<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="cmb-erp-modal" id="cmb_sales_clone_modal" role="dialog" aria-modal="true">
  <div class="cmb-erp-modal__content" style="width:min(520px,94vw);">
    <div class="cmb-erp-modal__header">
      <div>
        <h3 class="cmb-erp-title" style="margin:0;">➕ Clonar para otra fecha</h3>
        <p class="cmb-erp-subtitle">Crea una copia idéntica en la fecha seleccionada.</p>
      </div>
      <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost" data-s-close="cmb_sales_clone_modal">✖</button>
    </div>
    <div class="cmb-erp-modal__body">
      <input type="hidden" id="s_clone_id" value="0" />
      <label class="cmb-erp-label">Fecha (YYYY-MM-DD)</label>
      <input type="date" id="s_clone_date" class="cmb-erp-input" />
      <div id="s_clone_error" class="cmb-erp-text-muted" style="margin-top:8px;color:#ef4444;font-weight:800;"></div>
      <button type="button" class="cmb-erp-btn cmb-erp-btn--primary" style="width:100%;margin-top:12px;" id="s_clone_exec">CREAR REGISTRO</button>
    </div>
    <div class="cmb-erp-modal__footer">
      <button type="button" class="cmb-erp-btn cmb-erp-btn--dark" data-s-close="cmb_sales_clone_modal">Cerrar</button>
    </div>
  </div>
</div>

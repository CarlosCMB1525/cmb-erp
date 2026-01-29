<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="cmb-erp-modal" id="cmb_sales_recurrence_modal" role="dialog" aria-modal="true">
  <div class="cmb-erp-modal__content" style="width:min(520px,94vw);">
    <div class="cmb-erp-modal__header">
      <div>
        <h3 class="cmb-erp-title" style="margin:0;">📅 Día de cobro automático</h3>
        <p class="cmb-erp-subtitle">El sistema copiará esta venta cada mes ese día (1–28).</p>
      </div>
      <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost" data-s-close="cmb_sales_recurrence_modal">✖</button>
    </div>
    <div class="cmb-erp-modal__body">
      <input type="hidden" id="s_rec_id" value="0" />
      <label class="cmb-erp-label">Día (1–28)</label>
      <input type="number" id="s_rec_day" class="cmb-erp-input" min="1" max="28" value="1" />
      <div id="s_rec_error" class="cmb-erp-text-muted" style="margin-top:8px;color:#ef4444;font-weight:800;"></div>
      <button type="button" class="cmb-erp-btn cmb-erp-btn--primary" style="width:100%;margin-top:12px;" id="s_rec_exec">ACTUALIZAR</button>
    </div>
    <div class="cmb-erp-modal__footer">
      <button type="button" class="cmb-erp-btn cmb-erp-btn--dark" data-s-close="cmb_sales_recurrence_modal">Cerrar</button>
    </div>
  </div>
</div>

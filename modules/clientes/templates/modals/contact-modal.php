<?php if (!defined('ABSPATH')) { exit; } ?>
<div id="cl_contact_modal" class="cmb-erp-modal" style="display:none;">
  <div class="cmb-erp-modal__content" style="max-width:560px;">
    <div class="cmb-erp-header" style="margin-bottom:6px;">
      <div>
        <h3 id="cl_modal_title" class="cmb-erp-title">Contacto</h3>
        <p id="cl_modal_sub" class="cmb-erp-subtitle"></p>
      </div>
      <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost" id="cl_modal_close">✕</button>
    </div>

    <input type="hidden" id="cl_contact_id" value="0" />
    <input type="hidden" id="cl_empresa_id" value="0" />

    <label class="cmb-erp-label">Nombre</label>
    <input id="cl_c_nombre" class="cmb-erp-input" />

    <label class="cmb-erp-label">WhatsApp</label>
    <input id="cl_c_tel" class="cmb-erp-input" />

    <label class="cmb-erp-label">Email (opcional)</label>
    <input id="cl_c_email" class="cmb-erp-input" />

    <label class="cmb-erp-label">Cargo (opcional)</label>
    <input id="cl_c_cargo" class="cmb-erp-input" />

    <div style="display:flex;gap:10px;margin-top:12px;">
      <button type="button" class="cmb-erp-btn cmb-erp-btn--dark" id="cl_btn_save_contact">💾 Guardar</button>
      <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost" id="cl_btn_cancel_contact">Cancelar</button>
    </div>

    <div id="cl_modal_err" class="cmb-erp-text-muted" style="margin-top:10px;font-weight:900;"></div>
  </div>
</div>

<?php
if (!defined('ABSPATH')) exit;
$rows = (array)($data['rows'] ?? []);
$nonce = (string)($data['nonce'] ?? '');
?>
<div class="cmb-erp-root" id="svc_root">
  <script>
    window.CMBServices = window.CMBServices || {};
    window.CMBServices.ajaxurl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
    window.CMBServices.nonce = <?php echo wp_json_encode($nonce); ?>;
  </script>

  <div class="cmb-erp-card">
    <div class="cmb-erp-header">
      <div>
        <h2 class="cmb-erp-title">🧾 Catálogo de Servicios</h2>
        <p class="cmb-erp-subtitle">Crear / Editar / Eliminar servicios (sin recargar página)</p>
      </div>
      <div class="cmb-erp-actions" style="min-width:320px;">
        <input id="svc_search" class="cmb-erp-input" placeholder="🔎 Buscar (Nombre / Descripción / Código)" />
      </div>
    </div>

    <input type="hidden" id="svc_id" value="0" />

    <div class="cmb-erp-grid">
      <div>
        <label class="cmb-erp-label">Nombre</label>
        <input id="svc_nombre" class="cmb-erp-input" placeholder="EJ: SERVICIO X" />
      </div>
      <div>
        <label class="cmb-erp-label">Código Único</label>
        <input id="svc_codigo" class="cmb-erp-input" placeholder="EJ: ABC-001" />
      </div>
      <div>
        <label class="cmb-erp-label">Precio (Bs)</label>
        <input id="svc_precio" class="cmb-erp-input" type="number" step="0.01" placeholder="0.00" />
      </div>
      <div>
        <label class="cmb-erp-label">Tipo</label>
        <select id="svc_tipo" class="cmb-erp-select">
          <option value="UNICO">UNICO</option>
          <option value="MENSUAL">MENSUAL</option>
          <option value="ANUAL">ANUAL</option>
        </select>
      </div>
      <div class="cmb-erp-span-2">
        <label class="cmb-erp-label">Descripción / Detalle</label>
        <textarea id="svc_detalle" class="cmb-erp-input" rows="4" style="min-height:110px;"></textarea>
      </div>
    </div>

    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-top:10px;">
      <button type="button" class="cmb-erp-btn cmb-erp-btn--primary" id="svc_btn_save">💾 Guardar</button>
      <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost" id="svc_btn_cancel">Cancelar</button>
      <div id="svc_msg" class="cmb-erp-text-muted" style="margin-left:auto;font-weight:800;"></div>
    </div>
  </div>

  <div class="cmb-erp-card" style="margin-top:14px;">
    <div class="cmb-erp-header">
      <div>
        <h3 class="cmb-erp-title" style="font-size:16px;">📦 Lista de Servicios</h3>
        <p class="cmb-erp-subtitle" id="svc_count"></p>
      </div>
    </div>

    <div class="cmb-erp-table-wrap">
      <table class="cmb-erp-table" id="svc_table">
        <thead>
          <tr>
            <th>ID</th>
            <th>CÓDIGO</th>
            <th>SERVICIO</th>
            <th>TIPO</th>
            <th class="cmb-erp-text-right">PRECIO</th>
            <th class="cmb-erp-text-right">ACCIONES</th>
          </tr>
        </thead>
        <tbody id="svc_tbody">
          <?php require __DIR__ . '/servicios-tbody.php'; ?>
        </tbody>
      </table>
    </div>

    <div id="svc_no" class="cmb-erp-text-muted" style="display:none; padding:12px; text-align:center;">No se encontraron coincidencias.</div>
  </div>
</div>

<?php
if (!defined('ABSPATH')) { exit; }

use CMBERP\Modules\Clientes\Repositories\CompaniesRepository;
use CMBERP\Modules\Clientes\Repositories\ContactsRepository;

require_once __DIR__ . '/../src/Repositories/CompaniesRepository.php';
require_once __DIR__ . '/../src/Repositories/ContactsRepository.php';

$empresas = CompaniesRepository::list_recent(200);
?>
<div class="cmb-erp-root" id="cmb_clientes_root">

  <div class="cmb-erp-card">
    <div class="cmb-erp-header" style="display:flex;gap:14px;justify-content:space-between;flex-wrap:wrap;align-items:flex-end;">
      <div>
        <h2 class="cmb-erp-title">🏢 Clientes</h2>
        <p class="cmb-erp-subtitle">Empresas + contactos · NIT puede repetirse · buscador avanzado</p>
      </div>
      <div style="min-width:280px;">
        <label class="cmb-erp-label" style="margin-bottom:6px;">🔎 Buscador avanzado (Empresa / NIT / Contacto)</label>
        <input id="cl_search" class="cmb-erp-input" placeholder="Ej: COMMUNITY / 123456 / JUAN" />
        <small class="cmb-erp-text-muted" style="display:block;margin-top:6px;">Búsqueda en servidor (SQL LIKE) · vacía para recargar</small>
      </div>
    </div>

    <input type="hidden" id="cl_emp_id" value="0" />

    <div class="cmb-erp-grid" style="margin-top:10px;">
      <div>
        <label class="cmb-erp-label">Nombre Legal</label>
        <input id="cl_nombre" class="cmb-erp-input" placeholder="EJ: COMMUNITYBOLIVIA SRL" />
      </div>
      <div>
        <label class="cmb-erp-label">NIT / ID</label>
        <input id="cl_nit" class="cmb-erp-input" placeholder="EJ: 123456789" />
        <small class="cmb-erp-text-muted">✅ Se permite NIT duplicado</small>
      </div>
      <div>
        <label class="cmb-erp-label">Razón Social</label>
        <input id="cl_razon" class="cmb-erp-input" placeholder="OPCIONAL" />
      </div>
      <div>
        <label class="cmb-erp-label">Tipo</label>
        <select id="cl_tipo" class="cmb-erp-select">
          <option value="EMPRESA">EMPRESA</option>
          <option value="VIP">VIP</option>
          <option value="EMPRENDEDOR">EMPRENDEDOR</option>
          <option value="PERSONA">PERSONA</option>
        </select>
      </div>
    </div>

    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px; align-items:center;">
      <button type="button" class="cmb-erp-btn cmb-erp-btn--dark" id="btn_emp_save">💾 Guardar Empresa</button>
      <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost" id="btn_emp_cancel">Cancelar</button>
      <div id="cl_msg" class="cmb-erp-text-muted" style="margin-left:auto;font-weight:900;"></div>
    </div>
  </div>

  <div class="cmb-erp-card" style="margin-top:14px;">
    <div class="cmb-erp-header" style="display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;">
      <div>
        <h3 class="cmb-erp-title">📒 Cartera</h3>
        <p class="cmb-erp-subtitle">Últimos 200 · usa el buscador para filtrar</p>
      </div>
      <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost" id="btn_emp_reload">↻ Recargar</button>
    </div>

    <div class="cmb-erp-table-wrap">
      <table class="cmb-erp-table" aria-label="Cartera de clientes">
        <thead>
          <tr>
            <th>ID</th>
            <th>CLIENTE</th>
            <th>CONTACTOS</th>
            <th class="cmb-erp-text-right">ACCIONES</th>
          </tr>
        </thead>
        <tbody id="cl_tbody">
          <?php
          if (!empty($empresas)) {
            foreach ($empresas as $e) {
              $eid = (int)($e['id'] ?? 0);
              $contactos = $eid ? ContactsRepository::list_by_company($eid) : [];
              include __DIR__ . '/partials/row.php';
            }
          } else {
            echo '<tr><td colspan="4" class="cmb-erp-text-muted" style="padding:20px;text-align:center;">No hay empresas.</td></tr>';
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php include __DIR__ . '/modals/contact-modal.php'; ?>

</div>

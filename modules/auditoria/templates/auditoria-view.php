<?php
/** @var array $data */
$tables = $data['tables'] ?? [];
$prefix = $data['prefix'] ?? 'wp_';
?>
<div id="cmb_audit_root" class="crm-root">
  <div class="crm-card">
    <div class="crm-header-flex">
      <div>
        <h2 class="crm-title">🕵️ Auditoría Maestra SQL</h2>
        <p class="crm-subtitle">UI modular + Import/Export masivo + Reset ID + Test de lectura</p>
      </div>
      <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <select id="aud-sel" class="crm-input" style="width:auto;min-width:280px;">
          <option value="">-- Seleccionar Tabla --</option>
          <?php foreach ((array)$tables as $t): ?>
            <option value="<?php echo esc_attr($t); ?>"><?php echo esc_html(strtoupper(str_replace($prefix,'',$t))); ?></option>
          <?php endforeach; ?>
        </select>
        <button class="crm-btn crm-btn-primary" type="button" id="aud-load">📋 Cargar Tabla</button>
        <button class="crm-btn crm-btn-ghost" type="button" id="aud-test">✅ Probar lectura</button>
        <button class="crm-btn crm-btn-ghost" type="button" id="aud-export-page">⬇️ Exportar Página</button>
        <button class="crm-btn crm-btn-ghost" type="button" id="aud-export-all">⬇️ Exportar TODO</button>
        <button class="crm-btn crm-btn-danger" type="button" id="aud-truncate">🧨 Reset ID (Vaciar)</button>
      </div>
    </div>

    <div id="aud-test-status" class="text-muted" style="margin-top:10px; font-weight:700;"></div>

    <div class="crm-card" style="margin-top:14px;background:#f8fafc; border:2px dashed #9328AC;">
      <h3 class="crm-title" style="color:#9328AC;">📁 Importación masiva Excel/CSV</h3>
      <p class="crm-subtitle">La primera fila debe ser nombres de columnas. No incluyas "id".</p>
      <div class="crm-grid-form">
        <div>
          <label class="crm-label">Tabla destino</label>
          <select id="aud-import-tabla" class="crm-input">
            <option value="">-- Selecciona tabla --</option>
            <?php foreach ((array)$tables as $t): ?>
              <option value="<?php echo esc_attr($t); ?>"><?php echo esc_html(strtoupper(str_replace($prefix,'',$t))); ?></option>
            <?php endforeach; ?>
          </select>
          <div style="margin-top:10px;padding:12px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;">
            <ol style="margin:0;padding-left:18px;font-size:12px;color:#64748b;">
              <li>Selecciona la tabla destino</li>
              <li>Excel/CSV con headers = nombres de columnas</li>
              <li>No incluir columna <b>id</b></li>
            </ol>
          </div>
        </div>
        <div>
          <label class="crm-label">Archivo</label>
          <div id="aud-drop" style="border:2px dashed #cbd5e1;border-radius:12px;padding:22px;text-align:center;background:#fff;cursor:pointer;">
            <div style="font-size:42px;color:#9328AC;">📁</div>
            <p style="margin:6px 0;color:#64748b;font-weight:700;">Clic o arrastra archivo aquí</p>
            <p id="aud-file-name" style="margin:0;font-size:12px;color:#10b981;font-weight:700;"></p>
          </div>
          <input type="file" id="aud-file" accept=".xlsx,.xls,.csv" style="display:none;">
          <div id="aud-import-status" style="margin-top:10px;font-size:12px;min-height:20px;"></div>
          <button class="crm-btn crm-btn-primary crm-btn-block" type="button" id="aud-btn-import" style="margin-top:10px;">🚀 IMPORTAR DATOS</button>
        </div>
      </div>
    </div>

    <div id="aud-area" style="margin-top:15px;">
      <div class="text-muted" style="padding:18px;">Selecciona una tabla…</div>
    </div>

    <div id="aud-debug" class="crm-card" style="display:none;margin-top:14px;border-top-color:#ef4444;">
      <h3 class="crm-title" style="color:#ef4444;">🧪 Debug</h3>
      <pre id="aud-debug-pre" style="white-space:pre-wrap;font-size:12px;" class="text-muted"></pre>
    </div>
  </div>
</div>

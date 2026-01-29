<?php
/** @var array $data */

// Importar clase con namespace correcto (evita fatal "Class 'Logic' not found")
use CMBERP\Modules\Auditoria\Logic;

$tabla = (string)($data['tabla'] ?? '');
$pagina = (int)($data['pagina'] ?? 1);
$total = (int)($data['total'] ?? 0);
$total_paginas = (int)($data['total_paginas'] ?? 1);
$cols = (array)($data['cols'] ?? []);
$rows = (array)($data['rows'] ?? []);
$key_col = (string)($data['key_col'] ?? 'id');
$prefix = (string)($data['prefix'] ?? 'wp_');
$table_label = strtoupper(str_replace($prefix, '', $tabla));
?>
<div class="crm-card" style="margin-top:20px;">
  <div class="crm-header-flex">
    <div>
      <h3 class="crm-title" style="margin:0;">📋 Tabla: <?php echo esc_html($table_label); ?></h3>
      <p class="crm-subtitle" style="margin:8px 0 0;">
        Mostrando <?php echo esc_html(count($rows)); ?> de <?php echo esc_html($total); ?> · Página <?php echo esc_html($pagina); ?> de <?php echo esc_html($total_paginas); ?>
      </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <input type="text" id="aud-search" class="crm-input" placeholder="🔎 Buscar..." style="max-width:260px;">
      <button type="button" class="crm-btn crm-btn-ghost" id="aud-btn-cols">👁️ Columnas</button>
    </div>
  </div>

  <div class="crm-table-wrapper">
    <table class="crm-table sticky-mode" id="aud-table" style="min-width:1200px;">
      <thead>
        <tr>
          <?php foreach ($cols as $c): ?>
            <th><?php echo esc_html(strtoupper((string)($c->Field ?? ''))); ?></th>
          <?php endforeach; ?>
          <th class="sticky-col">ACCIONES</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($rows)): foreach ($rows as $r): ?>
          <?php
            // Clave de la fila
            $row_id = isset($r->{$key_col}) ? $r->{$key_col} : '';
          ?>
          <tr id="aud-row-<?php echo esc_attr($row_id); ?>">
            <?php foreach ($cols as $c):
              $f = (string)($c->Field ?? '');
              $val = isset($r->$f) ? (string)$r->$f : '';
              $short = Logic::truncate_value($val, 80);
              $is_key = ($f === $key_col);
            ?>
              <td class="dato-celda" data-campo="<?php echo esc_attr($f); ?>">
                <span class="view-mode" style="<?php echo $is_key ? 'font-weight:800;' : ''; ?>"><?php echo esc_html($short); ?></span>
                <?php if (!$is_key): ?>
                  <textarea class="edit-mode crm-input" style="display:none;min-height:60px;"><?php echo esc_textarea($val); ?></textarea>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
            <td class="sticky-col" style="background:#fff;">
              <div style="display:flex; gap:6px;">
                <button type="button" class="crm-btn crm-btn-ghost crm-btn-sm aud-edit" data-id="<?php echo esc_attr($row_id); ?>">✏️</button>
                <button type="button" class="crm-btn crm-btn-primary crm-btn-sm aud-save" style="display:none;" data-id="<?php echo esc_attr($row_id); ?>" data-tabla="<?php echo esc_attr($tabla); ?>">💾</button>
                <button type="button" class="crm-btn crm-btn-danger crm-btn-sm aud-del" data-id="<?php echo esc_attr($row_id); ?>" data-tabla="<?php echo esc_attr($tabla); ?>">🗑️</button>
              </div>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr>
            <td colspan="<?php echo esc_attr(count($cols) + 1); ?>" style="text-align:center;padding:25px;color:#94a3b8;">Sin registros.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($total_paginas > 1): ?>
    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:12px;">
      <button type="button" class="crm-btn crm-btn-ghost aud-page" data-page="1" <?php echo $pagina===1?'disabled':''; ?>>« Primero</button>
      <button type="button" class="crm-btn crm-btn-ghost aud-page" data-page="<?php echo esc_attr(max(1, $pagina-1)); ?>" <?php echo $pagina===1?'disabled':''; ?>>‹ Anterior</button>
      <?php
        $start = max(1, $pagina - 2);
        $end = min($total_paginas, $pagina + 2);
        for ($i=$start; $i<=$end; $i++):
      ?>
        <button type="button" class="crm-btn <?php echo ($i===$pagina?'crm-btn-primary':'crm-btn-ghost'); ?> aud-page" data-page="<?php echo esc_attr($i); ?>"><?php echo esc_html($i); ?></button>
      <?php endfor; ?>
      <button type="button" class="crm-btn crm-btn-ghost aud-page" data-page="<?php echo esc_attr(min($total_paginas, $pagina+1)); ?>" <?php echo $pagina===$total_paginas?'disabled':''; ?>>Siguiente ›</button>
      <button type="button" class="crm-btn crm-btn-ghost aud-page" data-page="<?php echo esc_attr($total_paginas); ?>" <?php echo $pagina===$total_paginas?'disabled':''; ?>>Último »</button>
    </div>
  <?php endif; ?>
</div>

<!-- Modal columnas -->
<div id="aud-modal-cols" class="crm-modal" style="display:none;">
  <div class="crm-modal-content" style="max-width:600px;">
    <h3 class="crm-title">👁️ Columnas visibles</h3>
    <p class="crm-subtitle">Marca/desmarca columnas.</p>
    <div style="max-height:320px; overflow:auto; border:1px solid #e2e8f0; border-radius:10px; padding:10px; background:#f8fafc;">
      <?php foreach ($cols as $idx => $c): $field=(string)($c->Field ?? ''); ?>
        <label style="display:flex; align-items:center; gap:10px; padding:8px; background:#fff; border-radius:8px; margin-bottom:8px;">
          <input class="aud-col-check" type="checkbox" checked data-col="<?php echo esc_attr($idx + 1); ?>">
          <span style="font-weight:700;"><?php echo esc_html(strtoupper($field)); ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <div style="display:flex; gap:10px; margin-top:12px;">
      <button type="button" class="crm-btn crm-btn-ghost" id="aud-cols-all">Todas</button>
      <button type="button" class="crm-btn crm-btn-ghost" id="aud-cols-none">Ninguna</button>
      <button type="button" class="crm-btn crm-btn-primary" id="aud-cols-apply" style="margin-left:auto;">Aplicar</button>
    </div>
  </div>
</div>

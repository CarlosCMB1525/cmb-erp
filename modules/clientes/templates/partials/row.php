<?php
$eid = (int)($e['id'] ?? 0);
$nombre = (string)($e['nombre_legal'] ?? '');
$nit = (string)($e['nit_id'] ?? '');
$razon = (string)($e['razon_social'] ?? '');
$tipo = (string)($e['tipo_cliente'] ?? 'EMPRESA');
?>
<tr class="cl-row" data-id="<?php echo esc_attr($eid); ?>">
  <td><strong>#<?php echo esc_html($eid); ?></strong></td>
  <td>
    <strong><?php echo esc_html($nombre); ?></strong><br>
    <small class="cmb-erp-text-muted">NIT: <?php echo esc_html($nit); ?></small><br>
    <?php if (!empty($razon)) : ?>
      <small class="cmb-erp-text-muted">Razón Social: <?php echo esc_html($razon); ?></small><br>
    <?php endif; ?>
    <span class="cmb-erp-badge cmb-erp-badge--brand"><?php echo esc_html($tipo ?: 'EMPRESA'); ?></span>
  </td>
  <td>
    <?php if (!empty($contactos)) : foreach ($contactos as $c) : ?>
      <div class="cmb-erp-card" style="padding:10px;margin:0 0 8px 0;">
        <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;">
          <div>
            <strong>👤 <?php echo esc_html($c['nombre_contacto'] ?? ''); ?></strong>
            <?php if (!empty($c['cargo'])) : ?>
              <div class="cmb-erp-text-muted" style="font-size:12px;"><?php echo esc_html($c['cargo']); ?></div>
            <?php endif; ?>
            <?php if (!empty($c['telefono_whatsapp'])) : ?>
              <div style="font-size:12px;">WA: <?php echo esc_html($c['telefono_whatsapp']); ?></div>
            <?php endif; ?>
            <?php if (!empty($c['correo_electronico'])) : ?>
              <div style="font-size:12px;">Email: <?php echo esc_html($c['correo_electronico']); ?></div>
            <?php endif; ?>
          </div>
          <div style="display:flex;gap:6px;">
            <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost cmb-erp-btn--sm" data-cl-edit-contact="<?php echo esc_attr((int)($c['id'] ?? 0)); ?>">✏️</button>
            <button type="button" class="cmb-erp-btn cmb-erp-btn--dark cmb-erp-btn--sm" data-cl-del-contact="<?php echo esc_attr((int)($c['id'] ?? 0)); ?>">🗑️</button>
          </div>
        </div>
      </div>
    <?php endforeach; else: ?>
      <span class="cmb-erp-text-muted">Sin contactos</span>
    <?php endif; ?>
  </td>
  <td class="cmb-erp-text-right">
    <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;">
      <button type="button" class="cmb-erp-btn cmb-erp-btn--primary cmb-erp-btn--sm" data-cl-add-contact="<?php echo esc_attr($eid); ?>" data-cl-name="<?php echo esc_attr($nombre); ?>">+ CONTACTO</button>
      <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost cmb-erp-btn--sm" data-cl-edit-company="<?php echo esc_attr($eid); ?>">✏️ Empresa</button>
      <button type="button" class="cmb-erp-btn cmb-erp-btn--dark cmb-erp-btn--sm" data-cl-del-company="<?php echo esc_attr($eid); ?>">🗑️ Empresa</button>
    </div>
  </td>
</tr>

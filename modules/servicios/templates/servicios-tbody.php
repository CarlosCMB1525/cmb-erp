<?php
if (!defined('ABSPATH')) exit;
$rows = (array)($data['rows'] ?? $rows ?? []);
if (empty($rows)) {
    echo '<tr><td colspan="6" class="cmb-erp-text-muted" style="padding:18px;text-align:center;">No hay servicios.</td></tr>';
    return;
}
foreach ($rows as $s) {
    $id = (int)($s['id'] ?? 0);
    $codigo = (string)($s['codigo_unico'] ?? '');
    $nombre = (string)($s['nombre_servicio'] ?? '');
    $tipo = (string)($s['tipo_servicio'] ?? 'UNICO');
    $precio = (float)($s['monto_unitario'] ?? 0);
?>
<tr class="svc-row" data-id="<?php echo esc_attr($id); ?>">
  <td data-label="ID"><strong>#<?php echo esc_html($id); ?></strong></td>
  <td data-label="CÓDIGO"><code><?php echo esc_html($codigo); ?></code></td>
  <td data-label="SERVICIO"><?php echo esc_html($nombre); ?></td>
  <td data-label="TIPO"><span class="cmb-erp-badge cmb-erp-badge--info"><?php echo esc_html($tipo); ?></span></td>
  <td data-label="PRECIO" class="cmb-erp-text-right"><strong><?php echo esc_html(number_format($precio, 2)); ?></strong> Bs</td>
  <td data-label="ACCIONES" class="cmb-erp-text-right">
    <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost cmb-erp-btn--sm" data-svc-action="edit" data-id="<?php echo esc_attr($id); ?>">✏️</button>
    <button type="button" class="cmb-erp-btn cmb-erp-btn--dark cmb-erp-btn--sm" data-svc-action="delete" data-id="<?php echo esc_attr($id); ?>">🗑️</button>
  </td>
</tr>
<?php }

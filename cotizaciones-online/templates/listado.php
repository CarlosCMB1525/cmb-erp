<?php
/** @var array $data */
if (!defined('ABSPATH')) exit;

$title = (string)($data['title'] ?? '📋 Cotizaciones');
$rows  = (array)($data['rows'] ?? []);
$nonce = (string)($data['nonce'] ?? '');

?>
<div class="cmb-erp-root" id="cqo_root">
  <div class="cmb-erp-card">
    <div class="cmb-erp-header">
      <div>
        <h2 class="cmb-erp-title"><?php echo esc_html($title); ?></h2>
        <p class="cmb-erp-subtitle">Listado general + PDF (jsPDF) desde el frontend.</p>
      </div>
      <div class="cmb-erp-actions">
        <input class="cmb-erp-input" id="cqo_search" type="search" placeholder="🔎 Buscar por Código / Empresa / Contacto" style="min-width:280px;" />
      </div>
    </div>

    <div class="cmb-erp-table-wrap">
      <table class="cmb-erp-table" id="cqo_table">
        <thead>
          <tr>
            <th>ID</th>
            <th>CÓDIGO</th>
            <th>FECHA</th>
            <th>EMPRESA</th>
            <th>CONTACTO</th>
            <th>ITEMS</th>
            <th class="cmb-erp-text-right">TOTAL</th>
            <th class="cmb-erp-text-right">PDF</th>
          </tr>
        </thead>
        <tbody id="cqo_tbody">
          <?php if (!empty($rows) && is_array($rows)): ?>
            <?php foreach ($rows as $r):
              $id = (int)($r['id'] ?? 0);
              $codigo = (string)($r['cot_codigo'] ?? '—');
              $fecha_raw = (string)($r['fecha_emision'] ?? '');
              $fecha = $fecha_raw ? date_i18n('d/m/Y', strtotime($fecha_raw)) : '—';
              $empresa = (string)($r['empresa'] ?? '—');
              $contacto = (string)($r['contacto'] ?? '—');
              $items_cnt = (int)($r['items_cnt'] ?? 0);
              $total = (float)($r['total'] ?? 0);
              $search = strtolower(trim($id . ' ' . $codigo . ' ' . $empresa . ' ' . $contacto));
            ?>
              <tr class="cqo-row" data-search="<?php echo esc_attr($search); ?>">
                <td data-label="ID"><strong>#<?php echo esc_html($id); ?></strong></td>
                <td data-label="CÓDIGO"><code><?php echo esc_html($codigo ?: '—'); ?></code></td>
                <td data-label="FECHA"><?php echo esc_html($fecha); ?></td>
                <td data-label="EMPRESA"><?php echo esc_html($empresa ?: '—'); ?></td>
                <td data-label="CONTACTO"><?php echo esc_html($contacto ?: '—'); ?></td>
                <td data-label="ITEMS"><?php echo esc_html($items_cnt); ?></td>
                <td data-label="TOTAL" class="cmb-erp-text-right"><strong><?php echo esc_html(number_format($total, 2)); ?></strong> Bs</td>
                <td data-label="PDF" class="cmb-erp-text-right">
                  <button type="button" class="cmb-erp-btn cmb-erp-btn--primary cmb-erp-btn--sm cqo-pdf" data-id="<?php echo esc_attr($id); ?>">
                    📄 PDF
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="8" class="cmb-erp-text-muted" style="padding:18px;text-align:center;">No hay cotizaciones.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div id="cqo_no" class="cmb-erp-text-muted" style="display:none;padding:12px;text-align:center;">No se encontraron coincidencias.</div>

  </div>

  <script>
    // Variables del módulo (sin contaminar globals legacy)
    window.CMBQuotesOnline = window.CMBQuotesOnline || {};
    window.CMBQuotesOnline.ajaxurl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
    window.CMBQuotesOnline.nonce = <?php echo wp_json_encode($nonce); ?>;
  </script>
</div>

<?php
/** @var array $view */
if (!defined('ABSPATH')) { exit; }

$inicio_default = (string)($view['inicio_default'] ?? date('Y-m-01'));
$fin_default = (string)($view['fin_default'] ?? date('Y-m-t'));
$cats = (array)($view['cats'] ?? []);
$metrics = (array)($view['metrics'] ?? []);
$rows = $view['rows'] ?? [];
?>
<div class="cmb-erp-root" id="cmb_dashboard_root">
  <div class="cmb-erp-card cmb-dashboard-hero">
    <div class="cmb-erp-header cmb-dashboard-top">
      <div>
        <div class="cmb-erp-title">📊 Dashboard</div>
        <div class="cmb-erp-subtitle">Buscador rápido + filtros avanzados + tabla</div>
      </div>
      <div class="cmb-dashboard-actions">
        <input id="cmb_dash_quick" class="cmb-erp-input" type="search" placeholder="Buscar empresa / doc…" />
        <button id="cmb_dash_open_filters" class="cmb-erp-btn cmb-erp-btn--ghost" type="button">Filtros</button>
        <button id="cmb_dash_clear_all" class="cmb-erp-btn cmb-erp-btn--ghost" type="button">Limpiar</button>
        <button id="cmb_btn_pnl" class="cmb-erp-btn cmb-erp-btn--primary" type="button">📄 P&amp;L (PDF)</button>
      </div>
    </div>

    <div id="cmb_dash_messages" class="cmb-erp-text-muted" style="font-weight:900;"></div>

    <div class="cmb-dashboard-metrics">
      <?php
      $cards = [
        ['TOTAL FACTURADO', 'Factura real (nro válido)', 'total_facturado'],
        ['TOTAL RECIBOS', 'Recibos independientes', 'total_recibos'],
        ['TOTAL GENERAL', 'Suma total filtrada', 'total_general'],
        ['COBRADO', 'Pagos asociados', 'cobrado'],
        ['POR COBRAR', 'General - cobrado', 'pendiente'],
        ['UTILIDAD REAL', 'Cobrado - % facturado', 'utilidad'],
        ['TOTAL COTIZADO (SIN VENTA)', 'Última versión por código base', 'cotizado_sin_v'],
        ['TOTAL COTIZADO (CON VENTA)', 'Última versión por código base', 'cotizado_con_v'],
        ['TOTAL EGRESOS (CASHFLOW)', 'Movimientos tipo Egreso', 'cashflow_egresos'],
        ['TOTAL INGRESOS (CASHFLOW)', 'Movimientos tipo Ingreso', 'cashflow_ingresos'],
      ];
      foreach ($cards as $idx => $c) {
        $k = $c[2];
        $val = (string)($metrics[$k] ?? '0.00');
        echo '<div class="cmb-erp-card cmb-dashboard-metric">';
        echo '<div class="label">' . esc_html($c[0]) . '</div>';
        echo '<div class="cmb-erp-text-muted" style="font-weight:800;">' . esc_html($c[1]) . '</div>';
        echo '<div class="value" id="m_' . esc_attr($k) . '">' . esc_html($val) . ' Bs</div>';
        echo '</div>';
      }
      ?>
    </div>

    <div class="cmb-dashboard-table-wrap">
      <h3 style="margin:14px 0 8px;font-weight:1000;">Detalle de ventas</h3>
      <div class="cmb-erp-table-wrap">
        <table class="cmb-erp-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>EMPRESA / NIT / CATEGORÍA</th>
              <th>PERIODO</th>
              <th>DOCUMENTO</th>
              <th>ESTADO PAGO</th>
              <th>FECHAS (V/D/P)</th>
              <th>TOTALES</th>
              <th>NETO</th>
            </tr>
          </thead>
          <tbody id="cmb_dash_tbody">
            <?php $rows = $rows; require __DIR__ . '/partials/tbody.php'; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php require __DIR__ . '/modals/filters-modal.php'; ?>
  <?php require __DIR__ . '/modals/pnl-modal.php'; ?>
</div>

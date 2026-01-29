<?php
namespace CMBERP\Modules\Cashflow;
if (!defined('ABSPATH')) exit;

final class Shortcode {
  private static function can_access(): bool {
    return current_user_can('administrator') || current_user_can('edit_posts');
  }
  private static function ver(string $rel): string {
    if (defined('CMB_ERP_DIR')) {
      $full = CMB_ERP_DIR . ltrim($rel, '/');
      if (is_file($full)) return (string) @filemtime($full);
    }
    return defined('CMB_ERP_VERSION') ? (string) CMB_ERP_VERSION : '1.0.0';
  }

  public static function render($atts = [], $content = null, $tag = ''): string {
    if (!self::can_access()) {
      return '<div class="cmb-erp-card"><p style="color:#ef4444;font-weight:800;">⚠️ No tienes permisos para acceder al Control de Flujo de Caja.</p></div>';
    }

    // Media Library (adjuntos)
    if (function_exists('wp_enqueue_media')) {
      wp_enqueue_media();
    }

    // Fallback assets (si Core\Assets no detectó el shortcode)
    if (defined('CMB_ERP_DIR') && defined('CMB_ERP_URL')) {
      $css_rel = 'modules/cashflow/assets/css/cashflow.css';
      $js_rel  = 'modules/cashflow/assets/js/cashflow.js';
      if (is_file(CMB_ERP_DIR . $css_rel) && !wp_style_is('cmb-erp-cashflow', 'enqueued')) {
        wp_enqueue_style('cmb-erp-cashflow', CMB_ERP_URL . $css_rel, ['cmb-erp-tables'], self::ver($css_rel));
      }
      if (is_file(CMB_ERP_DIR . $js_rel) && !wp_script_is('cmb-erp-cashflow', 'enqueued')) {
        wp_register_script('cmb-erp-cashflow', CMB_ERP_URL . $js_rel, ['jquery'], self::ver($js_rel), true);
        if (!isset($GLOBALS['cmbCashflowVars_localized'])) {
          $GLOBALS['cmbCashflowVars_localized'] = true;
          wp_localize_script('cmb-erp-cashflow', 'cmbCashflowVars', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => defined('CMB_ERP_NONCE_ACTION') ? wp_create_nonce(CMB_ERP_NONCE_ACTION) : wp_create_nonce('crm_erp_nonce'),
            'cats' => Settings::cats(),
            'mets' => Settings::mets(),
            'can_upload' => current_user_can('upload_files'),
          ]);
        }
        wp_enqueue_script('cmb-erp-cashflow');
      }
    }

    ob_start();
    ?>
    <div class="cmb-erp-root" id="cmb_cashflow_root">
      <div class="cmb-erp-card">
        <div class="cmb-erp-header">
          <div>
            <h2 class="cmb-erp-title">📊 Control de Flujo de Caja</h2>
            <div class="cmb-erp-subtitle">Registra ingresos/egresos y procesa pagos parciales con histórico.</div>
          </div>
          <div class="cmb-erp-actions">
            <button type="button" class="cmb-erp-btn cmb-erp-btn--primary" id="btn_ingreso">➕ Ingreso</button>
            <button type="button" class="cmb-erp-btn cmb-erp-btn--dark" id="btn_egreso">➖ Egreso</button>
            <span class="cmb-erp-badge cmb-erp-badge--brand" id="cf_tipo_label">Egreso</span>
          </div>
        </div>

        <input type="hidden" id="cf_tipo" value="Egreso" />

        <div class="cmb-erp-grid">
          <div>
            <label class="cmb-erp-label">Método</label>
            <select id="cf_metodo" class="cmb-erp-select"></select>
          </div>

          <div id="cf_int_wrap" style="display:none;">
            <label class="cmb-erp-label">Monto (USD)</label>
            <input id="cf_monto_usd" class="cmb-erp-input" type="number" step="0.01" />
            <label class="cmb-erp-label" style="margin-top:10px;">Tasa</label>
            <input id="cf_tasa" class="cmb-erp-input" type="number" step="0.000001" />
            <div class="cmb-erp-text-muted" style="margin-top:6px;font-size:12px;">Se recalcula automáticamente el monto en Bs.</div>
          </div>

          <div class="cmb-erp-span-2">
            <label class="cmb-erp-label">Detalle</label>
            <input id="cf_detalle" class="cmb-erp-input" type="text" placeholder="Ej: Compra de mercadería" />
          </div>

          <div class="cmb-erp-span-2">
            <label class="cmb-erp-label">Beneficiario (opcional)</label>
            <input id="cf_beneficiario" class="cmb-erp-input" type="text" />
          </div>

          <div id="cf_cat_eg_wrap">
            <label class="cmb-erp-label">Categoría (Egreso)</label>
            <select id="cf_categoria_egreso" class="cmb-erp-select"></select>
          </div>

          <div id="cf_cat_in_wrap" style="display:none;">
            <label class="cmb-erp-label">Categoría (Ingreso)</label>
            <select id="cf_categoria_ingreso" class="cmb-erp-select">
              <option>Inyección de Capital</option>
              <option>Préstamo</option>
            </select>
          </div>

          <div>
            <label class="cmb-erp-label">Monto (Bs)</label>
            <input id="cf_monto_bob" class="cmb-erp-input" type="number" step="0.01" />
          </div>

          <div class="cmb-erp-span-2">
            <label class="cmb-erp-label">Adjuntos (opcional · Biblioteca de Medios)</label>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
              <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost cmb-erp-btn--sm" id="cf_select_media">Seleccionar adjuntos</button>
              <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost cmb-erp-btn--sm" id="cf_clear_media">Limpiar</button>
              <input type="hidden" id="cf_adjuntos_ids" value="" />
              <div id="cf_adjuntos_list" class="cmb-erp-text-muted" style="font-size:12px;">Sin adjuntos seleccionados.</div>
            </div>
          </div>
        </div>

        <div style="margin-top:12px;display:flex;justify-content:flex-end;">
          <button type="button" class="cmb-erp-btn cmb-erp-btn--primary" id="cf_save">💾 Guardar</button>
        </div>

        <div style="margin-top:16px;" class="cmb-erp-table-wrap">
          <table class="cmb-erp-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>TIPO</th>
                <th>DETALLE</th>
                <th>BENEFICIARIO</th>
                <th>CATEGORÍA</th>
                <th>TOTAL</th>
                <th>PAGADO</th>
                <th>SALDO</th>
                <th>ESTADO</th>
                <th>ADJ</th>
                <th>FECHA</th>
                <th class="cmb-erp-text-right">ACCIONES</th>
              </tr>
            </thead>
            <tbody id="cf_tbody">
              <tr><td colspan="12" class="cmb-erp-text-muted">Cargando…</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Modal: Ver -->
      <div class="cmb-erp-modal" id="cf_modal_view">
        <div class="cmb-erp-modal__content cmb-erp-modal__dialog">
          <div class="cmb-erp-modal__header">
            <div>
              <h3 class="cmb-erp-title" style="font-size:16px;">Detalle del movimiento</h3>
              <div class="cmb-erp-subtitle" id="cf_view_sub">—</div>
            </div>
            <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost cmb-erp-btn--sm" data-cf-close="cf_modal_view">✖</button>
          </div>
          <div class="cmb-erp-modal__body" id="view_body"></div>
          <div class="cmb-erp-modal__footer">
            <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost" data-cf-close="cf_modal_view">Cerrar</button>
          </div>
        </div>
      </div>

      <!-- Modal: Pagar -->
      <div class="cmb-erp-modal" id="cf_modal_pay">
        <div class="cmb-erp-modal__content cmb-erp-modal__dialog">
          <div class="cmb-erp-modal__header">
            <div>
              <h3 class="cmb-erp-title" style="font-size:16px;">Registrar pago</h3>
              <div class="cmb-erp-subtitle" id="pay_sub">Saldo pendiente: —</div>
            </div>
            <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost cmb-erp-btn--sm" data-cf-close="cf_modal_pay">✖</button>
          </div>
          <div class="cmb-erp-modal__body">
            <input type="hidden" id="pay_id" value="" />
            <div class="cmb-erp-grid">
              <div>
                <label class="cmb-erp-label">Monto a pagar (Bs)</label>
                <input id="pay_monto_bs" class="cmb-erp-input" type="number" step="0.01" />
              </div>
              <div>
                <label class="cmb-erp-label">Fecha de pago</label>
                <input id="pay_fecha" class="cmb-erp-input" type="date" />
              </div>
              <div>
                <label class="cmb-erp-label">Método</label>
                <select id="pay_metodo" class="cmb-erp-select"></select>
              </div>
              <div id="pay_int_wrap" style="display:none;">
                <label class="cmb-erp-label">Monto (USD)</label>
                <input id="pay_monto_usd" class="cmb-erp-input" type="number" step="0.01" />
                <label class="cmb-erp-label" style="margin-top:10px;">Tasa</label>
                <input id="pay_tasa" class="cmb-erp-input" type="number" step="0.000001" />
                <div class="cmb-erp-text-muted" style="margin-top:6px;font-size:12px;">Si llenas USD + tasa, se recalcula el monto en Bs.</div>
              </div>
              <div class="cmb-erp-span-2">
                <label class="cmb-erp-label">Comprobante (opcional)</label>
                <input id="pay_comprobante" class="cmb-erp-input" type="text" />
              </div>
              <div>
                <label class="cmb-erp-label">Fecha comprobante (opcional)</label>
                <input id="pay_fecha_comprobante" class="cmb-erp-input" type="date" />
              </div>
              <div class="cmb-erp-span-2">
                <label class="cmb-erp-label">Adjuntos (Biblioteca de Medios)</label>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                  <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost cmb-erp-btn--sm" id="pay_select_media">Seleccionar adjuntos</button>
                  <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost cmb-erp-btn--sm" id="pay_clear_media">Limpiar</button>
                  <input type="hidden" id="pay_adjuntos_ids" value="" />
                  <div id="pay_adjuntos_list" class="cmb-erp-text-muted" style="font-size:12px;">Sin adjuntos seleccionados.</div>
                </div>
              </div>
            </div>
          </div>
          <div class="cmb-erp-modal__footer">
            <button type="button" class="cmb-erp-btn cmb-erp-btn--primary" id="pay_save">Guardar pago</button>
            <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost" data-cf-close="cf_modal_pay">Cancelar</button>
          </div>
        </div>
      </div>

      <!-- Modal: Histórico -->
      <div class="cmb-erp-modal" id="cf_modal_history">
        <div class="cmb-erp-modal__content cmb-erp-modal__dialog">
          <div class="cmb-erp-modal__header">
            <div>
              <h3 class="cmb-erp-title" style="font-size:16px;" id="cf_hist_title">Histórico</h3>
              <div class="cmb-erp-subtitle" id="cf_hist_sub">Cargando…</div>
            </div>
            <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost cmb-erp-btn--sm" data-cf-close="cf_modal_history">✖</button>
          </div>
          <div class="cmb-erp-modal__body">
            <div id="history_totals" style="margin-bottom:10px;display:none;"></div>
            <div id="history_body" class="cmb-erp-text-muted">Cargando…</div>
          </div>
          <div class="cmb-erp-modal__footer">
            <button type="button" class="cmb-erp-btn cmb-erp-btn--ghost" data-cf-close="cf_modal_history">Cerrar</button>
          </div>
        </div>
      </div>

    </div>
    <?php
    return (string) ob_get_clean();
  }
}

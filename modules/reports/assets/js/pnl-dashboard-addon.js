/* global window, document */
(function () {
  'use strict';

  function byId(id) { return document.getElementById(id); }

  function vars() {
    return window.cmbReportsVars || {};
  }

  function apiPost(action, data) {
    var v = vars();
    data = data || {};
    data.action = action;
    data.nonce = v.nonce;

    var body = new URLSearchParams();
    Object.keys(data).forEach(function (k) { body.append(k, data[k]); });

    return fetch(v.ajaxurl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function (r) {
      return r.text().then(function (txt) {
        var json;
        try { json = JSON.parse(txt); } catch (e) {
          var err = new Error('Respuesta no JSON (HTTP ' + r.status + ')');
          err._body = txt;
          throw err;
        }
        if (!r.ok) {
          var err2 = new Error('HTTP ' + r.status);
          err2._json = json;
          throw err2;
        }
        return json;
      });
    });
  }

  function ensureJsPDF() {
    // 1) Loader del módulo Reports
    if (window.CMBReportsJsPDFLoader && typeof window.CMBReportsJsPDFLoader.ensure === 'function') {
      return window.CMBReportsJsPDFLoader.ensure();
    }
    // 2) Fallback: loader de Cotizaciones
    if (window.CMBJsPDFLoader && typeof window.CMBJsPDFLoader.ensure === 'function') {
      return window.CMBJsPDFLoader.ensure();
    }
    // 3) Si ya está jsPDF, listo
    if ((window.jspdf && window.jspdf.jsPDF) || window.jsPDF) {
      return Promise.resolve(true);
    }
    return Promise.reject(new Error('Loader jsPDF no disponible'));
  }

  function fileName(payload) {
    var label = payload && payload.period_label ? payload.period_label : (payload && payload.period ? payload.period : 'Periodo');
    label = String(label || '').replace(/\s+/g, ' ').trim();
    return 'Estado de Resultados (P&L) - CMB - ' + (label || 'Periodo');
  }

  function openModal() {
    var m = byId('cmb_pnl_modal');
    if (m) m.classList.add('is-open');
  }

  function closeModal() {
    var m = byId('cmb_pnl_modal');
    if (m) m.classList.remove('is-open');
  }

  function init() {
    var v = vars();

    if (!v.ajaxurl || !v.nonce || !v.actions || !v.actions.pnl_payload) {
      // eslint-disable-next-line no-console
      console.warn('[CMB Reports] cmbReportsVars incompleto. Falta ajaxurl/nonce/actions.pnl_payload.');
      return;
    }

    var btn = byId('cmb_btn_pnl');
    if (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        openModal();
      });
    }

    var closeBtn = byId('cmb_pnl_close');
    if (closeBtn) {
      closeBtn.addEventListener('click', function (e) {
        e.preventDefault();
        closeModal();
      });
    }

    var genBtn = byId('cmb_pnl_generate');
    if (genBtn) {
      genBtn.addEventListener('click', function (e) {
        e.preventDefault();

        var mode = (byId('cmb_pnl_mode') || {}).value || 'MONTH';
        var ym = (byId('cmb_pnl_month') || {}).value || '';
        var y = (byId('cmb_pnl_year') || {}).value || '';
        var period = (mode === 'YEAR') ? y : ym;

        if (!period) {
          alert('Selecciona un periodo.');
          return;
        }

        genBtn.disabled = true;
        genBtn.textContent = 'Generando…';

        ensureJsPDF()
          .then(function () {
            return apiPost(v.actions.pnl_payload, { period_type: mode, period: period });
          })
          .then(function (r) {
            if (!(r && r.success && r.data && r.data.payload)) {
              throw new Error((r && r.data) ? r.data : 'No se pudo generar payload');
            }
            if (!window.CMBReportsPnlPDF || typeof window.CMBReportsPnlPDF.build !== 'function') {
              throw new Error('PDF builder no disponible');
            }

            var payload = r.data.payload;
            var doc = window.CMBReportsPnlPDF.build(payload);
            doc.save(fileName(payload) + '.pdf');
            closeModal();
          })
          .catch(function (err) {
            // eslint-disable-next-line no-console
            console.error(err);
            var msg = (err && err._json && err._json.data) ? err._json.data : (err && err.message ? err.message : 'Error');
            if (err && err._body) msg = msg + ' | ' + String(err._body).slice(0, 180);
            alert('Error al generar P&L: ' + msg);
          })
          .finally(function () {
            genBtn.disabled = false;
            genBtn.textContent = 'Generar PDF';
          });
      });
    }

    // Click fuera
    var modal = byId('cmb_pnl_modal');
    if (modal) {
      modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
      });
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

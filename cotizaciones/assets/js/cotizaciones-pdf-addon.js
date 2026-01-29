/* global window, document */
(function () {
  'use strict';

  var vars = window.cmbQuotesVars || {};

  function apiPost(action, data) {
    data = data || {};
    data.action = action;
    data.nonce = data.nonce || vars.nonce;
    var body = new URLSearchParams();
    Object.keys(data).forEach(function (k) { body.append(k, data[k]); });

    return fetch(vars.ajaxurl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function (r) { return r.json(); });
  }

  function ensureJsPDF() {
    if (window.CMBJsPDFLoader && typeof window.CMBJsPDFLoader.ensure === 'function') {
      return window.CMBJsPDFLoader.ensure();
    }
    var ok = !!(window.jspdf && window.jspdf.jsPDF) || !!window.jsPDF;
    return ok ? Promise.resolve(true) : Promise.reject(new Error('Loader de jsPDF no disponible'));
  }

  function fetchImageAsDataUrl(url) {
    url = String(url || '').trim();
    if (!url) return Promise.resolve('');

    return fetch(url, { mode: 'cors' })
      .then(function (r) {
        if (!r.ok) throw new Error('No se pudo cargar imagen del footer');
        return r.blob();
      })
      .then(function (blob) {
        return new Promise(function (resolve) {
          var reader = new FileReader();
          reader.onload = function () { resolve(String(reader.result || '')); };
          reader.onerror = function () { resolve(''); };
          reader.readAsDataURL(blob);
        });
      })
      .catch(function () { return ''; });
  }

  function formatDateDDMMYYYY(iso) {
    iso = String(iso || '');
    var m = iso.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!m) return '';
    return m[3] + '.' + m[2] + '.' + m[1];
  }

  function sanitizeFileName(s) {
    s = String(s || '').trim();
    return s.replace(/[\\/:*?"<>|]+/g, ' ').replace(/\s+/g, ' ').trim();
  }

  function buildFileName(payload) {
    var q = payload.quote || {};
    var c = payload.client || {};

    var code = String(q.codigo || ('cotizacion-' + (q.id || ''))).trim();
    var empresa = String(c.nombre_legal || '').trim() || 'Empresa';
    var fechaIso = String(q.fecha || '').trim();
    var fecha = formatDateDDMMYYYY(fechaIso) || fechaIso;

    var m = code.match(/^(\d{4}CDG\d{2})-V(\d+)$/);
    if (m) {
      var base = m[1];
      var vn = m[2];
      return sanitizeFileName(base + ' - V' + vn + ' - ' + empresa + ' - ' + fecha);
    }

    return sanitizeFileName(code + ' - ' + empresa + ' - ' + fecha);
  }

  function doDownload(id) {
    id = parseInt(id || 0, 10) || 0;
    if (!id) return;

    var action = (vars.actions && vars.actions.pdf_payload) ? vars.actions.pdf_payload : 'cmb_quotes_pdf_payload';

    return ensureJsPDF()
      .then(function () { return apiPost(action, { id: id }); })
      .then(function (r) {
        if (!(r && r.success && r.data)) throw new Error((r && r.data) ? r.data : 'Error al obtener payload');
        var payload = r.data;
        var imgUrl = payload && payload.company ? payload.company.footer_image_url : '';
        return fetchImageAsDataUrl(imgUrl).then(function (dataUrl) {
          if (!payload.company) payload.company = {};
          payload.company.footer_image_data = dataUrl; // para pdf-builder
          return payload;
        });
      })
      .then(function (payload) {
        if (!window.CMBQuotesPDF || !window.CMBQuotesPDF.build) throw new Error('PDF builder no disponible');
        var doc = window.CMBQuotesPDF.build(payload);
        var fname = buildFileName(payload) || ('cotizacion-' + id);
        doc.save(fname + '.pdf');
      });
  }

  function currentId() {
    var el = document.getElementById('q_id');
    return el ? parseInt(el.value || '0', 10) : 0;
  }

  function init() {
    document.querySelectorAll('[data-q="pdf"]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        doDownload(currentId());
      });
    });

    // API pública (historial)
    window.CMBQuotesPDFDownload = window.CMBQuotesPDFDownload || {};
    window.CMBQuotesPDFDownload.downloadById = doDownload;
    window.CMBQuotesPDFDownload.buildFileName = buildFileName;
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

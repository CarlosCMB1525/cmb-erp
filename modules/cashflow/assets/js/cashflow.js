/* global window, document */
(function () {
  'use strict';
  var $ = window.jQuery;

  function cfg() {
    return window.cmbCashflowVars || {};
  }
  function q(id) {
    return document.getElementById(id);
  }
  function openModal(id) {
    var m = q(id);
    if (m) m.classList.add('is-open');
  }
  function closeModal(id) {
    var m = q(id);
    if (m) m.classList.remove('is-open');
  }

  function fillSelect(sel, values) {
    if (!sel) return;
    var cur = sel.value;
    sel.innerHTML = '';
    (values || []).forEach(function (v) {
      var o = document.createElement('option');
      o.value = v;
      o.textContent = v;
      sel.appendChild(o);
    });
    if (cur && Array.from(sel.options).some(function (o) { return o.value === cur; })) {
      sel.value = cur;
    }
  }

  function toggleInternational(selectId, wrapId, usdId, tasaId) {
    var sel = q(selectId);
    var wrap = q(wrapId);
    if (!sel || !wrap) return;
    var isInt = sel.value === 'Tarjeta Internacional';
    wrap.style.display = isInt ? '' : 'none';
    if (!isInt) {
      var usd = q(usdId);
      var tasa = q(tasaId);
      if (usd) usd.value = '';
      if (tasa) tasa.value = '';
    }
  }

  function calcInternational(usdId, tasaId, bobId) {
    var usdEl = q(usdId);
    var tasaEl = q(tasaId);
    var bobEl = q(bobId);
    if (!usdEl || !tasaEl || !bobEl) return;
    var usd = parseFloat(usdEl.value || '0');
    var tasa = parseFloat(tasaEl.value || '0');
    if (usd > 0 && tasa > 0) {
      bobEl.value = (usd * tasa).toFixed(2);
    }
  }

  function setType(tipo) {
    var t = q('cf_tipo');
    var lbl = q('cf_tipo_label');
    if (t) t.value = tipo;
    if (lbl) lbl.textContent = tipo;

    var egWrap = q('cf_cat_eg_wrap');
    var igWrap = q('cf_cat_in_wrap');
    if (egWrap) egWrap.style.display = (tipo === 'Egreso') ? '' : 'none';
    if (igWrap) igWrap.style.display = (tipo === 'Ingreso') ? '' : 'none';
  }

  function resetForm() {
    ['cf_detalle', 'cf_beneficiario', 'cf_monto_bob', 'cf_monto_usd', 'cf_tasa'].forEach(function (id) {
      var el = q(id);
      if (el) el.value = '';
    });
    setType('Egreso');
    setAdjuntosIds('cf_adjuntos_ids', 'cf_adjuntos_list', []);
  }

  // Adjuntos helpers (IDs)
  function getAdjuntosIds(fieldId) {
    var hid = q(fieldId);
    if (!hid || !hid.value) return [];
    return hid.value
      .split(',')
      .map(function (x) { return parseInt(x, 10); })
      .filter(function (n) { return n > 0; });
  }

  function renderAdjuntosList(listId, ids) {
    var el = q(listId);
    if (!el) return;
    if (!ids || !ids.length) {
      el.textContent = 'Sin adjuntos seleccionados.';
      return;
    }
    el.innerHTML = '';
    ids.forEach(function (id) {
      var div = document.createElement('div');
      div.textContent = '📎 ID ' + id;
      el.appendChild(div);
    });
  }

  function setAdjuntosIds(fieldId, listId, ids) {
    var hid = q(fieldId);
    if (!hid) return;
    hid.value = (ids || []).join(',');
    renderAdjuntosList(listId, ids || []);
  }

  function openMediaPicker(fieldId, listId) {
    if (!window.wp || !wp.media) {
      alert('Biblioteca de medios no disponible.');
      return;
    }
    var v = cfg();
    if (!v.can_upload) {
      alert('Tu usuario no tiene permisos para usar la biblioteca de medios.');
      return;
    }

    var frame = wp.media({
      title: 'Seleccionar adjuntos',
      button: { text: 'Usar adjuntos' },
      multiple: true
    });

    frame.on('select', function () {
      var selection = frame.state().get('selection');
      var ids = getAdjuntosIds(fieldId);
      selection.each(function (att) {
        var id = att && att.id ? parseInt(att.id, 10) : 0;
        if (id > 0 && ids.indexOf(id) === -1) ids.push(id);
      });
      setAdjuntosIds(fieldId, listId, ids);
    });

    frame.open();
  }

  function post(action, data, files) {
    var v = cfg();
    data = data || {};
    data.action = action;
    data.nonce = data.nonce || v.nonce;

    if (!v.ajaxurl) {
      console.error('cmbCashflowVars.ajaxurl faltante');
      return $.Deferred().reject({ responseText: 'ajaxurl faltante' });
    }

    if (files) {
      var fd = new FormData();
      Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
      Object.keys(files).forEach(function (k) { if (files[k]) fd.append(k, files[k]); });
      return $.ajax({
        url: v.ajaxurl,
        method: 'POST',
        dataType: 'json',
        data: fd,
        processData: false,
        contentType: false
      });
    }

    return $.ajax({ url: v.ajaxurl, method: 'POST', dataType: 'json', data: data });
  }

  function gatherCreatePayload() {
    var v = cfg();
    return {
      tipo: (q('cf_tipo') || {}).value || 'Egreso',
      detalle: (q('cf_detalle') || {}).value || '',
      beneficiario: (q('cf_beneficiario') || {}).value || '',
      categoria_egreso: (q('cf_categoria_egreso') || {}).value || ((v.cats && v.cats[0]) ? v.cats[0] : ''),
      categoria_ingreso: (q('cf_categoria_ingreso') || {}).value || 'Inyección de Capital',
      metodo_pago: (q('cf_metodo') || {}).value || ((v.mets && v.mets[0]) ? v.mets[0] : ''),
      monto_bs: (q('cf_monto_bob') || {}).value || '0',
      monto_usd: (q('cf_monto_usd') || {}).value || '0',
      tasa_cambio: (q('cf_tasa') || {}).value || '0',
      adjuntos_ids: (q('cf_adjuntos_ids') || {}).value || ''
    };
  }

  function refresh() {
    post('cmb_cashflow_list', {}).done(function (r) {
      if (r && r.success && r.data && typeof r.data.tbody === 'string') {
        var tb = q('cf_tbody');
        if (tb) tb.innerHTML = r.data.tbody || '';
      } else {
        alert((r && r.data) ? r.data : 'Error al cargar');
      }
    }).fail(function (x) {
      alert('Fallo AJAX list: ' + (x.responseText || 'sin respuesta'));
    });
  }

  function renderView(row, attachments) {
    var body = q('view_body');
    var sub = q('cf_view_sub');
    if (!body) return;
    body.innerHTML = '';

    function addLine(label, value) {
      var wrap = document.createElement('div');
      wrap.style.marginBottom = '8px';
      var strong = document.createElement('strong');
      strong.textContent = label + ': ';
      wrap.appendChild(strong);
      var span = document.createElement('span');
      span.textContent = value;
      wrap.appendChild(span);
      body.appendChild(wrap);
    }

    addLine('ID', '#' + (row.id || ''));
    addLine('Tipo', row.tipo || '');
    addLine('Detalle', row.detalle || '');
    addLine('Beneficiario', row.beneficiario || '');
    addLine('Monto (Bs)', (row.monto_bs || '') + ' Bs');
    addLine('Estado', row.estado || '');

    var atts = Array.isArray(attachments) ? attachments : [];
    if (atts.length) {
      var wrap = document.createElement('div');
      wrap.style.marginTop = '10px';
      var t = document.createElement('div');
      t.style.fontWeight = '900';
      t.textContent = 'Adjuntos:';
      wrap.appendChild(t);
      atts.forEach(function (u) {
        var a = document.createElement('a');
        a.href = u;
        a.target = '_blank';
        a.rel = 'noopener';
        a.textContent = '📎 Abrir archivo';
        a.style.display = 'inline-block';
        a.style.marginRight = '10px';
        a.style.marginTop = '6px';
        wrap.appendChild(a);
      });
      body.appendChild(wrap);
    }

    if (sub) {
      sub.textContent = (row.creado_en ? ('Creado: ' + row.creado_en) : '—');
    }
  }

  function openView(id) {
    post('cmb_cashflow_get', { id: id }).done(function (r) {
      if (r && r.success && r.data && r.data.row) {
        renderView(r.data.row, r.data.attachments || []);
        openModal('cf_modal_view');
      } else {
        alert((r && r.data) ? r.data : 'Error');
      }
    }).fail(function (x) {
      alert('Fallo AJAX get: ' + (x.responseText || ''));
    });
  }

  function openPay(id, saldoText) {
    var v = cfg();
    var pid = q('pay_id');
    if (pid) pid.value = id;

    var fecha = q('pay_fecha');
    if (fecha) {
      try { fecha.value = new Date().toISOString().split('T')[0]; } catch (e) {}
    }

    var sub = q('pay_sub');
    if (sub) sub.textContent = 'Saldo pendiente: ' + (saldoText || '—');

    fillSelect(q('pay_metodo'), v.mets || []);
    toggleInternational('pay_metodo', 'pay_int_wrap', 'pay_monto_usd', 'pay_tasa');

    // reset adjuntos
    setAdjuntosIds('pay_adjuntos_ids', 'pay_adjuntos_list', []);

    openModal('cf_modal_pay');
  }

  function openHistory(id) {
    var title = q('cf_hist_title');
    var sub = q('cf_hist_sub');
    var body = q('history_body');
    var totals = q('history_totals');

    if (title) title.textContent = 'Histórico del movimiento #' + id;
    if (sub) sub.textContent = 'Cargando…';
    if (body) body.textContent = 'Cargando…';
    if (totals) { totals.style.display = 'none'; totals.innerHTML = ''; }

    openModal('cf_modal_history');

    post('cmb_cashflow_history', { id: id }).done(function (r) {
      if (r && r.success && r.data) {
        if (body && typeof r.data.html === 'string') body.innerHTML = r.data.html;
        if (totals) {
          totals.style.display = '';
          var t = parseFloat(r.data.total || 0);
          var p = parseFloat(r.data.paid || 0);
          var s = parseFloat(r.data.saldo || 0);
          totals.innerHTML =
            '<div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">' +
            '<span class="cmb-erp-badge cmb-erp-badge--info">TOTAL: ' + t.toFixed(2) + ' Bs</span>' +
            '<span class="cmb-erp-badge cmb-erp-badge--success">PAGADO: ' + p.toFixed(2) + ' Bs</span>' +
            '<span class="cmb-erp-badge cmb-erp-badge--warning">SALDO: ' + s.toFixed(2) + ' Bs</span>' +
            '</div>';
        }
        if (sub) sub.textContent = '—';
      } else {
        if (body) body.textContent = (r && r.data) ? r.data : 'Error al cargar histórico.';
        if (sub) sub.textContent = 'Error';
      }
    }).fail(function (x) {
      if (body) body.textContent = 'Fallo AJAX history: ' + (x.responseText || '');
      if (sub) sub.textContent = 'Error';
    });
  }

  function bindModalClose() {
    document.querySelectorAll('[data-cf-close]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var id = btn.getAttribute('data-cf-close');
        if (id) closeModal(id);
      });
    });

    ['cf_modal_view', 'cf_modal_pay', 'cf_modal_history'].forEach(function (mid) {
      var m = q(mid);
      if (!m) return;
      m.addEventListener('click', function (e) {
        if (e.target === m) closeModal(mid);
      });
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      ['cf_modal_history', 'cf_modal_pay', 'cf_modal_view'].some(function (mid) {
        var m = q(mid);
        if (m && m.classList.contains('is-open')) {
          closeModal(mid);
          return true;
        }
        return false;
      });
    });
  }

  function init() {
    var v = cfg();

    fillSelect(q('cf_metodo'), v.mets || []);
    fillSelect(q('cf_categoria_egreso'), v.cats || []);

    setType('Egreso');
    toggleInternational('cf_metodo', 'cf_int_wrap', 'cf_monto_usd', 'cf_tasa');

    var bi = q('btn_ingreso');
    if (bi) bi.addEventListener('click', function () { setType('Ingreso'); });
    var be = q('btn_egreso');
    if (be) be.addEventListener('click', function () { setType('Egreso'); });

    var met = q('cf_metodo');
    if (met) met.addEventListener('change', function () {
      toggleInternational('cf_metodo', 'cf_int_wrap', 'cf_monto_usd', 'cf_tasa');
    });

    var usd = q('cf_monto_usd');
    if (usd) usd.addEventListener('input', function () { calcInternational('cf_monto_usd', 'cf_tasa', 'cf_monto_bob'); });
    var tasa = q('cf_tasa');
    if (tasa) tasa.addEventListener('input', function () { calcInternational('cf_monto_usd', 'cf_tasa', 'cf_monto_bob'); });

    // Adjuntos (movimiento)
    var cfSel = q('cf_select_media');
    if (cfSel) cfSel.addEventListener('click', function (e) {
      e.preventDefault();
      openMediaPicker('cf_adjuntos_ids', 'cf_adjuntos_list');
    });
    var cfClr = q('cf_clear_media');
    if (cfClr) cfClr.addEventListener('click', function (e) {
      e.preventDefault();
      setAdjuntosIds('cf_adjuntos_ids', 'cf_adjuntos_list', []);
    });

    var save = q('cf_save');
    if (save) save.addEventListener('click', function () {
      var p = gatherCreatePayload();
      if (!p.detalle) return alert('Detalle es obligatorio.');
      post('cmb_cashflow_create', p).done(function (r) {
        if (r && r.success) {
          resetForm();
          refresh();
        } else {
          alert((r && r.data) ? r.data : 'Error al guardar');
        }
      }).fail(function (x) {
        alert('Fallo AJAX create: ' + (x.responseText || ''));
      });
    });

    // Delegación: view / pay / history
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-cf-action]');
      if (!btn) return;
      var act = btn.getAttribute('data-cf-action');
      var id = btn.getAttribute('data-id');
      var saldo = btn.getAttribute('data-saldo');
      if (!id) return;
      if (act === 'view') return openView(id);
      if (act === 'pay') return openPay(id, (saldo ? (parseFloat(saldo).toFixed(2) + ' Bs') : '—'));
      if (act === 'history') return openHistory(id);
    });

    // Pay modal: internacional + adjuntos
    var pmet = q('pay_metodo');
    if (pmet) pmet.addEventListener('change', function () {
      toggleInternational('pay_metodo', 'pay_int_wrap', 'pay_monto_usd', 'pay_tasa');
    });
    var pusd = q('pay_monto_usd');
    if (pusd) pusd.addEventListener('input', function () { calcInternational('pay_monto_usd', 'pay_tasa', 'pay_monto_bs'); });
    var ptasa = q('pay_tasa');
    if (ptasa) ptasa.addEventListener('input', function () { calcInternational('pay_monto_usd', 'pay_tasa', 'pay_monto_bs'); });

    var sm = q('pay_select_media');
    if (sm) sm.addEventListener('click', function (e) {
      e.preventDefault();
      openMediaPicker('pay_adjuntos_ids', 'pay_adjuntos_list');
    });
    var cm = q('pay_clear_media');
    if (cm) cm.addEventListener('click', function (e) {
      e.preventDefault();
      setAdjuntosIds('pay_adjuntos_ids', 'pay_adjuntos_list', []);
    });

    var ps = q('pay_save');
    if (ps) ps.addEventListener('click', function () {
      var id = (q('pay_id') || {}).value;
      var metodo_pago = (q('pay_metodo') || {}).value;
      var fecha_pago = (q('pay_fecha') || {}).value;
      var monto_bs = (q('pay_monto_bs') || {}).value || '0';
      var comprobante = (q('pay_comprobante') || {}).value || '';
      var fecha_comprobante = (q('pay_fecha_comprobante') || {}).value || '';
      var adjuntos_ids = (q('pay_adjuntos_ids') || {}).value || '';
      var monto_usd = (q('pay_monto_usd') || {}).value || '0';
      var tasa_cambio = (q('pay_tasa') || {}).value || '0';

      if (!fecha_pago) return alert('Selecciona fecha de pago.');

      post('cmb_cashflow_pay', {
        id: id,
        metodo_pago: metodo_pago,
        fecha_pago: fecha_pago,
        monto_bs: monto_bs,
        comprobante: comprobante,
        fecha_comprobante: fecha_comprobante,
        adjuntos_ids: adjuntos_ids,
        monto_usd: monto_usd,
        tasa_cambio: tasa_cambio
      }).done(function (r) {
        if (r && r.success) {
          closeModal('cf_modal_pay');
          refresh();
        } else {
          alert((r && r.data) ? r.data : 'Error al registrar pago');
        }
      }).fail(function (x) {
        alert('Fallo AJAX pay: ' + (x.responseText || ''));
      });
    });

    bindModalClose();
    refresh();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

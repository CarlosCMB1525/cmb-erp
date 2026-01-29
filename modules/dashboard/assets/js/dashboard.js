/* global window, document */
(function () {
  'use strict';

  var v = window.cmbDashboardVars || {};
  var ajaxurl = v.ajaxurl || '';
  var nonce = v.nonce || '';
  var action = v.action || 'cmb_dashboard_filter';
  var DEFAULTS = v.defaults || { q_quick:'', q_adv:'', inicio:'', fin:'', categoria:'TODAS', doc_tipo:'TODOS', pago_estado:'TODOS' };

  function byId(id){ return document.getElementById(id); }

  var root = byId('cmb_dashboard_root');
  if (!root || !ajaxurl) return;

  var msgBox = byId('cmb_dash_messages');
  var quick = byId('cmb_dash_quick');
  var tbody = byId('cmb_dash_tbody');
  var openModalBtn = byId('cmb_dash_open_filters');
  var clearAllBtn = byId('cmb_dash_clear_all');
  var modal = byId('cmb_dashboard_modal');
  var modalClose = byId('cmb_dash_modal_close');
  var modalApply = byId('cmb_dash_modal_apply');
  var modalClear = byId('cmb_dash_modal_clear');
  var adv = byId('cmb_dash_adv');
  var inicio = byId('cmb_dash_inicio');
  var fin = byId('cmb_dash_fin');
  var cat = byId('cmb_dash_categoria');
  var doc = byId('cmb_dash_doc_tipo');
  var pay = byId('cmb_dash_pago_estado');

  var m = {
    total_facturado: byId('m_total_facturado'),
    total_recibos: byId('m_total_recibos'),
    total_general: byId('m_total_general'),
    cobrado: byId('m_cobrado'),
    pendiente: byId('m_pendiente'),
    utilidad: byId('m_utilidad'),
    cot_sin_v: byId('m_cot_sin_v'),
    cot_con_v: byId('m_cot_con_v'),
    cf_eg: byId('m_cashflow_eg'),
    cf_in: byId('m_cashflow_in')
  };

  var state = Object.assign({}, DEFAULTS);
  var ajaxTimer = null;

  function showMsg(type, text){
    if (!msgBox) return;
    msgBox.className = 'cmb-erp-text-muted';
    msgBox.style.color = (type==='error') ? '#ef4444' : (type==='warn') ? '#f59e0b' : '#10b981';
    msgBox.textContent = String(text || '');
    window.setTimeout(function(){ msgBox.textContent=''; }, 2200);
  }

  function openModal(){
    if (!modal) return;
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden','false');
    window.setTimeout(function(){ if (adv) adv.focus(); }, 50);
  }

  function closeModal(){
    if (!modal) return;
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden','true');
  }

  function validateDates(d1, d2){
    if ((d1 && !d2) || (!d1 && d2)) { showMsg('warn','Selecciona ambas fechas (Desde/Hasta).'); return false; }
    if (d1 && d2 && new Date(d1) > new Date(d2)) { showMsg('warn','La fecha Desde no puede ser mayor que Hasta.'); return false; }
    return true;
  }

  function applyQuickClientFilter(){
    if (!tbody || !quick) return;
    var q = String(quick.value || '').trim().toLowerCase();
    tbody.querySelectorAll('tr').forEach(function(tr){
      var hay = String(tr.dataset.cmbDashQuick || '').toLowerCase();
      tr.style.display = (q === '' || hay.indexOf(q) !== -1) ? '' : 'none';
    });
  }

  function apiPost(payload){
    payload = payload || {};
    payload.action = action;
    payload.nonce = nonce;

    var body = new URLSearchParams();
    Object.keys(payload).forEach(function(k){ body.append(k, payload[k]); });

    return fetch(ajaxurl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function(r){
      return r.text().then(function(txt){
        var json;
        try { json = JSON.parse(txt); } catch(e){
          var err = new Error('Respuesta no-JSON');
          err._body = txt;
          throw err;
        }
        if (!r.ok) {
          var err2 = new Error('HTTP '+r.status);
          err2._json = json;
          throw err2;
        }
        return json;
      });
    });
  }

  function setMetric(el, val){
    if (!el) return;
    el.textContent = (String(val || '0.00')) + ' Bs';
  }

  function runAjaxRefresh(){
    window.clearTimeout(ajaxTimer);
    ajaxTimer = window.setTimeout(function(){
      apiPost({
        inicio: state.inicio,
        fin: state.fin,
        categoria: state.categoria,
        doc_tipo: state.doc_tipo,
        pago_estado: state.pago_estado,
        q_quick: state.q_quick,
        q_adv: state.q_adv
      }).then(function(r){
        if (r && r.success) {
          var mt = (r.data && r.data.metrics) ? r.data.metrics : {};
          setMetric(m.total_facturado, mt.total_facturado);
          setMetric(m.total_recibos, mt.total_recibos);
          setMetric(m.total_general, mt.total_general);
          setMetric(m.cobrado, mt.cobrado);
          setMetric(m.pendiente, mt.pendiente);
          setMetric(m.utilidad, mt.utilidad);
          setMetric(m.cot_sin_v, mt.cotizado_sin_v);
          setMetric(m.cot_con_v, mt.cotizado_con_v);
          setMetric(m.cf_eg, mt.cashflow_egresos);
          setMetric(m.cf_in, mt.cashflow_ingresos);
          if (tbody) tbody.innerHTML = (r.data && r.data.tbody) ? r.data.tbody : '';
          applyQuickClientFilter();
        } else {
          showMsg('error', 'Error: ' + ((r && r.data) ? r.data : 'desconocido'));
        }
      }).catch(function(e){
        console.error(e);
        showMsg('error', 'Fallo de conexión.');
      });
    }, 50);
  }

  if (quick) {
    quick.addEventListener('input', function(){
      // Filtrado visual + refresco
      applyQuickClientFilter();
      state.q_quick = String(quick.value || '').trim();
      runAjaxRefresh();
    });
    quick.addEventListener('keydown', function(e){
      if (e.key === 'Enter') { e.preventDefault(); state.q_quick = String(quick.value || '').trim(); runAjaxRefresh(); }
    });
  }

  if (openModalBtn) openModalBtn.addEventListener('click', openModal);

  if (clearAllBtn) clearAllBtn.addEventListener('click', function(){
    state = Object.assign({}, DEFAULTS);
    if (quick) quick.value='';
    if (adv) adv.value='';
    if (inicio) inicio.value = DEFAULTS.inicio;
    if (fin) fin.value = DEFAULTS.fin;
    if (cat) cat.value = DEFAULTS.categoria;
    if (doc) doc.value = DEFAULTS.doc_tipo;
    if (pay) pay.value = DEFAULTS.pago_estado;
    runAjaxRefresh();
  });

  if (modalClose) modalClose.addEventListener('click', closeModal);
  if (modal) modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });

  if (modalApply) modalApply.addEventListener('click', function(){
    var d1 = String(inicio ? inicio.value : '').trim();
    var d2 = String(fin ? fin.value : '').trim();
    if (!validateDates(d1,d2)) return;
    state.inicio = d1;
    state.fin = d2;
    state.categoria = String(cat ? cat.value : 'TODAS').trim();
    state.doc_tipo = String(doc ? doc.value : 'TODOS').trim();
    state.pago_estado = String(pay ? pay.value : 'TODOS').trim();
    state.q_adv = String(adv ? adv.value : '').trim();
    runAjaxRefresh();
    closeModal();
  });

  if (modalClear) modalClear.addEventListener('click', function(){
    if (adv) adv.value='';
    if (inicio) inicio.value = DEFAULTS.inicio;
    if (fin) fin.value = DEFAULTS.fin;
    if (cat) cat.value = DEFAULTS.categoria;
    if (doc) doc.value = DEFAULTS.doc_tipo;
    if (pay) pay.value = DEFAULTS.pago_estado;
    state.q_adv = '';
    state.inicio = DEFAULTS.inicio;
    state.fin = DEFAULTS.fin;
    state.categoria = DEFAULTS.categoria;
    state.doc_tipo = DEFAULTS.doc_tipo;
    state.pago_estado = DEFAULTS.pago_estado;
    runAjaxRefresh();
  });

  // ✅ CARGA AUTOMÁTICA AL ENTRAR / REFRESCAR
  // Siempre hacemos un refresh inicial para que la tabla cargue sin escribir en el buscador.
  window.setTimeout(function(){
    runAjaxRefresh();
  }, 80);
})();

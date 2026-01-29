/* global window, document */
(function(){
  'use strict';

  // Variables legacy
  var cv = window.crm_vars || {};
  var ajaxurl = cv.ajaxurl || '';
  var nonce = cv.nonce || '';

  // Helpers
  function q(id){ return document.getElementById(id); }

  window.cerrarModal = function(id){ var m=q(id); if(m) m.style.display='none'; };

  window.abrirP = function(id, saldo, cli){
    q('m_id').value = id;
    q('m_mnt').value = Number(saldo).toFixed(2);
    q('m_cli').innerText = 'Registrar Pago: ' + (cli || '');
    q('m_sub').innerText = 'Saldo Pendiente: ' + Number(saldo).toFixed(2) + ' Bs';
    q('m_fid').value = '';
    q('m_fname').innerText = '';
    q('m_ref').value = '';
    q('m_fec').value = (new Date()).toISOString().split('T')[0];
    q('modalP').style.display = 'flex';
  };

  // Normalizador para búsquedas
  function rpNorm(str){
    return (str || '').toString()
      .replace(/\u00A0/g, ' ')
      .normalize('NFKD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .replace(/\s+/g, ' ')
      .trim();
  }

  var rpFilterTimer = null;
  function rpScheduleFilters(){
    clearTimeout(rpFilterTimer);
    rpFilterTimer = setTimeout(rpApplyFilters, 120);
  }

  function rpApplyFilters(){
    var sEl = q('rp_search');
    var dEl = q('rp_days');
    var qtxt = rpNorm(sEl ? sEl.value : '');
    var dias = parseInt(dEl ? (dEl.value || '0') : '0', 10);
    var tol = 0.05;
    var shown = 0;

    var rows = document.querySelectorAll('#rp_table tbody .rp-row');
    rows.forEach(function(tr){
      var search = rpNorm(tr.dataset.search || '');
      var matchSearch = (qtxt === '') ? true : search.includes(qtxt);

      var saldo = parseFloat(tr.dataset.saldo || '0');
      var pagado = parseFloat(tr.dataset.pagado || '0');
      var total = parseFloat(tr.dataset.total || '0');
      var days = tr.dataset.days !== undefined ? (parseInt(tr.dataset.days || '0',10) || 0) : 0;

      var debe = (total > tol) && (saldo > tol) && (pagado < (total - tol));
      var cumpleAtraso = (dias > 0) ? (days >= dias) : true;

      var show = matchSearch;
      if (dias > 0) show = show && debe && cumpleAtraso;

      tr.classList.toggle('rp-overdue', (dias > 0) && debe && cumpleAtraso);
      tr.style.display = show ? '' : 'none';
      if (show) shown++;
    });

    var no = q('rp_no_results');
    if (no) no.style.display = (shown === 0) ? '' : 'none';
  }

  // Media picker
  
 // =========================
 // Filtros avanzados (modal) — independientes del buscador
 // =========================
 function openFiltersModal(){ var m = q('modalFilters'); if (m) m.style.display = 'flex'; }
 function closeFiltersModal(){ var m = q('modalFilters'); if (m) m.style.display = 'none'; }
 function getFilterPayload(){
   return {
     action: 'cmb_pagos_list',
     nonce: nonce,
     ff_from: (q('rp_ff_from') ? q('rp_ff_from').value : ''),
     ff_to: (q('rp_ff_to') ? q('rp_ff_to').value : ''),
     fp_from: (q('rp_fp_from') ? q('rp_fp_from').value : ''),
     fp_to: (q('rp_fp_to') ? q('rp_fp_to').value : ''),
     order_by: (q('rp_order_by') ? q('rp_order_by').value : 'venta_id'),
     order_dir: (q('rp_order_dir') ? q('rp_order_dir').value : 'desc')
   };
 }
 function applyAdvancedFilters(){
   var tb = q('rp_tbody');
   if (!tb) return;
   // indicador
   tb.innerHTML = '<tr><td colspan="11" class="cmb-crm-text-center cmb-crm-text-muted" style="padding:14px;">Cargando…</td></tr>';
   post(getFilterPayload(), function(err, r){
     if (err) {
       alert('Fallo AJAX');
       tb.innerHTML = '';
       return;
     }
     if (r && r.success && r.data && typeof r.data.tbody === 'string') {
       tb.innerHTML = r.data.tbody;
       closeFiltersModal();
       // re-aplicar buscador client-side
       rpApplyFilters();
     } else {
       alert((r && r.data) ? r.data : 'Error al filtrar');
     }
   });
 }
 function resetAdvancedFilters(){
   ['rp_ff_from','rp_ff_to','rp_fp_from','rp_fp_to'].forEach(function(id){ var el=q(id); if(el) el.value=''; });
   if (q('rp_order_by')) q('rp_order_by').value = 'venta_id';
   if (q('rp_order_dir')) q('rp_order_dir').value = 'desc';
 }

function openMediaPicker(targetIdField, targetNameField){
    if (!(window.crm_vars && window.crm_vars.can_upload)){
      alert('Tu usuario no tiene permisos para usar la biblioteca de medios.');
      return;
    }
    if (typeof wp === 'undefined' || !wp.media){
      alert('La biblioteca de medios no está disponible en esta página.');
      return;
    }
    var frame = wp.media({
      frame: 'select',
      title: 'Seleccionar comprobante',
      button: { text: 'Usar este archivo' },
      multiple: false,
      library: { type: '' }
    });
    frame.on('select', function(){
      var a = frame.state().get('selection').first().toJSON();
      q(targetIdField).value = a.id;
      q(targetNameField).innerText = '✅ Archivo: ' + a.filename;
    });
    frame.open();
  }

  // Exponer para onclick legacy
  window.abrirComprobantes = function(cliente, urls){
 var modal = q('modalDocs');
 var title = q('docs_title');
 var list = q('docs_list');
 if (!modal || !title || !list) return;
 title.innerText = '📎 Comprobantes - ' + (cliente || '');
 list.innerHTML = '';
 try{ if (typeof urls === 'string') urls = JSON.parse(urls); }catch(e){}
 urls = urls || [];
 if (!urls.length){
   list.innerHTML = '<div class=\"cmb-crm-text-muted\">No hay comprobantes adjuntos.</div>';
   modal.style.display = 'flex';
   return;
 }
 urls.forEach(function(u){
   var safe = String(u || '');
   var btn = document.createElement('button');
   btn.type='button';
   btn.className='cmb-crm-btn cmb-crm-btn-3 cmb-crm-btn-block';
   btn.textContent = safe;
   btn.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); window.open(safe,'_blank','noopener,noreferrer'); });
   list.appendChild(btn);
 });
 modal.style.display='flex';
 };

  // AJAX helpers
  function post(data, cb){
    var fd = new URLSearchParams();
    Object.keys(data).forEach(function(k){ fd.append(k, data[k]); });
    fetch(ajaxurl, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: fd.toString() })
      .then(function(r){ return r.json(); })
      .then(function(j){ cb(null, j); })
      .catch(function(err){ cb(err); });
  }

  // Guardar pago
  function guardarP(){
    var btn = q('btn_save');
    var monto = parseFloat(q('m_mnt').value);
    var ref = (q('m_ref').value || '').trim();
    var fec = (q('m_fec').value || '').trim();
    if (!monto || monto <= 0) return alert('Monto inválido');
    if (!ref) return alert('Referencia obligatoria');
    if (!fec) return alert('Selecciona la fecha de pago');

    btn.disabled = true; btn.innerText = 'PROCESANDO...';

    post({
      action: 'crm_pago_v83',
      nonce: nonce,
      v_id: q('m_id').value,
      monto: monto,
      metodo: q('m_met').value,
      ref: ref,
      fecha_pago: fec,
      adjunto_id: q('m_fid').value
    }, function(err, r){
      btn.disabled = false; btn.innerText = 'CONFIRMAR';
      if (err) return alert('Fallo AJAX');
      if (r && r.success) location.reload();
      else alert((r && r.data) ? r.data : 'Error al guardar');
    });
  }
  window.guardarP = guardarP;

  // Borrar pago
  window.borrarRegistro = function(id){
    if (!confirm('¿Eliminar este pago?')) return;
    post({ action:'crm_borrar_pago_v83', nonce: nonce, id: id }, function(err, r){
      if (err) return alert('Fallo AJAX');
      if (r && r.success) location.reload();
      else alert((r && r.data) ? r.data : 'Error al eliminar');
    });
  };

  // Editar pago
  window.abrirEditarPago = function(id, monto, metodo, ref){
    q('ep_id').value = id;
    q('ep_mnt').value = Number(monto).toFixed(2);
    q('ep_ref').value = ref || '';
    q('ep_met').value = metodo || 'TRANSFERENCIA';
    q('ep_fid').value = '';
    q('ep_fname').innerText = '';
    q('modalEditPago').style.display = 'flex';
  };

  function guardarEdicionPago(){
    var btn = q('ep_save');
    var id = q('ep_id').value;
    var monto = parseFloat(q('ep_mnt').value);
    var ref = (q('ep_ref').value || '').trim();
    var metodo = q('ep_met').value;
    var adj = q('ep_fid').value;
    if (!monto || monto <= 0) return alert('Monto inválido');
    if (!ref) return alert('Referencia obligatoria');

    btn.disabled = true; btn.innerText = 'GUARDANDO...';
    post({ action:'crm_editar_pago_v83', nonce: nonce, id:id, monto:monto, metodo:metodo, ref:ref, adjunto_id:adj }, function(err, r){
      btn.disabled = false; btn.innerText = 'GUARDAR CAMBIOS';
      if (err) return alert('Fallo AJAX');
      if (r && r.success) location.reload();
      else alert((r && r.data) ? r.data : 'Error al guardar');
    });
  }
  window.guardarEdicionPago = guardarEdicionPago;

  // Bind media buttons
  function bindMedia(){
    var mFile = q('m_file');
    var eFile = q('ep_file');
    if (mFile) mFile.addEventListener('click', function(e){ e.preventDefault(); openMediaPicker('m_fid','m_fname'); });
    if (eFile) eFile.addEventListener('click', function(e){ e.preventDefault(); openMediaPicker('ep_fid','ep_fname'); });
  }

  // Close modals on outside click
  document.addEventListener('click', function(e){
    ['modalP','modalDocs','modalEditPago'].forEach(function(mid){
      var m = q(mid);
      if (m && e.target === m) m.style.display = 'none';
    });
  });

  // Bind buttons
  function bind(){
    var s = q('rp_search');
    var d = q('rp_days');
    if (s) ['input','keyup','change','paste','search','compositionend'].forEach(function(ev){ s.addEventListener(ev, rpScheduleFilters, {passive:true}); });
    if (d) ['input','keyup','change','paste','compositionend'].forEach(function(ev){ d.addEventListener(ev, rpScheduleFilters, {passive:true}); });

    var mSearch = q('rp_m_search');
    var mClear = q('rp_m_clear');
    function runNow(){ try{ if(s) s.blur(); }catch(e){} requestAnimationFrame(rpApplyFilters); }
    function clearNow(){ if(s) s.value=''; if(d) d.value='0'; runNow(); }
    if (mSearch) ['pointerup','click','touchend'].forEach(function(ev){ mSearch.addEventListener(ev, function(e){ e.preventDefault(); e.stopPropagation(); runNow(); }, {passive:false}); });
    if (mClear) ['pointerup','click','touchend'].forEach(function(ev){ mClear.addEventListener(ev, function(e){ e.preventDefault(); e.stopPropagation(); clearNow(); }, {passive:false}); });

    var btnSave = q('btn_save');
    if (btnSave) btnSave.addEventListener('click', function(){ guardarP(); });
    var epSave = q('ep_save');
    if (epSave) epSave.addEventListener('click', function(){ guardarEdicionPago(); });

    bindMedia();

 // Filtros avanzados (modal)
 var btnFilters = q('rp_btn_filters');
 var btnFApply = q('rp_filters_apply');
 var btnFReset = q('rp_filters_reset');
 var btnFClose = q('rp_filters_close');
 if (btnFilters) btnFilters.addEventListener('click', function(e){ e.preventDefault(); openFiltersModal(); });
 if (btnFApply) btnFApply.addEventListener('click', function(e){ e.preventDefault(); applyAdvancedFilters(); });
 if (btnFReset) btnFReset.addEventListener('click', function(e){ e.preventDefault(); resetAdvancedFilters(); });
 if (btnFClose) btnFClose.addEventListener('click', function(e){ e.preventDefault(); closeFiltersModal(); });
 // click fuera cierra
 var mf = q('modalFilters');
 if (mf) mf.addEventListener('click', function(e){ if (e.target === mf) closeFiltersModal(); });

 rpApplyFilters();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();

})();

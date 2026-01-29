/* global window, document */
(function(){
  'use strict';
  var $ = window.jQuery;
  var cfg = function(){ return window.CMBServices || {}; };
  function post(action, data){
    var v = cfg();
    data = data || {};
    data.action = action;
    data.nonce = v.nonce;
    return $.ajax({ url: v.ajaxurl, method:'POST', dataType:'json', data:data });
  }
  function msg(text, ok){
    var el = document.getElementById('svc_msg');
    if(!el) return;
    el.style.color = ok === false ? '#ef4444' : '#10b981';
    el.textContent = text;
    setTimeout(function(){ el.textContent=''; }, 2500);
  }
  function resetForm(){
    document.getElementById('svc_id').value = 0;
    document.getElementById('svc_nombre').value = '';
    document.getElementById('svc_codigo').value = '';
    document.getElementById('svc_precio').value = '';
    document.getElementById('svc_tipo').value = 'UNICO';
    document.getElementById('svc_detalle').value = '';
  }
  function setCount(n){
    var el = document.getElementById('svc_count');
    if(!el) return;
    el.textContent = n ? ('Resultados: ' + n) : '';
  }
  function renderTbody(html, count){
    var tb = document.getElementById('svc_tbody');
    if(tb) tb.innerHTML = html;
    setCount(count || 0);
    var no = document.getElementById('svc_no');
    if(no) no.style.display = (count === 0) ? '' : 'none';
  }
  function doSearch(){
    var q = (document.getElementById('svc_search').value || '').trim();
    post('cmb_services_search', {q:q})
      .done(function(r){
        if(r && r.success){
          renderTbody(r.data.tbody, r.data.count);
        } else {
          msg((r && r.data) ? r.data : 'Error al buscar', false);
        }
      })
      .fail(function(xhr){ msg('Fallo AJAX: ' + (xhr.responseText || 'sin respuesta'), false); });
  }
  function save(){
    var nombre = (document.getElementById('svc_nombre').value || '').trim();
    var codigo = (document.getElementById('svc_codigo').value || '').trim();
    var precio = parseFloat(document.getElementById('svc_precio').value || '0');
    if(!nombre) return msg('Nombre obligatorio.', false);
    if(!codigo) return msg('Código obligatorio.', false);
    if(!precio || precio <= 0) return msg('Precio debe ser mayor a 0.', false);
    post('cmb_services_save', {
      id: document.getElementById('svc_id').value,
      nombre_servicio: nombre,
      codigo_unico: codigo,
      monto_unitario: precio,
      tipo_servicio: document.getElementById('svc_tipo').value,
      detalle_tecnico: document.getElementById('svc_detalle').value
    })
    .done(function(r){
      if(r && r.success){
        msg((r.data && r.data.msg) ? r.data.msg : 'Guardado');
        resetForm();
        doSearch();
      } else {
        msg((r && r.data) ? r.data : 'Error', false);
      }
    })
    .fail(function(xhr){ msg('Fallo AJAX: ' + (xhr.responseText || 'sin respuesta'), false); });
  }
  function loadIntoForm(id){
    post('cmb_services_get', {id:id})
      .done(function(r){
        if(!(r && r.success)) return msg((r && r.data) ? r.data : 'Error', false);
        var s = r.data.row;
        document.getElementById('svc_id').value = s.id;
        document.getElementById('svc_nombre').value = s.nombre_servicio || '';
        document.getElementById('svc_codigo').value = s.codigo_unico || '';
        document.getElementById('svc_precio').value = s.monto_unitario || '';
        document.getElementById('svc_tipo').value = s.tipo_servicio || 'UNICO';
        document.getElementById('svc_detalle').value = s.detalle_tecnico || '';
        window.scrollTo(0,0);
        msg('Editando servicio #' + s.id);
      })
      .fail(function(xhr){ msg('Fallo AJAX: ' + (xhr.responseText || 'sin respuesta'), false); });
  }
  function del(id){
    if(!confirm('¿Eliminar servicio #' + id + '?')) return;
    post('cmb_services_delete', {id:id})
      .done(function(r){
        if(r && r.success){
          msg((r.data && r.data.msg) ? r.data.msg : 'Eliminado');
          doSearch();
        } else {
          msg((r && r.data) ? r.data : 'Error', false);
        }
      })
      .fail(function(xhr){ msg('Fallo AJAX: ' + (xhr.responseText || 'sin respuesta'), false); });
  }
  function init(){
    if(!$) return;
    var t = null;
    document.getElementById('svc_search').addEventListener('input', function(){
      clearTimeout(t);
      t = setTimeout(doSearch, 220);
    });
    document.getElementById('svc_btn_save').addEventListener('click', save);
    document.getElementById('svc_btn_cancel').addEventListener('click', resetForm);
    document.addEventListener('click', function(e){
      var btn = e.target.closest('[data-svc-action]');
      if(!btn) return;
      var act = btn.getAttribute('data-svc-action');
      var id = parseInt(btn.getAttribute('data-id') || '0', 10);
      if(!id) return;
      if(act === 'edit') return loadIntoForm(id);
      if(act === 'delete') return del(id);
    });
    setCount(document.querySelectorAll('#svc_tbody tr.svc-row').length);
  }
  if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

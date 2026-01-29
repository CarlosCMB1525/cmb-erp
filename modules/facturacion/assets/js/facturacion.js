/* global window, document */
(function($){
  'use strict';

  var v = window.cmbInvoicingVars || {};
  var ajaxurl = v.ajaxurl || '';
  var nonce = v.nonce || '';
  var A = v.actions || {};

  function filterTable(val){
    val = (val || '').toLowerCase();
    document.querySelectorAll('#v17_table tbody tr').forEach(function(tr){
      tr.style.display = tr.innerText.toLowerCase().includes(val) ? '' : 'none';
    });
  }

  function checkTipo(){
    var tipoEl = document.getElementById('v17_tipo');
    var nro = document.getElementById('v17_nro');
    if(!tipoEl || !nro) return;

    if(tipoEl.value === 'Recibo'){
      nro.value = 'SIN FACTURA';
      nro.readOnly = true;
      nro.style.background = '#f8fafc';
    } else {
      nro.value = '';
      nro.readOnly = false;
      nro.style.background = '#fff';
    }
  }

  function openModal(id, mon, cli){
    document.getElementById('v17_v_id').value = id;
    document.getElementById('v17_v_mon').value = mon;
    document.getElementById('v17_m_cli').innerText = 'Asignar Documento a: ' + cli;
    document.getElementById('v17_m_sub').innerText = 'Monto: ' + Number(mon).toFixed(2) + ' Bs';

    var modal = document.getElementById('v17_mod');
    if(modal){
      modal.style.display = 'flex';
      modal.setAttribute('aria-hidden','false');
    }
    checkTipo();
  }

  function closeModal(){
    var modal = document.getElementById('v17_mod');
    if(modal){
      modal.style.display = 'none';
      modal.setAttribute('aria-hidden','true');
    }
  }

  function save(){
    var btn = document.getElementById('v17_btn_save');
    if(!btn) return;

    var tipo = document.getElementById('v17_tipo').value;
    var nro = document.getElementById('v17_nro').value;

    btn.disabled = true;
    btn.innerText = 'PROCESANDO...';

    $.ajax({
      url: ajaxurl,
      method: 'POST',
      dataType: 'json',
      data: {
        action: A.save,
        nonce: nonce,
        v_id: document.getElementById('v17_v_id').value,
        tipo: tipo,
        nro: nro,
        mon: document.getElementById('v17_v_mon').value,
        fec: document.getElementById('v17_fec').value
      }
    }).done(function(r){
      if(r && r.success){
        window.location.reload();
      } else {
        alert((r && r.data) ? r.data : 'Error: respuesta sin data (backend).');
        btn.disabled = false;
        btn.innerText = 'CONFIRMAR';
      }
    }).fail(function(xhr){
      alert('Fallo AJAX. Respuesta del servidor:\\n\\n' + (xhr.responseText || 'Sin responseText'));
      btn.disabled = false;
      btn.innerText = 'CONFIRMAR';
    });
  }

  function borrar(id){
    if(!confirm('è¢ƒEliminar este documento?')) return;

    $.ajax({
      url: ajaxurl,
      method: 'POST',
      dataType: 'json',
      data: { action: A.delete, nonce: nonce, f_id: id }
    }).done(function(r){
      if(r && r.success){
        window.location.reload();
      } else {
        alert((r && r.data) ? r.data : 'Error al eliminar');
      }
    }).fail(function(xhr){
      alert('Fallo AJAX:\\n\\n' + (xhr.responseText || 'Sin responseText'));
    });
  }

  $(function(){
    var search = document.getElementById('v17_search');
    if(search){
      search.addEventListener('input', function(){ filterTable(this.value); });
    }

    var tipo = document.getElementById('v17_tipo');
    if(tipo) tipo.addEventListener('change', checkTipo);

    var saveBtn = document.getElementById('v17_btn_save');
    if(saveBtn) saveBtn.addEventListener('click', save);

    var cancelBtn = document.getElementById('v17_btn_cancel');
    if(cancelBtn) cancelBtn.addEventListener('click', closeModal);

    document.addEventListener('click', function(e){
      var m = document.getElementById('v17_mod');
      if(m && e.target === m) closeModal();

      var btn = e.target.closest('[data-action]');
      if(!btn) return;

      var act = btn.getAttribute('data-action');
      if(act === 'open'){
        e.preventDefault();
        openModal(btn.getAttribute('data-id'), btn.getAttribute('data-mon'), btn.getAttribute('data-cli'));
      }
      if(act === 'delete'){
        e.preventDefault();
        borrar(btn.getAttribute('data-id'));
      }
    });
  });
})(window.jQuery);

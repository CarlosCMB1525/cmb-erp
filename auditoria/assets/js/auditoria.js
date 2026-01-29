/* global window, document, FileReader */
(function($){
  'use strict';

  // =========================
  // Debug toggle
  // =========================
  var AUD_DEBUG = false; // Cambia a true cuando quieras ver el panel Debug

  // Variables legacy (como auditoria.php original)
  var cv = window.crm_vars || {};
  var ajaxurl = cv.ajaxurl || '';
  var nonce = cv.nonce || '';
  var wpPrefix = cv.wp_prefix || 'wp_';

  // Actions: se inyectan desde Shortcode.php; fallback a legacy si faltan.
  var A = (window.cmbAuditVars && window.cmbAuditVars.actions) ? window.cmbAuditVars.actions : {};
  function act(key, fallback){ return (A && A[key]) ? A[key] : fallback; }

  // Estado
  var tablaActual = '';
  var paginaActual = 1;
  var excelPayload = null;

  function debug(title, obj){
    if (!AUD_DEBUG) return;
    var box = document.getElementById('aud-debug');
    var pre = document.getElementById('aud-debug-pre');
    if(!box || !pre) return;
    box.style.display = 'block';
    pre.textContent = String(title || '') + "\n" + (typeof obj === 'string' ? obj : JSON.stringify(obj, null, 2));
  }

  function setTestStatus(msg, ok){
    var el = document.getElementById('aud-test-status');
    if(!el) return;
    el.style.color = ok ? '#10b981' : '#ef4444';
    el.textContent = msg || '';
  }

  function setImportStatus(msg, ok){
    $('#aud-import-status').html('<span style="color:'+(ok?'#10b981':'#ef4444')+';font-weight:700;">'+msg+'</span>');
  }

  function cargar(tabla, pagina){
    tablaActual = tabla;
    paginaActual = pagina || 1;
    $('#aud-area').html('<div style="padding:18px;color:#64748b;">⏳ Cargando…</div>');

    var payload = {
      action: act('load','auditoria_cargar_tabla'),
      nonce: nonce,
      tabla: tabla,
      pagina: paginaActual
    };
    debug('POST -> admin-ajax.php', payload);

    $.ajax({ url: ajaxurl, method:'POST', dataType:'json', data: payload })
      .done(function(r){
        debug('Respuesta JSON', r);
        if(r && r.success){
          $('#aud-area').html(r.data && r.data.html ? r.data.html : '<div style="padding:18px;color:#ef4444;">❌ Respuesta sin html</div>');
        } else {
          // cuando fail() devuelve objeto con message
          var msg = (r && r.data) ? (r.data.message || r.data) : 'Error';
          $('#aud-area').html('<div style="padding:18px;color:#ef4444;">❌ '+ msg +'</div>');
        }
      })
      .fail(function(xhr){
        var txt = xhr.responseText || '';
        debug('Respuesta NO JSON', {status:xhr.status, statusText:xhr.statusText, responseText: txt});
        $('#aud-area').html('<div style="padding:18px;color:#ef4444;">❌ Respuesta no JSON<br><pre style="white-space:pre-wrap;">'+txt+'</pre></div>');
      });
  }

  function probarLectura(tabla){
    setTestStatus('Probando lectura…', true);
    var payload = { action: act('test','cmb_audit_test_table'), nonce: nonce, tabla: tabla };
    debug('POST TEST', payload);

    $.ajax({ url: ajaxurl, method:'POST', dataType:'json', data: payload })
      .done(function(r){
        debug('TEST JSON', r);
        if(r && r.success){
          var d = r.data || {};
          setTestStatus('✅ OK: '+(d.tabla||tabla)+' · columnas: '+d.cols+' · filas: '+d.rows+' · key: '+(d.key_col||'—'), true);
        } else {
          var msg = (r && r.data) ? (r.data.message || r.data) : 'Error';
          setTestStatus('❌ '+ msg, false);
        }
      })
      .fail(function(xhr){
        debug('TEST NO JSON', {status:xhr.status, statusText:xhr.statusText, responseText:xhr.responseText});
        setTestStatus('❌ Fallo AJAX (test).', false);
      });
  }

  // ============ UI events ============
  $(document).on('click', '#aud-load', function(){
    var t = $('#aud-sel').val();
    if(!t) return alert('Selecciona una tabla primero.');
    $('#aud-import-tabla').val(t);
    cargar(t, 1);
  });

  $(document).on('click', '#aud-test', function(){
    var t = $('#aud-sel').val();
    if(!t) return alert('Selecciona una tabla primero.');
    probarLectura(t);
  });

  $(document).on('change', '#aud-sel', function(){
    var t = $(this).val();
    if(!t) return;
    $('#aud-import-tabla').val(t);
    cargar(t, 1);
  });

  // Paginación
  $(document).on('click', '.aud-page', function(){
    var p = parseInt($(this).data('page') || 1, 10);
    if(!tablaActual) tablaActual = $('#aud-sel').val();
    if(!tablaActual) return;
    cargar(tablaActual, p);
  });

  // Buscar dentro de la tabla cargada
  $(document).on('input', '#aud-search', function(){
    var v = ($(this).val() || '').toLowerCase();
    $('#aud-table tbody tr').each(function(){
      $(this).toggle($(this).text().toLowerCase().includes(v));
    });
  });

  // Modal columnas
  $(document).on('click', '#aud-btn-cols', function(){ $('#aud-modal-cols').css('display','flex'); });
  $(document).on('click', '#aud-cols-all', function(){ $('.aud-col-check').prop('checked', true); });
  $(document).on('click', '#aud-cols-none', function(){ $('.aud-col-check').prop('checked', false); });
  $(document).on('click', '#aud-cols-apply', function(){
    $('.aud-col-check').each(function(){
      var col = parseInt($(this).data('col'), 10);
      var show = $(this).is(':checked');
      $('#aud-table tr').each(function(){
        $(this).find('th:nth-child('+col+'), td:nth-child('+col+')').toggle(show);
      });
    });
    $('#aud-modal-cols').hide();
  });
  $(document).on('click', function(e){ if($(e.target).is('#aud-modal-cols')) $('#aud-modal-cols').hide(); });

  // Editar fila
  $(document).on('click', '.aud-edit', function(){
    var id = $(this).data('id');
    var $row = $('#aud-row-' + id);
    $row.find('.view-mode').hide();
    $row.find('.edit-mode').show();
    $row.find('.aud-save').show();
    $(this).hide();
    $row.css('background','#fef3c7');
  });

  // Guardar fila
  $(document).on('click', '.aud-save', function(){
    var id = $(this).data('id');
    var tabla = $(this).data('tabla') || tablaActual || $('#aud-sel').val();
    var $row = $('#aud-row-' + id);
    var datos = {};

    $row.find('.dato-celda').each(function(){
      var campo = $(this).data('campo');
      var $ta = $(this).find('.edit-mode');
      if($ta.length) datos[campo] = $ta.val();
    });

    var payload = { action: act('save','auditoria_guardar_fila'), nonce: nonce, tabla: tabla, id: id, datos: datos };
    debug('POST SAVE', payload);

    $.ajax({ url: ajaxurl, method:'POST', dataType:'json', data: payload })
      .done(function(r){
        debug('SAVE JSON', r);
        if(r && r.success) cargar(tablaActual, paginaActual);
        else alert((r && r.data) ? (r.data.message || r.data) : 'Error al guardar');
      })
      .fail(function(xhr){
        debug('SAVE NO JSON', {status:xhr.status, responseText:xhr.responseText});
        alert('Fallo AJAX:\n\n' + (xhr.responseText || 'Sin responseText'));
      });
  });

  // Eliminar fila
  $(document).on('click', '.aud-del', function(){
    var id = $(this).data('id');
    var tabla = $(this).data('tabla') || tablaActual || $('#aud-sel').val();
    if(!confirm('¿Eliminar #' + id + '?')) return;

    var payload = { action: act('del','auditoria_eliminar_fila'), nonce: nonce, tabla: tabla, id: id };
    debug('POST DEL', payload);

    $.ajax({ url: ajaxurl, method:'POST', dataType:'json', data: payload })
      .done(function(r){
        debug('DEL JSON', r);
        if(r && r.success) cargar(tablaActual, paginaActual);
        else alert((r && r.data) ? (r.data.message || r.data) : 'Error al eliminar');
      })
      .fail(function(xhr){
        debug('DEL NO JSON', {status:xhr.status, responseText:xhr.responseText});
        alert('Fallo AJAX:\n\n' + (xhr.responseText || 'Sin responseText'));
      });
  });

  // Exportar página
  $(document).on('click', '#aud-export-page', function(){
    if(typeof XLSX === 'undefined') return alert('SheetJS no cargó.');
    var table = document.getElementById('aud-table');
    if(!table) return alert('No hay tabla cargada.');
    var wb = XLSX.utils.table_to_book(table, {sheet:'Datos'});
    var nombre = (tablaActual || 'tabla').replace(wpPrefix, '');
    XLSX.writeFile(wb, 'Auditoria_' + nombre + '_PAGINA_' + (paginaActual||1) + '.xlsx');
  });

  // Exportar TODO
  $(document).on('click', '#aud-export-all', function(){
    if(!tablaActual) tablaActual = $('#aud-sel').val();
    if(!tablaActual) return alert('Selecciona una tabla primero.');
    if(typeof XLSX === 'undefined') return alert('SheetJS no cargó.');

    var limit = prompt('¿Cuántas filas exportar? (máx 20000)', '5000');
    var lim = Math.min(20000, Math.max(1, parseInt(limit || '5000', 10)));

    var btn = $(this);
    btn.prop('disabled', true).text('EXPORTANDO...');

    var payload = { action: act('export_all','auditoria_exportar_todo'), nonce: nonce, tabla: tablaActual, limit: lim };
    debug('POST EXPORT_ALL', payload);

    $.ajax({ url: ajaxurl, method:'POST', dataType:'json', data: payload })
      .done(function(r){
        btn.prop('disabled', false).text('⬇️ Exportar TODO');
        debug('EXPORT_ALL JSON', r);
        if(!(r && r.success)) return alert((r && r.data) ? (r.data.message || r.data) : 'Error al exportar');
        var rows = (r.data && r.data.rows) ? r.data.rows : [];
        if(!rows.length) return alert('No hay filas para exportar.');
        var ws = XLSX.utils.json_to_sheet(rows);
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Datos');
        var nombre = (tablaActual || 'tabla').replace(wpPrefix, '');
        XLSX.writeFile(wb, 'Auditoria_' + nombre + '_TODO_' + rows.length + '.xlsx');
      })
      .fail(function(xhr){
        btn.prop('disabled', false).text('⬇️ Exportar TODO');
        debug('EXPORT_ALL NO JSON', {status:xhr.status, responseText:xhr.responseText});
        alert('Fallo AJAX:\n\n' + (xhr.responseText || 'Sin responseText'));
      });
  });

  // Reset ID (TRUNCATE)
  $(document).on('click', '#aud-truncate', function(){
    if(!tablaActual) tablaActual = $('#aud-sel').val();
    if(!tablaActual) return alert('Selecciona una tabla primero.');

    var nombre = tablaActual.replace(wpPrefix, '');
    if(!confirm('⚠️ ADVERTENCIA\n\nEsto VACÍA la tabla y REINICIA ID:\n' + nombre + '\n\n¿Continuar?')) return;

    var payload = { action: act('truncate','auditoria_reset_id'), nonce: nonce, tabla: tablaActual };
    debug('POST TRUNCATE', payload);

    $.ajax({ url: ajaxurl, method:'POST', dataType:'json', data: payload })
      .done(function(r){
        debug('TRUNCATE JSON', r);
        if(r && r.success) cargar(tablaActual, 1);
        else alert((r && r.data) ? (r.data.message || r.data) : 'Error al resetear');
      })
      .fail(function(xhr){
        debug('TRUNCATE NO JSON', {status:xhr.status, responseText:xhr.responseText});
        alert('Fallo AJAX:\n\n' + (xhr.responseText || 'Sin responseText'));
      });
  });

  // Importación (SheetJS)
  $(document).on('click', '#aud-drop', function(){ $('#aud-file').trigger('click'); });

  $(document).on('dragover', '#aud-drop', function(e){ e.preventDefault(); e.stopPropagation(); $('#aud-drop').css({borderColor:'#9328AC', background:'#f0f0ff'}); });
  $(document).on('dragleave', '#aud-drop', function(e){ e.preventDefault(); e.stopPropagation(); $('#aud-drop').css({borderColor:'#cbd5e1', background:'#fff'}); });
  $(document).on('drop', '#aud-drop', function(e){
    e.preventDefault(); e.stopPropagation();
    $('#aud-drop').css({borderColor:'#cbd5e1', background:'#fff'});
    var files = e.originalEvent.dataTransfer.files;
    if(files && files.length){ $('#aud-file')[0].files = files; $('#aud-file').trigger('change'); }
  });

  $(document).on('change', '#aud-file', function(e){
    var file = e.target.files[0];
    if(!file) return;
    $('#aud-file-name').text('📄 ' + file.name);
    if(typeof XLSX === 'undefined'){ setImportStatus('❌ SheetJS no está cargado.', false); excelPayload=null; return; }

    var reader = new FileReader();
    reader.onload = function(evt){
      try{
        var data = new Uint8Array(evt.target.result);
        var wb = XLSX.read(data, {type:'array'});
        var sheet = wb.Sheets[wb.SheetNames[0]];
        excelPayload = XLSX.utils.sheet_to_json(sheet);
        setImportStatus('✅ Archivo listo (' + excelPayload.length + ' filas)', true);
      }catch(err){ excelPayload=null; setImportStatus('❌ Error: ' + err.message, false); }
    };
    reader.readAsArrayBuffer(file);
  });

  $(document).on('click', '#aud-btn-import', function(){
    var t = $('#aud-import-tabla').val();
    if(!t) return alert('Selecciona tabla destino.');
    if(!excelPayload || !excelPayload.length) return alert('Selecciona un archivo válido.');

    var nombre = t.replace(wpPrefix, '');
    if(!confirm('¿Importar ' + excelPayload.length + ' filas a ' + nombre + '?')) return;

    var btn = $(this);
    btn.prop('disabled', true).text('PROCESANDO...');

    var payload = { action: act('import','auditoria_importar_json'), nonce: nonce, tabla: t, payload: excelPayload };
    debug('POST IMPORT', {action: payload.action, tabla: t, filas: excelPayload.length});

    $.ajax({ url: ajaxurl, method:'POST', dataType:'json', data: payload })
      .done(function(r){
        btn.prop('disabled', false).text('🚀 IMPORTAR DATOS');
        debug('IMPORT JSON', r);
        if(!(r && r.success)) return alert((r && r.data) ? (r.data.message || r.data) : 'Error al importar');

        var res = r.data || {};
        var msg = 'Importación completa.\n\nTotal: ' + res.total + '\nInsertados: ' + res.insertados;
        if(res.errores && res.errores.length){
          msg += '\n\nErrores:\n- ' + res.errores.slice(0,10).join('\n- ');
          if(res.errores.length > 10) msg += '\n... (' + res.errores.length + ' errores)';
        }
        alert(msg);

        // refrescar
        $('#aud-sel').val(t);
        tablaActual = t;
        cargar(t, 1);
        excelPayload = null;
        $('#aud-file').val('');
        $('#aud-file-name').text('');
        setImportStatus('✅ Listo', true);
      })
      .fail(function(xhr){
        btn.prop('disabled', false).text('🚀 IMPORTAR DATOS');
        debug('IMPORT NO JSON', {status:xhr.status, responseText:xhr.responseText});
        alert('Fallo AJAX:\n\n' + (xhr.responseText || 'Sin responseText'));
      });
  });

  // Init diagnostics (solo si debug activo)
  debug('INIT', {ajaxurl: ajaxurl, nonce: nonce, wpPrefix: wpPrefix, actions: A});

})(window.jQuery);

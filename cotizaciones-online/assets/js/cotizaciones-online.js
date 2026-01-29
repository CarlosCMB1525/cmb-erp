/* global window, document */
(function(){
  'use strict';

  var $ = window.jQuery;
  var cfg = function(){ return window.CMBQuotesOnline || {}; };

  // URL del logo (la que indicaste). Si el navegador no puede (CORS/mixed content), usamos proxy server-side.
  var LOGO_URL = 'http://interno.communitybolivia.com/crm/wp-content/uploads/2026/01/CMB-logo1.png';
  var _logoCache = null; // dataURL

  function ajaxGetQuote(id){
    var v = cfg();
    return $.ajax({
      url: v.ajaxurl,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'cmb_quotes_online_get',
        nonce: v.nonce,
        id: id
      }
    });
  }

  function ajaxGetLogoDataUrl(){
    var v = cfg();
    return $.ajax({
      url: v.ajaxurl,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'cmb_quotes_online_logo',
        nonce: v.nonce,
        url: LOGO_URL
      }
    });
  }

  function ensureJsPDF(){
    if(!(window.jspdf && window.jspdf.jsPDF)){
      throw new Error('No cargó jsPDF.');
    }
    if(!window.CMBQuotesPDFBuilder || !window.CMBQuotesPDFBuilder.buildPdfPro){
      throw new Error('No cargó el PDF builder.');
    }
  }

  function blobToDataUrl(blob){
    return new Promise(function(resolve, reject){
      var reader = new FileReader();
      reader.onload = function(){ resolve(String(reader.result || '')); };
      reader.onerror = function(){ reject(new Error('No se pudo leer el logo')); };
      reader.readAsDataURL(blob);
    });
  }

  function fetchLogoAsDataUrlBrowser(){
    // Intento directo en navegador
    return fetch(LOGO_URL, { mode: 'cors', cache: 'force-cache' })
      .then(function(res){
        if(!res.ok) throw new Error('No se pudo descargar el logo');
        return res.blob();
      })
      .then(function(blob){
        return blobToDataUrl(blob);
      });
  }

  function fetchLogoAsDataUrl(){
    if(_logoCache) return Promise.resolve(_logoCache);

    // 1) Intento navegador
    return fetchLogoAsDataUrlBrowser()
      .then(function(dataUrl){
        _logoCache = String(dataUrl || '');
        return _logoCache;
      })
      .catch(function(){
        // 2) Fallback proxy server-side
        return ajaxGetLogoDataUrl()
          .then(function(r){
            if(r && r.success && r.data && r.data.dataUrl){
              _logoCache = String(r.data.dataUrl);
              return _logoCache;
            }
            return '';
          })
          .catch(function(){ return ''; });
      });
  }

  function preloadLogoIntoBuilder(){
    return fetchLogoAsDataUrl().then(function(dataUrl){
      try {
        if(window.CMBQuotesPDFBuilder && typeof window.CMBQuotesPDFBuilder.setLogoDataUrl === 'function'){
          window.CMBQuotesPDFBuilder.setLogoDataUrl(dataUrl);
        }
      } catch(e) {}
      return dataUrl;
    });
  }

  function bindSearch(){
    var input = document.getElementById('cqo_search');
    var tbody = document.getElementById('cqo_tbody');
    var no = document.getElementById('cqo_no');
    if(!input || !tbody) return;

    var t = null;
    input.addEventListener('input', function(){
      clearTimeout(t);
      t = setTimeout(function(){
        var q = String(input.value || '').trim().toLowerCase();
        var shown = 0;
        Array.prototype.forEach.call(tbody.querySelectorAll('tr.cqo-row'), function(tr){
          var hay = String(tr.getAttribute('data-search') || '').toLowerCase();
          var ok = (q === '') ? true : hay.indexOf(q) !== -1;
          tr.style.display = ok ? '' : 'none';
          if(ok) shown++;
        });
        if(no) no.style.display = (shown === 0) ? '' : 'none';
      }, 120);
    });
  }

  function bindPdfButtons(){
    document.addEventListener('click', function(e){
      var btn = e.target.closest('.cqo-pdf');
      if(!btn) return;
      e.preventDefault();
      var id = btn.getAttribute('data-id');
      if(!id) return;

      var old = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'Generando...';

      try { ensureJsPDF(); } catch(err){
        alert(err.message || 'Falta jsPDF.');
        btn.disabled = false;
        btn.textContent = old;
        return;
      }

      preloadLogoIntoBuilder()
        .then(function(){
          return ajaxGetQuote(id);
        })
        .then(function(r){
          if(r && r.success){
            var out = window.CMBQuotesPDFBuilder.buildPdfPro(r.data);
            out.doc.save(out.filename);
          } else {
            alert((r && r.data) ? r.data : 'Error al obtener cotización');
          }
        })
        .catch(function(err){
          if(err && err.responseText){
            alert('Fallo AJAX: ' + (err.responseText || 'sin respuesta'));
          } else {
            alert((err && err.message) ? err.message : 'Error generando PDF');
          }
        })
        .finally(function(){
          btn.disabled = false;
          btn.textContent = old;
        });
    });
  }

  function init(){
    if(!$) return;
    bindSearch();
    bindPdfButtons();
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

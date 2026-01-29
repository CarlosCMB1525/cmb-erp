/* global window, document */
(function () {
  'use strict';

  var once;

  function hasJsPDF() {
    return !!(window.jspdf && window.jspdf.jsPDF) || !!window.jsPDF;
  }

  function loadScript(url) {
    return new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = url;
      s.async = true;
      s.onload = function () { resolve(url); };
      s.onerror = function () { reject(new Error('No se pudo cargar jsPDF: ' + url)); };
      document.head.appendChild(s);
    });
  }

  function getUrls() {
    var vars = window.cmbQuotesVars || {};
    // Acepta string (una URL), o array.
    var u = (vars.vendor && (vars.vendor.jspdf_urls || vars.vendor.jspdf_url)) || '';
    if (Array.isArray(u)) return u.map(String).filter(Boolean);
    if (typeof u === 'string') {
      // Permite separar por | o ; para fallback.
      if (u.indexOf('|') >= 0) return u.split('|').map(function (x) { return x.trim(); }).filter(Boolean);
      if (u.indexOf(';') >= 0) return u.split(';').map(function (x) { return x.trim(); }).filter(Boolean);
      u = u.trim();
      return u ? [u] : [];
    }
    return [];
  }

  function ensure() {
    if (hasJsPDF()) return Promise.resolve(true);
    if (once) return once;

    var urls = getUrls();
    if (!urls.length) {
      return Promise.reject(new Error('URL de jsPDF no configurada'));
    }

    // Intenta en orden (fallback)
    once = urls.reduce(function (p, url) {
      return p.catch(function () {
        return loadScript(url);
      });
    }, Promise.reject(new Error('init')))
      .then(function () {
        // Compat: algunas builds exponen window.jspdf; otras window.jsPDF
        if (window.jspdf && window.jspdf.jsPDF && !window.jsPDF) {
          window.jsPDF = window.jspdf.jsPDF;
        }
        if (!hasJsPDF()) {
          throw new Error('jsPDF se carg¨® pero no expuso el API esperado');
        }
        return true;
      });

    return once;
  }

  window.CMBJsPDFLoader = {
    ensure: ensure,
    has: hasJsPDF
  };
})();

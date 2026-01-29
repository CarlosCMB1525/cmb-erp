/* global window, document */
(function () {
  'use strict';

  var once;

  function hasJsPDF() {
    return !!(window.jspdf && window.jspdf.jsPDF) || !!window.jsPDF;
  }

  function normalizeUrls(u) {
    if (Array.isArray(u)) return u.map(String).filter(Boolean);
    if (typeof u === 'string') {
      u = u.trim();
      if (!u) return [];
      if (u.indexOf('
') >= 0) return u.split('
').map(function (x) { return x.trim(); }).filter(Boolean);
      if (u.indexOf(';') >= 0) return u.split(';').map(function (x) { return x.trim(); }).filter(Boolean);
      return [u];
    }
    return [];
  }

  function getUrls() {
    // Igual que Cotizaciones: leer URLs desde vars localizadas.
    var v = window.cmbReportsVars || {};
    var u = (v.vendor && (v.vendor.jspdf_urls || v.vendor.jspdf_url)) || '';
    return normalizeUrls(u);
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

  function ensure() {
    // Si ya existe loader de Cotizaciones, usarlo.
    if (window.CMBJsPDFLoader && typeof window.CMBJsPDFLoader.ensure === 'function') {
      return window.CMBJsPDFLoader.ensure();
    }

    if (hasJsPDF()) return Promise.resolve(true);
    if (once) return once;

    var urls = getUrls();
    if (!urls.length) return Promise.reject(new Error('URL de jsPDF no configurada'));

    once = urls.reduce(function (p, url) {
      return p.catch(function () { return loadScript(url); });
    }, Promise.reject(new Error('init')))
    .then(function () {
      // Compat: algunas builds exponen window.jspdf.jsPDF
      if (window.jspdf && window.jspdf.jsPDF && !window.jsPDF) {
        window.jsPDF = window.jspdf.jsPDF;
      }
      if (!hasJsPDF()) throw new Error('jsPDF carg¨® pero no expuso el API esperado');
      return true;
    });

    return once;
  }

  // API p¨²blica del m¨®dulo Reports
  window.CMBReportsJsPDFLoader = {
    ensure: ensure,
    has: hasJsPDF
  };
})();

/* global window */
(function () {
  'use strict';

  function hasJsPDF() {
    return !!(window.jspdf && window.jspdf.jsPDF) || !!window.jsPDF;
  }

  function getJsPDF() {
    if (window.jspdf && window.jspdf.jsPDF) return window.jspdf.jsPDF;
    if (window.jsPDF) return window.jsPDF;
    return null;
  }

  function money(n) {
    var x = parseFloat(n);
    if (!isFinite(x)) x = 0;
    return x.toFixed(2);
  }

  function pct(p) {
    if (p == null || !isFinite(p)) return '';
    return (p * 100).toFixed(1) + '%';
  }

  function safe(s) {
    // Mantener tildes/ñ tal cual (UTF-8). Guarda este archivo como UTF-8 sin BOM.
    return String(s == null ? '' : s);
  }

  function isEbitda(label) {
    label = String(label || '').toUpperCase();
    return label.indexOf('EBITDA') !== -1;
  }

  function isOpex(label) {
    label = String(label || '').toUpperCase();
    return label.indexOf('GASTOS OPERATIVOS') !== -1 || label.indexOf('(OPEX)') !== -1;
  }

  function drawSeparator(doc, x, y, w, rgb, width) {
    rgb = rgb || [31, 39, 43];
    width = width || 0.8;
    doc.setDrawColor(rgb[0], rgb[1], rgb[2]);
    doc.setLineWidth(width);
    doc.line(x, y, x + w, y);
  }

  function drawTable(doc, x, y, w, rows) {
    var col1 = x;
    var col2 = x + w * 0.68;
    var col3 = x + w * 0.84;

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(10);
    doc.text('Concepto', col1, y);
    doc.text('Monto (Bs)', col2, y);
    doc.text('%', col3, y);
    y += 8;

    drawSeparator(doc, x, y, w);
    y += 12;

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);

    var pageH = doc.internal.pageSize.getHeight();

    rows = rows || [];
    for (var i = 0; i < rows.length; i++) {
      var r = rows[i] || {};
      var next = (i + 1 < rows.length) ? (rows[i + 1] || {}) : null;

      var label = safe(r.label);
      var lines = doc.splitTextToSize(label, w * 0.64);
      var rowH = Math.max(12, lines.length * 12);

      // salto de página si hace falta
      if (y + rowH > pageH - 120) {
        doc.addPage();
        y = 60;
      }

      var highlight = !!r._highlight;

      doc.text(lines, col1, y);
      doc.text(money(r.amount), col2, y);
      doc.text(pct(r.pct_of_net), col3, y);

      if (highlight) {
        // volver a normal + separador inferior
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(10);
        y += Math.max(16, rowH + 4);
        drawSeparator(doc, x, y - 4, w);
      } else {
        y += rowH;
      }

      // ✅ Mejora solicitada: salto + línea oscura (#1F272B) entre OPEX y EBITDA
      // Detecta fila OPEX y siguiente fila EBITDA.
      if (next && isOpex(r.label) && isEbitda(next.label)) {
        // espacio visual
        y += 1;
        // línea oscura de marca: #1F272B => rgb(31,39,43)
        drawSeparator(doc, x, y, w, [31, 39, 43], 1.2);
        y += 14;
      }
    }

    return y;
  }

  function topNOpex(opexRows, n) {
    n = n || 20;
    var rows = (opexRows || []).slice();
    rows.sort(function (a, b) {
      return (parseFloat(b.amount || 0) || 0) - (parseFloat(a.amount || 0) || 0);
    });
    if (rows.length <= n) return { rows: rows, others: 0 };
    var top = rows.slice(0, n);
    var rest = rows.slice(n);
    var others = rest.reduce(function (sum, r) {
      return sum + (parseFloat(r.amount || 0) || 0);
    }, 0);
    return { rows: top, others: others };
  }

  function build(payload) {
    if (!hasJsPDF()) {
      throw new Error('jsPDF no está disponible en la página.');
    }

    var JsPDF = getJsPDF();
    if (!JsPDF) {
      throw new Error('No se pudo resolver el constructor jsPDF.');
    }

    var doc = new JsPDF({ unit: 'pt', format: 'a4' });
    var pageW = doc.internal.pageSize.getWidth();
    var pageH = doc.internal.pageSize.getHeight();
    var margin = 44;

    var title = 'Estado de Resultados (P&L) - CMB';
    var period = safe(payload && (payload.period_label || payload.period) || '');

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(16);
    doc.text(title, margin, 48);

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(11);
    doc.text('Periodo: ' + period, margin, 68);

    var y = 92;

    // Tabla principal con resaltado simple para EBITDA
    var mainRows = (payload && payload.lines ? payload.lines : []).map(function (r) {
      var rr = Object.assign({}, r);
      rr._highlight = isEbitda(rr.label);
      return rr;
    });

    y = drawTable(doc, margin, y, pageW - margin * 2, mainRows);

    // Detalle OPEX (Top 20 + Otros)
    var opex = payload && payload.opex_breakdown ? payload.opex_breakdown : [];
    if (opex && opex.length) {
      var pack = topNOpex(opex, 20);
      var rowsToPrint = pack.rows;
      if (pack.others > 0.0001) {
        rowsToPrint = rowsToPrint.concat([{ label: 'Otros OPEX', amount: pack.others, pct_of_net: null }]);
      }

      y += 18;
      if (y > pageH - 160) { doc.addPage(); y = 60; }
      doc.setFont('helvetica', 'bold');
      doc.setFontSize(12);
      doc.text('Detalle OPEX (Top 20)', margin, y);
      y += 14;
      doc.setFont('helvetica', 'normal');
      doc.setFontSize(10);
      y = drawTable(doc, margin, y, pageW - margin * 2, rowsToPrint);
    }

    // Conciliación IVA/IT
    var rec = payload && payload.reconciliation ? payload.reconciliation : null;
    if (rec) {
      y += 18;
      if (y > pageH - 160) { doc.addPage(); y = 60; }
      doc.setFont('helvetica', 'bold');
      doc.setFontSize(12);
      doc.text('Conciliación IVA/IT', margin, y);
      y += 14;

      doc.setFont('helvetica', 'normal');
      doc.setFontSize(10);
      doc.text('IVA/IT devengado: ' + money(rec.iva_devengado) + ' Bs', margin, y); y += 12;
      doc.text('IVA/IT pagado (Cashflow): ' + money(rec.iva_pagado_cashflow) + ' Bs', margin, y); y += 12;
      doc.text('Diferencia: ' + money(rec.diferencia) + ' Bs', margin, y); y += 12;

      if (rec.note) {
        var lines2 = doc.splitTextToSize(safe(rec.note), pageW - margin * 2);
        doc.text(lines2, margin, y);
      }
    }

    // Footer
    var pages = doc.getNumberOfPages();
    for (var p = 1; p <= pages; p++) {
      doc.setPage(p);
      doc.setFont('helvetica', 'normal');
      doc.setFontSize(9);
      doc.setTextColor(120);
      doc.text('Página ' + p + ' / ' + pages, pageW - margin, pageH - 24, { align: 'right' });
      doc.setTextColor(0);
    }

    return doc;
  }

  window.CMBReportsPnlPDF = { build: build };
})();

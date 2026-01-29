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

  function fmtMoney(n) {
    var x = parseFloat(n);
    if (!isFinite(x)) x = 0;
    return x.toFixed(2);
  }

  function safe(s) {
    return String(s == null ? '' : s);
  }

  function currencySymbol(m) {
    return m === 'USD' ? '$' : 'Bs';
  }

  function formatDateDDMMYYYY(iso) {
    iso = safe(iso);
    var m = iso.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!m) return iso;
    return m[3] + '.' + m[2] + '.' + m[1];
  }

  function mapValidez(v) {
    v = safe(v);
    if (v === '15') return '15 días de validez';
    if (v === '1') return '1 día de validez';
    if (v === '0.04') return 'Válido por 1 hora';
    return v;
  }

  function mapPago(v) {
    v = safe(v);
    if (v === '100_pre') return '100% por adelantado';
    if (v === '50_50') return '50% contrato / 50% entrega';
    if (v === '100_ent') return '100% a contra entrega';
    return v;
  }

  function htmlToText(str) {
    str = safe(str);
    // convertir <br> a salto de línea
    str = str.replace(/<\s*br\s*\/?>/gi, '\n');
    str = str.replace(/<\s*\/p\s*>/gi, '\n');
    str = str.replace(/<\s*p\b[^>]*>/gi, '');
    // listas: <li> -> \n- item
    str = str.replace(/<\s*li\b[^>]*>/gi, '\n- ');
    str = str.replace(/<\s*\/li\s*>/gi, '');

    // eliminar tags restantes
    str = str.replace(/<[^>]*>/g, '');

    // entidades mínimas
    str = str.replace(/&nbsp;/g, ' ')
      .replace(/&amp;/g, '&')
      .replace(/&quot;/g, '"')
      .replace(/&#39;/g, "'")
      .replace(/&lt;/g, '<')
      .replace(/&gt;/g, '>');

    // normalizar
    str = str.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    // colapsar demasiados saltos
    str = str.replace(/\n{3,}/g, '\n\n');
    return str.trim();
  }

  function wrapMultiline(doc, text, width) {
    text = htmlToText(text);
    if (!text) return [''];
    var parts = text.split('\n');
    var out = [];
    parts.forEach(function (p, idx) {
      var t = (p || '').trim();
      if (!t) {
        out.push('');
        return;
      }
      var lines = doc.splitTextToSize(t, width);
      for (var i = 0; i < lines.length; i++) out.push(lines[i]);
    });
    // quitar vacío final
    while (out.length && out[out.length - 1] === '') out.pop();
    return out.length ? out : [''];
  }

  function groupItems(groups, items) {
    var map = {};
    (groups || []).forEach(function (g) {
      map[String(g.id)] = { group: g, items: [] };
    });
    (items || []).forEach(function (it) {
      var gid = String(it.grupo_id || 0);
      if (!map[gid]) {
        map[gid] = { group: { id: parseInt(gid, 10) || 0, tipo: 'UNICO', titulo: 'Únicos', orden: 999 }, items: [] };
      }
      map[gid].items.push(it);
    });

    return Object.keys(map).map(function (k) { return map[k]; }).sort(function (a, b) {
      return (a.group.orden || 0) - (b.group.orden || 0);
    });
  }

  function build(payload) {
    if (!hasJsPDF()) throw new Error('jsPDF no está disponible en la página.');

    var JsPDF = getJsPDF();
    var doc = new JsPDF({ unit: 'pt', format: 'a4' });

    var pageW = doc.internal.pageSize.getWidth();
    var pageH = doc.internal.pageSize.getHeight();
    var margin = 40;

    var footerH = 72; // reserva para 3 bloques + paginación

    var company = payload.company || {};
    var quote = payload.quote || {};
    var client = payload.client || {};
    var contact = payload.contact || {};
    var groups = payload.groups || [];
    var items = payload.items || [];

    var moneda = safe(quote.moneda || 'BOB');
    var sym = currencySymbol(moneda);

    // Footer blocks
    var fb1 = htmlToText(company.footer_block1_html || '');
    var fb2 = htmlToText(company.footer_block2_html || '');
    var footerImgData = safe(company.footer_image_data || ''); // dataURL prefetched

    function drawHeaderPage() {
      var y = 48;
      doc.setFont('helvetica', 'bold');
      doc.setFontSize(14);
      doc.text(safe(company.nombre || 'Empresa'), margin, y);

      doc.setFont('helvetica', 'normal');
      doc.setFontSize(10);
      y += 16;

      var addr = safe(company.direccion || '');
      if (addr) { doc.text(addr, margin, y); y += 12; }

      var phone = safe(company.telefono || '');
      var email = safe(company.email || '');
      var line = [phone, email].filter(Boolean).join(' · ');
      if (line) { doc.text(line, margin, y); y += 12; }

      // Quote box
      var bxW = 240;
      var bxX = pageW - margin - bxW;
      var bxY = 48;
      doc.setDrawColor(220);
      doc.setFillColor(250, 250, 250);
      doc.roundedRect(bxX, bxY, bxW, 76, 6, 6, 'FD');

      doc.setFont('helvetica', 'bold');
      doc.setFontSize(11);
      doc.text('COTIZACIÓN', bxX + 12, bxY + 18);

      doc.setFont('helvetica', 'normal');
      doc.setFontSize(10);
      var code = safe(quote.codigo || ('#' + safe(quote.id || '')));
      doc.text('Código: ' + code, bxX + 12, bxY + 36);
      doc.text('Fecha: ' + formatDateDDMMYYYY(quote.fecha || ''), bxX + 12, bxY + 52);
      doc.text('Moneda: ' + moneda, bxX + 12, bxY + 68);

      // Client
      y = Math.max(y + 8, bxY + 92);
      doc.setFont('helvetica', 'bold');
      doc.setFontSize(11);
      doc.text('Cliente', margin, y);
      y += 14;

      doc.setFont('helvetica', 'normal');
      doc.setFontSize(10);
      doc.text(safe(client.nombre_legal || ''), margin, y); y += 12;
      var nit = safe(client.nit_id || '');
      if (nit) { doc.text('NIT: ' + nit, margin, y); y += 12; }

      var contactLine = safe(contact.nombre_contacto || '');
      var cemail = safe(contact.correo_electronico || '');
      var ctel = safe(contact.telefono_whatsapp || '');
      var pieces = [contactLine, cemail, ctel].filter(Boolean);
      if (pieces.length) { doc.text('Contacto: ' + pieces.join(' · '), margin, y); y += 14; }

      return y + 6;
    }

    function drawTableHeader(y, cols) {
      doc.setFont('helvetica', 'bold');
      doc.setFontSize(9);
      doc.setDrawColor(235);
      doc.setLineWidth(1);

      doc.line(margin, y, pageW - margin, y);
      y += 14;

      doc.text('Código', cols.codigo, y);
      doc.text('Descripción', cols.desc, y);
      doc.text('Cant.', cols.cantR, y, { align: 'right' });
      doc.text('P.Unit', cols.puR, y, { align: 'right' });
      doc.text('Subtotal', cols.subR, y, { align: 'right' });

      y += 8;
      doc.line(margin, y, pageW - margin, y);
      doc.setFont('helvetica', 'normal');
      doc.setFontSize(9);
      return y + 12;
    }

    function newPage() {
      doc.addPage();
      return drawHeaderPage();
    }

    // Start
    var y = drawHeaderPage();

    var cols = {
      codigo: margin,
      desc: margin + 72,
      descW: (pageW - margin * 2) - (72 + 50 + 70 + 80),
      cantR: pageW - margin - 200,
      puR: pageW - margin - 120,
      subR: pageW - margin
    };
    if (cols.descW < 180) cols.descW = 180;

    var grouped = groupItems(groups, items);
    var grandTotal = 0;

    grouped.forEach(function (gpack) {
      var g = gpack.group || {};
      var gTitle = safe(g.titulo || g.tipo || 'Tabla');
      var gTipo = safe(g.tipo || 'UNICO');

      if (y > pageH - footerH - 140) y = newPage();

      doc.setFont('helvetica', 'bold');
      doc.setFontSize(11);
      doc.text(gTipo + ' — ' + gTitle, margin, y);
      y += 10;

      y = drawTableHeader(y, cols);

      var sub = 0;
      (gpack.items || []).forEach(function (it) {
        var code2 = safe(it.codigo || '');
        var cant2 = parseFloat(it.cantidad || 0) || 0;
        var pu2 = parseFloat(it.precio_unitario || 0) || 0;
        var st2 = parseFloat(it.subtotal || (cant2 * pu2)) || 0;

        // descripción completa preservando formato
        var descLines = wrapMultiline(doc, it.descripcion || '', cols.descW);

        var lineH = 12;
        var rowH = Math.max(lineH, descLines.length * lineH);

        if (y + rowH > pageH - footerH - 40) {
          y = newPage();
          doc.setFont('helvetica', 'bold');
          doc.setFontSize(11);
          doc.text(gTipo + ' — ' + gTitle, margin, y);
          y += 10;
          y = drawTableHeader(y, cols);
        }

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);

        // Top aligned
        doc.text(code2, cols.codigo, y, { baseline: 'top' });
        doc.text(descLines, cols.desc, y, { baseline: 'top' });
        doc.text(fmtMoney(cant2), cols.cantR, y, { align: 'right', baseline: 'top' });
        doc.text(sym + ' ' + fmtMoney(pu2), cols.puR, y, { align: 'right', baseline: 'top' });
        doc.text(sym + ' ' + fmtMoney(st2), cols.subR, y, { align: 'right', baseline: 'top' });

        y += rowH;
        doc.setDrawColor(245);
        doc.setLineWidth(0.6);
        doc.line(margin, y, pageW - margin, y);
        y += 8;

        sub += st2;
      });

      grandTotal += sub;

      if (y > pageH - footerH - 60) y = newPage();
      doc.setFont('helvetica', 'bold');
      doc.setFontSize(10);
      doc.text('TOTAL ' + gTitle + ':', pageW - margin - 220, y);
      doc.text(sym + ' ' + fmtMoney(sub), pageW - margin, y, { align: 'right' });
      doc.setFont('helvetica', 'normal');
      y += 18;
    });

    // Totals
    if (y > pageH - footerH - 110) y = newPage();

    var total = quote.total != null ? quote.total : grandTotal;
    var subtotal = quote.subtotal != null ? quote.subtotal : grandTotal;

    doc.setDrawColor(220);
    doc.setLineWidth(0.8);
    doc.line(pageW - margin - 260, y, pageW - margin, y);
    y += 14;

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);
    doc.text('Subtotal:', pageW - margin - 260, y);
    doc.text(sym + ' ' + fmtMoney(subtotal), pageW - margin, y, { align: 'right' });
    y += 14;

    doc.setFont('helvetica', 'bold');
    doc.text('TOTAL:', pageW - margin - 260, y);
    doc.text(sym + ' ' + fmtMoney(total), pageW - margin, y, { align: 'right' });

    // Condiciones comerciales
    y += 22;
    if (y > pageH - footerH - 110) y = newPage();

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(11);
    doc.text('Condiciones comerciales', margin, y);
    y += 14;

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);

    var validez = mapValidez(quote.validez_sel || '');
    var pago = mapPago(quote.pago_sel || '');
    var obs = htmlToText(quote.condiciones || '');

    if (validez) {
      doc.setFont('helvetica', 'bold');
      doc.text('Validez de la oferta:', margin, y);
      doc.setFont('helvetica', 'normal');
      doc.text(validez, margin + 120, y);
      y += 14;
    }

    if (pago) {
      doc.setFont('helvetica', 'bold');
      doc.text('Forma de pago:', margin, y);
      doc.setFont('helvetica', 'normal');
      doc.text(pago, margin + 120, y);
      y += 14;
    }

    if (obs) {
      doc.setFont('helvetica', 'bold');
      doc.text('Observaciones:', margin, y);
      doc.setFont('helvetica', 'normal');
      y += 12;
      var obsLines = doc.splitTextToSize(obs, pageW - margin * 2);
      doc.text(obsLines, margin, y);
      y += (obsLines.length * 12) + 6;
    }

    // -------- Footer render on all pages (3 blocks + image + pagination below image)
    var totalPages = doc.getNumberOfPages();

    function drawFooterForPage(pageNum) {
      doc.setPage(pageNum);

      var footerTop = pageH - footerH + 10;
      var blockW = (pageW - margin * 2) / 3;

      doc.setDrawColor(230);
      doc.setLineWidth(0.8);
      doc.line(margin, footerTop - 10, pageW - margin, footerTop - 10);

      doc.setFont('helvetica', 'normal');
      doc.setFontSize(8);
      doc.setTextColor(70);

      // Block 1
      if (fb1) {
        var b1 = doc.splitTextToSize(fb1, blockW - 10);
        doc.text(b1, margin, footerTop, { baseline: 'top' });
      }

      // Block 2
      if (fb2) {
        var b2 = doc.splitTextToSize(fb2, blockW - 10);
        doc.text(b2, margin + blockW, footerTop, { baseline: 'top' });
      }

      // Block 3: Image + pagination
      var imgX = margin + blockW * 2;
      var imgY = footerTop;
      var imgW = blockW - 10;
      var imgH = 32;

      if (footerImgData) {
        try {
          doc.addImage(footerImgData, 'PNG', imgX, imgY, imgW, imgH);
        } catch (e) {
          // ignore if invalid
        }
      }

      // Pagination under image
      var pageLabel = 'Página ' + pageNum + ' / ' + totalPages;
      doc.setFont('helvetica', 'bold');
      doc.setFontSize(8);
      doc.text(pageLabel, imgX + imgW, imgY + imgH + 14, { align: 'right' });

      doc.setTextColor(0);
    }

    for (var p = 1; p <= totalPages; p++) {
      drawFooterForPage(p);
    }

    return doc;
  }

  window.CMBQuotesPDF = { build: build };
})();

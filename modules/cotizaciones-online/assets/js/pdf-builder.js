/* global window */
(function(){
  'use strict';

  // El logo se precarga desde cotizaciones-online.js (o via proxy) y se inyecta aquí.
  var _logoDataUrl = '';

  function setLogoDataUrl(dataUrl){
    _logoDataUrl = String(dataUrl || '');
  }

  function money(n){
    var x = Number(n || 0);
    try {
      return x.toLocaleString('es-BO', {minimumFractionDigits:2, maximumFractionDigits:2});
    } catch(e){
      return x.toFixed(2);
    }
  }

  function ddmmyyyyDot(iso){
    if(!iso) return '';
    iso = String(iso).slice(0,10);
    return iso.slice(8,10) + '.' + iso.slice(5,7) + '.' + iso.slice(0,4);
  }

  function safeFileName(s){
    s = String(s || '').trim();
    s = s.normalize ? s.normalize('NFKD').replace(/[̀-ͯ]/g,'') : s;
    s = s.replace(/[^a-zA-Z0-9\-_.\s]/g,'');
    s = s.replace(/\s+/g,' ').trim();
    return s.length ? s : 'cotizacion';
  }

  function hexToRgb(hex){
    hex = String(hex || '').replace('#','').trim();
    if(hex.length===3) hex = hex.split('').map(function(c){return c+c;}).join('');
    var bigint = parseInt(hex, 16);
    return { r:(bigint>>16)&255, g:(bigint>>8)&255, b:bigint&255 };
  }

  function lerp(a,b,t){ return a + (b-a)*t; }

  // ✅ Header: Título + Subtítulo (Código)
  function drawGradientHeader(doc, pageW, margin, subtitle){
    var col1 = '#1F272B';
    var col2 = '#504AA5';
    var steps = 120;
    var rgb1 = hexToRgb(col1);
    var rgb2 = hexToRgb(col2);
    var h = 62;
    var stripeW = pageW/steps;

    for(var i=0;i<steps;i++){
      var t=i/(steps-1);
      doc.setFillColor(
        Math.round(lerp(rgb1.r,rgb2.r,t)),
        Math.round(lerp(rgb1.g,rgb2.g,t)),
        Math.round(lerp(rgb1.b,rgb2.b,t))
      );
      doc.rect(i*stripeW, 0, stripeW+1, h, 'F');
    }

    doc.setTextColor(255,255,255);
    doc.setFont('helvetica','bold');
    doc.setFontSize(14);
    doc.text('COTIZACIÓN', margin, 34);

    doc.setFont('helvetica','normal');
    doc.setFontSize(10);
    doc.setTextColor(235,235,235);
    var sub = String(subtitle || '').trim();
    if(sub) doc.text(sub, margin, 50);
  }

  // ✅ Footer:
  // Izquierda: Community Manager Bolivia + WhatsApp + web
  // Centro: redes formateadas en 3 líneas con "|" (iconos textuales)
  // Derecha: logo
  // Abajo derecha: PÁGINA N
  function drawFooter(doc, pageW, pageH, margin, pageNum){
    var lineY = pageH - 140;

    doc.setDrawColor(226,232,240);
    doc.setLineWidth(0.6);
    doc.line(margin, lineY, pageW - margin, lineY);

    // ---- Izquierda ----
    var leftX = margin;
    var leftY = lineY + 18;

    doc.setFont('helvetica','normal');
    doc.setFontSize(9);
    doc.setTextColor(100,116,139);

    doc.text('Community Manager Bolivia', leftX, leftY);
    doc.text('WhatsApp: +591 74850178', leftX, leftY + 14);
    doc.text('https://communitybolivia.com', leftX, leftY + 28);

    // ---- Derecha (logo) ----
    var rightX = pageW - margin;
    var boxSize = 52;
    var boxX = rightX - boxSize;
    var boxY = lineY + 10;

    doc.setDrawColor(226,232,240);
    doc.setLineWidth(0.6);
    doc.roundedRect(boxX, boxY, boxSize, boxSize, 6, 6);

    try {
      if(_logoDataUrl && _logoDataUrl.indexOf('data:image') === 0){
        var imgPad = 4;
        var imgW = boxSize - (imgPad*2);
        var imgH = boxSize - (imgPad*2);
        var fmt = (_logoDataUrl.indexOf('image/jpeg') !== -1) ? 'JPEG' : 'PNG';
        doc.addImage(_logoDataUrl, fmt, boxX + imgPad, boxY + imgPad, imgW, imgH);
      } else {
        doc.setFont('helvetica','bold');
        doc.setFontSize(7);
        doc.setTextColor(148,163,184);
        doc.text('LOGO', boxX + (boxSize/2), boxY + (boxSize/2) + 2, {align:'center'});
      }
    } catch(e) {
      doc.setFont('helvetica','bold');
      doc.setFontSize(7);
      doc.setTextColor(148,163,184);
      doc.text('LOGO', boxX + (boxSize/2), boxY + (boxSize/2) + 2, {align:'center'});
    }

    // ---- Centro (con separación real) ----
    // Reservamos ancho para la izquierda y un gap, y centramos dentro del espacio restante.
    var gap = 22;
    var leftColW = 100; // ✅ tu valor

    // Área central = desde (margin + leftColW + gap) hasta (boxX - gap)
    var centerAreaLeft = margin + leftColW + gap;
    var centerAreaRight = boxX - gap;
    var centerX = (centerAreaLeft + centerAreaRight) / 2;
    var centerY = lineY + 18;

    doc.setFont('helvetica','normal');
    doc.setFontSize(8);
    doc.setTextColor(71,85,105);

    var socialsLines = [
      'FB: CommunityBolivia | IG: CommunityBolivia | IN: CommunityBolivia |',
      'TT: CommunityBolivia |',
      'YT: CommunityBolivia'
    ];

    for(var i=0;i<socialsLines.length;i++){
      doc.text(socialsLines[i], centerX, centerY + (i*12), {align:'center'});
    }

    // ---- Página ----
    doc.setFont('helvetica','normal');
    doc.setFontSize(9);
    doc.setTextColor(100,116,139);
    doc.text('PÁGINA ' + pageNum, rightX, pageH - 18, {align:'right'});
  }

  function textWrap(doc, text, x, y, w, lh){
    var lines = doc.splitTextToSize(String(text || ''), w);
    for(var i=0;i<lines.length;i++){
      doc.text(lines[i], x, y);
      y += lh;
    }
    return y;
  }

  function buildPdfPro(payload){
    var JSPDF = (window.jspdf && window.jspdf.jsPDF) ? window.jspdf.jsPDF : null;
    if(!JSPDF) throw new Error('jsPDF no cargó.');

    payload = payload || {};
    var cot = payload.cotizacion || {};
    var cli = payload.cliente || {};
    var items = payload.items || [];

    var codigo = cot.cot_codigo || 'BORRADOR';
    var empresa = cli.nombre_legal || 'SIN EMPRESA';
    var fechaISO = String(cot.fecha_emision || '').slice(0,10);
    var fechaDot = ddmmyyyyDot(fechaISO || new Date().toISOString().slice(0,10));

    var contactoNom = cot.contacto_nombre || '—';
    var contactoEmail = cot.contacto_email || '—';
    var contactoTel = cot.contacto_tel || '—';

    var subtotal = Number(cot.subtotal || cot.total || 0);
    var descuento = Number(cot.descuento || 0);
    var impuestos = Number(cot.impuestos || 0);
    var total = Number(cot.total || 0);

    var doc = new JSPDF({unit:'pt', format:'a4'});
    var pageW = doc.internal.pageSize.getWidth();
    var pageH = doc.internal.pageSize.getHeight();

    var margin = 52;
    var headerH = 62;
    var footerH = 160; // espacio para redes + logo + contacto + página
    var contentTop = margin + headerH - 20;
    var contentBottom = pageH - footerH - 14;

    var border = '#E2E8F0';

    function newPage(pageNum){
      if(pageNum > 1) doc.addPage();
      drawGradientHeader(doc, pageW, margin, codigo);
      drawFooter(doc, pageW, pageH, margin, pageNum);
      return contentTop;
    }

    var pageNum = 1;
    var y = newPage(pageNum);

    // Resumen
    doc.setTextColor(15,23,42);
    doc.setFont('helvetica','bold');
    doc.setFontSize(12);
    doc.text('Resumen Ejecutivo', margin, y);
    y += 14;

    doc.setFont('helvetica','normal');
    doc.setFontSize(9);
    y = textWrap(doc, 'Propuesta para gestión integral de contenido y performance digital. Este documento detalla el alcance, inversión y condiciones comerciales.', margin, y, pageW - 2*margin, 12);
    y += 10;

    // Panel cliente
    var panelH = 176;
    if(y + panelH > contentBottom){ pageNum++; y = newPage(pageNum); }

    doc.setDrawColor(border);
    doc.setFillColor(255,255,255);
    doc.roundedRect(margin, y, pageW - 2*margin, panelH, 10, 10, 'FD');

    var px = margin + 14;
    doc.setFont('helvetica','bold');
    doc.setFontSize(10);
    doc.text('INFORMACIÓN DEL CLIENTE', px, y + 24);

    doc.setFont('helvetica','normal');
    doc.setFontSize(9);

    var colGap = 16;
    var colW = (pageW - 2*margin - 28 - colGap)/2;
    var leftX = px;
    var rightX = px + colW + colGap;

    function labelValue(x, yy, label, value){
      doc.setFont('helvetica','bold');
      doc.setTextColor(100,116,139);
      doc.text(label + ':', x, yy);
      doc.setFont('helvetica','normal');
      doc.setTextColor(15,23,42);
      var v = String(value || '—');
      var lines = doc.splitTextToSize(v, colW - 10);
      doc.text(lines[0] || '—', x, yy + 14);
      if(lines.length>1) doc.text(lines[1], x, yy + 26);
      return yy + (lines.length>1 ? 40 : 28);
    }

    var ly = y + 48;
    ly = labelValue(leftX, ly, 'CLIENTE', empresa);
    ly = labelValue(leftX, ly, 'NIT', cli.nit_id || '—');
    ly = labelValue(leftX, ly, 'ATENCIÓN', contactoNom);

    var ry = y + 48;
    ry = labelValue(rightX, ry, 'CÓDIGO', codigo);
    ry = labelValue(rightX, ry, 'FECHA', fechaISO ? (fechaISO.slice(8,10)+'/'+fechaISO.slice(5,7)+'/'+fechaISO.slice(0,4)) : '—');
    ry = labelValue(rightX, ry, 'EMAIL', contactoEmail);
    ry = labelValue(rightX, ry, 'TEL', contactoTel);

    y += panelH + 22;

    // Tabla items
    doc.setFont('helvetica','bold');
    doc.setFontSize(12);
    doc.setTextColor(15,23,42);
    if(y + 20 > contentBottom){ pageNum++; y = newPage(pageNum); }
    doc.text('Detalle de Servicios', margin, y);
    y += 10;

    var tableX = margin;
    var tableW = pageW - 2*margin;
    var headH = 18;
    var rowH = 28;
    var colCode = 90;
    var colUnit = 90;
    var colTot = 90;
    var colTitle = tableW - (colCode + colUnit + colTot);

    function header(){
      doc.setFillColor(31,39,43);
      doc.rect(tableX, y, tableW, headH, 'F');
      doc.setTextColor(255,255,255);
      doc.setFont('helvetica','bold');
      doc.setFontSize(9);
      doc.text('Código', tableX + 6, y + 12);
      doc.text('Servicio', tableX + colCode + 6, y + 12);
      doc.text('P. Unit.', tableX + colCode + colTitle + 6, y + 12);
      doc.text('Total', tableX + colCode + colTitle + colUnit + 6, y + 12);
      y += headH;
      doc.setFont('helvetica','normal');
      doc.setFontSize(9);
      doc.setTextColor(15,23,42);
    }

    function row(it, idx){
      if(idx % 2 === 1){
        doc.setFillColor(248,250,252);
        doc.rect(tableX, y, tableW, rowH, 'F');
      }
      doc.setDrawColor(226,232,240);
      doc.setLineWidth(0.4);
      doc.rect(tableX, y, tableW, rowH);

      var code = it.codigo_servicio || it.codigo_unico || '';
      var title = (it.nombre_servicio || it.descripcion || '').toString().replace(/\s+/g,' ').trim();
      var maxW = Math.max(40, colTitle - 12);
      var lines = doc.splitTextToSize(title, maxW);
      if(lines.length>2){
        lines = [lines[0], String(lines[1]).slice(0, Math.max(1, String(lines[1]).length-1)) + '…'];
      }

      var cant = Number(it.cantidad || 1);
      if(!isFinite(cant) || cant<=0) cant = 1;
      var pu = Number(it.precio_unitario || 0);
      if(!isFinite(pu)) pu = 0;
      var tot = Number(it.subtotal_item || (cant*pu));

      doc.text(String(code).slice(0,24), tableX + 6, y + 18);
      doc.text(lines[0] || '', tableX + colCode + 6, y + 14);
      if(lines.length>1) doc.text(lines[1], tableX + colCode + 6, y + 24);

      doc.text(money(pu), tableX + colCode + colTitle + colUnit - 6, y + 18, {align:'right'});
      doc.text(money(tot), tableX + tableW - 6, y + 18, {align:'right'});

      y += rowH;
    }

    if(y + headH + rowH > contentBottom){ pageNum++; y = newPage(pageNum); }
    header();

    for(var i=0;i<items.length;i++){
      if(y + rowH > contentBottom){ pageNum++; y = newPage(pageNum); header(); }
      row(items[i], i);
    }

    // Totales
    var totalsH = 72;
    if(y + totalsH > contentBottom){ pageNum++; y = newPage(pageNum); }

    var boxW = 190;
    var boxX = pageW - margin - boxW;

    doc.setDrawColor(border);
    doc.setFillColor(255,255,255);
    doc.roundedRect(boxX, y + 10, boxW, totalsH, 8, 8, 'FD');

    function totLine(label, value, yy, bold){
      doc.setFont('helvetica', bold ? 'bold' : 'normal');
      doc.setFontSize(9);
      doc.setTextColor(15,23,42);
      doc.text(label, boxX + 10, yy);
      doc.text(value, boxX + boxW - 10, yy, {align:'right'});
    }

    totLine('Subtotal', 'Bs ' + money(subtotal), y + 28, false);
    totLine('Descuento', 'Bs ' + money(descuento), y + 44, false);
    totLine('Impuestos', 'Bs ' + money(impuestos), y + 60, false);

    doc.setFillColor(237,233,254);
    doc.roundedRect(boxX, y + 70, boxW, 22, 8, 8, 'F');
    doc.setTextColor(80,74,165);
    doc.setFont('helvetica','bold');
    doc.text('TOTAL', boxX + 10, y + 85);
    doc.text('Bs ' + money(total), boxX + boxW - 10, y + 85, {align:'right'});

    // Nombre archivo
    var fname = safeFileName((codigo || 'COT') + ' - ' + (empresa || 'SIN EMPRESA') + ' - ' + fechaDot);

    return { doc: doc, filename: fname + '.pdf' };
  }

  window.CMBQuotesPDFBuilder = {
    setLogoDataUrl: setLogoDataUrl,
    buildPdfPro: buildPdfPro
  };
})();

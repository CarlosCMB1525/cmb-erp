/* global window, document */
(function () {
  'use strict';

  var vars = window.cmbQuotesVars || {};

  function byId(id) { return document.getElementById(id); }

  function ensureToastRoot() {
    var el = document.getElementById('cmb_erp_toasts');
    if (el) return el;
    el = document.createElement('div');
    el.id = 'cmb_erp_toasts';
    el.setAttribute('aria-live', 'polite');
    el.style.position = 'fixed';
    el.style.right = '16px';
    el.style.bottom = '16px';
    el.style.zIndex = '99999';
    el.style.display = 'flex';
    el.style.flexDirection = 'column';
    el.style.gap = '10px';
    document.body.appendChild(el);
    return el;
  }

  function toast(text, ok) {
    text = String(text || '').trim();
    if (!text) return;
    var root = ensureToastRoot();

    var t = document.createElement('div');
    t.style.minWidth = '240px';
    t.style.maxWidth = '360px';
    t.style.padding = '10px 12px';
    t.style.borderRadius = '12px';
    t.style.fontWeight = '800';
    t.style.fontSize = '13px';
    t.style.boxShadow = '0 10px 24px rgba(0,0,0,.16)';
    t.style.border = '1px solid rgba(0,0,0,.08)';
    t.style.color = '#0b1220';
    t.style.background = ok === false ? '#fee2e2' : '#dcfce7';
    t.textContent = text;

    root.appendChild(t);

    window.setTimeout(function () {
      t.style.opacity = '0';
      t.style.transform = 'translateY(6px)';
      t.style.transition = 'all .22s ease';
      window.setTimeout(function () {
        if (t && t.parentNode) t.parentNode.removeChild(t);
      }, 260);
    }, 2600);
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function money(n) {
    var x = parseFloat(n);
    if (!isFinite(x)) x = 0;
    return x.toFixed(2);
  }

  function setCodeBadge(code) {
    var badge = byId('q_code_badge');
    if (!badge) return;
    code = (code || '').toString().trim();
    if (!code) {
      badge.style.display = 'none';
      return;
    }
    badge.style.display = '';
    badge.textContent = code;
  }

  function openModal(id) {
    var m = byId(id);
    if (m) m.classList.add('is-open');
  }

  function closeModal(id) {
    var m = byId(id);
    if (m) m.classList.remove('is-open');
  }

  function apiPost(action, data) {
    data = data || {};
    data.action = action;
    data.nonce = data.nonce || vars.nonce;

    var body = new URLSearchParams();
    Object.keys(data).forEach(function (k) { body.append(k, data[k]); });

    return fetch(vars.ajaxurl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function (r) {
      return r.text().then(function (txt) {
        var json;
        try { json = JSON.parse(txt); } catch (e) {
          var err = new Error('Respuesta no-JSON (HTTP ' + r.status + ')');
          err._body = txt;
          throw err;
        }
        if (!r.ok) {
          var err2 = new Error('HTTP ' + r.status);
          err2._json = json;
          throw err2;
        }
        return json;
      });
    });
  }

  function uid() {
    return 'g_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
  }

  // -------------------------
  // State
  // -------------------------
  var state = {
    id: 0,
    items: [],
    groups: [{ id: 0, key: 'g_default', tipo: 'UNICO', titulo: 'Únicos', orden: 1 }],
    activeGroupKey: 'g_default'
  };

  function ensureGroups() {
    if (!Array.isArray(state.groups) || !state.groups.length) {
      state.groups = [{ id: 0, key: 'g_default', tipo: 'UNICO', titulo: 'Únicos', orden: 1 }];
    }
    if (!state.activeGroupKey) state.activeGroupKey = state.groups[0].key;

    state.items.forEach(function (it) {
      if (!it.group_key) it.group_key = state.groups[0].key;
    });
  }

  function syncIdUI() {
    var hid = byId('q_id');
    var badge = byId('q_id_badge');
    if (hid) hid.value = String(state.id || 0);
    if (badge) {
      if (state.id > 0) {
        badge.style.display = '';
        badge.textContent = 'ID: ' + state.id;
      } else {
        badge.style.display = 'none';
      }
    }
  }

  function clampItem(it) {
    var c = parseFloat(it.cantidad);
    if (!isFinite(c) || c <= 0) c = 1;
    it.cantidad = c;

    var p = parseFloat(it.precio_unitario);
    if (!isFinite(p)) p = 0;

    var isManual = String(it.codigo_servicio || '').toUpperCase() === 'MANUAL' || (parseInt(it.servicio_id || 0, 10) === 0);
    if (!isManual && p < 0) p = 0;
    it.precio_unitario = p;

    return it;
  }

  function groupSubtotal(key) {
    var s = 0;
    state.items.filter(function (it) { return it.group_key === key; }).forEach(function (it) {
      it = clampItem(it);
      s += it.cantidad * it.precio_unitario;
    });
    return Math.round(s * 100) / 100;
  }

  function calcTotals() {
    var subtotal = 0;
    state.items.forEach(function (it) {
      it = clampItem(it);
      subtotal += (it.cantidad * it.precio_unitario);
    });
    subtotal = Math.round(subtotal * 100) / 100;
    var total = subtotal;
    if (total < 0) total = 0;
    return { subtotal: subtotal, total: total };
  }

  function renderItems() {
    ensureGroups();

    var tb = byId('q_items_tbody');
    if (!tb) return;

    var totals = calcTotals();
    if (byId('q_subtotal')) byId('q_subtotal').textContent = money(totals.subtotal);
    if (byId('q_total')) byId('q_total').textContent = money(totals.total);

    var html = '';
    var groups = state.groups.slice().sort(function (a, b) { return (a.orden || 0) - (b.orden || 0); });

    groups.forEach(function (g) {
      var tipo = esc(g.tipo || 'UNICO');
      var titulo = esc(g.titulo || tipo);
      var sub = groupSubtotal(g.key);

      html += (
        '<tr class="q-gr-h">' +
          '<td colspan="6" style="background:#f8fafc;border-top:2px solid #e2e8f0;">' +
            '<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding:10px 12px;">' +
              '<span class="cmb-erp-badge cmb-erp-badge--info" style="font-weight:900;">' + tipo + '</span>' +
              '<input class="cmb-erp-input q-group-title" style="min-width:240px;flex:1;" value="' + titulo + '" data-gkey="' + esc(g.key) + '" />' +
              '<button type="button" class="cmb-erp-btn cmb-erp-btn--primary cmb-erp-btn--sm q-add-srv" data-gkey="' + esc(g.key) + '">🔎 Agregar servicio</button>' +
              '<button type="button" class="cmb-erp-btn cmb-erp-btn--dark cmb-erp-btn--sm q-add-manual" data-gkey="' + esc(g.key) + '">➕ Ítem manual</button>' +
              '<button type="button" class="cmb-erp-btn cmb-erp-btn--ghost cmb-erp-btn--sm q-del-group" data-gkey="' + esc(g.key) + '">🗑 Eliminar tabla</button>' +
            '</div>' +
          '</td>' +
        '</tr>'
      );

      var list = state.items.filter(function (it) { return it.group_key === g.key; });
      if (!list.length) {
        html += '<tr><td colspan="6" class="cmb-erp-text-muted" style="padding:12px;">Sin ítems en esta tabla</td></tr>';
      } else {
        list.forEach(function (it) {
          var idx = state.items.indexOf(it);
          it = clampItem(it);
          var st = it.cantidad * it.precio_unitario;
          html += (
            '<tr>' +
              '<td><code>' + esc(it.codigo_servicio || '') + '</code></td>' +
              '<td><textarea class="cmb-erp-input" style="min-height:60px;" data-item-idx="' + idx + '" data-item-k="descripcion">' + esc(it.descripcion || '') + '</textarea></td>' +
              '<td><input class="cmb-erp-input" type="number" step="0.01" value="' + money(it.cantidad) + '" data-item-idx="' + idx + '" data-item-k="cantidad" /></td>' +
              '<td><input class="cmb-erp-input" type="number" step="0.01" value="' + money(it.precio_unitario) + '" data-item-idx="' + idx + '" data-item-k="precio_unitario" /></td>' +
              '<td><strong>' + money(st) + '</strong></td>' +
              '<td class="cmb-erp-text-right"><button type="button" class="cmb-erp-btn cmb-erp-btn--ghost cmb-erp-btn--sm" data-item-del="' + idx + '">🗑</button></td>' +
            '</tr>'
          );
        });
      }

      html += (
        '<tr class="q-gr-total">' +
          '<td colspan="6" style="background:#fff;border-bottom:2px solid #e2e8f0;">' +
            '<div style="display:flex;justify-content:flex-end;padding:8px 12px;">' +
              '<span class="cmb-erp-badge cmb-erp-badge--brand" style="font-weight:900;">TOTAL ' + titulo + ': ' + money(sub) + '</span>' +
            '</div>' +
          '</td>' +
        '</tr>'
      );
    });

    tb.innerHTML = html || '<tr><td colspan="6" class="cmb-erp-text-muted">Agrega tablas/servicios para comenzar…</td></tr>';
  }

  function addManualItem(groupKey) {
    ensureGroups();
    state.items.push({
      group_key: groupKey || state.activeGroupKey,
      servicio_id: 0,
      codigo_servicio: 'MANUAL',
      nombre_servicio: 'Ítem manual',
      descripcion: '',
      cantidad: 1,
      precio_unitario: 0
    });
    renderItems();
    toast('Ítem manual agregado.', true);
  }

  function addServiceItem(s) {
    ensureGroups();
    state.items.push({
      group_key: state.activeGroupKey,
      servicio_id: parseInt(s.id || 0, 10) || 0,
      codigo_servicio: String(s.codigo_unico || ''),
      nombre_servicio: String(s.nombre_servicio || ''),
      descripcion: String(s.detalle_tecnico || s.nombre_servicio || ''),
      cantidad: 1,
      precio_unitario: parseFloat(s.monto_unitario || 0) || 0
    });
    renderItems();
    toast('Servicio agregado. Puedes seguir agregando.', true);
  }

  function addGroup() {
    ensureGroups();
    var tipo = window.prompt('Tipo/Categoría (ej: UNICO, MENSUAL, EXTRAS, ADICIONALES):', 'UNICO');
    if (tipo === null) return;
    tipo = String(tipo || '').trim().toUpperCase();
    if (!tipo) tipo = 'UNICO';
    var titulo = window.prompt('Nombre de la tabla (se usa en el TOTAL):', tipo);
    if (titulo === null) return;
    titulo = String(titulo || '').trim() || tipo;

    var key = uid();
    var maxOrden = 0;
    state.groups.forEach(function (g) { maxOrden = Math.max(maxOrden, parseInt(g.orden || 0, 10) || 0); });

    state.groups.push({ id: 0, key: key, tipo: tipo.slice(0, 20), titulo: titulo.slice(0, 200), orden: maxOrden + 1 });
    state.activeGroupKey = key;
    renderItems();
  }

  function gatherDraftPayload() {
    var clienteId = parseInt((byId('q_cliente_id') || {}).value || '0', 10) || 0;
    var contactoId = parseInt((byId('q_contacto_id') || {}).value || '0', 10) || 0;

    return {
      id: state.id,
      fecha: (byId('q_fecha') || {}).value || '',
      moneda: (byId('q_moneda') || {}).value || 'BOB',
      cliente_id: clienteId,
      contacto_id: contactoId,
      validez_sel: (byId('q_validez') || {}).value || '15',
      pago_sel: (byId('q_pago') || {}).value || '50_50',
      condiciones: (byId('q_cond') || {}).value || '',
      groups: JSON.stringify(state.groups),
      items: JSON.stringify(state.items)
    };
  }

  function saveDraft() {
    var p = gatherDraftPayload();
    if (!p.cliente_id) return toast('Selecciona un cliente.', false);
    if (!state.items.length) return toast('Agrega al menos un ítem.', false);

    toast('Guardando borrador…', true);
    var action = vars.actions && vars.actions.save_draft ? vars.actions.save_draft : 'cmb_quotes_save_draft';

    return apiPost(action, p).then(function (r) {
      if (!(r && r.success && r.data)) {
        toast((r && r.data) ? r.data : 'Error al guardar', false);
        return Promise.reject(new Error('save failed'));
      }
      state.id = parseInt(r.data.id || 0, 10) || state.id;
      syncIdUI();
      renderItems();
      toast('Borrador guardado (ID #' + state.id + ').', true);
      loadHistory();
      return r.data;
    }).catch(function (e) {
      console.error(e);
      if (e && e._json && e._json.data) toast(String(e._json.data), false);
      else if (e && e._body) toast('Error: ' + String(e._body).slice(0, 180), false);
      else toast('Fallo de red al guardar', false);
      throw e;
    });
  }

  function emitQuote() {
    if (!state.id) {
      return saveDraft().then(function () { return emitQuote(); });
    }

    toast('Emitiendo…', true);
    var action = vars.actions && vars.actions.emit ? vars.actions.emit : 'cmb_quotes_emit';

    return apiPost(action, { id: state.id }).then(function (r) {
      if (!(r && r.success && r.data)) {
        toast((r && r.data) ? r.data : 'Error al emitir', false);
        return;
      }
      var code = r.data.cot_codigo || '';
      setCodeBadge(code);

      if (r.data.mode === 'version' && r.data.id) {
        loadQuote(r.data.id);
      } else {
        toast('Cotización emitida: ' + code, true);
        loadHistory();
      }
    }).catch(function (e) {
      console.error(e);
      if (e && e._json && e._json.data) toast(String(e._json.data), false);
      else if (e && e._body) toast('Error: ' + String(e._body).slice(0, 180), false);
      else toast('Fallo de red al emitir', false);
    });
  }

  function loadQuote(id) {
    id = parseInt(id || 0, 10) || 0;
    if (!id) return;

    toast('Cargando cotización #' + id + '…', true);
    var action = vars.actions && vars.actions.get_quote ? vars.actions.get_quote : 'cmb_quotes_get';

    return apiPost(action, { id: id }).then(function (r) {
      if (!(r && r.success && r.data)) {
        toast((r && r.data) ? r.data : 'Error al cargar', false);
        return;
      }

      var cot = r.data.cotizacion || {};
      var cli = r.data.cliente || null;
      var groupsDb = r.data.groups || [];
      var itemsDb = r.data.items || [];

      state.id = parseInt(cot.id || id, 10) || id;
      syncIdUI();
      setCodeBadge(cot.cot_codigo || '');

      var fe = (cot.fecha_emision || '').toString().slice(0, 10);
      if (byId('q_fecha') && fe) byId('q_fecha').value = fe;
      if (byId('q_moneda') && cot.moneda) byId('q_moneda').value = cot.moneda;

      if (byId('q_cliente_id')) byId('q_cliente_id').value = String(cot.cliente_id || 0);
      if (byId('q_cliente_nombre')) byId('q_cliente_nombre').value = cli && cli.nombre_legal ? cli.nombre_legal : '';

      if (byId('q_validez') && cot.validez_sel) byId('q_validez').value = cot.validez_sel;
      if (byId('q_pago') && cot.pago_sel) byId('q_pago').value = cot.pago_sel;
      if (byId('q_cond')) byId('q_cond').value = cot.condiciones || '';

      state.groups = (groupsDb || []).map(function (g) {
        return { id: parseInt(g.id || 0, 10) || 0, key: 'gid_' + (parseInt(g.id || 0, 10) || 0), tipo: g.tipo || 'UNICO', titulo: g.titulo || 'Únicos', orden: parseInt(g.orden || 1, 10) || 1 };
      });
      if (!state.groups.length) state.groups = [{ id: 0, key: 'g_default', tipo: 'UNICO', titulo: 'Únicos', orden: 1 }];

      var map = {};
      state.groups.forEach(function (g) { map[String(g.id)] = g.key; });

      state.items = (itemsDb || []).map(function (it) {
        var gid = parseInt(it.grupo_id || 0, 10) || 0;
        return {
          group_key: map[String(gid)] || state.groups[0].key,
          grupo_id: gid,
          servicio_id: parseInt(it.servicio_id || 0, 10) || 0,
          codigo_servicio: String(it.codigo_servicio || ''),
          nombre_servicio: String(it.nombre_servicio || ''),
          descripcion: String(it.descripcion || ''),
          cantidad: parseFloat(it.cantidad || 1) || 1,
          precio_unitario: parseFloat(it.precio_unitario || 0) || 0
        };
      });

      state.activeGroupKey = state.groups[0].key;

      renderItems();
      toast('Cotización cargada (ID #' + state.id + ').', true);

      var contactoId = parseInt(cot.contacto_id || 0, 10) || 0;
      var empresaId = parseInt(cot.cliente_id || 0, 10) || 0;
      if (empresaId) {
        loadContacts(empresaId).then(function () {
          if (byId('q_contacto_id')) byId('q_contacto_id').value = String(contactoId || 0);
        });
      }
    }).catch(function (e) {
      console.error(e);
      if (e && e._json && e._json.data) toast(String(e._json.data), false);
      else if (e && e._body) toast('Error: ' + String(e._body).slice(0, 180), false);
      else toast('Fallo de red al cargar', false);
    });
  }

  // Clients/Contacts/Services/History
  var clientSearch = { q: '', page: 1, per_page: (vars.ui && vars.ui.per_page) ? parseInt(vars.ui.per_page, 10) : 20, total: 0, has_more: false, loading: false };
  function renderClientRows(rows) {
    var tb = byId('q_cli_tbody');
    if (!tb) return;
    if (!rows || !rows.length) { tb.innerHTML = '<tr><td colspan="5" class="cmb-erp-text-muted">Sin resultados</td></tr>'; return; }
    tb.innerHTML = rows.map(function (r) {
      var id = parseInt(r.id || 0, 10) || 0;
      var empresa = esc(r.nombre_legal || '');
      var nit = esc(r.nit_id || '');
      var tipo = esc(r.tipo_cliente || '');
      return '<tr>' +
        '<td><strong>#' + id + '</strong></td>' +
        '<td><strong>' + empresa + '</strong></td>' +
        '<td>' + nit + '</td>' +
        '<td><span class="cmb-erp-badge cmb-erp-badge--info">' + (tipo || '—') + '</span></td>' +
        '<td class="cmb-erp-text-right"><button type="button" class="cmb-erp-btn cmb-erp-btn--primary cmb-erp-btn--sm" data-pick-client="' + id + '" data-pick-name="' + empresa.replace(/"/g, '&quot;') + '">Seleccionar</button></td>' +
        '</tr>';
    }).join('');
  }

  function searchClients(opts) {
    if (clientSearch.loading) return Promise.resolve();
    clientSearch.loading = true;
    opts = opts || {}; if (typeof opts.q === 'string') clientSearch.q = opts.q; if (typeof opts.page === 'number') clientSearch.page = opts.page;
    var tb = byId('q_cli_tbody'); if (tb) tb.innerHTML = '<tr><td colspan="5" class="cmb-erp-text-muted">Cargando…</td></tr>';
    return apiPost(vars.actions.search_clients, { q: clientSearch.q, page: clientSearch.page, per_page: clientSearch.per_page })
      .then(function (r) {
        clientSearch.loading = false;
        if (!(r && r.success && r.data)) { renderClientRows([]); toast((r && r.data) ? r.data : 'Error al buscar clientes', false); return; }
        renderClientRows(r.data.rows || []);
      }).catch(function (e) {
        clientSearch.loading = false;
        renderClientRows([]);
        toast('Fallo de red al buscar clientes', false);
        console.error(e);
      });
  }

  function loadContacts(empresaId) {
    var sel = byId('q_contacto_id');
    if (!sel) return Promise.resolve();
    sel.innerHTML = '<option value="0">Cargando…</option>';
    return apiPost(vars.actions.get_contacts, { empresa_id: empresaId }).then(function (r) {
      sel.innerHTML = '<option value="0">— Seleccionar contacto —</option>';
      if (!(r && r.success && r.data)) { toast((r && r.data) ? r.data : 'Error al cargar contactos', false); return; }
      (r.data.rows || []).forEach(function (c) {
        var opt = document.createElement('option'); opt.value = String(c.id || '0');
        var label = String(c.nombre_contacto || ''); if (c.cargo) label += ' — ' + c.cargo; if (c.correo_electronico) label += ' · ' + c.correo_electronico;
        opt.textContent = label; sel.appendChild(opt);
      });
    }).catch(function (e) {
      sel.innerHTML = '<option value="0">— Seleccionar contacto —</option>';
      toast('Fallo de red al cargar contactos', false);
      console.error(e);
    });
  }

  var serviceSearch = { q: '', page: 1, per_page: (vars.ui && vars.ui.per_page) ? parseInt(vars.ui.per_page, 10) : 20, total: 0, has_more: false, loading: false };
  function renderServiceRows(rows) {
    var tb = byId('q_srv_tbody'); if (!tb) return;
    if (!rows || !rows.length) { tb.innerHTML = '<tr><td colspan="4" class="cmb-erp-text-muted">Sin resultados</td></tr>'; return; }
    tb.innerHTML = rows.map(function (r) {
      var safe = esc(JSON.stringify(r)).replace(/"/g, '&quot;');
      return '<tr>' +
        '<td><code>' + esc(r.codigo_unico || '') + '</code></td>' +
        '<td><strong>' + esc(r.nombre_servicio || '') + '</strong></td>' +
        '<td>' + money(r.monto_unitario || 0) + '</td>' +
        '<td class="cmb-erp-text-right"><button type="button" class="cmb-erp-btn cmb-erp-btn--primary cmb-erp-btn--sm" data-pick-service="1" data-service-json="' + safe + '">Añadir</button></td>' +
      '</tr>';
    }).join('');
  }

  function searchServices(opts) {
    if (serviceSearch.loading) return Promise.resolve();
    serviceSearch.loading = true;
    opts = opts || {}; if (typeof opts.q === 'string') serviceSearch.q = opts.q; if (typeof opts.page === 'number') serviceSearch.page = opts.page;
    var tb = byId('q_srv_tbody'); if (tb) tb.innerHTML = '<tr><td colspan="4" class="cmb-erp-text-muted">Cargando…</td></tr>';
    return apiPost(vars.actions.list_services, { q: serviceSearch.q, page: serviceSearch.page, per_page: serviceSearch.per_page }).then(function (r) {
      serviceSearch.loading = false;
      if (!(r && r.success && r.data)) { renderServiceRows([]); toast((r && r.data) ? r.data : 'Error al listar servicios', false); return; }
      renderServiceRows(r.data.rows || []);
    }).catch(function (e) {
      serviceSearch.loading = false;
      renderServiceRows([]);
      toast('Fallo de red al listar servicios', false);
      console.error(e);
    });
  }

  function renderHistory(rows) {
    var tb = byId('q_hist_tbody');
    if (!tb) return;
    if (!rows || !rows.length) {
      tb.innerHTML = '<tr><td colspan="9" class="cmb-erp-text-muted">Sin registros</td></tr>';
      return;
    }
    tb.innerHTML = rows.map(function (r) {
      var id = parseInt(r.id || 0, 10) || 0;
      var codigo = esc(r.cot_codigo || ('#' + id));
      var fecha = esc((r.fecha_emision || '').toString().slice(0, 10));
      var empresa = esc(r.empresa || '');
      var contacto = esc(r.contacto_nombre || '');
      var total = money(r.total || 0);
      var estado = esc(r.estado || '');
      return '<tr>' +
        '<td><strong>#' + id + '</strong></td>' +
        '<td><strong>' + codigo + '</strong></td>' +
        '<td>' + fecha + '</td>' +
        '<td>' + empresa + '</td>' +
        '<td>' + contacto + '</td>' +
        '<td><strong>' + total + '</strong></td>' +
        '<td>' + estado + '</td>' +
        '<td class="cmb-erp-text-right"><button type="button" class="cmb-erp-btn cmb-erp-btn--ghost cmb-erp-btn--sm" data-hist-pdf="' + id + '">PDF</button></td>' +
        '<td class="cmb-erp-text-right"><button type="button" class="cmb-erp-btn cmb-erp-btn--primary cmb-erp-btn--sm" data-hist-edit="' + id + '">Editar</button></td>' +
      '</tr>';
    }).join('');
  }

  function loadHistory() {
    var action = vars.actions && vars.actions.list_versions ? vars.actions.list_versions : 'cmb_quotes_list_versions';
    var base = (byId('q_hist_base') || {}).value || '';
    var limit = (vars.ui && vars.ui.history_limit) ? parseInt(vars.ui.history_limit, 10) : 200;
    var tb = byId('q_hist_tbody');
    if (tb) tb.innerHTML = '<tr><td colspan="9" class="cmb-erp-text-muted">Cargando…</td></tr>';
    return apiPost(action, { base: base, limit: limit }).then(function (r) {
      if (!(r && r.success && r.data)) {
        renderHistory([]);
        toast((r && r.data) ? r.data : 'Error al cargar historial', false);
        return;
      }
      renderHistory(r.data.rows || []);
    }).catch(function (e) {
      console.error(e);
      toast('Fallo de red al cargar historial', false);
      renderHistory([]);
    });
  }

  function bindModals() {
    document.querySelectorAll('[data-q-close]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var id = btn.getAttribute('data-q-close');
        if (id) closeModal(id);
      });
    });
    ['cmb_quotes_client_modal', 'cmb_quotes_service_modal'].forEach(function (mid) {
      var m = byId(mid);
      if (!m) return;
      m.addEventListener('click', function (e) {
        if (e.target === m) closeModal(mid);
      });
    });
  }

  function bindUI() {
    var btnCliente = byId('q_btn_cliente');
    if (btnCliente) btnCliente.addEventListener('click', function (e) {
      e.preventDefault();
      openModal('cmb_quotes_client_modal');
      searchClients({ q: '', page: 1 });
      var inp = byId('q_cli_search');
      if (inp) setTimeout(function () { inp.focus(); }, 60);
    });

    var btnSrv = byId('q_btn_service');
    if (btnSrv) btnSrv.addEventListener('click', function (e) {
      e.preventDefault();
      openModal('cmb_quotes_service_modal');
      searchServices({ q: '', page: 1 });
      var inp = byId('q_srv_search');
      if (inp) setTimeout(function () { inp.focus(); }, 60);
    });

    var cliInp = byId('q_cli_search');
    if (cliInp) {
      var t1;
      cliInp.addEventListener('input', function () {
        window.clearTimeout(t1);
        var q = (cliInp.value || '').trim();
        t1 = window.setTimeout(function () { searchClients({ q: q, page: 1 }); }, 180);
      });
    }

    var srvInp = byId('q_srv_search');
    if (srvInp) {
      var t2;
      srvInp.addEventListener('input', function () {
        window.clearTimeout(t2);
        var q = (srvInp.value || '').trim();
        t2 = window.setTimeout(function () { searchServices({ q: q, page: 1 }); }, 180);
      });
    }

    var btnManual = byId('q_btn_manual');
    if (btnManual) btnManual.addEventListener('click', function (e) { e.preventDefault(); addManualItem(); });

    var btnGroup = byId('q_btn_group');
    if (btnGroup) btnGroup.addEventListener('click', function (e) { e.preventDefault(); addGroup(); });

    var cliTbody = byId('q_cli_tbody');
    if (cliTbody) cliTbody.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('[data-pick-client]') : null;
      if (!btn) return;
      var id = parseInt(btn.getAttribute('data-pick-client') || '0', 10) || 0;
      var name = btn.getAttribute('data-pick-name') || '';
      if (byId('q_cliente_id')) byId('q_cliente_id').value = String(id);
      if (byId('q_cliente_nombre')) byId('q_cliente_nombre').value = name;
      closeModal('cmb_quotes_client_modal');
      toast('Cliente seleccionado. Cargando contactos…', true);
      loadContacts(id);
    });

    var srvTbody = byId('q_srv_tbody');
    if (srvTbody) srvTbody.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('[data-pick-service]') : null;
      if (!btn) return;
      var raw = btn.getAttribute('data-service-json') || '{}';
      var obj = {}; try { obj = JSON.parse(raw); } catch (err) { obj = {}; }
      addServiceItem(obj);
    });

    var itemsTbody = byId('q_items_tbody');
    if (itemsTbody) {
      itemsTbody.addEventListener('click', function (e) {
        var del = e.target && e.target.closest ? e.target.closest('[data-item-del]') : null;
        if (del) {
          var idx = parseInt(del.getAttribute('data-item-del') || '-1', 10);
          if (idx >= 0) { state.items.splice(idx, 1); renderItems(); }
          return;
        }
        var addSrv = e.target && e.target.closest ? e.target.closest('.q-add-srv') : null;
        if (addSrv) {
          state.activeGroupKey = addSrv.getAttribute('data-gkey') || state.activeGroupKey;
          byId('q_btn_service').click();
          return;
        }
        var addMan = e.target && e.target.closest ? e.target.closest('.q-add-manual') : null;
        if (addMan) {
          var gk = addMan.getAttribute('data-gkey') || state.activeGroupKey;
          addManualItem(gk);
          return;
        }
        var delG = e.target && e.target.closest ? e.target.closest('.q-del-group') : null;
        if (delG) {
          var gkey = delG.getAttribute('data-gkey');
          if (!gkey) return;
          if (!window.confirm('¿Eliminar esta tabla y todos sus ítems? No se puede deshacer.')) return;
          state.items = state.items.filter(function (it) { return it.group_key !== gkey; });
          state.groups = state.groups.filter(function (g) { return g.key !== gkey; });
          state.activeGroupKey = state.groups[0] ? state.groups[0].key : 'g_default';
          renderItems();
        }
      });

      itemsTbody.addEventListener('change', function (e) {
        var el = e.target;
        if (!el) return;
        if (el.classList.contains('q-group-title')) {
          var gk = el.getAttribute('data-gkey');
          var g = state.groups.find(function (x) { return x.key === gk; });
          if (g) { g.titulo = String(el.value || '').trim().slice(0, 200); renderItems(); }
          return;
        }
        var idx = parseInt(el.getAttribute('data-item-idx') || '-1', 10);
        var key = el.getAttribute('data-item-k') || '';
        if (idx < 0 || !state.items[idx] || !key) return;
        var val = el.value;
        if (key === 'cantidad' || key === 'precio_unitario') {
          var n = parseFloat(val);
          if (!isFinite(n)) n = (key === 'cantidad') ? 1 : 0;
          state.items[idx][key] = n;
        } else {
          state.items[idx][key] = val;
        }
        renderItems();
      });
    }

    var btnHist = byId('q_hist_search');
    if (btnHist) btnHist.addEventListener('click', function (e) { e.preventDefault(); loadHistory(); });
    var btnReload = byId('q_hist_reload');
    if (btnReload) btnReload.addEventListener('click', function (e) { e.preventDefault(); loadHistory(); });

    var histTbody = byId('q_hist_tbody');
    if (histTbody) histTbody.addEventListener('click', function (e) {
      var btnPdf = e.target && e.target.closest ? e.target.closest('[data-hist-pdf]') : null;
      if (btnPdf) {
        var pid = parseInt(btnPdf.getAttribute('data-hist-pdf') || '0', 10) || 0;
        if (window.CMBQuotesPDFDownload && typeof window.CMBQuotesPDFDownload.downloadById === 'function') {
          window.CMBQuotesPDFDownload.downloadById(pid);
        } else {
          toast('PDF no disponible aún (loader).', false);
        }
        return;
      }

      var btnEdit = e.target && e.target.closest ? e.target.closest('[data-hist-edit]') : null;
      if (btnEdit) {
        var eid = parseInt(btnEdit.getAttribute('data-hist-edit') || '0', 10) || 0;
        if (eid) loadQuote(eid);
      }
    });

    document.querySelectorAll('[data-q]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        var act = btn.getAttribute('data-q');
        if (act === 'new') {
          e.preventDefault();
          state.id = 0;
          state.items = [];
          state.groups = [{ id: 0, key: 'g_default', tipo: 'UNICO', titulo: 'Únicos', orden: 1 }];
          state.activeGroupKey = 'g_default';
          syncIdUI();
          renderItems();
          setCodeBadge('');
          if (byId('q_cliente_id')) byId('q_cliente_id').value = '0';
          if (byId('q_cliente_nombre')) byId('q_cliente_nombre').value = '';
          var sel = byId('q_contacto_id');
          if (sel) sel.innerHTML = '<option value="0">— Seleccionar contacto —</option>';
          if (byId('q_cond')) byId('q_cond').value = '';
          toast('Nueva cotización (reiniciada).', true);
        }
        if (act === 'save') { e.preventDefault(); saveDraft(); }
        if (act === 'emit') { e.preventDefault(); emitQuote(); }
      });
    });
  }

  function init() {
    if (!byId('cmb_quotes_root')) return;

    vars.actions = vars.actions || {};
    vars.actions.search_clients = vars.actions.search_clients || 'cmb_quotes_search_clients';
    vars.actions.get_contacts = vars.actions.get_contacts || 'cmb_quotes_get_contacts';
    vars.actions.list_services = vars.actions.list_services || 'cmb_quotes_list_services';
    vars.actions.save_draft = vars.actions.save_draft || 'cmb_quotes_save_draft';
    vars.actions.get_quote = vars.actions.get_quote || 'cmb_quotes_get';
    vars.actions.list_versions = vars.actions.list_versions || 'cmb_quotes_list_versions';
    vars.actions.emit = vars.actions.emit || 'cmb_quotes_emit';

    bindModals();
    bindUI();
    syncIdUI();
    renderItems();
    loadHistory();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();

})();

/* global window, document */
(function () {
  'use strict';
  var vars = window.cmbSalesVars || {};
  function byId(id) { return document.getElementById(id); }

  function toast(text, ok) {
    text = String(text || '').trim();
    if (!text) return;
    var root = document.getElementById('cmb_erp_toasts');
    if (!root) {
      root = document.createElement('div');
      root.id = 'cmb_erp_toasts';
      root.style.position = 'fixed';
      root.style.right = '16px';
      root.style.bottom = '16px';
      root.style.zIndex = '99999';
      root.style.display = 'flex';
      root.style.flexDirection = 'column';
      root.style.gap = '10px';
      document.body.appendChild(root);
    }
    var t = document.createElement('div');
    t.style.minWidth = '240px';
    t.style.maxWidth = '420px';
    t.style.padding = '10px 12px';
    t.style.borderRadius = '12px';
    t.style.fontWeight = '900';
    t.style.fontSize = '13px';
    t.style.boxShadow = '0 10px 24px rgba(0,0,0,.16)';
    t.style.border = '1px solid rgba(0,0,0,.08)';
    t.style.background = ok === false ? '#fee2e2' : '#dcfce7';
    t.style.color = '#0b1220';
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

  function setMsg(text, ok) {
    var el = byId('cmb_sales_msg');
    if (!el) return;
    el.style.color = ok === false ? '#ef4444' : '#10b981';
    el.textContent = text || '';
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
          var err = new Error('Respuesta no-JSON');
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

  // State
  var state = {
    id: 0,
    cliente_id: 0,
    cliente_nombre: '',
    tipo: 'UNICO',
    meses: 1,
    quote_id: 0,
    quote_code: '',
    items: [] // {n,p,c,m}
  };

  function syncBadges() {
    var b = byId('s_id_badge');
    if (b) {
      if (state.id > 0) { b.style.display = ''; b.textContent = 'ID: ' + state.id; }
      else { b.style.display = 'none'; }
    }
    var qb = byId('s_quote_badge');
    if (qb) {
      if (state.quote_id > 0 || state.quote_code) {
        qb.style.display = '';
        qb.textContent = 'COT: ' + (state.quote_code || ('#' + state.quote_id));
      } else qb.style.display = 'none';
    }
    if (byId('s_id')) byId('s_id').value = String(state.id || 0);
    if (byId('s_cliente_id')) byId('s_cliente_id').value = String(state.cliente_id || 0);
    if (byId('s_cliente_nombre')) byId('s_cliente_nombre').value = state.cliente_nombre || '';
    if (byId('s_tipo')) byId('s_tipo').value = state.tipo || 'UNICO';
    if (byId('s_meses')) byId('s_meses').value = String(state.meses || 1);
    if (byId('s_quote_id')) byId('s_quote_id').value = String(state.quote_id || 0);
    if (byId('s_quote_code')) byId('s_quote_code').value = state.quote_code || '';
    if (byId('s_quote_label')) byId('s_quote_label').value = state.quote_id ? (state.quote_code || ('#' + state.quote_id)) : '';
  }

  function clampItem(it) {
    var c = parseInt(it.c, 10);
    if (!isFinite(c) || c < 1) c = 1;
    it.c = c;
    var p = parseFloat(it.p);
    if (!isFinite(p)) p = 0;
    var manual = (parseInt(it.m, 10) === 1);
    if (!manual && p < 0) p = 0;
    it.p = p;
    it.m = manual ? 1 : 0;
    it.n = String(it.n || '').trim();
    return it;
  }

  function calcTotal() {
    var tot = 0;
    state.items.forEach(function (it) {
      it = clampItem(it);
      tot += (it.p * it.c);
    });
    tot = Math.round(tot * 100) / 100;
    return tot;
  }

  function renderItems() {
    var tb = byId('s_items_tbody');
    if (!tb) return;
    if (!state.items.length) {
      tb.innerHTML = '<tr><td colspan="5" class="cmb-erp-text-muted" style="padding:14px;">Agrega ítems para comenzar…</td></tr>';
      if (byId('s_total')) byId('s_total').textContent = money(0);
      return;
    }
    tb.innerHTML = state.items.map(function (it, idx) {
      it = clampItem(it);
      var sub = it.p * it.c;
      return (
        '<tr>' +
          '<td data-label="DESCRIPCIÓN">' +
            '<input class="cmb-erp-input" value="' + esc(it.n) + '" data-it-idx="' + idx + '" data-it-k="n" />' +
            '<div class="cmb-erp-text-muted" style="font-size:11px;margin-top:6px;">' + (it.m ? 'MANUAL' : 'SERVICIO') + '</div>' +
          '</td>' +
          '<td class="cmb-erp-text-right" data-label="PRECIO">' +
            '<input class="cmb-erp-input" type="number" step="0.01" value="' + money(it.p) + '" data-it-idx="' + idx + '" data-it-k="p" />' +
          '</td>' +
          '<td class="cmb-erp-text-right" data-label="CANT.">' +
            '<input class="cmb-erp-input" type="number" step="1" min="1" value="' + it.c + '" data-it-idx="' + idx + '" data-it-k="c" />' +
          '</td>' +
          '<td class="cmb-erp-text-right" data-label="SUBTOTAL"><strong>' + money(sub) + '</strong></td>' +
          '<td class="cmb-erp-text-right" data-label="—">' +
            '<button type="button" class="cmb-erp-btn cmb-erp-btn--ghost cmb-erp-btn--sm" data-it-del="' + idx + '">🗑️</button>' +
          '</td>' +
        '</tr>'
      );
    }).join('');
    if (byId('s_total')) byId('s_total').textContent = money(calcTotal());
  }

  function addManual() {
    var d = window.prompt('Descripción del ítem manual (descuento/ajuste):', 'DESCUENTO');
    if (d === null) return;
    d = String(d || '').trim();
    if (!d) return;
    state.items.push({ n: d, p: 0, c: 1, m: 1 });
    renderItems();
    toast('Ítem manual agregado.', true);
  }

  function addServiceItem(s) {
    var desc = (s.detalle_tecnico || s.nombre_servicio || s.codigo_unico || '').toString();
    state.items.push({ n: desc, p: parseFloat(s.monto_unitario || 0) || 0, c: 1, m: 0 });
    renderItems();
    toast('Servicio agregado.', true);
  }

  // Clients modal
  var clientT = null;
  function searchClients(q) {
    var tb = byId('s_cli_tbody');
    if (tb) tb.innerHTML = '<tr><td colspan="5" class="cmb-erp-text-muted" style="padding:14px;">Cargando…</td></tr>';
    return apiPost(vars.actions.search_clients, { q: q || '', page: 1, per_page: (vars.ui && vars.ui.per_page) ? vars.ui.per_page : 20 })
      .then(function (r) {
        if (!(r && r.success && r.data)) throw new Error((r && r.data) ? r.data : 'Error');
        var rows = r.data.rows || [];
        if (!rows.length) {
          if (tb) tb.innerHTML = '<tr><td colspan="5" class="cmb-erp-text-muted" style="padding:14px;">Sin resultados</td></tr>';
          return;
        }
        tb.innerHTML = rows.map(function (c) {
          return '<tr>' +
            '<td><strong>#' + esc(c.id) + '</strong></td>' +
            '<td><strong>' + esc(c.nombre_legal || '') + '</strong></td>' +
            '<td>' + esc(c.nit_id || '') + '</td>' +
            '<td><span class="cmb-erp-badge cmb-erp-badge--info">' + esc(c.tipo_cliente || '—') + '</span></td>' +
            '<td class="cmb-erp-text-right"><button type="button" class="cmb-erp-btn cmb-erp-btn--primary cmb-erp-btn--sm" data-pick-client="' + esc(c.id) + '" data-pick-name="' + esc(c.nombre_legal || '') + '">Seleccionar</button></td>' +
          '</tr>';
        }).join('');
      })
      .catch(function (e) {
        console.error(e);
        if (tb) tb.innerHTML = '<tr><td colspan="5" class="cmb-erp-text-muted" style="padding:14px;">Error al buscar</td></tr>';
      });
  }

  // Services modal
  function searchServices(q) {
    var tb = byId('s_srv_tbody');
    if (tb) tb.innerHTML = '<tr><td colspan="4" class="cmb-erp-text-muted" style="padding:14px;">Cargando…</td></tr>';
    return apiPost(vars.actions.list_services, { q: q || '', page: 1, per_page: (vars.ui && vars.ui.per_page) ? vars.ui.per_page : 20 })
      .then(function (r) {
        if (!(r && r.success && r.data)) throw new Error((r && r.data) ? r.data : 'Error');
        var rows = r.data.rows || [];
        if (!rows.length) {
          if (tb) tb.innerHTML = '<tr><td colspan="4" class="cmb-erp-text-muted" style="padding:14px;">Sin resultados</td></tr>';
          return;
        }
        tb.innerHTML = rows.map(function (s) {
          var safe = esc(JSON.stringify(s)).replace(/&quot;/g, '&amp;quot;');
          return '<tr>' +
            '<td><code>' + esc(s.codigo_unico || '') + '</code></td>' +
            '<td><strong>' + esc(s.nombre_servicio || '') + '</strong></td>' +
            '<td class="cmb-erp-text-right">' + money(s.monto_unitario || 0) + '</td>' +
            '<td class="cmb-erp-text-right"><button type="button" class="cmb-erp-btn cmb-erp-btn--primary cmb-erp-btn--sm" data-pick-service="1" data-service-json="' + safe + '">Añadir</button></td>' +
          '</tr>';
        }).join('');
      })
      .catch(function (e) {
        console.error(e);
      });
  }

  // Quotes modal
  function searchQuotes(q) {
    var tb = byId('s_q_tbody');
    if (tb) tb.innerHTML = '<tr><td colspan="6" class="cmb-erp-text-muted" style="padding:14px;">Cargando…</td></tr>';
    return apiPost(vars.actions.list_quotes, { q: q || '', page: 1, per_page: (vars.ui && vars.ui.per_page) ? vars.ui.per_page : 20 })
      .then(function (r) {
        if (!(r && r.success && r.data)) throw new Error((r && r.data) ? r.data : 'Error');
        var rows = r.data.rows || [];
        
      // Filtro local (ID / Código / Cliente) para el buscador del modal
      // Normaliza: minúsculas, sin signos (#, guiones, etc.).
      function _qNorm(x) {
        x = String(x == null ? '' : x).toLowerCase();
        try { x = x.normalize('NFKD').replace(/[\u0300-\u036f]/g, ''); } catch (e) {}
        x = x.replace(/[^a-z0-9\s]+/g, ' ').replace(/\s+/g, ' ').trim();
        return x;
      }
      var qq = _qNorm(q);
      if (qq) {
        var toks = qq.split(' ').filter(Boolean);
        rows = rows.filter(function (qt) {
          var hay = _qNorm(String(qt.id || '') + ' ' + String(qt.cot_codigo || '') + ' ' + String(qt.nombre_legal || '') + ' ' + String(qt.cliente_id || ''));
          for (var i = 0; i < toks.length; i++) {
            if (hay.indexOf(toks[i]) === -1) return false;
          }
          return true;
        });
      }
if (!rows.length) {
          if (tb) tb.innerHTML = '<tr><td colspan="6" class="cmb-erp-text-muted" style="padding:14px;">Sin resultados</td></tr>';
          return;
        }
        tb.innerHTML = rows.map(function (qt) {
          return '<tr>' +
            '<td><strong>#' + esc(qt.id) + '</strong></td>' +
            '<td><strong>' + esc(qt.cot_codigo || '') + '</strong></td>' +
            '<td>' + esc(String(qt.fecha_emision || '').slice(0,10)) + '</td>' +
            '<td>' + esc(qt.nombre_legal || '') + '</td>' +
            '<td class="cmb-erp-text-right"><strong>' + money(qt.total || 0) + '</strong></td>' +
            '<td class="cmb-erp-text-right"><button type="button" class="cmb-erp-btn cmb-erp-btn--primary cmb-erp-btn--sm" data-pick-quote="' + esc(qt.id) + '" data-pick-code="' + esc(qt.cot_codigo || '') + '" data-pick-client="' + esc(qt.cliente_id || 0) + '" data-pick-clientname="' + esc(qt.nombre_legal || '') + '">Seleccionar</button></td>' +
          '</tr>';
        }).join('');
      })
      .catch(function (e) {
 console.error(e);
 if (tb) tb.innerHTML = '<tr><td colspan="6" class="cmb-erp-text-muted" style="padding:14px;">Error al buscar. Verifica permisos/nonce y recarga.</td></tr>';
 toast('Error al buscar cotizaciones.', false);
 });
  }

  function importQuoteItems() {
    if (!state.quote_id) {
      toast('Primero vincula una cotización emitida.', false);
      return;
    }
    toast('Importando ítems desde cotización…', true);
    return apiPost(vars.actions.get_quote, { id: state.quote_id })
      .then(function (r) {
        if (!(r && r.success && r.data)) throw new Error((r && r.data) ? r.data : 'Error');
        var d = r.data;
        if (d.client && d.client.id) {
          state.cliente_id = parseInt(d.client.id, 10) || state.cliente_id;
          state.cliente_nombre = d.client.nombre_legal || state.cliente_nombre;
        }
        state.items = (d.items || []).map(function (it) {
          return { n: it.n, p: it.p, c: it.c, m: it.m };
        });
        syncBadges();
        renderItems();
        toast('Ítems importados. Revisa y guarda.', true);
      })
      .catch(function (e) {
        console.error(e);
        toast('No se pudo importar.', false);
      });
  }

  function gatherPayload() {
    return {
      id: state.id,
      cliente_id: state.cliente_id,
      tipo_contrato: state.tipo,
      meses: state.meses,
      items: JSON.stringify(state.items),
      quote_id: state.quote_id,
      quote_code: state.quote_code
    };
  }

  function saveSale() {
    if (!state.cliente_id) return toast('Selecciona un cliente.', false);
    if (!state.items.length) return toast('Agrega al menos un ítem.', false);
    toast('Registrando venta…', true);
    return apiPost(vars.actions.save, gatherPayload())
      .then(function (r) {
        if (!(r && r.success && r.data)) throw new Error((r && r.data) ? r.data : 'Error');
        state.id = parseInt(r.data.id, 10) || state.id;
        syncBadges();
        byId('s_btn_delete').style.display = state.id ? '' : 'none';
        toast('enta registrada (ID #' + state.id + ').', true);
        loadHistory();
      })
      .catch(function (e) {
        console.error(e);
        toast((e && e._json && e._json.data) ? String(e._json.data) : 'Error al registrar', false);
      });
  }

  function loadSale(id) {
    id = parseInt(id || 0, 10) || 0;
    if (!id) return;
    toast('Cargando venta #' + id + '…', true);
    return apiPost(vars.actions.get, { id: id })
      .then(function (r) {
        if (!(r && r.success && r.data)) throw new Error((r && r.data) ? r.data : 'Error');
        var s = r.data.sale || {};
        var link = r.data.link || null;
        var cli = r.data.client || null;
        state.id = parseInt(s.id || id, 10) || id;
        state.cliente_id = parseInt(s.cliente_id || 0, 10) || 0;
        state.tipo = (s.tipo_contrato || 'UNICO');
        state.meses = parseInt(s.meses || 1, 10) || 1;
        state.items = (r.data.items || []).map(function (it) { return { n: it.n, p: it.p, c: it.c, m: it.m }; });
        state.quote_id = link && link.cotizacion_id ? parseInt(link.cotizacion_id, 10) || 0 : 0;
        state.quote_code = link && link.cot_codigo ? String(link.cot_codigo) : '';
        state.cliente_nombre = cli && cli.nombre_legal ? cli.nombre_legal : '';
        syncBadges();
        renderItems();
        byId('s_btn_delete').style.display = state.id ? '' : 'none';
        toast('Venta cargada.', true);
        window.scrollTo({ top: 0, behavior: 'smooth' });
      })
      .catch(function (e) {
        console.error(e);
        toast('No se pudo cargar.', false);
      });
  }

  function deleteSale() {
    if (!state.id) return;
    if (!window.confirm('¿Eliminar la venta #' + state.id + '? Solo si no tiene pagos/facturas.')) return;
    toast('Eliminando…', true);
    return apiPost(vars.actions.delete, { id: state.id })
      .then(function (r) {
        if (!(r && r.success)) throw new Error((r && r.data) ? r.data : 'Error');
        toast('Eliminada.', true);
        resetForm();
        loadHistory();
      })
      .catch(function (e) {
        console.error(e);
        toast((e && e._json && e._json.data) ? String(e._json.data) : 'No se pudo eliminar', false);
      });
  }

  function resetForm() {
    state.id = 0;
    state.cliente_id = 0;
    state.cliente_nombre = '';
    state.tipo = 'UNICO';
    state.meses = 1;
    state.quote_id = 0;
    state.quote_code = '';
    state.items = [];
    syncBadges();
    renderItems();
    byId('s_btn_delete').style.display = 'none';
    setMsg('', true);
  }

  function loadHistory() {
    var tb = byId('s_hist_tbody');
    if (tb) tb.innerHTML = '<tr><td colspan="8" class="cmb-erp-text-muted" style="padding:14px;">Cargando…</td></tr>';
    var limit = (vars.ui && vars.ui.history_limit) ? parseInt(vars.ui.history_limit, 10) : 50;
    return apiPost(vars.actions.history, { limit: limit })
      .then(function (r) {
        if (!(r && r.success && r.data)) throw new Error((r && r.data) ? r.data : 'Error');
        var rows = r.data.rows || [];
        if (!rows.length) {
          if (tb) tb.innerHTML = '<tr><td colspan="8" class="cmb-erp-text-muted" style="padding:14px;">Sin registros</td></tr>';
          return;
        }
        tb.innerHTML = rows.map(function (h) {
          var id = parseInt(h.id || 0, 10) || 0;
          var fecha = String(h.fecha_venta || '').slice(0, 10);
          var periodo = h.mes_correspondiente || '';
          var cliente = h.nombre_legal || '---';
          var total = money(h.total_bs || 0);
          var quote = h.quote_code ? h.quote_code : (h.quote_id ? ('#' + h.quote_id) : '—');
          var rec = (h.tipo_contrato === 'MENSUAL') ? ('Día: ' + (h.dia_facturacion || '—')) : 'Única';
          return (
            '<tr>' +
              '<td><strong>#' + id + '</strong>' + (h.venta_maestra_id ? '<br><span class="cmb-erp-text-muted">Clon de #' + esc(h.venta_maestra_id) + '</span>' : '') + '</td>' +
              '<td>' + esc(fecha) + '</td>' +
              '<td>' + esc(periodo) + '</td>' +
              '<td>' + esc(cliente) + '</td>' +
              '<td class="cmb-erp-text-right"><strong>' + total + '</strong> Bs</td>' +
              '<td>' + esc(quote) + '</td>' +
              '<td>' + esc(rec) + '</td>' +
              '<td class="cmb-erp-text-right">' +
                '<div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;">' +
                  '<button type="button" class="cmb-erp-btn cmb-erp-btn--primary cmb-erp-btn--sm" data-h-edit="' + id + '">Editar</button>' +
                  '<button type="button" class="cmb-erp-btn cmb-erp-btn--ghost cmb-erp-btn--sm" data-h-clone="' + id + '">Clonar</button>' +
                  '<button type="button" class="cmb-erp-btn cmb-erp-btn--dark cmb-erp-btn--sm" data-h-rec="' + id + '" data-h-dia="' + esc(h.dia_facturacion || 1) + '">Recurrencia</button>' +
                '</div>' +
              '</td>' +
            '</tr>'
          );
        }).join('');
      })
      .catch(function (e) {
        console.error(e);
        if (tb) tb.innerHTML = '<tr><td colspan="8" class="cmb-erp-text-muted" style="padding:14px;">Error</td></tr>';
      });
  }

  function bindModals() {
    // close buttons
    document.querySelectorAll('[data-s-close]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var id = btn.getAttribute('data-s-close');
        if (id) closeModal(id);
      });
    });
    // click outside
    ['cmb_sales_client_modal','cmb_sales_service_modal','cmb_sales_quote_modal','cmb_sales_clone_modal','cmb_sales_recurrence_modal'].forEach(function (mid) {
      var m = byId(mid);
      if (!m) return;
      m.addEventListener('click', function (e) {
        if (e.target === m) closeModal(mid);
      });
    });
  }

  function bindUI() {
    byId('s_btn_cliente').addEventListener('click', function (e) {
      e.preventDefault();
      openModal('cmb_sales_client_modal');
      searchClients('');
      var inp = byId('s_cli_search');
      if (inp) { inp.value=''; setTimeout(function(){inp.focus();}, 80); }
    });
    byId('s_btn_service').addEventListener('click', function (e) {
      e.preventDefault();
      openModal('cmb_sales_service_modal');
      searchServices('');
      var inp = byId('s_srv_search');
      if (inp) { inp.value=''; setTimeout(function(){inp.focus();}, 80); }
    });
    byId('s_btn_quote').addEventListener('click', function (e) {
      e.preventDefault();
      openModal('cmb_sales_quote_modal');
      searchQuotes('');
      var inp = byId('s_q_search');
      if (inp) { inp.value=''; setTimeout(function(){inp.focus();}, 80); }
    });
    byId('s_btn_quote_clear').addEventListener('click', function (e) {
      e.preventDefault();
      state.quote_id = 0;
      state.quote_code = '';
      if (byId('s_quote_label')) byId('s_quote_label').value = '';
      syncBadges();
      toast('Vínculo con cotización removido.', true);
    });
    byId('s_btn_import_quote').addEventListener('click', function (e) {
      e.preventDefault();
      importQuoteItems();
    });
    byId('s_btn_manual').addEventListener('click', function (e) {
      e.preventDefault();
      addManual();
    });
    byId('s_btn_save').addEventListener('click', function (e) {
      e.preventDefault();
      saveSale();
    });
    byId('s_btn_delete').addEventListener('click', function (e) {
      e.preventDefault();
      deleteSale();
    });
    byId('s_hist_reload').addEventListener('click', function (e) {
      e.preventDefault();
      loadHistory();
    });
    document.querySelectorAll('[data-s="new"]').forEach(function(btn){
      btn.addEventListener('click', function(e){ e.preventDefault(); resetForm(); toast('Formulario reiniciado.', true); });
    });

    // select fields
    byId('s_tipo').addEventListener('change', function(){ state.tipo = byId('s_tipo').value || 'UNICO'; });
    byId('s_meses').addEventListener('change', function(){ state.meses = parseInt(byId('s_meses').value || '1', 10) || 1; if (state.meses<1) state.meses=1; });

    // Search inputs with debounce
    var t1, t2, t3;
    byId('s_cli_search').addEventListener('input', function(){
      window.clearTimeout(t1);
      var q = (byId('s_cli_search').value || '').trim();
      t1 = window.setTimeout(function(){ searchClients(q); }, 180);
    });
    byId('s_srv_search').addEventListener('input', function(){
      window.clearTimeout(t2);
      var q = (byId('s_srv_search').value || '').trim();
      t2 = window.setTimeout(function(){ searchServices(q); }, 180);
    });
    byId('s_q_search').addEventListener('input', function(){
      window.clearTimeout(t3);
      var q = (byId('s_q_search').value || '').trim();
      t3 = window.setTimeout(function(){ searchQuotes(q); }, 180);
    });

    // Tbody interactions
    byId('s_items_tbody').addEventListener('click', function(e){
      var del = e.target && e.target.closest ? e.target.closest('[data-it-del]') : null;
      if (del) {
        var idx = parseInt(del.getAttribute('data-it-del') || '-1', 10);
        if (idx>=0) { state.items.splice(idx,1); renderItems(); }
      }
    });
    byId('s_items_tbody').addEventListener('change', function(e){
      var el = e.target;
      if (!el) return;
      var idx = parseInt(el.getAttribute('data-it-idx') || '-1', 10);
      var k = el.getAttribute('data-it-k') || '';
      if (idx<0 || !state.items[idx] || !k) return;
      var v = el.value;
      if (k === 'p') state.items[idx].p = parseFloat(v || '0') || 0;
      else if (k === 'c') state.items[idx].c = parseInt(v || '1', 10) || 1;
      else state.items[idx].n = v;
      renderItems();
    });

    // Modal pick buttons
    byId('s_cli_tbody').addEventListener('click', function(e){
      var btn = e.target && e.target.closest ? e.target.closest('[data-pick-client]') : null;
      if (!btn) return;
      state.cliente_id = parseInt(btn.getAttribute('data-pick-client') || '0', 10) || 0;
      state.cliente_nombre = btn.getAttribute('data-pick-name') || '';
      syncBadges();
      closeModal('cmb_sales_client_modal');
      toast('Cliente seleccionado.', true);
    });
    byId('s_srv_tbody').addEventListener('click', function(e){
      var btn = e.target && e.target.closest ? e.target.closest('[data-pick-service]') : null;
      if (!btn) return;
      var raw = btn.getAttribute('data-service-json') || '{}';
      var obj = {};
      try { obj = JSON.parse(raw); } catch (err) { obj = {}; }
      addServiceItem(obj);
    });
    byId('s_q_tbody').addEventListener('click', function(e){
      var btn = e.target && e.target.closest ? e.target.closest('[data-pick-quote]') : null;
      if (!btn) return;
      state.quote_id = parseInt(btn.getAttribute('data-pick-quote') || '0', 10) || 0;
      state.quote_code = btn.getAttribute('data-pick-code') || '';
      // Set client from quote selection if empty or different
      var qcli = parseInt(btn.getAttribute('data-pick-client') || '0', 10) || 0;
      var qname = btn.getAttribute('data-pick-clientname') || '';
      if (qcli) {
        state.cliente_id = qcli;
        state.cliente_nombre = qname;
      }
      syncBadges();
      closeModal('cmb_sales_quote_modal');
      toast('Cotización vinculada. Puedes importar ítems.', true);
    });

    // History actions
    byId('s_hist_tbody').addEventListener('click', function(e){
      var edit = e.target && e.target.closest ? e.target.closest('[data-h-edit]') : null;
      if (edit) {
        var id = parseInt(edit.getAttribute('data-h-edit') || '0', 10) || 0;
        if (id) loadSale(id);
        return;
      }
      var cl = e.target && e.target.closest ? e.target.closest('[data-h-clone]') : null;
      if (cl) {
        var cid = parseInt(cl.getAttribute('data-h-clone') || '0', 10) || 0;
        if (!cid) return;
        byId('s_clone_id').value = String(cid);
        byId('s_clone_date').valueAsDate = new Date();
        byId('s_clone_error').textContent = '';
        openModal('cmb_sales_clone_modal');
        return;
      }
      var rec = e.target && e.target.closest ? e.target.closest('[data-h-rec]') : null;
      if (rec) {
        var rid = parseInt(rec.getAttribute('data-h-rec') || '0', 10) || 0;
        var dia = parseInt(rec.getAttribute('data-h-dia') || '1', 10) || 1;
        byId('s_rec_id').value = String(rid);
        byId('s_rec_day').value = String(dia);
        byId('s_rec_error').textContent = '';
        openModal('cmb_sales_recurrence_modal');
      }
    });

    // Clone exec
    byId('s_clone_exec').addEventListener('click', function(){
      var id = parseInt(byId('s_clone_id').value || '0', 10) || 0;
      var fecha = (byId('s_clone_date').value || '').trim();
      if (!id || !fecha) { byId('s_clone_error').textContent = 'Selecciona fecha.'; return; }
      byId('s_clone_error').textContent = '';
      toast('Clonando…', true);
      apiPost(vars.actions.clone, { id: id, fecha: fecha }).then(function(r){
        if (!(r && r.success && r.data)) throw new Error((r && r.data) ? r.data : 'Error');
        toast('Clon creado (ID #' + r.data.id + ').', true);
        closeModal('cmb_sales_clone_modal');
        loadHistory();
      }).catch(function(e){
        console.error(e);
        byId('s_clone_error').textContent = (e && e._json && e._json.data) ? String(e._json.data) : 'Error al clonar';
      });
    });

    // Recurrence exec
    byId('s_rec_exec').addEventListener('click', function(){
      var id = parseInt(byId('s_rec_id').value || '0', 10) || 0;
      var dia = parseInt(byId('s_rec_day').value || '0', 10) || 0;
      if (!id || dia<1 || dia>28) { byId('s_rec_error').textContent = 'Día inválido.'; return; }
      byId('s_rec_error').textContent = '';
      toast('Actualizando recurrencia…', true);
      apiPost(vars.actions.recurrence, { id: id, dia: dia }).then(function(r){
        if (!(r && r.success)) throw new Error((r && r.data) ? r.data : 'Error');
        toast('Recurrencia actualizada.', true);
        closeModal('cmb_sales_recurrence_modal');
        loadHistory();
      }).catch(function(e){
        console.error(e);
        byId('s_rec_error').textContent = (e && e._json && e._json.data) ? String(e._json.data) : 'Error al actualizar';
      });
    });
  }

  function init() {
    if (!byId('cmb_sales_root') || !vars.ajaxurl) return;
    vars.actions = vars.actions || {};
    // defaults
    vars.actions.search_clients = vars.actions.search_clients || 'cmb_sales_search_clients';
    vars.actions.list_services = vars.actions.list_services || 'cmb_sales_list_services';
    vars.actions.list_quotes = vars.actions.list_quotes || 'cmb_sales_list_emitted_quotes';
    vars.actions.get_quote = vars.actions.get_quote || 'cmb_sales_get_quote_payload';
    vars.actions.save = vars.actions.save || 'cmb_sales_save';
    vars.actions.get = vars.actions.get || 'cmb_sales_get';
    vars.actions.delete = vars.actions.delete || 'cmb_sales_delete';
    vars.actions.clone = vars.actions.clone || 'cmb_sales_clone_manual';
    vars.actions.recurrence = vars.actions.recurrence || 'cmb_sales_set_recurrence';
    vars.actions.history = vars.actions.history || 'cmb_sales_history';

    bindModals();
    bindUI();
    syncBadges();
    renderItems();
    loadHistory();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

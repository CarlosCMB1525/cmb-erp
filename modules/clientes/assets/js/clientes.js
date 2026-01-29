/* global window, document */
(function () {
  'use strict';

  var vars = window.cmbClientesVars || {};

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
    var el = byId('cl_msg');
    if (!el) return;
    el.style.color = ok === false ? '#ef4444' : '#10b981';
    el.textContent = String(text || '');
    window.setTimeout(function () { if (el) el.textContent = ''; }, 3500);
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

  function showModal(show) {
    var m = byId('cl_contact_modal');
    if (!m) return;
    m.style.display = show ? 'flex' : 'none';
  }

  function resetCompanyForm() {
    if (byId('cl_emp_id')) byId('cl_emp_id').value = '0';
    if (byId('cl_nombre')) byId('cl_nombre').value = '';
    if (byId('cl_nit')) byId('cl_nit').value = '';
    if (byId('cl_razon')) byId('cl_razon').value = '';
    if (byId('cl_tipo')) byId('cl_tipo').value = 'EMPRESA';
  }

  function renderTbody(html) {
    var tb = byId('cl_tbody');
    if (!tb) return;
    tb.innerHTML = html || '<tr><td colspan="4" class="cmb-erp-text-muted" style="padding:20px;text-align:center;">Sin resultados</td></tr>';
  }

  function reloadRecent() {
    var tb = byId('cl_tbody');
    if (tb) tb.innerHTML = '<tr><td colspan="4" class="cmb-erp-text-muted" style="padding:20px;text-align:center;">Cargando…</td></tr>';
    return apiPost(vars.actions.list_recent, {}).then(function (r) {
      if (!(r && r.success && r.data)) throw new Error((r && r.data) ? r.data : 'Error');
      renderTbody(r.data.tbody);
      toast('Cartera actualizada.', true);
    }).catch(function (e) {
      console.error(e);
      renderTbody('<tr><td colspan="4" class="cmb-erp-text-muted" style="padding:20px;text-align:center;">Error al cargar</td></tr>');
      toast('Error al cargar cartera.', false);
    });
  }

  function saveCompany() {
    var nombre = (byId('cl_nombre').value || '').trim();
    var nit = (byId('cl_nit').value || '').trim();
    if (!nombre) return setMsg('Nombre Legal es obligatorio.', false);
    if (!nit) return setMsg('NIT / ID es obligatorio.', false);

    setMsg('Guardando...', true);
    return apiPost(vars.actions.save_company, {
      id: byId('cl_emp_id').value,
      nombre_legal: nombre,
      nit_id: nit,
      razon_social: byId('cl_razon').value,
      tipo_cliente: byId('cl_tipo').value
    }).then(function (r) {
      if (!(r && r.success && r.data)) throw new Error((r && r.data) ? r.data : 'Error');
      toast(r.data.msg || 'Guardado correcto', true);
      resetCompanyForm();
      reloadRecent();
    }).catch(function (e) {
      console.error(e);
      toast((e && e._json && e._json.data) ? String(e._json.data) : 'Error al guardar', false);
      setMsg('Error al guardar.', false);
    });
  }

  function editCompany(id) {
    id = parseInt(id || 0, 10) || 0;
    if (!id) return;
    setMsg('Cargando empresa...', true);
    return apiPost(vars.actions.get_company, { id: id }).then(function (r) {
      if (!(r && r.success && r.data && r.data.empresa)) throw new Error((r && r.data) ? r.data : 'Error');
      var e = r.data.empresa;
      byId('cl_emp_id').value = e.id;
      byId('cl_nombre').value = e.nombre_legal || '';
      byId('cl_nit').value = e.nit_id || '';
      byId('cl_razon').value = e.razon_social || '';
      byId('cl_tipo').value = e.tipo_cliente || 'EMPRESA';
      window.scrollTo({ top: 0, behavior: 'smooth' });
      setMsg('Editando empresa #' + e.id, true);
    }).catch(function (e) {
      console.error(e);
      toast('No se pudo cargar empresa.', false);
      setMsg('Error al cargar empresa.', false);
    });
  }

  function deleteCompany(id) {
    id = parseInt(id || 0, 10) || 0;
    if (!id) return;
    if (!window.confirm('¿Eliminar empresa #' + id + '? Solo si NO tiene contactos.')) return;
    return apiPost(vars.actions.delete_company, { id: id }).then(function (r) {
      if (!(r && r.success)) throw new Error((r && r.data) ? r.data : 'Error');
      toast('Empresa eliminada.', true);
      reloadRecent();
    }).catch(function (e) {
      console.error(e);
      toast((e && e._json && e._json.data) ? String(e._json.data) : 'No se pudo eliminar', false);
    });
  }

  function openContactModal(empId, empNombre) {
    byId('cl_contact_id').value = '0';
    byId('cl_empresa_id').value = String(empId || 0);
    byId('cl_modal_title').textContent = 'Agregar Contacto';
    byId('cl_modal_sub').textContent = 'Empresa: ' + (empNombre || '');
    byId('cl_c_nombre').value = '';
    byId('cl_c_tel').value = '';
    byId('cl_c_email').value = '';
    byId('cl_c_cargo').value = '';
    byId('cl_modal_err').textContent = '';
    showModal(true);
  }

  function editContact(id) {
    id = parseInt(id || 0, 10) || 0;
    if (!id) return;
    byId('cl_modal_err').textContent = '';
    return apiPost(vars.actions.get_contact, { id: id }).then(function (r) {
      if (!(r && r.success && r.data && r.data.contacto)) throw new Error((r && r.data) ? r.data : 'Error');
      var c = r.data.contacto;
      byId('cl_contact_id').value = c.id;
      byId('cl_empresa_id').value = c.empresa_id;
      byId('cl_modal_title').textContent = 'Editar Contacto';
      byId('cl_modal_sub').textContent = 'Empresa ID: ' + c.empresa_id;
      byId('cl_c_nombre').value = c.nombre_contacto || '';
      byId('cl_c_tel').value = c.telefono_whatsapp || '';
      byId('cl_c_email').value = c.correo_electronico || '';
      byId('cl_c_cargo').value = c.cargo || '';
      showModal(true);
    }).catch(function (e) {
      console.error(e);
      toast('No se pudo cargar contacto.', false);
    });
  }

  function saveContact() {
    var empId = parseInt(byId('cl_empresa_id').value || '0', 10) || 0;
    var nom = (byId('cl_c_nombre').value || '').trim();
    if (!empId) return byId('cl_modal_err').textContent = 'Empresa inválida.';
    if (!nom) return byId('cl_modal_err').textContent = 'Nombre del contacto es obligatorio.';

    byId('cl_modal_err').textContent = '';
    return apiPost(vars.actions.save_contact, {
      id: byId('cl_contact_id').value,
      empresa_id: empId,
      nombre_contacto: nom,
      telefono_whatsapp: byId('cl_c_tel').value,
      correo_electronico: byId('cl_c_email').value,
      cargo: byId('cl_c_cargo').value
    }).then(function (r) {
      if (!(r && r.success)) throw new Error((r && r.data) ? r.data : 'Error');
      toast((r.data && r.data.msg) ? r.data.msg : 'Contacto guardado.', true);
      showModal(false);
      reloadRecent();
    }).catch(function (e) {
      console.error(e);
      var msg = (e && e._json && e._json.data) ? String(e._json.data) : 'Error al guardar contacto';
      byId('cl_modal_err').textContent = msg;
      toast(msg, false);
    });
  }

  function deleteContact(id) {
    id = parseInt(id || 0, 10) || 0;
    if (!id) return;
    if (!window.confirm('¿Eliminar este contacto?')) return;
    return apiPost(vars.actions.delete_contact, { id: id }).then(function (r) {
      if (!(r && r.success)) throw new Error((r && r.data) ? r.data : 'Error');
      toast('Contacto eliminado.', true);
      reloadRecent();
    }).catch(function (e) {
      console.error(e);
      toast((e && e._json && e._json.data) ? String(e._json.data) : 'No se pudo eliminar', false);
    });
  }

  var searchTimer = null;
  function searchPortfolio(q) {
    q = String(q || '').trim();
    if (!q) {
      reloadRecent();
      return;
    }
    var tb = byId('cl_tbody');
    if (tb) tb.innerHTML = '<tr><td colspan="4" class="cmb-erp-text-muted" style="padding:20px;text-align:center;">Cargando…</td></tr>';
    return apiPost(vars.actions.search_portfolio, { q: q }).then(function (r) {
      if (!(r && r.success && r.data)) throw new Error((r && r.data) ? r.data : 'Error');
      renderTbody(r.data.tbody);
    }).catch(function (e) {
      console.error(e);
      renderTbody('<tr><td colspan="4" class="cmb-erp-text-muted" style="padding:20px;text-align:center;">Error al buscar</td></tr>');
      toast('Error al buscar.', false);
    });
  }

  function bind() {
    if (!byId('cmb_clientes_root') || !vars.ajaxurl || !vars.actions) return;

    // Buttons
    byId('btn_emp_save').addEventListener('click', function (e) { e.preventDefault(); saveCompany(); });
    byId('btn_emp_cancel').addEventListener('click', function (e) { e.preventDefault(); resetCompanyForm(); });
    byId('btn_emp_reload').addEventListener('click', function (e) { e.preventDefault(); reloadRecent(); });

    // Modal
    byId('cl_modal_close').addEventListener('click', function () { showModal(false); });
    byId('cl_btn_cancel_contact').addEventListener('click', function () { showModal(false); });
    byId('cl_btn_save_contact').addEventListener('click', function () { saveContact(); });
    byId('cl_contact_modal').addEventListener('click', function (e) { if (e.target === byId('cl_contact_modal')) showModal(false); });

    // Delegated actions
    byId('cl_tbody').addEventListener('click', function (e) {
      var t = e.target;
      var btn;

      btn = t.closest('[data-cl-edit-company]');
      if (btn) return editCompany(btn.getAttribute('data-cl-edit-company'));

      btn = t.closest('[data-cl-del-company]');
      if (btn) return deleteCompany(btn.getAttribute('data-cl-del-company'));

      btn = t.closest('[data-cl-add-contact]');
      if (btn) return openContactModal(btn.getAttribute('data-cl-add-contact'), btn.getAttribute('data-cl-name') || '');

      btn = t.closest('[data-cl-edit-contact]');
      if (btn) return editContact(btn.getAttribute('data-cl-edit-contact'));

      btn = t.closest('[data-cl-del-contact]');
      if (btn) return deleteContact(btn.getAttribute('data-cl-del-contact'));
    });

    // Search with debounce
    byId('cl_search').addEventListener('input', function () {
      var q = (this.value || '').trim();
      window.clearTimeout(searchTimer);
      searchTimer = window.setTimeout(function () { searchPortfolio(q); }, 220);
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();

})();

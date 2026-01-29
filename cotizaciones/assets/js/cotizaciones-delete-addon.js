/* global window, document */
(function () {
  'use strict';

  var vars = window.cmbQuotesVars || {};

  function byId(id) { return document.getElementById(id); }

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
    }).then(function (r) { return r.json(); });
  }

  function setMsg(text, ok) {
    var el = byId('cmb_quotes_msg');
    if (!el) return;
    el.style.color = ok === false ? '#ef4444' : '#10b981';
    el.textContent = text || '';
    if (text) {
      window.clearTimeout(setMsg._t);
      setMsg._t = window.setTimeout(function () { el.textContent = ''; }, 3200);
    }
  }

  function refreshHistory() {
    // Dispara click en recargar si existe, para reutilizar el JS principal.
    var b = byId('q_hist_reload');
    if (b) b.click();
  }

  function handleDelete(id) {
    id = parseInt(id || 0, 10) || 0;
    if (!id) return;
    if (!window.confirm('¿Eliminar la cotización #' + id + '? Esta acción no se puede deshacer.')) return;

    var action = (vars.actions && (vars.actions.delete_quote || vars.actions.deleteQuote)) || 'cmb_quotes_delete';
    setMsg('Eliminando #' + id + '…', true);

    apiPost(action, { id: id }).then(function (r) {
      if (!(r && r.success)) {
        setMsg((r && r.data) ? r.data : 'Error al eliminar', false);
        return;
      }
      setMsg('Eliminada #' + id + '.', true);
      refreshHistory();
    }).catch(function (e) {
      // eslint-disable-next-line no-console
      console.error(e);
      setMsg('Fallo de red al eliminar', false);
    });
  }

  function injectDeleteButtons() {
    var tb = byId('q_hist_tbody');
    if (!tb) return;
    // Delegación: cualquier botón con data-hist-del
    tb.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('[data-hist-del]') : null;
      if (!btn) return;
      var id = btn.getAttribute('data-hist-del');
      handleDelete(id);
    });
  }

  // Observa cambios de la tabla historial y agrega botón si falta.
  function observeHistory() {
    var tb = byId('q_hist_tbody');
    if (!tb) return;

    var ensure = function () {
      var rows = tb.querySelectorAll('tr');
      rows.forEach(function (tr) {
        var editBtn = tr.querySelector('[data-hist-edit]');
        if (!editBtn) return;
        // ya tiene delete
        if (tr.querySelector('[data-hist-del]')) return;
        var id = editBtn.getAttribute('data-hist-edit');
        var actionsCell = tr.lastElementChild;
        if (!actionsCell) return;
        // Agrega un botón pequeño
        var del = document.createElement('button');
        del.type = 'button';
        del.className = 'cmb-erp-btn cmb-erp-btn--ghost cmb-erp-btn--sm';
        del.textContent = '🗑 Eliminar';
        del.setAttribute('data-hist-del', id);
        // Si ya hay un wrapper div, lo usamos
        var wrap = actionsCell.querySelector('div');
        if (wrap) wrap.appendChild(del);
        else actionsCell.appendChild(del);
      });
    };

    // observer
    var obs = new MutationObserver(function () { ensure(); });
    obs.observe(tb, { childList: true, subtree: true });
    ensure();
  }

  function init() {
    if (!vars.ajaxurl) return;
    injectDeleteButtons();
    observeHistory();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();

})();

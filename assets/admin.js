(function () {
  'use strict';

  const TAB_KEY = 'egbp_active_tab';

  const $ = function (sel, ctx) {
    return (ctx || document).querySelector(sel);
  };

  const $$ = function (sel, ctx) {
    return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
  };

  function activateTab(name) {
    $$('.egbp-tab').forEach(function (btn) {
      const active = btn.dataset.tab === name;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });

    $$('.egbp-panel').forEach(function (panel) {
      panel.classList.toggle('is-active', panel.id === 'egbp-panel-' + name);
    });

    $$('.egbp-help-card').forEach(function (card) {
      card.style.display = card.id === 'egbp-help-' + name ? '' : 'none';
    });

    try {
      sessionStorage.setItem(TAB_KEY, name);
    } catch (e) {}
  }

  function setStatus(text, cls) {
    const el = $('#egbp-status');

    if (!el) {
      return;
    }

    el.textContent = text || '';
    el.className = 'egbp-status' + (cls ? ' ' + cls : '');
  }

  function appendLog(obj) {
    const el = $('#egbp-log');

    if (!el) {
      return;
    }

    const line = typeof obj === 'string' ? obj : JSON.stringify(obj, null, 2);
    el.textContent += line + '\n';
    el.scrollTop = el.scrollHeight;
  }

  function syncSourceUI() {
    const source = $('#egbp_source');
    const gbpFields = $('#egbp-fields-gbp');
    const placesFields = $('#egbp-fields-places');
    const tipsGbp = $('#egbp-tips-gbp');
    const tipsPlaces = $('#egbp-tips-places');

    if (!source) {
      return;
    }

    const isPlaces = source.value === 'places_api';

    if (gbpFields) {
      gbpFields.style.display = isPlaces ? 'none' : '';
    }

    if (placesFields) {
      placesFields.style.display = isPlaces ? '' : 'none';
    }

    if (tipsGbp) {
      tipsGbp.style.display = isPlaces ? 'none' : '';
    }

    if (tipsPlaces) {
      tipsPlaces.style.display = isPlaces ? '' : 'none';
    }
  }

  function runImport() {
    const btn = $('#egbp-update-btn');

    if (!btn) {
      return;
    }

    btn.classList.add('egbp-running');
    setStatus(EGBP.i18n.updating);

    const log = $('#egbp-log');

    if (log) {
      log.textContent = '';
    }

    const body = new URLSearchParams();
    body.append('action', 'egbp_update_reviews');
    body.append('_wpnonce', EGBP.nonce);

    fetch(EGBP.ajax, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8'
      },
      body: body.toString()
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data || !data.success) {
          setStatus(EGBP.i18n.error, 'error');
          appendLog(data && data.data ? data.data : 'Unknown error');
          appendLog(EGBP.i18n.seeLog);
          return;
        }

        const res = data.data;

        setStatus(EGBP.i18n.done);

        appendLog({
          pages: res.pages,
          fetched: res.fetched,
          created: res.created,
          skipped: res.skipped
        });

        if (res.log && res.log.length) {
          appendLog('--- LOG ---');
          res.log.forEach(function (item) {
            appendLog(item);
          });
        }
      })
      .catch(function (err) {
        setStatus(EGBP.i18n.error, 'error');
        appendLog(err && err.message ? err.message : String(err));
      })
      .finally(function () {
        btn.classList.remove('egbp-running');
      });
  }

  function initTabs() {
    $$('.egbp-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        activateTab(btn.dataset.tab);
      });
    });

    let saved = '';

    try {
      saved = sessionStorage.getItem(TAB_KEY);
    } catch (e) {}

    if (saved && $('#egbp-panel-' + saved)) {
      activateTab(saved);
      return;
    }

    const activeTab = $('.egbp-tab.is-active');

    if (activeTab && activeTab.dataset.tab) {
      activateTab(activeTab.dataset.tab);
      return;
    }

    activateTab('connection');
  }

  function initSourceSwitcher() {
    const source = $('#egbp_source');

    if (!source) {
      return;
    }

    source.addEventListener('change', syncSourceUI);
    syncSourceUI();
  }

  function bindImportButton() {
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('#egbp-update-btn');

      if (!btn) {
        return;
      }

      e.preventDefault();
      runImport();
    });
  }

  function init() {
    initTabs();
    initSourceSwitcher();
    bindImportButton();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
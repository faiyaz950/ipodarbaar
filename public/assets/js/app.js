/* IPO Darbaar — site interactions (vanilla JS, no build step) */
(function () {
  'use strict';

  var $ = function (s, el) { return (el || document).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };
  var store = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  };

  /* ---------- Toast ---------- */
  var toastTimer;
  function toast(msg) {
    var el = $('[data-toast]');
    if (!el) return;
    el.textContent = msg;
    el.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.classList.remove('show'); }, 2200);
  }
  window.darbaarToast = toast;

  /* ---------- Theme ---------- */
  $$('[data-theme-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
      store.set('theme', next);
    });
  });

  /* ---------- Mobile drawer ---------- */
  var drawer = $('[data-drawer]');
  var menuBtn = $('[data-menu-toggle]');
  if (drawer && menuBtn) {
    menuBtn.addEventListener('click', function () {
      var open = !drawer.classList.contains('open');
      drawer.classList.toggle('open', open);
      document.body.classList.toggle('drawer-open', open);
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  /* ---------- Search suggestions ---------- */
  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  $$('[data-search]').forEach(function (form) {
    var input = $('[data-search-input]', form);
    var box = $('[data-suggest]', form);
    var timer, controller, focusIndex = -1;

    function close() { box.classList.remove('show'); focusIndex = -1; }

    function render(items, q) {
      if (!items.length) {
        box.innerHTML = '<div class="s-empty">No IPOs match “' + escapeHtml(q) + '”</div>';
      } else {
        box.innerHTML = items.map(function (i) {
          return '<a href="' + i.url + '"><span style="flex:1;min-width:0"><span class="s-name">' + escapeHtml(i.name) +
            '</span><span class="s-meta"> · ' + escapeHtml(i.type) + ' · ' + escapeHtml(i.meta) + '</span></span>' +
            '<span class="badge b-' + i.statusKey + '">' + escapeHtml(i.status) + '</span></a>';
        }).join('') + '<a class="s-all" href="' + window.IPO_DARBAAR.searchUrl + '?q=' + encodeURIComponent(q) + '">See all results</a>';
      }
      box.classList.add('show');
    }

    input.addEventListener('input', function () {
      var q = input.value.trim();
      clearTimeout(timer);
      if (q.length < 2) { close(); return; }
      timer = setTimeout(function () {
        if (controller) controller.abort();
        controller = 'AbortController' in window ? new AbortController() : null;
        fetch(window.IPO_DARBAAR.suggestUrl + '?q=' + encodeURIComponent(q), {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          signal: controller ? controller.signal : undefined
        }).then(function (r) { return r.json(); })
          .then(function (items) { render(items, q); })
          .catch(function () {});
      }, 180);
    });

    input.addEventListener('keydown', function (e) {
      var links = $$('a', box);
      if (!box.classList.contains('show') || !links.length) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        focusIndex = (focusIndex + (e.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
        links.forEach(function (l, i) { l.classList.toggle('focus', i === focusIndex); });
      } else if (e.key === 'Enter' && focusIndex >= 0) {
        e.preventDefault();
        window.location = links[focusIndex].href;
      } else if (e.key === 'Escape') {
        close();
      }
    });

    document.addEventListener('click', function (e) { if (!form.contains(e.target)) close(); });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === '/' && !/input|textarea|select/i.test(document.activeElement.tagName)) {
      var input = $('.header-actions [data-search-input]');
      if (input && input.offsetParent !== null) { e.preventDefault(); input.focus(); }
    }
  });

  /* ---------- Tabs ---------- */
  $$('[data-tabs]').forEach(function (root) {
    var tabs = $$('[data-tab]', root);
    var panes = $$('[data-pane]', root);
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var key = tab.getAttribute('data-tab');
        tabs.forEach(function (t) { t.classList.toggle('active', t === tab); t.setAttribute('aria-selected', t === tab); });
        panes.forEach(function (p) { p.classList.toggle('active', p.getAttribute('data-pane') === key); });
        var more = $('[data-tab-more]', root);
        if (more && tab.getAttribute('data-more')) more.href = tab.getAttribute('data-more');
      });
    });
  });

  /* ---------- Client-side type filter (All / Mainboard / SME) ---------- */
  $$('[data-type-filter]').forEach(function (group) {
    var scope = document.getElementById(group.getAttribute('data-type-filter'));
    $$('button', group).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var type = btn.getAttribute('data-value');
        $$('button', group).forEach(function (b) { b.classList.toggle('active', b === btn); });
        $$('tr[data-type]', scope).forEach(function (row) {
          row.style.display = (type === 'all' || row.getAttribute('data-type') === type) ? '' : 'none';
        });
      });
    });
  });

  /* ---------- Share / copy ---------- */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-share]');
    if (!btn) return;
    e.preventDefault();
    var url = btn.getAttribute('data-url') || window.location.href;
    var title = btn.getAttribute('data-title') || document.title;
    var mode = btn.getAttribute('data-share');

    if (mode === 'whatsapp') {
      window.open('https://wa.me/?text=' + encodeURIComponent(title + ' ' + url), '_blank', 'noopener');
      return;
    }
    if (mode === 'x') {
      window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent(title) + '&url=' + encodeURIComponent(url), '_blank', 'noopener');
      return;
    }
    if (mode === 'native' && navigator.share) {
      navigator.share({ title: title, url: url }).catch(function () {});
      return;
    }
    if (navigator.clipboard) {
      navigator.clipboard.writeText(url).then(function () { toast('Link copied'); }, function () { toast(url); });
    } else {
      toast(url);
    }
  });

  /* ---------- Language toggle (English / Hinglish) ---------- */
  function applyLang(lang) {
    $$('[data-lang-root]').forEach(function (root) { root.classList.toggle('lang-hi', lang === 'hi'); });
    $$('[data-lang-btn]').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-lang-btn') === lang); });
  }
  if ($('[data-lang-root]')) {
    applyLang(store.get('newsLang') || 'en');
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-lang-btn]');
      if (!b) return;
      var lang = b.getAttribute('data-lang-btn');
      store.set('newsLang', lang);
      applyLang(lang);
      document.dispatchEvent(new CustomEvent('darbaar:lang', { detail: lang }));
    });
  }
  window.darbaarApplyLang = applyLang;
})();

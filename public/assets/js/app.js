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
    var compareUrl = form.getAttribute('data-compare-url');
    var timer, controller, focusIndex = -1;

    function close() { box.classList.remove('show'); focusIndex = -1; }

    function render(items, q) {
      if (!items.length) {
        box.innerHTML = '<div class="s-empty">No IPOs match “' + escapeHtml(q) + '”</div>';
      } else {
        box.innerHTML = items.map(function (i) {
          var href = compareUrl ? compareUrl.replace('__SLUG__', encodeURIComponent(i.slug)) : i.url;
          return '<a href="' + escapeHtml(href) + '"><span style="flex:1;min-width:0"><span class="s-name">' + escapeHtml(i.name) +
            '</span><span class="s-meta"> · ' + escapeHtml(i.type) + ' · ' + escapeHtml(i.meta) + '</span></span>' +
            '<span class="badge b-' + i.statusKey + '">' + escapeHtml(i.status) + '</span></a>';
        }).join('') + (compareUrl ? '' : '<a class="s-all" href="' + window.IPO_DARBAAR.searchUrl + '?q=' + encodeURIComponent(q) + '">See all results</a>');
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

    if (compareUrl) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var first = $('a', box);
        if (first) window.location = first.href;
      });
    }

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
    track('share', { method: mode, url: url });

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
    if (lang === 'hi') {
      $$('template[data-hi-template]').forEach(function (tpl) {
        var target = document.getElementById(tpl.getAttribute('data-target'));
        if (target && !target.childNodes.length) target.appendChild(tpl.content.cloneNode(true));
      });
    }
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

  /* ---------- Analytics events (no-op unless GA4 is configured) ---------- */
  function track(name, params) {
    if (typeof window.darbaarTrack === 'function') window.darbaarTrack(name, params);
  }

  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-track]');
    if (el) track(el.getAttribute('data-track'), { label: el.getAttribute('data-track-label') || '' });
  });

  var compareTable = $('.compare-table');
  if (compareTable) track('compare', { count: $$('thead th', compareTable).length - 1 });

  function csrfToken() {
    var meta = $('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function postJson(url, data) {
    return fetch(url, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin',
      body: JSON.stringify(data)
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (body) { return { ok: r.ok, status: r.status, body: body }; });
    });
  }

  /* ---------- Watchlist (stored in this browser only) ---------- */
  var WATCH_KEY = 'darbaar:watchlist';
  var WATCH_MAX = 50;

  function watchlist() {
    try {
      var list = JSON.parse(store.get(WATCH_KEY) || '[]');
      return Array.isArray(list) ? list.filter(function (s) { return typeof s === 'string' && /^[a-z0-9-]{1,120}$/.test(s); }) : [];
    } catch (e) { return []; }
  }

  function syncWatchUi() {
    var list = watchlist();
    $$('[data-watch]').forEach(function (btn) {
      var on = list.indexOf(btn.getAttribute('data-watch')) !== -1;
      btn.classList.toggle('on', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      var label = $('[data-watch-label]', btn);
      if (label) label.textContent = on ? 'Watching' : 'Watch';
    });
    $$('[data-watch-count]').forEach(function (el) {
      el.textContent = list.length;
      el.hidden = list.length === 0;
    });
  }

  function loadWatchlistPage() {
    var root = $('[data-watchlist]');
    if (!root) return;
    var list = watchlist();
    var items = $('[data-watchlist-items]', root);
    var empty = $('[data-watchlist-empty]', root);
    if (!list.length) {
      items.innerHTML = '';
      empty.hidden = false;
      return;
    }
    empty.hidden = true;
    fetch(root.getAttribute('data-url') + '?slugs=' + encodeURIComponent(list.join(',')), {
      headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) { return r.text(); })
      .then(function (html) { items.innerHTML = html; syncWatchUi(); })
      .catch(function () { items.innerHTML = '<div class="card card-pad muted">Could not load your watchlist. Check your connection and try again.</div>'; });
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-watch]');
    if (!btn) return;
    e.preventDefault();
    var slug = btn.getAttribute('data-watch');
    var list = watchlist();
    var index = list.indexOf(slug);
    if (index === -1) {
      if (list.length >= WATCH_MAX) { toast('Watchlist is full (' + WATCH_MAX + ' IPOs)'); return; }
      list.unshift(slug);
      toast('Added to your watchlist');
      track('watchlist_add', { ipo: slug });
    } else {
      list.splice(index, 1);
      toast('Removed from your watchlist');
      track('watchlist_remove', { ipo: slug });
    }
    store.set(WATCH_KEY, JSON.stringify(list));
    syncWatchUi();
    if ($('[data-watchlist]')) loadWatchlistPage();
  });

  window.addEventListener('storage', function (e) { if (e.key === WATCH_KEY) syncWatchUi(); });
  syncWatchUi();
  loadWatchlistPage();

  /* ---------- Calculators: search ---------- */
  var calcSearch = $('[data-calc-search]');
  if (calcSearch) {
    calcSearch.addEventListener('input', function () {
      var words = calcSearch.value.toLowerCase().split(/\s+/).filter(Boolean);
      var shown = 0;
      $$('[data-calc-group]').forEach(function (group) {
        var visible = 0;
        $$('[data-calc-card]', group).forEach(function (card) {
          var text = card.getAttribute('data-calc-card');
          var match = words.every(function (w) { return text.indexOf(w) !== -1; });
          card.hidden = !match;
          if (match) visible++;
        });
        group.hidden = visible === 0;
        shown += visible;
      });
      $('[data-calc-empty]').hidden = shown !== 0;
    });
  }

  /* ---------- IPO sentiment poll ---------- */
  function renderPoll(poll, data) {
    var total = data.total || 0;
    $$('[data-poll-choice]', poll).forEach(function (row) {
      var key = row.getAttribute('data-poll-choice');
      var pct = total ? Math.round((data.counts[key] || 0) * 100 / total) : 0;
      $('[data-poll-bar]', row).style.width = pct + '%';
      $('[data-poll-pct]', row).textContent = pct + '%';
      row.classList.toggle('mine', data.mine === key);
    });
    var totalEl = $('[data-poll-total]', poll);
    if (totalEl) totalEl.textContent = total.toLocaleString('en-IN') + (total === 1 ? ' vote' : ' votes');
    poll.classList.add('voted');
  }

  $$('[data-poll]').forEach(function (poll) {
    poll.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-poll-vote]');
      if (!btn || poll.classList.contains('busy')) return;
      poll.classList.add('busy');
      postJson(poll.getAttribute('data-url'), { choice: btn.getAttribute('data-poll-vote') })
        .then(function (res) {
          if (res.ok) {
            renderPoll(poll, res.body);
            toast('Thanks for voting!');
            track('poll_vote', { ipo: poll.getAttribute('data-ipo'), choice: btn.getAttribute('data-poll-vote') });
          } else {
            toast((res.body && res.body.message) || 'Could not record your vote. Please try again.');
          }
        })
        .catch(function () { toast('Could not record your vote. Please try again.'); })
        .then(function () { poll.classList.remove('busy'); });
    });
  });

  /* ---------- Email digest sign-up ---------- */
  $$('[data-subscribe]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var status = $('[data-subscribe-status]', form);
      var button = $('button[type="submit"]', form);
      var data = {};
      new FormData(form).forEach(function (value, key) { data[key] = value; });
      button.disabled = true;
      postJson(form.getAttribute('action'), data)
        .then(function (res) {
          var errors = res.body && res.body.errors;
          var message = (errors && errors[Object.keys(errors)[0]][0]) || (res.body && res.body.message) || 'Something went wrong. Please try again.';
          status.textContent = message;
          status.className = 'subscribe-status ' + (res.ok ? 'ok' : 'err');
          if (res.ok) { form.reset(); track('subscribe', { frequency: data.frequency || 'daily' }); }
        })
        .catch(function () {
          status.textContent = 'Something went wrong. Please try again.';
          status.className = 'subscribe-status err';
        })
        .then(function () { button.disabled = false; });
    });
  });

  /* ---------- Installable app (PWA) ---------- */
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js').catch(function () {});
    });
  }

  var installPrompt = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    installPrompt = e;
    $$('[data-install-app]').forEach(function (btn) { btn.hidden = false; });
  });
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-install-app]');
    if (!btn || !installPrompt) return;
    installPrompt.prompt();
    installPrompt.userChoice.then(function (choice) {
      track('app_install_prompt', { outcome: choice.outcome });
      installPrompt = null;
      $$('[data-install-app]').forEach(function (b) { b.hidden = true; });
    });
  });
})();

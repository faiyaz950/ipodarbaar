/* IPO Darbaar — News Shorts feed */
(function () {
  'use strict';

  var feed = document.querySelector('[data-shorts-feed]');
  if (!feed) return;

  var ICON_SHARE = '<svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.59 13.51 6.83 3.98M15.41 6.51l-6.82 3.98"/></svg>';
  var ICON_ARROW = '<svg class="icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';

  var loading = false;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function list(points, lang) {
    if (!points || !points.length) return '';
    return '<ul data-lang-' + lang + '>' + points.slice(0, 3).map(function (p) { return '<li>' + esc(p) + '</li>'; }).join('') + '</ul>';
  }

  function card(item) {
    var el = document.createElement('article');
    el.className = 'short';
    el.setAttribute('data-id', item.id);
    el.innerHTML =
      '<div class="short-card">' +
        '<div class="short-media">' +
          (item.image ? '<img src="' + esc(item.image) + '" alt="" loading="lazy" decoding="async">' : '') +
          '<span class="cat-chip" style="--c:' + esc(item.category.color) + '"><i></i>' + esc(item.category.name) + '</span>' +
        '</div>' +
        '<div class="short-body">' +
          '<h2><span data-lang-en>' + esc(item.headline) + '</span><span data-lang-hi>' + esc(item.headline_hi) + '</span></h2>' +
          '<p class="sum"><span data-lang-en>' + esc(item.summary) + '</span><span data-lang-hi>' + esc(item.summary_hi) + '</span></p>' +
          list(item.points, 'en') + list(item.points_hi, 'hi') +
        '</div>' +
        '<div class="short-foot">' +
          '<span class="when">' + esc(item.date_label) + ' · IPO Darbaar</span>' +
          '<div class="acts">' +
            '<button type="button" class="sq-btn" data-share="native" data-title="' + esc(item.headline) + '" data-url="' + esc(item.url) + '" aria-label="Share">' + ICON_SHARE + '</button>' +
            '<a class="btn btn-navy btn-sm" href="' + esc(item.url) + '">Read full ' + ICON_ARROW + '</a>' +
          '</div>' +
        '</div>' +
      '</div>';
    return el;
  }

  function endCard() {
    var el = document.createElement('div');
    el.className = 'shorts-end';
    el.innerHTML = '<div><div style="font-size:40px">👑</div><h3>You\'re all caught up</h3><p>Check back soon for fresh market stories.</p>' +
      '<p style="margin-top:16px"><a class="btn btn-gold btn-sm" href="' + window.location.pathname + '">Back to top</a></p></div>';
    return el;
  }

  function loadMore() {
    var loader = feed.querySelector('[data-loader]');
    if (loading || !loader || feed.getAttribute('data-has-more') !== '1') return;
    loading = true;

    var page = parseInt(feed.getAttribute('data-next-page'), 10) || 2;
    var url = feed.getAttribute('data-feed-url') + '?page=' + page;
    var cat = feed.getAttribute('data-category');
    if (cat) url += '&category=' + encodeURIComponent(cat);

    fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var seen = {};
        feed.querySelectorAll('.short').forEach(function (s) { seen[s.getAttribute('data-id')] = true; });
        (data.items || []).forEach(function (item) {
          if (!seen[item.id]) feed.insertBefore(card(item), loader);
        });
        feed.setAttribute('data-next-page', page + 1);
        if (!data.has_more || !(data.items || []).length) {
          feed.setAttribute('data-has-more', '0');
          loader.replaceWith(endCard());
        }
      })
      .catch(function () {
        loader.innerHTML = '<button type="button" class="btn btn-ghost-light btn-sm">Couldn\'t load. Tap to retry</button>';
        loader.querySelector('button').addEventListener('click', function () {
          loader.innerHTML = '<div class="spinner"></div>';
          loadMore();
        });
      })
      .then(function () { loading = false; });
  }

  // Prefetch when the user is two cards away from the end.
  feed.addEventListener('scroll', function () {
    if (feed.scrollTop + feed.clientHeight * 3 >= feed.scrollHeight) loadMore();
  }, { passive: true });

  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) { if (e.isIntersecting) loadMore(); });
    }, { root: feed, rootMargin: '0px 0px 200% 0px' });
    var l = feed.querySelector('[data-loader]');
    if (l) io.observe(l);
  }

  function step(dir) {
    feed.scrollBy({ top: dir * feed.clientHeight, behavior: 'smooth' });
  }

  var prev = document.querySelector('[data-shorts-prev]');
  var next = document.querySelector('[data-shorts-next]');
  if (prev) prev.addEventListener('click', function () { step(-1); });
  if (next) next.addEventListener('click', function () { step(1); });

  document.addEventListener('keydown', function (e) {
    if (/input|textarea|select/i.test(document.activeElement.tagName)) return;
    if (e.key === 'ArrowDown' || e.key === 'j' || e.key === 'PageDown') { e.preventDefault(); step(1); }
    if (e.key === 'ArrowUp' || e.key === 'k' || e.key === 'PageUp') { e.preventDefault(); step(-1); }
  });

  // Deep link: /shorts?id=123 jumps to that story if it's loaded.
  var startId = feed.getAttribute('data-start-id');
  if (startId && startId !== '0') {
    var target = feed.querySelector('.short[data-id="' + startId + '"]');
    if (target) feed.scrollTop = target.offsetTop - feed.offsetTop;
  }
})();

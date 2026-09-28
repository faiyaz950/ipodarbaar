/* IPO Darbaar — IPO portfolio tracker.
 * Applications live in localStorage on this device only. The server is asked for the
 * latest price, lot size, GMP and listing price of the IPOs in the list, nothing else.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-portfolio]');
  if (!root) return;

  var KEY = 'darbaar:portfolio';
  var MAX = 200;
  var RESULTS = { pending: 'Awaiting', allotted: 'Allotted', rejected: 'Not allotted' };
  var live = {};
  var editingId = null;

  var $ = function (s, el) { return (el || root).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || root).querySelectorAll(s)); };
  var form = $('[data-pf-form]');
  var list = $('[data-pf-list]');
  var summary = $('[data-pf-summary]');
  var suggestBox = $('[data-pf-suggest]');

  function toast(msg) { if (typeof window.darbaarToast === 'function') window.darbaarToast(msg); }
  function track(name, params) { if (typeof window.darbaarTrack === 'function') window.darbaarTrack(name, params); }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function inr(v, d) {
    if (!isFinite(v)) return '—';
    var abs = Math.abs(v).toLocaleString('en-IN', { maximumFractionDigits: d == null ? 0 : d });
    return (v < 0 ? '−₹' : '₹') + abs;
  }
  function signed(v) { return (v > 0 ? '+' : '') + inr(v); }
  function tone(v) { return v > 0 ? 'up' : (v < 0 ? 'down' : ''); }
  function num(v) { var n = parseFloat(v); return isFinite(n) ? n : null; }
  function int(v) { var n = parseInt(v, 10); return isFinite(n) ? n : null; }

  /* ---------- Storage ---------- */
  function clean(e) {
    if (!e || typeof e !== 'object') return null;
    var name = String(e.name || '').trim().slice(0, 120);
    var price = num(e.price), lot = int(e.lot), applied = int(e.lotsApplied);
    var result = RESULTS[e.result] ? e.result : 'pending';
    if (!name || !(price > 0) || !(lot >= 1) || !(applied >= 1)) return null;
    var allotted = result === 'allotted' ? Math.min(Math.max(int(e.lotsAllotted) || 1, 1), applied) : 0;
    var sold = num(e.soldAt);
    return {
      id: /^[a-z0-9]{6,32}$/.test(String(e.id || '')) ? e.id : newId(),
      slug: /^[a-z0-9-]{1,120}$/.test(String(e.slug || '')) ? e.slug : null,
      name: name,
      price: price,
      lot: lot,
      lotsApplied: applied,
      result: result,
      lotsAllotted: allotted,
      soldAt: result === 'allotted' && sold > 0 ? sold : null,
      added: typeof e.added === 'string' ? e.added.slice(0, 10) : new Date().toISOString().slice(0, 10)
    };
  }
  function newId() { return Date.now().toString(36) + Math.random().toString(36).slice(2, 8); }
  function load() {
    try {
      var data = JSON.parse(localStorage.getItem(KEY) || '[]');
      return Array.isArray(data) ? data.map(clean).filter(Boolean).slice(0, MAX) : [];
    } catch (e) { return []; }
  }
  function save(items) {
    try {
      localStorage.setItem(KEY, JSON.stringify(items));
      return true;
    } catch (e) {
      toast('Could not save: this browser is blocking storage');
      return false;
    }
  }

  /* ---------- Live prices ---------- */
  function refreshPrices() {
    var slugs = load().map(function (e) { return e.slug; }).filter(Boolean);
    slugs = slugs.filter(function (s, i) { return slugs.indexOf(s) === i; });
    if (!slugs.length) { render(); return; }
    fetch(root.getAttribute('data-prices') + '?slugs=' + encodeURIComponent(slugs.join(',')), { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : {}; })
      .then(function (data) { live = data || {}; render(); })
      .catch(function () { render(); });
  }

  /* ---------- Maths ---------- */
  function valuation(e) {
    var info = e.slug ? live[e.slug] : null;
    var shares = e.lot * e.lotsAllotted, invested = e.price * shares;
    if (e.result !== 'allotted') return { shares: 0, invested: 0, pnl: null, basis: e.result === 'pending' ? 'Result awaited' : '—', kind: null };
    if (e.soldAt) return { shares: shares, invested: invested, pnl: (e.soldAt - e.price) * shares, basis: 'Sold at ' + inr(e.soldAt, 2), kind: 'booked' };
    if (info && info.listingPrice) return { shares: shares, invested: invested, pnl: (info.listingPrice - e.price) * shares, basis: 'Listing price ' + inr(info.listingPrice, 2), kind: 'open' };
    if (info && info.gmp !== null && info.gmp !== undefined) return { shares: shares, invested: invested, pnl: info.gmp * shares, basis: 'GMP ' + inr(info.gmp, 2) + ' (est.)', kind: 'estimate' };
    return { shares: shares, invested: invested, pnl: null, basis: 'Add a selling price', kind: null };
  }

  /* ---------- Render ---------- */
  function render() {
    var items = load();
    var allotted = 0, decided = 0, invested = 0, booked = 0, open = 0, estimated = false;
    items.forEach(function (e) {
      if (e.result !== 'pending') decided++;
      if (e.result === 'allotted') allotted++;
      var v = valuation(e);
      invested += v.invested;
      if (v.pnl !== null) { if (v.kind === 'booked') booked += v.pnl; else open += v.pnl; }
      if (v.kind === 'estimate') estimated = true;
    });
    var total = booked + open;

    summary.innerHTML =
      fact('Applications', String(items.length)) +
      fact('Allotment rate', decided ? Math.round(allotted / decided * 100) + '% <small>' + allotted + ' of ' + decided + '</small>' : '—') +
      fact('Invested (allotted)', inr(invested)) +
      fact('Profit' + (estimated ? ' (incl. est.)' : ''), signed(total) + (booked ? ' <small>' + signed(booked) + ' booked</small>' : ''), tone(total));

    if (!items.length) {
      list.innerHTML = '<div class="table-empty"><h3>No IPOs added yet</h3><p>Add the first IPO you applied for using the form above.</p></div>';
      return;
    }

    list.innerHTML = '<div class="table-wrap"><table class="table ipo-table pf-table"><thead><tr>' +
      '<th>IPO</th><th class="r">Applied</th><th>Allotment</th><th class="r">Invested</th><th>Valued at</th><th class="r">Profit</th><th class="r"><span class="sr-only">Actions</span></th>' +
      '</tr></thead><tbody>' + items.map(row).join('') + '</tbody></table></div>' +
      '<div class="table-foot"><span class="muted">Profit is before charges and tax.</span><button type="button" class="btn btn-outline btn-sm" data-pf-clear>Clear all</button></div>';
  }

  function fact(label, value, cls) {
    return '<div class="fact"><span>' + esc(label) + '</span><b' + (cls ? ' class="' + cls + '"' : '') + '>' + value + '</b></div>';
  }

  function row(e) {
    var info = e.slug ? live[e.slug] : null;
    var v = valuation(e);
    var name = info && info.url
      ? '<a class="co-name" href="' + esc(info.url) + '">' + esc(e.name) + '</a>'
      : '<span class="co-name">' + esc(e.name) + '</span>';
    var meta = info ? '<div class="co-meta"><span class="badge b-' + (info.type === 'SME' ? 'sme' : 'mainboard') + '">' + esc(info.type) + '</span><span>' + esc(info.statusLabel) + '</span></div>' : '';
    var badge = { pending: 'b-closed', allotted: 'b-open', rejected: 'b-listed' }[e.result] || '';
    var pct = v.pnl !== null && v.invested ? ' <small class="cell-sub ' + tone(v.pnl) + '">' + (v.pnl > 0 ? '+' : '') + (v.pnl / v.invested * 100).toFixed(1) + '%</small>' : '';
    return '<tr>' +
      '<td class="company"><div style="min-width:0">' + name + meta + '</div></td>' +
      '<td class="r" data-label="Applied">' + e.lotsApplied + (e.lotsApplied === 1 ? ' lot' : ' lots') + '<small class="cell-sub muted">' + inr(e.price * e.lot * e.lotsApplied) + ' blocked</small></td>' +
      '<td data-label="Allotment"><span class="badge ' + badge + '">' + esc(RESULTS[e.result]) + '</span>' + (e.result === 'allotted' ? '<small class="cell-sub muted">' + e.lotsAllotted + (e.lotsAllotted === 1 ? ' lot' : ' lots') + ' · ' + (e.lot * e.lotsAllotted).toLocaleString('en-IN') + ' shares</small>' : '') + '</td>' +
      '<td class="r" data-label="Invested">' + (v.invested ? inr(v.invested) : '—') + '</td>' +
      '<td data-label="Valued at">' + esc(v.basis) + '</td>' +
      '<td class="r" data-label="Profit">' + (v.pnl === null ? '<span class="muted">—</span>' : '<b class="' + tone(v.pnl) + '">' + signed(v.pnl) + '</b>' + pct) + '</td>' +
      '<td class="r pf-row-actions"><button type="button" class="btn btn-outline btn-sm" data-pf-edit="' + e.id + '">Edit</button> <button type="button" class="btn btn-outline btn-sm" data-pf-remove="' + e.id + '" aria-label="Remove ' + esc(e.name) + '">Remove</button></td>' +
      '</tr>';
  }

  /* ---------- Form ---------- */
  function field(name) { return form.elements[name]; }
  function toggleAllotted() {
    var on = field('result').value === 'allotted';
    $$('[data-pf-allotted]').forEach(function (el) { el.hidden = !on; });
  }
  function resetForm() {
    form.reset();
    field('id').value = '';
    field('slug').value = '';
    editingId = null;
    $('[data-pf-form-title]').textContent = 'Add an IPO application';
    $('[data-pf-submit]').lastChild.textContent = ' Add to portfolio';
    $('[data-pf-cancel]').hidden = true;
    toggleAllotted();
  }

  field('result').addEventListener('change', toggleAllotted);
  $('[data-pf-cancel]').addEventListener('click', resetForm);

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var entry = clean({
      id: editingId || newId(),
      slug: field('slug').value,
      name: field('name').value,
      price: field('price').value,
      lot: field('lot').value,
      lotsApplied: field('lotsApplied').value,
      result: field('result').value,
      lotsAllotted: field('lotsAllotted').value,
      soldAt: field('soldAt').value
    });
    if (!entry) { toast('Enter the IPO name, price, lot size and lots applied'); return; }
    if (entry.result === 'allotted' && int(field('lotsAllotted').value) > entry.lotsApplied) { toast('Lots allotted cannot be more than lots applied'); return; }

    var items = load();
    var index = items.findIndex(function (e) { return e.id === entry.id; });
    if (index === -1) {
      if (items.length >= MAX) { toast('Your portfolio is full (' + MAX + ' IPOs)'); return; }
      items.unshift(entry);
      track('portfolio_add', { ipo: entry.slug || 'manual' });
    } else {
      entry.added = items[index].added;
      items[index] = entry;
    }
    if (!save(items)) return;
    toast(index === -1 ? 'Added to your portfolio' : 'Saved');
    resetForm();
    refreshPrices();
  });

  list.addEventListener('click', function (ev) {
    var edit = ev.target.closest('[data-pf-edit]');
    var remove = ev.target.closest('[data-pf-remove]');
    var clear = ev.target.closest('[data-pf-clear]');
    var items = load();
    if (edit) {
      var e = items.find(function (x) { return x.id === edit.getAttribute('data-pf-edit'); });
      if (!e) return;
      editingId = e.id;
      field('id').value = e.id;
      field('slug').value = e.slug || '';
      field('name').value = e.name;
      field('price').value = e.price;
      field('lot').value = e.lot;
      field('lotsApplied').value = e.lotsApplied;
      field('result').value = e.result;
      field('lotsAllotted').value = e.lotsAllotted || 1;
      field('soldAt').value = e.soldAt || '';
      toggleAllotted();
      $('[data-pf-form-title]').textContent = 'Edit ' + e.name;
      $('[data-pf-submit]').lastChild.textContent = ' Save changes';
      $('[data-pf-cancel]').hidden = false;
      form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else if (remove) {
      var id = remove.getAttribute('data-pf-remove');
      if (!window.confirm('Remove this IPO from your portfolio?')) return;
      save(items.filter(function (x) { return x.id !== id; }));
      if (editingId === id) resetForm();
      render();
    } else if (clear) {
      if (!window.confirm('Remove all ' + items.length + ' IPOs from your portfolio? Download a backup first if you may need them.')) return;
      save([]);
      resetForm();
      render();
    }
  });

  /* ---------- IPO search ---------- */
  var searchTimer, searchSeq = 0;
  field('name').addEventListener('input', function () {
    field('slug').value = '';
    clearTimeout(searchTimer);
    var q = field('name').value.trim();
    if (q.length < 2) { suggestBox.hidden = true; return; }
    searchTimer = setTimeout(function () {
      var seq = ++searchSeq;
      fetch(root.getAttribute('data-suggest') + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : []; })
        .then(function (results) {
          if (seq !== searchSeq) return;
          if (!results.length) { suggestBox.hidden = true; return; }
          suggestBox.innerHTML = results.map(function (r) {
            return '<button type="button" data-pf-pick="' + esc(r.slug) + '" data-name="' + esc(r.name) + '"><b>' + esc(r.name) + '</b><small>' + esc(r.type) + ' · ' + esc(r.status) + ' · ' + esc(r.meta) + '</small></button>';
          }).join('');
          suggestBox.hidden = false;
        })
        .catch(function () { suggestBox.hidden = true; });
    }, 200);
  });

  suggestBox.addEventListener('click', function (ev) {
    var pick = ev.target.closest('[data-pf-pick]');
    if (!pick) return;
    var slug = pick.getAttribute('data-pf-pick');
    field('slug').value = slug;
    field('name').value = pick.getAttribute('data-name');
    suggestBox.hidden = true;
    fetch(root.getAttribute('data-prices') + '?slugs=' + encodeURIComponent(slug), { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : {}; })
      .then(function (data) {
        var info = data[slug];
        if (!info) return;
        live[slug] = info;
        if (info.price && !field('price').value) field('price').value = info.price;
        if (info.lot && !field('lot').value) field('lot').value = info.lot;
        (info.lot ? field('lotsApplied') : field('lot')).focus();
      })
      .catch(function () {});
  });

  document.addEventListener('click', function (ev) {
    if (!ev.target.closest('.pf-search')) suggestBox.hidden = true;
  });

  /* ---------- Backup ---------- */
  $('[data-pf-export]').addEventListener('click', function () {
    var blob = new Blob([JSON.stringify({ app: 'ipo-darbaar-portfolio', version: 1, items: load() }, null, 2)], { type: 'application/json' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'ipo-darbaar-portfolio-' + new Date().toISOString().slice(0, 10) + '.json';
    document.body.appendChild(a);
    a.click();
    setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 0);
  });

  $('[data-pf-import]').addEventListener('change', function (ev) {
    var file = ev.target.files && ev.target.files[0];
    ev.target.value = '';
    if (!file) return;
    if (file.size > 1024 * 1024) { toast('That file is too large to be a portfolio backup'); return; }
    var reader = new FileReader();
    reader.onload = function () {
      var incoming;
      try {
        var data = JSON.parse(reader.result);
        incoming = (Array.isArray(data) ? data : data.items || []).map(clean).filter(Boolean);
      } catch (e) { incoming = []; }
      if (!incoming.length) { toast('No IPOs found in that file'); return; }
      var items = load();
      incoming.forEach(function (e) {
        var i = items.findIndex(function (x) { return x.id === e.id; });
        if (i === -1) items.push(e); else items[i] = e;
      });
      if (save(items.slice(0, MAX))) {
        toast('Restored ' + incoming.length + (incoming.length === 1 ? ' IPO' : ' IPOs'));
        refreshPrices();
      }
    };
    reader.readAsText(file);
  });

  window.addEventListener('storage', function (ev) { if (ev.key === KEY) refreshPrices(); });

  toggleAllotted();
  refreshPrices();
})();

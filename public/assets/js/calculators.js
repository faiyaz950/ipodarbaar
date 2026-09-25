/* IPO Darbaar — calculator engine
 * Each calculator declares its inputs and a pure compute(v) that returns
 * { hero, rows, donut?, table? }. The engine renders the UI and re-computes live.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-calc]');
  if (!root) return;

  /* ---------- Formatting ---------- */
  function n(v, d) {
    if (!isFinite(v)) return '—';
    return Number(v).toLocaleString('en-IN', { maximumFractionDigits: d == null ? 0 : d, minimumFractionDigits: 0 });
  }
  var F = {
    inr: function (v) { return (v < 0 ? '−₹' : '₹') + n(Math.abs(v), 0); },
    inr2: function (v) { return (v < 0 ? '−₹' : '₹') + n(Math.abs(v), 2); },
    pct: function (v, d) { return isFinite(v) ? n(v, d == null ? 2 : d) + '%' : '—'; },
    num: function (v, d) { return n(v, d); },
    compact: function (v) {
      var a = Math.abs(v), s = v < 0 ? '−₹' : '₹';
      if (a >= 1e7) return s + n(a / 1e7, 2) + ' Cr';
      if (a >= 1e5) return s + n(a / 1e5, 2) + ' L';
      return s + n(a, 0);
    }
  };
  function tone(v) { return v > 0 ? 'up' : (v < 0 ? 'down' : ''); }

  /* ---------- Shared maths ---------- */
  function emiOf(p, annualRate, months) {
    var r = annualRate / 1200;
    return r ? p * r * Math.pow(1 + r, months) / (Math.pow(1 + r, months) - 1) : p / months;
  }
  function amortise(p, annualRate, months, emi) {
    var r = annualRate / 1200, bal = p, rows = [], yP = 0, yI = 0;
    for (var m = 1; m <= months; m++) {
      var int = bal * r, prin = emi - int;
      bal -= prin; yP += prin; yI += int;
      if (m % 12 === 0 || m === months) { rows.push([Math.ceil(m / 12), F.inr(yP), F.inr(yI), F.inr(Math.max(0, bal))]); yP = 0; yI = 0; }
    }
    return rows;
  }
  function npdf(x) { return Math.exp(-x * x / 2) / Math.sqrt(2 * Math.PI); }
  function ncdf(x) {
    var t = 1 / (1 + 0.2316419 * Math.abs(x));
    var p = npdf(x) * t * (0.319381530 + t * (-0.356563782 + t * (1.781477937 + t * (-1.821255978 + t * 1.330274429))));
    return x > 0 ? 1 - p : p;
  }
  function slabTax(income, slabs) {
    var tax = 0, lower = 0;
    for (var i = 0; i < slabs.length; i++) {
      var upper = slabs[i][0], rate = slabs[i][1];
      if (income > lower) tax += (Math.min(income, upper) - lower) * rate;
      lower = upper;
    }
    return tax;
  }
  function parseList(text) {
    return String(text || '').split(/[\s,;]+/).map(function (s) { return parseFloat(s.replace('%', '')); }).filter(isFinite);
  }

  /* ---------- Calculator definitions ---------- */
  var RETAIL_MAX = 200000, SHNI_MAX = 1000000;

  var CALCS = {
    'ipo-gmp': {
      inputs: [
        { id: 'price', label: 'Issue price (upper band)', prefix: '₹', min: 1, max: 3000, step: 1, value: 100 },
        { id: 'gmp', label: 'GMP per share', prefix: '₹', min: -200, max: 500, step: 0.5, value: 20 },
        { id: 'lot', label: 'Lot size', suffix: 'shares', min: 1, max: 5000, step: 1, value: 140 },
        { id: 'lots', label: 'Lots applied / allotted', min: 1, max: 50, step: 1, value: 1 }
      ],
      compute: function (v) {
        var shares = v.lot * v.lots, inv = v.price * shares, listing = v.price + v.gmp, profit = v.gmp * shares;
        var pct = v.price ? v.gmp / v.price * 100 : 0;
        return {
          hero: { label: 'Estimated listing profit', value: F.inr(profit), tone: tone(profit), sub: F.pct(pct) + ' expected listing gain' },
          rows: [
            ['Expected listing price', F.inr2(listing)],
            ['Gain per share', F.inr2(v.gmp)],
            ['Shares', F.num(shares)],
            ['Investment', F.inr(inv)],
            ['Value at expected listing', F.inr(listing * shares)]
          ],
          donut: profit > 0 ? [{ label: 'Investment', value: inv }, { label: 'Expected profit', value: profit }] : null
        };
      }
    },

    'ipo-profit': {
      inputs: [
        { id: 'price', label: 'Issue price', prefix: '₹', min: 1, max: 3000, step: 1, value: 100 },
        { id: 'sell', label: 'Selling / listing price', prefix: '₹', min: 1, max: 5000, step: 0.5, value: 135 },
        { id: 'lot', label: 'Lot size', suffix: 'shares', min: 1, max: 5000, step: 1, value: 140 },
        { id: 'lots', label: 'Lots allotted', min: 1, max: 50, step: 1, value: 1 }
      ],
      compute: function (v) {
        var shares = v.lot * v.lots, inv = v.price * shares, sale = v.sell * shares, profit = sale - inv;
        var pct = inv ? profit / inv * 100 : 0;
        return {
          hero: { label: profit >= 0 ? 'Your profit' : 'Your loss', value: F.inr(profit), tone: tone(profit), sub: F.pct(pct) + ' return on investment' },
          rows: [
            ['Amount invested', F.inr(inv)],
            ['Sale value', F.inr(sale)],
            ['Profit per share', F.inr2(v.sell - v.price)],
            ['Shares sold', F.num(shares)]
          ],
          donut: profit > 0 ? [{ label: 'Investment', value: inv }, { label: 'Profit', value: profit }] : null
        };
      }
    },

    'ipo-application': {
      inputs: [
        { id: 'board', label: 'IPO type', type: 'options', options: [['mainboard', 'Mainboard'], ['sme', 'SME']], value: 'mainboard' },
        { id: 'price', label: 'Upper price band', prefix: '₹', min: 1, max: 3000, step: 1, value: 250 },
        { id: 'lot', label: 'Lot size', suffix: 'shares', min: 1, max: 5000, step: 1, value: 60 },
        { id: 'lots', label: 'Lots you want to apply for', min: 1, max: 200, step: 1, value: 1 }
      ],
      compute: function (v) {
        var perLot = v.price * v.lot, amount = perLot * v.lots, sme = v.board === 'sme';
        var retailMax = sme ? 2 : Math.max(1, Math.floor(RETAIL_MAX / perLot));
        var shniMin = retailMax + 1, shniMax = Math.max(shniMin, Math.floor(SHNI_MAX / perLot));
        var cat;
        if (sme) {
          cat = v.lots < 2 ? 'Below SME minimum (2 lots)' : (v.lots === 2 ? 'Individual investor' : (amount <= SHNI_MAX ? 'Small HNI (S-HNI)' : 'Big HNI (B-HNI)'));
        } else {
          cat = amount <= RETAIL_MAX ? 'Retail (RII)' : (amount <= SHNI_MAX ? 'Small HNI (S-HNI)' : 'Big HNI (B-HNI)');
        }
        return {
          hero: { label: 'Amount to be blocked', value: F.inr(amount), sub: 'Category: ' + cat },
          rows: [
            ['Shares', F.num(v.lot * v.lots)],
            ['Amount per lot', F.inr(perLot)],
            [sme ? 'Individual investor lots' : 'Max retail lots (≤ ₹2L)', F.num(retailMax) + ' · ' + F.inr(retailMax * perLot)],
            ['S-HNI range', F.num(shniMin) + '–' + F.num(shniMax) + ' lots'],
            ['B-HNI minimum', F.num(shniMax + 1) + ' lots · ' + F.inr((shniMax + 1) * perLot)]
          ]
        };
      }
    },

    'ipo-allotment-chance': {
      inputs: [
        { id: 'x', label: 'Retail subscription', suffix: 'times', min: 0.5, max: 500, step: 0.5, value: 25 },
        { id: 'apps', label: 'Applications (different PANs)', min: 1, max: 20, step: 1, value: 1 }
      ],
      compute: function (v) {
        var p = v.x <= 1 ? 1 : 1 / v.x;
        var overall = 1 - Math.pow(1 - p, v.apps);
        var odds = p >= 1 ? 'Assured' : '1 in ' + n(1 / p, 1);
        return {
          hero: { label: 'Chance of at least one allotment', value: F.pct(overall * 100, 1), tone: overall >= 0.5 ? 'up' : '', sub: v.x <= 1 ? 'Undersubscribed: every valid application should get shares' : 'Based on a lottery for 1 lot per application' },
          rows: [
            ['Chance per application', F.pct(p * 100, 2)],
            ['Odds per application', odds],
            ['Expected lots allotted', n(v.apps * p, 2)],
            ['Applications', F.num(v.apps)]
          ],
          donut: [{ label: 'Chance', value: overall }, { label: 'No allotment', value: Math.max(0, 1 - overall) }],
          donutFmt: function (x) { return F.pct(x * 100, 1); }
        };
      }
    },

    'sip': {
      inputs: [
        { id: 'amount', label: 'Monthly investment', prefix: '₹', min: 500, max: 200000, step: 500, value: 10000 },
        { id: 'rate', label: 'Expected return (p.a.)', suffix: '%', min: 1, max: 30, step: 0.5, value: 12 },
        { id: 'years', label: 'Time period', suffix: 'years', min: 1, max: 40, step: 1, value: 10 },
        { id: 'stepup', label: 'Annual step-up', suffix: '%', min: 0, max: 50, step: 1, value: 0 }
      ],
      compute: function (v) {
        var i = v.rate / 1200, amt = v.amount, bal = 0, inv = 0, table = [];
        for (var m = 1; m <= v.years * 12; m++) {
          if (m > 1 && (m - 1) % 12 === 0) amt *= 1 + v.stepup / 100;
          bal = (bal + amt) * (1 + i);
          inv += amt;
          if (m % 12 === 0) table.push([m / 12, F.inr(inv), F.inr(bal - inv), F.inr(bal)]);
        }
        return {
          hero: { label: 'Estimated value', value: F.inr(bal), sub: '≈ ' + F.compact(bal) + ' after ' + v.years + ' years' },
          rows: [['Total invested', F.inr(inv)], ['Estimated returns', F.inr(bal - inv)], ['Wealth gain', F.pct(inv ? (bal - inv) / inv * 100 : 0, 1)]],
          donut: [{ label: 'Invested', value: inv }, { label: 'Returns', value: bal - inv }],
          table: { head: ['Year', 'Invested', 'Returns', 'Value'], rows: table }
        };
      }
    },

    'lumpsum': {
      inputs: [
        { id: 'amount', label: 'Investment amount', prefix: '₹', min: 1000, max: 10000000, step: 1000, value: 100000 },
        { id: 'rate', label: 'Expected return (p.a.)', suffix: '%', min: 1, max: 30, step: 0.5, value: 12 },
        { id: 'years', label: 'Time period', suffix: 'years', min: 1, max: 40, step: 1, value: 10 }
      ],
      compute: function (v) {
        var fv = v.amount * Math.pow(1 + v.rate / 100, v.years), table = [];
        for (var y = 1; y <= v.years; y++) {
          var val = v.amount * Math.pow(1 + v.rate / 100, y);
          table.push([y, F.inr(v.amount), F.inr(val - v.amount), F.inr(val)]);
        }
        return {
          hero: { label: 'Estimated value', value: F.inr(fv), sub: '≈ ' + F.compact(fv) + ' · ' + n(fv / v.amount, 2) + 'x your money' },
          rows: [['Invested', F.inr(v.amount)], ['Estimated returns', F.inr(fv - v.amount)]],
          donut: [{ label: 'Invested', value: v.amount }, { label: 'Returns', value: fv - v.amount }],
          table: { head: ['Year', 'Invested', 'Returns', 'Value'], rows: table }
        };
      }
    },

    'swp': {
      inputs: [
        { id: 'corpus', label: 'Total investment', prefix: '₹', min: 10000, max: 50000000, step: 10000, value: 2500000 },
        { id: 'withdraw', label: 'Monthly withdrawal', prefix: '₹', min: 500, max: 500000, step: 500, value: 20000 },
        { id: 'rate', label: 'Expected return (p.a.)', suffix: '%', min: 1, max: 20, step: 0.5, value: 8 },
        { id: 'years', label: 'Time period', suffix: 'years', min: 1, max: 40, step: 1, value: 10 }
      ],
      compute: function (v) {
        var i = v.rate / 1200, bal = v.corpus, withdrawn = 0, lasted = null, table = [];
        for (var m = 1; m <= v.years * 12; m++) {
          bal = bal * (1 + i);
          var w = Math.min(v.withdraw, bal);
          bal -= w; withdrawn += w;
          if (bal <= 0 && lasted === null) { lasted = m; bal = 0; }
          if (m % 12 === 0) table.push([m / 12, F.inr(withdrawn), F.inr(bal)]);
        }
        return {
          hero: { label: 'Final value', value: F.inr(bal), tone: bal > 0 ? '' : 'down', sub: lasted ? 'Corpus runs out in month ' + lasted + ' (' + n(lasted / 12, 1) + ' years)' : 'Corpus lasts the full ' + v.years + ' years' },
          rows: [['Total investment', F.inr(v.corpus)], ['Total withdrawn', F.inr(withdrawn)], ['Monthly withdrawal', F.inr(v.withdraw)]],
          donut: [{ label: 'Withdrawn', value: withdrawn }, { label: 'Remaining', value: bal }],
          table: { head: ['Year', 'Withdrawn so far', 'Balance'], rows: table }
        };
      }
    },

    'cagr': {
      inputs: [
        { id: 'start', label: 'Initial value', prefix: '₹', min: 1000, max: 10000000, step: 1000, value: 100000 },
        { id: 'end', label: 'Final value', prefix: '₹', min: 1000, max: 50000000, step: 1000, value: 250000 },
        { id: 'years', label: 'Duration', suffix: 'years', min: 1, max: 40, step: 0.5, value: 5 }
      ],
      compute: function (v) {
        var cagr = (Math.pow(v.end / v.start, 1 / v.years) - 1) * 100;
        var abs = (v.end - v.start) / v.start * 100;
        return {
          hero: { label: 'CAGR', value: F.pct(cagr), tone: tone(cagr), sub: 'Compounded annual growth over ' + v.years + ' years' },
          rows: [['Absolute return', F.pct(abs)], ['Total gain', F.inr(v.end - v.start)], ['Growth multiple', n(v.end / v.start, 2) + 'x']]
        };
      }
    },

    'stock-average': {
      inputs: [
        { id: 'q1', label: 'Shares already held', min: 1, max: 100000, step: 1, value: 100 },
        { id: 'p1', label: 'Average buy price', prefix: '₹', min: 1, max: 100000, step: 0.5, value: 250 },
        { id: 'q2', label: 'New shares to buy', min: 0, max: 100000, step: 1, value: 50 },
        { id: 'p2', label: 'New buy price', prefix: '₹', min: 1, max: 100000, step: 0.5, value: 200 }
      ],
      compute: function (v) {
        var qty = v.q1 + v.q2, cost = v.q1 * v.p1 + v.q2 * v.p2, avg = qty ? cost / qty : 0;
        var change = v.p1 ? (avg - v.p1) / v.p1 * 100 : 0;
        return {
          hero: { label: 'New average price', value: F.inr2(avg), sub: (change <= 0 ? 'Averaged down by ' : 'Averaged up by ') + F.pct(Math.abs(change)) },
          rows: [['Total shares', F.num(qty)], ['Total investment', F.inr(cost)], ['Existing investment', F.inr(v.q1 * v.p1)], ['New investment', F.inr(v.q2 * v.p2)]],
          donut: [{ label: 'Existing', value: v.q1 * v.p1 }, { label: 'New purchase', value: v.q2 * v.p2 }]
        };
      }
    },

    'inflation': {
      inputs: [
        { id: 'cost', label: 'Current cost', prefix: '₹', min: 1000, max: 10000000, step: 1000, value: 100000 },
        { id: 'rate', label: 'Inflation rate (p.a.)', suffix: '%', min: 1, max: 15, step: 0.5, value: 6 },
        { id: 'years', label: 'Years', suffix: 'years', min: 1, max: 40, step: 1, value: 10 }
      ],
      compute: function (v) {
        var f = Math.pow(1 + v.rate / 100, v.years), future = v.cost * f;
        return {
          hero: { label: 'Future cost', value: F.inr(future), sub: 'What ' + F.inr(v.cost) + ' of expenses will cost in ' + v.years + ' years' },
          rows: [['Increase in cost', F.inr(future - v.cost)], ['Value of ' + F.inr(v.cost) + ' then (today’s money)', F.inr(v.cost / f)], ['Purchasing power lost', F.pct((1 - 1 / f) * 100, 1)]],
          donut: [{ label: 'Current cost', value: v.cost }, { label: 'Inflation impact', value: future - v.cost }]
        };
      }
    },

    'fd': {
      inputs: [
        { id: 'p', label: 'Deposit amount', prefix: '₹', min: 1000, max: 10000000, step: 1000, value: 100000 },
        { id: 'rate', label: 'Interest rate (p.a.)', suffix: '%', min: 1, max: 15, step: 0.05, value: 7 },
        { id: 'years', label: 'Tenure', suffix: 'years', min: 0.25, max: 10, step: 0.25, value: 5 },
        { id: 'freq', label: 'Compounding', type: 'options', options: [['4', 'Quarterly'], ['12', 'Monthly'], ['2', 'Half-yearly'], ['1', 'Yearly']], value: '4' }
      ],
      compute: function (v) {
        var k = parseInt(v.freq, 10), A = v.p * Math.pow(1 + v.rate / 100 / k, k * v.years);
        var eff = (Math.pow(1 + v.rate / 100 / k, k) - 1) * 100;
        return {
          hero: { label: 'Maturity value', value: F.inr(A), sub: 'Effective annual yield ' + F.pct(eff) },
          rows: [['Principal', F.inr(v.p)], ['Interest earned', F.inr(A - v.p)]],
          donut: [{ label: 'Principal', value: v.p }, { label: 'Interest', value: A - v.p }]
        };
      }
    },

    'rd': {
      inputs: [
        { id: 'r', label: 'Monthly deposit', prefix: '₹', min: 100, max: 200000, step: 100, value: 5000 },
        { id: 'rate', label: 'Interest rate (p.a.)', suffix: '%', min: 1, max: 15, step: 0.05, value: 7 },
        { id: 'years', label: 'Tenure', suffix: 'years', min: 0.5, max: 10, step: 0.5, value: 5 }
      ],
      compute: function (v) {
        var months = Math.round(v.years * 12), M = 0;
        for (var k = 1; k <= months; k++) M += v.r * Math.pow(1 + v.rate / 400, (months - k + 1) / 3);
        var inv = v.r * months;
        return {
          hero: { label: 'Maturity value', value: F.inr(M), sub: months + ' monthly instalments, compounded quarterly' },
          rows: [['Total deposited', F.inr(inv)], ['Interest earned', F.inr(M - inv)]],
          donut: [{ label: 'Deposits', value: inv }, { label: 'Interest', value: M - inv }]
        };
      }
    },

    'ppf': {
      inputs: [
        { id: 'dep', label: 'Yearly deposit', prefix: '₹', min: 500, max: 150000, step: 500, value: 150000 },
        { id: 'rate', label: 'Interest rate (p.a.)', suffix: '%', min: 5, max: 10, step: 0.05, value: 7.1 },
        { id: 'years', label: 'Tenure', suffix: 'years', min: 15, max: 50, step: 5, value: 15 }
      ],
      compute: function (v) {
        var bal = 0, inv = 0, table = [];
        for (var y = 1; y <= v.years; y++) {
          bal = (bal + v.dep) * (1 + v.rate / 100);
          inv += v.dep;
          table.push([y, F.inr(inv), F.inr(bal - inv), F.inr(bal)]);
        }
        return {
          hero: { label: 'Maturity value (tax-free)', value: F.inr(bal), sub: '≈ ' + F.compact(bal) + ' after ' + v.years + ' years' },
          rows: [['Total deposited', F.inr(inv)], ['Total interest', F.inr(bal - inv)]],
          donut: [{ label: 'Deposits', value: inv }, { label: 'Interest', value: bal - inv }],
          table: { head: ['Year', 'Deposited', 'Interest', 'Balance'], rows: table }
        };
      }
    },

    'emi': {
      inputs: [
        { id: 'p', label: 'Loan amount', prefix: '₹', min: 10000, max: 50000000, step: 10000, value: 2500000 },
        { id: 'rate', label: 'Interest rate (p.a.)', suffix: '%', min: 1, max: 24, step: 0.05, value: 8.5 },
        { id: 'years', label: 'Tenure', suffix: 'years', min: 1, max: 30, step: 1, value: 20 }
      ],
      compute: function (v) {
        var r = v.rate / 1200, nM = v.years * 12;
        var emi = r ? v.p * r * Math.pow(1 + r, nM) / (Math.pow(1 + r, nM) - 1) : v.p / nM;
        var total = emi * nM, bal = v.p, table = [], yP = 0, yI = 0;
        for (var m = 1; m <= nM; m++) {
          var int = bal * r, prin = emi - int;
          bal -= prin; yP += prin; yI += int;
          if (m % 12 === 0) { table.push([m / 12, F.inr(yP), F.inr(yI), F.inr(Math.max(0, bal))]); yP = 0; yI = 0; }
        }
        return {
          hero: { label: 'Monthly EMI', value: F.inr(emi), sub: 'for ' + v.years + ' years at ' + v.rate + '% p.a.' },
          rows: [['Principal amount', F.inr(v.p)], ['Total interest', F.inr(total - v.p)], ['Total payment', F.inr(total)]],
          donut: [{ label: 'Principal', value: v.p }, { label: 'Interest', value: total - v.p }],
          table: { head: ['Year', 'Principal paid', 'Interest paid', 'Balance'], rows: table }
        };
      }
    },

    'brokerage': {
      inputs: [
        { id: 'seg', label: 'Trade type', type: 'options', options: [['delivery', 'Delivery'], ['intraday', 'Intraday']], value: 'delivery' },
        { id: 'ex', label: 'Exchange', type: 'options', options: [['nse', 'NSE'], ['bse', 'BSE']], value: 'nse' },
        { id: 'buy', label: 'Buy price', prefix: '₹', min: 1, max: 100000, step: 0.05, value: 1000 },
        { id: 'sell', label: 'Sell price', prefix: '₹', min: 1, max: 100000, step: 0.05, value: 1050 },
        { id: 'qty', label: 'Quantity', min: 1, max: 100000, step: 1, value: 100 },
        { id: 'brk', label: 'Brokerage per order (max)', prefix: '₹', min: 0, max: 100, step: 1, value: 20 }
      ],
      compute: function (v) {
        var intra = v.seg === 'intraday';
        var bv = v.buy * v.qty, sv = v.sell * v.qty, to = bv + sv;
        var brk = intra ? Math.min(v.brk, bv * 0.0003) + Math.min(v.brk, sv * 0.0003) : v.brk * 2;
        var stt = intra ? sv * 0.00025 : to * 0.001;
        var exch = to * (v.ex === 'bse' ? 0.0000375 : 0.0000297);
        var sebi = to * 0.000001;
        var stamp = bv * (intra ? 0.00003 : 0.00015);
        var gst = (brk + exch + sebi) * 0.18;
        var dp = intra ? 0 : 15.93;
        var total = brk + stt + exch + sebi + stamp + gst + dp;
        var gross = sv - bv, net = gross - total;
        return {
          hero: { label: 'Net P&L', value: F.inr2(net), tone: tone(net), sub: 'Total charges ' + F.inr2(total) + ' · breakeven ' + F.inr2(total / v.qty) + ' per share' },
          rows: [
            ['Turnover', F.inr2(to)],
            ['Gross P&L', F.inr2(gross)],
            ['Brokerage', F.inr2(brk)],
            ['STT', F.inr2(stt)],
            ['Exchange transaction charges', F.inr2(exch)],
            ['SEBI fees', F.inr2(sebi)],
            ['Stamp duty', F.inr2(stamp)],
            ['GST (18%)', F.inr2(gst)],
            ['DP charges', F.inr2(dp)]
          ]
        };
      }
    },

    'capital-gains': {
      inputs: [
        { id: 'buy', label: 'Buy price per share', prefix: '₹', min: 1, max: 100000, step: 0.5, value: 200 },
        { id: 'sell', label: 'Sell price per share', prefix: '₹', min: 1, max: 100000, step: 0.5, value: 320 },
        { id: 'qty', label: 'Quantity', min: 1, max: 1000000, step: 1, value: 1000 },
        { id: 'months', label: 'Holding period', suffix: 'months', min: 1, max: 120, step: 1, value: 8 },
        { id: 'exempt', label: 'LTCG exemption available', prefix: '₹', min: 0, max: 125000, step: 5000, value: 125000 }
      ],
      compute: function (v) {
        var gain = (v.sell - v.buy) * v.qty, lt = v.months > 12;
        var taxable = gain <= 0 ? 0 : (lt ? Math.max(0, gain - v.exempt) : gain);
        var rate = lt ? 0.125 : 0.20;
        var base = taxable * rate, cess = base * 0.04, tax = base + cess;
        return {
          hero: { label: (lt ? 'LTCG' : 'STCG') + ' tax payable', value: F.inr(tax), tone: tax > 0 ? 'down' : '', sub: gain <= 0 ? 'Capital loss: no tax; can be set off against gains' : (lt ? 'Long-term: held more than 12 months' : 'Short-term: held 12 months or less') },
          rows: [
            ['Capital gain', F.inr(gain)],
            ['Taxable gain', F.inr(taxable)],
            ['Tax rate', (lt ? '12.5%' : '20%') + ' + 4% cess'],
            ['Health & education cess', F.inr(cess)],
            ['Profit after tax', F.inr(gain - tax)],
            ['Effective tax on gain', F.pct(gain > 0 ? tax / gain * 100 : 0)]
          ],
          donut: gain > 0 ? [{ label: 'Profit after tax', value: gain - tax }, { label: 'Tax', value: tax }] : null
        };
      }
    },

    'return': {
      inputs: [
        { id: 'mode', label: 'How you invest', type: 'options', options: [['sip', 'Monthly (SIP)'], ['lumpsum', 'One-time (Lumpsum)']], value: 'sip' },
        { id: 'amount', label: 'Investment amount', prefix: '₹', min: 500, max: 10000000, step: 500, value: 10000 },
        { id: 'rate', label: 'Expected return (p.a.)', suffix: '%', min: 1, max: 30, step: 0.5, value: 12 },
        { id: 'years', label: 'Time period', suffix: 'years', min: 1, max: 40, step: 1, value: 10 }
      ],
      compute: function (v) {
        var sip = v.mode === 'sip', i = v.rate / 1200, bal = 0, inv = 0, table = [];
        for (var y = 1; y <= v.years; y++) {
          if (sip) {
            for (var m = 0; m < 12; m++) { bal = (bal + v.amount) * (1 + i); inv += v.amount; }
          } else {
            if (y === 1) { bal = v.amount; inv = v.amount; }
            bal *= 1 + v.rate / 100;
          }
          table.push([y, F.inr(inv), F.inr(bal - inv), F.inr(bal)]);
        }
        var gain = bal - inv;
        return {
          hero: { label: 'Estimated value', value: F.inr(bal), sub: '≈ ' + F.compact(bal) + ' · ' + F.pct(inv ? gain / inv * 100 : 0, 1) + ' absolute return' },
          rows: [['Total invested', F.inr(inv)], ['Estimated returns', F.inr(gain)], ['Money multiplied', n(inv ? bal / inv : 0, 2) + 'x']],
          donut: [{ label: 'Invested', value: inv }, { label: 'Returns', value: gain }],
          table: { head: ['Year', 'Invested', 'Returns', 'Value'], rows: table }
        };
      }
    },

    'compound-interest': {
      inputs: [
        { id: 'p', label: 'Principal amount', prefix: '₹', min: 1000, max: 100000000, step: 1000, value: 100000 },
        { id: 'rate', label: 'Interest rate (p.a.)', suffix: '%', min: 0.5, max: 30, step: 0.1, value: 10 },
        { id: 'years', label: 'Time period', suffix: 'years', min: 1, max: 50, step: 1, value: 10 },
        { id: 'freq', label: 'Compounding', type: 'options', options: [['12', 'Monthly'], ['4', 'Quarterly'], ['2', 'Half-yearly'], ['1', 'Yearly']], value: '4' }
      ],
      compute: function (v) {
        var nF = parseInt(v.freq, 10), r = v.rate / 100, table = [];
        for (var y = 1; y <= v.years; y++) {
          var b = v.p * Math.pow(1 + r / nF, nF * y);
          table.push([y, F.inr(b - v.p), F.inr(b)]);
        }
        var A = v.p * Math.pow(1 + r / nF, nF * v.years), ci = A - v.p, si = v.p * r * v.years;
        return {
          hero: { label: 'Maturity value', value: F.inr(A), sub: 'Compound interest ' + F.inr(ci) + ' on ' + F.inr(v.p) },
          rows: [
            ['Compound interest', F.inr(ci)],
            ['Simple interest (for comparison)', F.inr(si)],
            ['Extra earned by compounding', F.inr(ci - si)],
            ['Effective annual rate', F.pct((Math.pow(1 + r / nF, nF) - 1) * 100)]
          ],
          donut: [{ label: 'Principal', value: v.p }, { label: 'Interest', value: ci }],
          table: { head: ['Year', 'Interest earned', 'Balance'], rows: table }
        };
      }
    },

    'retirement': {
      inputs: [
        { id: 'expense', label: 'Current monthly expenses', prefix: '₹', min: 5000, max: 1000000, step: 1000, value: 50000 },
        { id: 'infl', label: 'Expected inflation', suffix: '%', min: 0, max: 15, step: 0.5, value: 6 },
        { id: 'toRet', label: 'Years to retirement', suffix: 'years', min: 1, max: 50, step: 1, value: 25 },
        { id: 'inRet', label: 'Years in retirement', suffix: 'years', min: 1, max: 50, step: 1, value: 25 },
        { id: 'pre', label: 'Return before retirement', suffix: '%', min: 1, max: 20, step: 0.5, value: 12 },
        { id: 'post', label: 'Return after retirement', suffix: '%', min: 1, max: 15, step: 0.5, value: 7 }
      ],
      compute: function (v) {
        var i = v.infl / 100, r = v.post / 100;
        var monthlyAtRet = v.expense * Math.pow(1 + i, v.toRet), yearly = monthlyAtRet * 12;
        var corpus = Math.abs(r - i) < 1e-9 ? yearly * v.inRet : yearly * (1 + r) * (1 - Math.pow((1 + i) / (1 + r), v.inRet)) / (r - i);
        var j = v.pre / 1200, m = v.toRet * 12;
        var sip = corpus * j / ((Math.pow(1 + j, m) - 1) * (1 + j));
        return {
          hero: { label: 'Retirement corpus needed', value: F.inr(corpus), sub: '≈ ' + F.compact(corpus) + ' in ' + v.toRet + ' years' },
          rows: [
            ['Monthly expenses at retirement', F.inr(monthlyAtRet)],
            ['Yearly expenses in first year', F.inr(yearly)],
            ['Monthly SIP needed', F.inr(sip)],
            ['Total you will invest', F.inr(sip * m)]
          ],
          donut: [{ label: 'You invest', value: sip * m }, { label: 'Growth', value: Math.max(0, corpus - sip * m) }]
        };
      }
    },

    'home-loan': {
      inputs: [
        { id: 'price', label: 'Property price', prefix: '₹', min: 500000, max: 200000000, step: 50000, value: 8000000 },
        { id: 'down', label: 'Down payment', suffix: '%', min: 0, max: 90, step: 1, value: 20 },
        { id: 'rate', label: 'Interest rate (p.a.)', suffix: '%', min: 5, max: 16, step: 0.05, value: 8.5 },
        { id: 'years', label: 'Loan tenure', suffix: 'years', min: 1, max: 30, step: 1, value: 20 }
      ],
      compute: function (v) {
        var down = v.price * v.down / 100, loan = v.price - down, nM = v.years * 12;
        var emi = loan > 0 ? emiOf(loan, v.rate, nM) : 0, total = emi * nM, interest = total - loan;
        return {
          hero: { label: 'Monthly EMI', value: F.inr(emi), sub: 'on a loan of ' + F.compact(loan) + ' for ' + v.years + ' years' },
          rows: [
            ['Loan amount', F.inr(loan)],
            ['Down payment', F.inr(down)],
            ['Total interest', F.inr(interest)],
            ['Total loan repayment', F.inr(total)],
            ['Total cost of the home', F.inr(down + total)]
          ],
          donut: loan > 0 ? [{ label: 'Principal', value: loan }, { label: 'Interest', value: interest }] : null,
          table: loan > 0 ? { head: ['Year', 'Principal paid', 'Interest paid', 'Balance'], rows: amortise(loan, v.rate, nM, emi) } : null
        };
      }
    },

    'loan-eligibility': {
      inputs: [
        { id: 'income', label: 'Net monthly income', prefix: '₹', min: 10000, max: 5000000, step: 1000, value: 100000 },
        { id: 'emis', label: 'Existing monthly EMIs', prefix: '₹', min: 0, max: 2000000, step: 500, value: 10000 },
        { id: 'rate', label: 'Interest rate (p.a.)', suffix: '%', min: 5, max: 24, step: 0.05, value: 9 },
        { id: 'years', label: 'Loan tenure', suffix: 'years', min: 1, max: 30, step: 1, value: 20 },
        { id: 'foir', label: 'FOIR allowed by lender', suffix: '%', min: 30, max: 75, step: 1, value: 50 }
      ],
      compute: function (v) {
        var limit = v.income * v.foir / 100, maxEmi = Math.max(0, limit - v.emis), r = v.rate / 1200, nM = v.years * 12;
        var loan = maxEmi <= 0 ? 0 : (r ? maxEmi * (Math.pow(1 + r, nM) - 1) / (r * Math.pow(1 + r, nM)) : maxEmi * nM);
        return {
          hero: {
            label: 'Maximum loan you can get', value: F.inr(loan), tone: loan > 0 ? '' : 'down',
            sub: maxEmi > 0 ? '≈ ' + F.compact(loan) + ' at ' + v.rate + '% for ' + v.years + ' years' : 'Existing EMIs already use up your EMI limit'
          },
          rows: [
            ['Maximum new EMI', F.inr(maxEmi)],
            ['Total EMI limit (' + v.foir + '% of income)', F.inr(limit)],
            ['Existing EMIs', F.inr(v.emis)],
            ['Income left after all EMIs', F.inr(v.income - v.emis - maxEmi)]
          ],
          donut: [{ label: 'Existing EMIs', value: v.emis }, { label: 'New EMI capacity', value: maxEmi }, { label: 'Rest of income', value: Math.max(0, v.income - v.emis - maxEmi) }]
        };
      }
    },

    'fno-margin': {
      inputs: [
        { id: 'price', label: 'Futures price', prefix: '₹', min: 1, max: 100000, step: 0.5, value: 1500 },
        { id: 'lot', label: 'Lot size', suffix: 'units', min: 1, max: 20000, step: 1, value: 500 },
        { id: 'lots', label: 'Number of lots', min: 1, max: 100, step: 1, value: 1 },
        { id: 'margin', label: 'Margin required (SPAN + exposure)', suffix: '%', min: 5, max: 100, step: 0.5, value: 18 }
      ],
      compute: function (v) {
        var qty = v.lot * v.lots, value = v.price * qty, margin = value * v.margin / 100, move = value * 0.01;
        return {
          hero: { label: 'Margin required', value: F.inr(margin), sub: 'to control a position worth ' + F.compact(value) },
          rows: [
            ['Contract value', F.inr(value)],
            ['Margin per lot', F.inr(margin / v.lots)],
            ['Leverage', n(margin ? value / margin : 0, 1) + 'x'],
            ['P&L for a 1% move', '± ' + F.inr(move)],
            ['Return on margin for a 1% move', '± ' + F.pct(margin ? move / margin * 100 : 0, 1)]
          ]
        };
      }
    },

    'options-pnl': {
      inputs: [
        { id: 'kind', label: 'Option type', type: 'options', options: [['call', 'Call (CE)'], ['put', 'Put (PE)']], value: 'call' },
        { id: 'side', label: 'Your position', type: 'options', options: [['buy', 'Bought'], ['sell', 'Sold']], value: 'buy' },
        { id: 'strike', label: 'Strike price', prefix: '₹', min: 1, max: 100000, step: 50, value: 25000 },
        { id: 'prem', label: 'Premium per unit', prefix: '₹', min: 0.05, max: 5000, step: 0.05, value: 120 },
        { id: 'lot', label: 'Lot size', suffix: 'units', min: 1, max: 20000, step: 1, value: 75 },
        { id: 'lots', label: 'Number of lots', min: 1, max: 100, step: 1, value: 1 },
        { id: 'spot', label: 'Underlying price at expiry', prefix: '₹', min: 1, max: 100000, step: 10, value: 25300 }
      ],
      compute: function (v) {
        var call = v.kind === 'call', buy = v.side === 'buy', qty = v.lot * v.lots;
        function pnlAt(s) {
          var payoff = call ? Math.max(s - v.strike, 0) : Math.max(v.strike - s, 0);
          return (buy ? payoff - v.prem : v.prem - payoff) * qty;
        }
        var pnl = pnlAt(v.spot), be = call ? v.strike + v.prem : v.strike - v.prem, premTotal = v.prem * qty;
        var putFloor = Math.max(0, v.strike - v.prem) * qty;
        var maxProfit = buy ? (call ? 'Unlimited' : F.inr(putFloor)) : F.inr(premTotal);
        var maxLoss = buy ? F.inr(premTotal) : (call ? 'Unlimited' : F.inr(putFloor));
        var table = [];
        for (var k = -5; k <= 5; k++) {
          var s = Math.max(0, v.strike * (1 + k * 0.01));
          table.push([F.inr2(s), F.inr(pnlAt(s))]);
        }
        return {
          hero: { label: 'P&L at expiry', value: F.inr(pnl), tone: tone(pnl), sub: (buy ? 'Bought ' : 'Sold ') + v.lots + ' lot' + (v.lots > 1 ? 's' : '') + ' of ' + F.num(v.strike) + (call ? ' CE' : ' PE') },
          rows: [
            ['Breakeven at expiry', F.inr2(be)],
            [buy ? 'Premium paid' : 'Premium received', F.inr(premTotal)],
            ['Maximum profit', maxProfit],
            ['Maximum loss', maxLoss],
            ['Quantity', F.num(qty) + ' units']
          ],
          table: { title: 'Payoff at expiry (strike ± 5%)', head: ['Underlying at expiry', 'P&L'], rows: table }
        };
      }
    },

    'option-premium': {
      inputs: [
        { id: 'kind', label: 'Option type', type: 'options', options: [['call', 'Call (CE)'], ['put', 'Put (PE)']], value: 'call' },
        { id: 's', label: 'Spot price', prefix: '₹', min: 1, max: 100000, step: 1, value: 25000 },
        { id: 'k', label: 'Strike price', prefix: '₹', min: 1, max: 100000, step: 50, value: 25000 },
        { id: 'days', label: 'Days to expiry', suffix: 'days', min: 1, max: 365, step: 1, value: 7 },
        { id: 'iv', label: 'Implied volatility (IV)', suffix: '%', min: 1, max: 150, step: 0.5, value: 14 },
        { id: 'r', label: 'Risk-free rate', suffix: '%', min: 0, max: 15, step: 0.1, value: 6.5 },
        { id: 'lot', label: 'Lot size', suffix: 'units', min: 1, max: 20000, step: 1, value: 75 }
      ],
      compute: function (v) {
        var call = v.kind === 'call', T = v.days / 365, sig = v.iv / 100, r = v.r / 100;
        var sq = Math.sqrt(T), d1 = (Math.log(v.s / v.k) + (r + sig * sig / 2) * T) / (sig * sq), d2 = d1 - sig * sq;
        var disc = v.k * Math.exp(-r * T);
        var price = call ? v.s * ncdf(d1) - disc * ncdf(d2) : disc * ncdf(-d2) - v.s * ncdf(-d1);
        var intrinsic = Math.max(0, call ? v.s - v.k : v.k - v.s);
        var delta = call ? ncdf(d1) : ncdf(d1) - 1;
        var gamma = npdf(d1) / (v.s * sig * sq);
        var theta = (-v.s * npdf(d1) * sig / (2 * sq) + (call ? -r * disc * ncdf(d2) : r * disc * ncdf(-d2))) / 365;
        var vega = v.s * npdf(d1) * sq / 100;
        var rho = (call ? disc * T * ncdf(d2) : -disc * T * ncdf(-d2)) / 100;
        var money = Math.abs(v.s - v.k) < v.k * 0.005 ? 'At the money' : ((call ? v.s > v.k : v.s < v.k) ? 'In the money' : 'Out of the money');
        return {
          hero: { label: 'Fair premium (Black-Scholes)', value: F.inr2(price), sub: money + ' · ' + F.inr(price * v.lot) + ' per lot' },
          rows: [
            ['Intrinsic value', F.inr2(intrinsic)],
            ['Time value', F.inr2(Math.max(0, price - intrinsic))],
            ['Delta', n(delta, 3)],
            ['Gamma', n(gamma, 5)],
            ['Theta (per day)', F.inr2(theta)],
            ['Vega (per 1% IV)', F.inr2(vega)],
            ['Rho (per 1% rate)', F.inr2(rho)]
          ]
        };
      }
    },

    'hedging': {
      inputs: [
        { id: 'pv', label: 'Portfolio value', prefix: '₹', min: 100000, max: 1000000000, step: 50000, value: 5000000 },
        { id: 'beta', label: 'Portfolio beta', min: 0.1, max: 3, step: 0.05, value: 1.2 },
        { id: 'fut', label: 'Index futures price', prefix: '₹', min: 100, max: 100000, step: 10, value: 25000 },
        { id: 'lot', label: 'Index lot size', suffix: 'units', min: 1, max: 1000, step: 1, value: 75 },
        { id: 'margin', label: 'Margin per lot', suffix: '%', min: 5, max: 50, step: 0.5, value: 12 }
      ],
      compute: function (v) {
        var exposure = v.pv * v.beta, cv = v.fut * v.lot, exact = cv ? exposure / cv : 0, lots = Math.round(exact);
        var cover = exposure ? lots * cv / exposure * 100 : 0;
        return {
          hero: {
            label: 'Index futures lots to sell', value: F.num(lots) + (lots === 1 ? ' lot' : ' lots'), tone: lots ? '' : 'down',
            sub: lots ? 'Exact hedge ' + n(exact, 2) + ' lots · covers ' + F.pct(cover, 0) + ' of beta exposure' : 'Portfolio is smaller than half a lot; a futures hedge is too large'
          },
          rows: [
            ['Beta-adjusted exposure', F.inr(exposure)],
            ['Value of one lot', F.inr(cv)],
            ['Hedge value (lots × lot value)', F.inr(lots * cv)],
            ['Margin required', F.inr(lots * cv * v.margin / 100)]
          ]
        };
      }
    },

    'beta': {
      inputs: [
        { id: 'stock', label: 'Stock returns (%) — one per period', type: 'text', value: '4.2, -2.1, 3.5, 5.1, -4.9, 3.0, 5.1, -2.4, 1.9, -3.2, 3.8, 4.4' },
        { id: 'market', label: 'Market returns (%) — same periods', type: 'text', value: '2.9, -1.2, 2.1, 4.3, -3.1, 1.6, 3.2, -0.6, 2.4, -2.2, 1.9, 2.7' }
      ],
      compute: function (v) {
        var s = parseList(v.stock), m = parseList(v.market);
        var err = s.length < 2 || m.length < 2 ? 'Enter at least two returns in each list' : (s.length !== m.length ? 'Both lists need the same number of returns (' + s.length + ' vs ' + m.length + ')' : '');
        if (err) return { hero: { label: 'Beta', value: '—', sub: err }, rows: [] };
        var N = s.length, ms = 0, mm = 0, cov = 0, vs = 0, vm = 0;
        for (var i = 0; i < N; i++) { ms += s[i] / N; mm += m[i] / N; }
        for (i = 0; i < N; i++) { cov += (s[i] - ms) * (m[i] - mm) / N; vs += Math.pow(s[i] - ms, 2) / N; vm += Math.pow(m[i] - mm, 2) / N; }
        if (!vm) return { hero: { label: 'Beta', value: '—', sub: 'Market returns must vary to calculate beta' }, rows: [] };
        var beta = cov / vm, corr = vs ? cov / Math.sqrt(vs * vm) : 0;
        var read = beta > 1.05 ? 'More volatile than the market (aggressive)' : (beta >= 0.95 ? 'Moves broadly in line with the market' : (beta > 0 ? 'Less volatile than the market (defensive)' : 'Tends to move opposite to the market'));
        return {
          hero: { label: 'Beta', value: n(beta, 2), sub: read },
          rows: [
            ['Correlation with market', n(corr, 2)],
            ['Covariance', n(cov, 3)],
            ['Market variance', n(vm, 3)],
            ['Average stock return', F.pct(ms)],
            ['Average market return', F.pct(mm)],
            ['Data points', F.num(N)]
          ]
        };
      }
    },

    'income-tax': {
      inputs: [
        { id: 'income', label: 'Gross annual income', prefix: '₹', min: 0, max: 50000000, step: 10000, value: 1500000 },
        { id: 'salaried', label: 'Salaried or pensioner?', type: 'options', options: [['yes', 'Yes'], ['no', 'No']], value: 'yes' },
        { id: 'ded', label: 'Deductions (old regime: 80C, 80D, HRA, home-loan interest…)', prefix: '₹', min: 0, max: 1500000, step: 5000, value: 250000 }
      ],
      compute: function (v) {
        var sal = v.salaried === 'yes';
        var tiNew = Math.max(0, v.income - (sal ? 75000 : 0));
        var tiOld = Math.max(0, v.income - (sal ? 50000 : 0) - v.ded);
        var newBase = slabTax(tiNew, [[400000, 0], [800000, 0.05], [1200000, 0.10], [1600000, 0.15], [2000000, 0.20], [2400000, 0.25], [Infinity, 0.30]]);
        if (tiNew <= 1200000) newBase = 0; else newBase = Math.min(newBase, tiNew - 1200000);
        var oldBase = tiOld <= 500000 ? 0 : slabTax(tiOld, [[250000, 0], [500000, 0.05], [1000000, 0.20], [Infinity, 0.30]]);
        var newTax = newBase * 1.04, oldTax = oldBase * 1.04, best = Math.min(newTax, oldTax), diff = Math.abs(newTax - oldTax);
        var better = newTax <= oldTax ? 'New regime' : 'Old regime';
        return {
          hero: { label: 'Tax payable (' + better.toLowerCase() + ')', value: F.inr(best), sub: diff < 1 ? 'Both regimes cost the same' : better + ' saves ' + F.inr(diff) },
          rows: [
            ['Tax under new regime', F.inr(newTax)],
            ['Taxable income (new)', F.inr(tiNew)],
            ['Tax under old regime', F.inr(oldTax)],
            ['Taxable income (old)', F.inr(tiOld)],
            ['Effective tax rate', F.pct(v.income ? best / v.income * 100 : 0)],
            ['Monthly income after tax', F.inr((v.income - best) / 12)]
          ],
          donut: v.income > 0 ? [{ label: 'Take-home', value: v.income - best }, { label: 'Tax', value: best }] : null
        };
      }
    },

    'break-even': {
      inputs: [
        { id: 'fc', label: 'Fixed costs', prefix: '₹', min: 0, max: 100000000, step: 1000, value: 100000 },
        { id: 'vc', label: 'Variable cost per unit', prefix: '₹', min: 0, max: 100000, step: 1, value: 50 },
        { id: 'sp', label: 'Selling price per unit', prefix: '₹', min: 1, max: 100000, step: 1, value: 100 }
      ],
      compute: function (v) {
        var cm = v.sp - v.vc;
        if (cm <= 0) return { hero: { label: 'Break-even units', value: 'Not possible', tone: 'down', sub: 'Selling price must be higher than the variable cost per unit' }, rows: [['Loss on every unit sold', F.inr2(-cm)]] };
        var exact = v.fc / cm, units = Math.ceil(exact), table = [];
        [0.5, 0.75, 1, 1.25, 1.5, 2].forEach(function (f) {
          var u = Math.round(units * f);
          table.push([F.num(u), F.inr(u * v.sp), F.inr(u * cm - v.fc)]);
        });
        return {
          hero: { label: 'Break-even units', value: F.num(units) + ' units', sub: 'Sales of ' + F.inr(units * v.sp) + ' cover all costs' },
          rows: [
            ['Break-even sales revenue', F.inr(exact * v.sp)],
            ['Contribution per unit', F.inr2(cm)],
            ['Contribution margin', F.pct(cm / v.sp * 100)]
          ],
          table: { title: 'Profit at different sales volumes', head: ['Units sold', 'Revenue', 'Profit'], rows: table }
        };
      }
    }
  };

  var slug = root.getAttribute('data-calc');
  var def = CALCS[slug];
  if (!def) return;

  var inputsEl = root.querySelector('[data-inputs]');
  var resultsEl = root.querySelector('[data-results]');
  var state = {};
  var params = new URLSearchParams(window.location.search);

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  }

  function fillPct(range) {
    var min = parseFloat(range.min), max = parseFloat(range.max), val = parseFloat(range.value);
    range.style.setProperty('--p', ((Math.min(max, Math.max(min, val)) - min) / (max - min) * 100) + '%');
  }

  function scaleLabel(inp, v) {
    return (inp.prefix || '') + n(v, 2) + (inp.suffix ? ' ' + inp.suffix : '');
  }

  /* ---------- Build inputs ---------- */
  function build() {
    inputsEl.innerHTML = '';
    def.inputs.forEach(function (inp) {
      var initial = params.has(inp.id) ? params.get(inp.id) : inp.value;
      var row = document.createElement('div');
      row.className = 'field-row';

      if (inp.type === 'options') {
        state[inp.id] = String(initial);
        row.innerHTML = '<div class="field-top"><label>' + esc(inp.label) + '</label></div><div class="opt-group" role="group">' +
          inp.options.map(function (o) {
            return '<button type="button" data-v="' + esc(o[0]) + '" class="' + (String(o[0]) === state[inp.id] ? 'active' : '') + '">' + esc(o[1]) + '</button>';
          }).join('') + '</div>';
        row.querySelectorAll('button').forEach(function (b) {
          b.addEventListener('click', function () {
            state[inp.id] = b.getAttribute('data-v');
            row.querySelectorAll('button').forEach(function (x) { x.classList.toggle('active', x === b); });
            render();
          });
        });
      } else if (inp.type === 'text') {
        state[inp.id] = String(initial);
        var tid = 'f-' + inp.id;
        row.innerHTML = '<div class="field-top"><label for="' + tid + '">' + esc(inp.label) + '</label></div>' +
          '<textarea id="' + tid + '" class="calc-text" rows="3" spellcheck="false"></textarea>';
        var ta = row.querySelector('textarea');
        ta.value = state[inp.id];
        ta.addEventListener('input', function () { state[inp.id] = ta.value; render(); });
      } else {
        var val = parseFloat(initial);
        if (!isFinite(val)) val = inp.value;
        state[inp.id] = val;
        var id = 'f-' + inp.id;
        row.innerHTML =
          '<div class="field-top"><label for="' + id + '">' + esc(inp.label) + '</label>' +
          '<div class="num-box">' + (inp.prefix ? '<span>' + inp.prefix + '</span>' : '') +
          '<input id="' + id + '" type="number" inputmode="decimal" step="' + inp.step + '" value="' + val + '">' +
          (inp.suffix ? '<span>' + esc(inp.suffix) + '</span>' : '') + '</div></div>' +
          '<input class="range" type="range" min="' + inp.min + '" max="' + inp.max + '" step="' + inp.step + '" value="' + val + '" aria-label="' + esc(inp.label) + '">' +
          '<div class="range-scale"><span>' + scaleLabel(inp, inp.min) + '</span><span>' + scaleLabel(inp, inp.max) + '</span></div>';

        var num = row.querySelector('input[type=number]');
        var range = row.querySelector('input[type=range]');
        fillPct(range);

        num.addEventListener('input', function () {
          var x = parseFloat(num.value);
          state[inp.id] = isFinite(x) ? x : 0;
          range.value = state[inp.id];
          fillPct(range);
          render();
        });
        num.addEventListener('blur', function () {
          if (!isFinite(parseFloat(num.value)) || parseFloat(num.value) < inp.min) {
            num.value = inp.min; state[inp.id] = inp.min; range.value = inp.min; fillPct(range); render();
          }
        });
        range.addEventListener('input', function () {
          state[inp.id] = parseFloat(range.value);
          num.value = range.value;
          fillPct(range);
          render();
        });
      }
      inputsEl.appendChild(row);
    });

    var reset = document.createElement('button');
    reset.type = 'button';
    reset.className = 'btn btn-outline btn-sm';
    reset.style.justifySelf = 'start';
    reset.textContent = 'Reset to defaults';
    reset.addEventListener('click', function () {
      params = new URLSearchParams();
      build();
      render();
    });
    inputsEl.appendChild(reset);
  }

  /* ---------- Donut ---------- */
  var COLORS = ['var(--chart-1)', 'var(--gold)', 'var(--green)'];
  function donut(parts, fmt) {
    var total = parts.reduce(function (s, p) { return s + Math.max(0, p.value); }, 0);
    if (!total) return '';
    var r = 42, c = 2 * Math.PI * r, offset = 0;
    var arcs = parts.map(function (p, i) {
      var len = Math.max(0, p.value) / total * c;
      var seg = '<circle cx="60" cy="60" r="' + r + '" fill="none" stroke="' + COLORS[i % COLORS.length] + '" stroke-width="16" stroke-dasharray="' + len + ' ' + (c - len) + '" stroke-dashoffset="' + (-offset) + '" transform="rotate(-90 60 60)"/>';
      offset += len;
      return seg;
    }).join('');
    var legend = parts.map(function (p, i) {
      return '<div><span class="sw" style="width:10px;height:10px;border-radius:3px;background:' + COLORS[i % COLORS.length] + '"></span>' + esc(p.label) +
        '<b>' + (fmt ? fmt(p.value) : F.inr(p.value)) + '</b></div>';
    }).join('');
    var mainPct = Math.round(Math.max(0, parts[parts.length > 1 ? 1 : 0].value) / total * 100);
    return '<div class="donut-wrap"><svg class="donut" viewBox="0 0 120 120" role="img" aria-label="Breakdown chart">' +
      '<circle cx="60" cy="60" r="' + r + '" fill="none" stroke="var(--surface-3)" stroke-width="16"/>' + arcs +
      '<text x="60" y="58" text-anchor="middle" font-size="17" font-weight="800" fill="var(--text)">' + mainPct + '%</text>' +
      '<text x="60" y="74" text-anchor="middle" font-size="9" fill="var(--muted)">' + esc(parts[parts.length > 1 ? 1 : 0].label) + '</text>' +
      '</svg><div class="donut-legend" style="flex:1">' + legend + '</div></div>';
  }

  /* ---------- Render results ---------- */
  var tableOpen = false;
  function render() {
    var out;
    try { out = def.compute(state); } catch (e) { return; }
    var h = out.hero;
    var html = '<div class="result-hero"><span>' + esc(h.label) + '</span><b class="' + (h.tone || '') + '">' + esc(h.value) + '</b>' +
      (h.sub ? '<small>' + esc(h.sub) + '</small>' : '') + '</div>';
    if (out.donut) html += donut(out.donut, out.donutFmt);
    html += '<div class="result-rows">' + out.rows.map(function (r) {
      return '<div class="rr"><span>' + esc(r[0]) + '</span><b>' + esc(r[1]) + '</b></div>';
    }).join('') + '</div>';
    if (out.table && out.table.rows.length) {
      html += '<details class="card" data-yr ' + (tableOpen ? 'open' : '') + ' style="overflow:hidden"><summary style="cursor:pointer;padding:12px 16px;font-weight:600;font-size:14px">' + esc(out.table.title || 'Year-wise breakdown') + '</summary>' +
        '<div class="table-wrap" style="max-height:320px;overflow:auto"><table class="table yr-table"><thead><tr>' +
        out.table.head.map(function (x, i) { return '<th' + (i ? ' class="r"' : '') + '>' + esc(x) + '</th>'; }).join('') + '</tr></thead><tbody>' +
        out.table.rows.map(function (row) {
          return '<tr>' + row.map(function (c, i) { return '<td' + (i ? ' class="r"' : '') + '>' + esc(c) + '</td>'; }).join('') + '</tr>';
        }).join('') + '</tbody></table></div></details>';
    }
    resultsEl.innerHTML = html;
    var d = resultsEl.querySelector('[data-yr]');
    if (d) d.addEventListener('toggle', function () { tableOpen = d.open; });
  }

  build();
  render();
})();

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
      html += '<details class="card" data-yr ' + (tableOpen ? 'open' : '') + ' style="overflow:hidden"><summary style="cursor:pointer;padding:12px 16px;font-weight:600;font-size:14px">Year-wise breakdown</summary>' +
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

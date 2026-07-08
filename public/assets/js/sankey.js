/* LIPA money-flow Sankey renderer.
   Reads a {nodes,links} payload from #sankey-data and draws inline SVG into #sankey.
   Columns: 0 = income source, 1 = account, 2 = expense category.
   Accounts that are fed by a transfer (e.g. Cash funded from Bank) get their own lane to the
   right of the main accounts, so the transfer reads as a clean forward flow. Link endpoints are
   ordered by the opposite node's position to minimise crossings.
   Ribbons: in = --pos (green), out = --neg (red), transfer = --flow-transfer (blue). */
(function () {
  "use strict";
  var host = document.getElementById("sankey");
  var dataEl = document.getElementById("sankey-data");
  if (!host || !dataEl) return;

  var payload;
  try { payload = JSON.parse(dataEl.textContent); } catch (e) { return; }
  var nodes = payload.nodes || [], links = payload.links || [];
  if (!links.length) return;

  var N = {}; nodes.forEach(function (n) { N[n.id] = n; });
  var fmt = new Intl.NumberFormat("en-GB");
  var kindColor = { "in": "var(--pos)", "out": "var(--neg)", "transfer": "var(--flow-transfer)" };
  var kindLabel = { "in": "Income", "out": "Expense", "transfer": "Transfer" };

  var NW = 15, TOP = 78, BOT = 560, HAV = BOT - TOP, PAD = 15;

  // node in/out sums & value
  nodes.forEach(function (n) { n.inSum = 0; n.outSum = 0; });
  links.forEach(function (l) { N[l.s].outSum += l.v; N[l.t].inSum += l.v; });
  nodes.forEach(function (n) { n.value = n.col === 0 ? n.outSum : n.col === 2 ? n.inSum : Math.max(n.inSum, n.outSum); });

  // lanes: income | accounts | (transfer-fed accounts) | expenses
  var transferIn = {};
  links.forEach(function (l) { if (l.kind === "transfer") transferIn[l.t] = (transferIn[l.t] || 0) + l.v; });
  nodes.forEach(function (n) {
    n.lane = n.col === 0 ? "income" : n.col === 2 ? "expense" : (transferIn[n.id] > 0 ? "down" : "acc");
  });
  var downUsed = nodes.some(function (n) { return n.lane === "down"; });
  var W = downUsed ? 1050 : 1000;
  var laneX = downUsed
    ? { income: 245, acc: 455, down: 600, expense: 800 }
    : { income: 255, acc: 500, expense: 745 };
  var laneOrder = downUsed ? ["income", "acc", "down", "expense"] : ["income", "acc", "expense"];
  host.setAttribute("viewBox", "0 0 " + W + " 600");

  // Group expense categories by their dominant source account (main vs transfer-fed),
  // so Bank's expenses cluster at the top and Cash's at the bottom instead of interleaving.
  nodes.forEach(function (n) { if (n.col === 2) { n._acc = 0; n._down = 0; } });
  links.forEach(function (l) {
    var t = N[l.t];
    if (t.col === 2) { if (N[l.s].lane === "down") t._down += l.v; else t._acc += l.v; }
  });
  nodes.forEach(function (n) { if (n.col === 2) n._grp = n._down > n._acc ? 1 : 0; });

  var lanes = {}; laneOrder.forEach(function (k) { lanes[k] = []; });
  nodes.forEach(function (n) { lanes[n.lane].push(n); });
  laneOrder.forEach(function (k) {
    if (k === "expense") lanes[k].sort(function (a, b) { return (a._grp - b._grp) || (b.value - a.value); });
    else lanes[k].sort(function (a, b) { return b.value - a.value; });
  });

  var scale = Infinity;
  laneOrder.forEach(function (k) {
    var sum = lanes[k].reduce(function (a, n) { return a + n.value; }, 0);
    var avail = HAV - Math.max(lanes[k].length - 1, 0) * PAD;
    if (sum > 0) scale = Math.min(scale, avail / sum);
  });
  if (!isFinite(scale) || scale <= 0) scale = 0.0001;

  laneOrder.forEach(function (k) {
    var list = lanes[k];
    var h = list.reduce(function (a, n) { return a + n.value * scale; }, 0) + Math.max(list.length - 1, 0) * PAD;
    // Transfer-fed accounts (Cash) sit at the bottom so the transfer bends downward,
    // clear of the main account's expenses.
    var y = k === "down" ? (TOP + HAV - h) : TOP + (HAV - h) / 2;
    list.forEach(function (n) {
      n.h = n.value * scale; n.y0 = y; n.y1 = y + n.h; y = n.y1 + PAD;
      n.x1 = laneX[k]; n.x2 = laneX[k] + NW; n.yc = (n.y0 + n.y1) / 2;
    });
  });

  // order link endpoints by the opposite node's vertical centre (crossing reduction)
  nodes.forEach(function (n) { n._out = []; n._in = []; });
  links.forEach(function (l) { N[l.s]._out.push(l); N[l.t]._in.push(l); });
  nodes.forEach(function (n) {
    n._out.sort(function (a, b) { return N[a.t].yc - N[b.t].yc; });
    var o = n.y0; n._out.forEach(function (l) { l._sy0 = o; o += l.v * scale; });
    n._in.sort(function (a, b) { return N[a.s].yc - N[b.s].yc; });
    var i = n.y0; n._in.forEach(function (l) { l._ty0 = i; i += l.v * scale; });
  });

  var SVGNS = "http://www.w3.org/2000/svg";
  function el(tag, a) { var e = document.createElementNS(SVGNS, tag); for (var k in a) e.setAttribute(k, a[k]); return e; }

  // column headers
  var heads = [];
  if (lanes.income.length) heads.push(["INCOME · SOURCE", laneX.income + NW, "end"]);
  if (lanes.acc.length) {
    var accHeadX = downUsed ? (laneX.acc + laneX.down) / 2 + NW / 2 : laneX.acc + NW / 2;
    heads.push(["ACCOUNTS", accHeadX, "middle"]);
  }
  if (lanes.expense.length) heads.push(["EXPENSES · CATEGORY", laneX.expense, "start"]);
  heads.forEach(function (h) {
    var t = el("text", { x: h[1], y: 42, "text-anchor": h[2], "class": "colhead" });
    t.textContent = h[0]; host.appendChild(t);
  });

  function ribbonPath(sx, sy0, sy1, tx, ty0, ty1, bow) {
    if (bow) {
      var cp = Math.max(sx, tx) + bow;
      return "M" + sx + "," + sy0 + " C" + cp + "," + sy0 + " " + cp + "," + ty0 + " " + tx + "," + ty0 +
             " L" + tx + "," + ty1 + " C" + cp + "," + ty1 + " " + cp + "," + sy1 + " " + sx + "," + sy1 + " Z";
    }
    var mx = (sx + tx) / 2;
    return "M" + sx + "," + sy0 + " C" + mx + "," + sy0 + " " + mx + "," + ty0 + " " + tx + "," + ty0 +
           " L" + tx + "," + ty1 + " C" + mx + "," + ty1 + " " + mx + "," + sy1 + " " + sx + "," + sy1 + " Z";
  }

  var ribbonEls = [];
  // draw larger flows first so thin ones sit on top and stay hoverable
  links.slice().sort(function (a, b) { return b.v - a.v; }).forEach(function (l) {
    var s = N[l.s], t = N[l.t], th = l.v * scale, rth = Math.max(th, 2.5);
    var sc = l._sy0 + th / 2, tc = l._ty0 + th / 2;
    var bow = (t.x1 >= s.x2) ? 0 : 55;   // clean forward flow; only a same-lane/back flow bows
    var d = ribbonPath(s.x2, sc - rth / 2, sc + rth / 2, t.x1, tc - rth / 2, tc + rth / 2, bow);
    var p = el("path", { d: d, "class": "ribbon", fill: kindColor[l.kind], "fill-opacity": 0.44 });
    p.__l = l; host.appendChild(p); ribbonEls.push(p);
  });

  // nodes + labels on top
  nodes.forEach(function (n) {
    host.appendChild(el("rect", { x: n.x1, y: n.y0, width: NW, height: Math.max(n.h, 2), rx: 2.5, "class": "node-rect" }));
    var cy = n.yc;
    if (n.col === 1) {
      var tt = el("text", { x: (n.x1 + n.x2) / 2, y: n.y0 - 8, "text-anchor": "middle", "class": "nlabel" });
      var a = el("tspan", {}); a.textContent = n.label + "  ";
      var b = el("tspan", { fill: "var(--muted)" }); b.textContent = fmt.format(n.value) + " TZS";
      tt.appendChild(a); tt.appendChild(b); host.appendChild(tt);
    } else {
      var right = n.col === 2, lx = right ? n.x2 + 9 : n.x1 - 9, anc = right ? "start" : "end";
      var l1 = el("text", { x: lx, y: cy - 1, "text-anchor": anc, "class": "nlabel" });
      l1.textContent = n.label; host.appendChild(l1);
      var l2 = el("text", { x: lx, y: cy + 13, "text-anchor": anc, "class": "nval" });
      l2.textContent = fmt.format(n.value) + " TZS"; host.appendChild(l2);
    }
  });

  // interaction
  var tip = document.getElementById("tip");
  function showTip(html, x, y) {
    if (!tip) return;
    tip.innerHTML = html; tip.classList.add("on");
    var w = tip.offsetWidth, h = tip.offsetHeight, px = x + 14, py = y + 14;
    if (px + w > window.innerWidth - 8) px = x - w - 14;
    if (py + h > window.innerHeight - 8) py = y - h - 14;
    tip.style.left = px + "px"; tip.style.top = py + "px";
  }
  function hideTip() { if (tip) tip.classList.remove("on"); }

  ribbonEls.forEach(function (p) {
    var l = p.__l, s = N[l.s], t = N[l.t];
    p.addEventListener("mouseenter", function (e) {
      ribbonEls.forEach(function (o) { if (o !== p) o.classList.add("dim"); });
      p.setAttribute("fill-opacity", 0.72);
      showTip('<div class="t-amt">' + fmt.format(l.v) + ' TZS</div>' +
              '<div class="t-sub"><span class="dot" style="background:' + kindColor[l.kind] + '"></span>' +
              s.label + ' &rarr; ' + t.label + '</div>' +
              '<div class="t-sub">' + kindLabel[l.kind] + '</div>', e.clientX, e.clientY);
    });
    p.addEventListener("mousemove", function (e) { showTip(tip.innerHTML, e.clientX, e.clientY); });
    p.addEventListener("mouseleave", function () {
      ribbonEls.forEach(function (o) { o.classList.remove("dim"); });
      p.setAttribute("fill-opacity", 0.44); hideTip();
    });
  });

  // table
  var tb = document.querySelector("#flowtable tbody");
  if (tb) {
    links.slice().sort(function (a, b) { return b.v - a.v; }).forEach(function (l) {
      var tr = document.createElement("tr");
      tr.innerHTML = '<td>' + N[l.s].label + '</td><td>' + N[l.t].label + '</td>' +
        '<td><span class="kind-pill"><span class="dot" style="background:' + kindColor[l.kind] + '"></span>' +
        kindLabel[l.kind] + '</span></td>' +
        '<td class="num">' + fmt.format(l.v) + '</td>';
      tb.appendChild(tr);
    });
  }
})();

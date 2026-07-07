/* LIPA money-flow Sankey renderer.
   Reads a {nodes,links} payload from #sankey-data and draws inline SVG into #sankey.
   Columns: 0 = income source, 1 = account, 2 = expense category.
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
  var colX = { 0: 255, 1: 500, 2: 745 };

  // node values
  nodes.forEach(function (n) { n.inSum = 0; n.outSum = 0; });
  links.forEach(function (l) { N[l.s].outSum += l.v; N[l.t].inSum += l.v; });
  nodes.forEach(function (n) { n.value = n.col === 0 ? n.outSum : n.col === 2 ? n.inSum : Math.max(n.inSum, n.outSum); });

  var cols = { 0: [], 1: [], 2: [] };
  nodes.forEach(function (n) { cols[n.col].push(n); });
  // biggest first within a column reads tidiest
  [0, 1, 2].forEach(function (c) { cols[c].sort(function (a, b) { return b.value - a.value; }); });

  var scale = Infinity;
  [0, 1, 2].forEach(function (c) {
    var sum = cols[c].reduce(function (a, n) { return a + n.value; }, 0);
    var avail = HAV - Math.max(cols[c].length - 1, 0) * PAD;
    if (sum > 0) scale = Math.min(scale, avail / sum);
  });
  if (!isFinite(scale) || scale <= 0) scale = 0.0001;

  [0, 1, 2].forEach(function (c) {
    var list = cols[c];
    var h = list.reduce(function (a, n) { return a + n.value * scale; }, 0) + Math.max(list.length - 1, 0) * PAD;
    var y = TOP + (HAV - h) / 2;
    list.forEach(function (n) {
      n.h = n.value * scale; n.y0 = y; n.y1 = y + n.h; y = n.y1 + PAD;
      n.x1 = colX[c]; n.x2 = colX[c] + NW; n.inC = n.y0; n.outC = n.y0;
    });
  });

  var SVGNS = "http://www.w3.org/2000/svg";
  function el(tag, a) { var e = document.createElementNS(SVGNS, tag); for (var k in a) e.setAttribute(k, a[k]); return e; }

  // column headers (only where that column has nodes)
  var heads = [];
  if (cols[0].length) heads.push(["INCOME · SOURCE", colX[0] + NW, "end"]);
  if (cols[1].length) heads.push(["ACCOUNTS", colX[1] + NW / 2, "middle"]);
  if (cols[2].length) heads.push(["EXPENSES · CATEGORY", colX[2], "start"]);
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
  var order = { "in": 0, "out": 1, "transfer": 2 };
  links.slice().sort(function (a, b) { return order[a.kind] - order[b.kind]; }).forEach(function (l) {
    var s = N[l.s], t = N[l.t], th = l.v * scale, rth = Math.max(th, 2.5);
    var sy0 = s.outC; s.outC += th;
    var ty0 = t.inC;  t.inC += th;
    var sc = sy0 + th / 2, tc = ty0 + th / 2, bow = l.kind === "transfer" ? 55 : 0;
    var d = ribbonPath(s.x2, sc - rth / 2, sc + rth / 2, t.x1, tc - rth / 2, tc + rth / 2, bow);
    var p = el("path", { d: d, "class": "ribbon", fill: kindColor[l.kind], "fill-opacity": 0.44 });
    p.__l = l; host.appendChild(p); ribbonEls.push(p);
  });

  // nodes + labels on top
  nodes.forEach(function (n) {
    host.appendChild(el("rect", { x: n.x1, y: n.y0, width: NW, height: Math.max(n.h, 2), rx: 2.5, "class": "node-rect" }));
    var cy = (n.y0 + n.y1) / 2;
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

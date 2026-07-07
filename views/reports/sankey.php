<?php /** @var array $d  @var array $s */ ?>
<!DOCTYPE html>
<html lang="en-GB" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script>/* apply saved theme before paint */(function(){try{var t=localStorage.getItem('lipa_theme');if(t==='dark'||t==='light'){document.documentElement.setAttribute('data-theme',t);}}catch(e){}})();</script>
<title>Money flow — <?= e($s['org_name'] ?? 'Organisation') ?></title>
<link rel="stylesheet" href="<?= asset('/assets/css/theme.css') ?>">
<style>
  :root{ --accent: <?= e(\App\hex_color($s['accent_color'] ?? null)) ?>; --flow-transfer:#2a78d6; }
  :root[data-theme="dark"]{ --flow-transfer:#3987e5; }
  @media (prefers-color-scheme:dark){ :root{ --flow-transfer:#3987e5; } }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--ink);
    font-family:var(--font-body,system-ui,-apple-system,"Segoe UI",sans-serif);line-height:1.5;-webkit-font-smoothing:antialiased;}
  .page{max-width:1080px;margin:0 auto;padding:26px 24px 60px;}
  .actions{display:flex;gap:8px;margin-bottom:18px;}
  .btn{font:inherit;font-weight:600;font-size:14px;padding:9px 15px;border-radius:10px;border:1px solid transparent;
    background:var(--accent);color:var(--accent-ink);cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:7px;}
  .btn.ghost{background:var(--surface);color:var(--muted);border-color:var(--line);}
  h1{font-size:clamp(22px,3vw,30px);margin:0 0 4px;letter-spacing:-.01em;}
  .meta{color:var(--muted);font-size:14px;margin:0 0 2px;}
  .totals{display:flex;flex-wrap:wrap;gap:16px;margin:14px 0 4px;font-size:13.5px;color:var(--ink);}
  .totals b{font-variant-numeric:tabular-nums;}
  .legend{display:flex;flex-wrap:wrap;gap:16px;margin:12px 0 14px;font-size:13px;color:var(--muted);}
  .legend span{display:inline-flex;align-items:center;gap:7px;}
  .swatch{width:22px;height:11px;border-radius:3px;display:inline-block;}
  .card{background:var(--surface);border:1px solid var(--line);border-radius:16px;padding:14px 10px 10px;
    box-shadow:0 1px 2px rgba(20,18,14,.04),0 10px 26px rgba(20,18,14,.05);}
  .scroller{overflow-x:auto;}
  svg.sankey{display:block;width:100%;min-width:760px;height:auto;}
  .colhead{fill:var(--faint);font-size:12.5px;font-weight:700;letter-spacing:.05em;}
  .nlabel{fill:var(--ink);font-size:12.5px;}
  .nval{fill:var(--muted);font-size:11px;}
  .node-rect{fill:var(--faint);opacity:.55;transition:opacity .15s;}
  .ribbon{transition:opacity .15s;cursor:pointer;}
  .dim{opacity:.1 !important;}
  .empty{padding:60px 20px;text-align:center;color:var(--muted);}
  h2.tbl-h{font-size:15px;margin:30px 0 10px;}
  table{width:100%;border-collapse:collapse;font-size:13.5px;}
  th,td{padding:8px 12px;border-bottom:1px solid var(--line);text-align:left;}
  th{font-size:11px;letter-spacing:.05em;text-transform:uppercase;color:var(--faint);font-weight:700;}
  td.num{text-align:right;font-variant-numeric:tabular-nums;}
  .kind-pill{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);}
  .kind-pill .dot{width:8px;height:8px;border-radius:50%;}
  footer{margin-top:26px;color:var(--faint);font-size:12px;max-width:74ch;}
  #tip{position:fixed;pointer-events:none;z-index:20;background:var(--surface);color:var(--ink);
    border:1px solid var(--line);border-radius:10px;box-shadow:0 8px 24px rgba(20,18,14,.14);padding:9px 11px;font-size:13px;
    max-width:300px;opacity:0;transform:translateY(-4px);transition:opacity .12s;}
  #tip.on{opacity:1;transform:none;}
  #tip .t-amt{font-weight:700;font-variant-numeric:tabular-nums;}
  #tip .t-sub{color:var(--muted);font-size:12px;margin-top:3px;display:flex;align-items:center;gap:7px;}
  #tip .dot{width:9px;height:9px;border-radius:50%;flex:none;display:inline-block;}
  @media (prefers-reduced-motion:reduce){*{transition:none !important}}
  @media print{ .actions,#tip{display:none !important} body{background:#fff} .card{box-shadow:none} @page{margin:12mm} }
</style>
</head>
<body>
<div class="page">
  <div class="actions">
    <button class="btn" onclick="window.print()">Print / Save as PDF</button>
    <a class="btn ghost" href="/reports">Back</a>
  </div>

  <h1>Money flow</h1>
  <p class="meta"><?= e($s['org_name'] ?? 'Organisation') ?> · Period <?= e($d['from']) ?> to <?= e($d['to']) ?> · Currency TZS</p>

  <?php if (empty($d['links'])): ?>
    <div class="card"><div class="empty">No transactions in this period.</div></div>
  <?php else: ?>
    <div class="totals">
      <span>Income in <b><?= number_format($d['totals']['in'], 2) ?></b></span>
      <span>Transfers <b><?= number_format($d['totals']['transfer'], 2) ?></b></span>
      <span>Expenses out <b><?= number_format($d['totals']['out'], 2) ?></b></span>
    </div>
    <div class="legend">
      <span><i class="swatch" style="background:var(--pos)"></i>Income (in)</span>
      <span><i class="swatch" style="background:var(--flow-transfer)"></i>Transfer (between accounts)</span>
      <span><i class="swatch" style="background:var(--neg)"></i>Expenses (out)</span>
      <span>Hover a flow for details</span>
    </div>

    <div class="card">
      <div class="scroller">
        <svg class="sankey" viewBox="0 0 1000 600" id="sankey" role="img"
             aria-label="Sankey diagram of money flow from income sources through accounts to expense categories"></svg>
      </div>
    </div>

    <h2 class="tbl-h">All flows</h2>
    <div class="scroller">
      <table id="flowtable">
        <thead><tr><th>From</th><th>To</th><th>Type</th><th class="num">Amount (TZS)</th></tr></thead>
        <tbody></tbody>
      </table>
    </div>

    <footer>Ribbon width is to scale (tiny flows have a minimum width for visibility). In a cashbook
      money is fungible — the account is the hub, not a 1:1 “donor X paid expense Y”. Non-donor income
      (e.g. bank interest) is grouped under “Other income”.</footer>

    <script type="application/json" id="sankey-data"><?= json_encode(['nodes'=>$d['nodes'],'links'=>$d['links']], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE) ?></script>
    <div id="tip" role="status" aria-live="polite"></div>
    <script src="<?= asset('/assets/js/sankey.js') ?>"></script>
  <?php endif; ?>
</div>
</body>
</html>

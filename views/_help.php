<?php
// Everyday task recipes for the in-app help popup. Static content — edit freely.
$recipes = [
  ['Record a donation', 'Income → <span class="path">New income</span> → pick the donor and (if it funds a project) the project → enter the amount → Save.'],
  ['Record an expense', 'Expenses → <span class="path">New expense</span> → choose the account, category and project → enter the amount → attach a receipt if you have one → Save.'],
  ['A donation in USD', 'On New income, set <span class="path">Currency = USD</span> and the exchange rate. LIPA stores the value in TZS so your books stay in one currency.'],
  ['Move money between accounts', 'Transfers → <span class="path">New transfer</span> → from which account, to which account, and the amount. (This isn\'t income or an expense — it just moves cash.)'],
  ['Get a report for one donor', 'Model the grant/donor as a <span class="path">Project</span>. Tag that donor\'s income <em>and</em> the expenses paid from it to that project. Then Reports → <span class="path">Project / donor statement</span> shows opening, received, spent and closing.'],
  ['Statement or Excel for the accountant', 'Reports → <span class="path">Income &amp; Expenditure statement</span> (opens a printable page → Print / Save as PDF), or <span class="path">Excel export</span> for a multi-sheet workbook.'],
  ['Log an activity with photos', 'Activities → <span class="path">New activity</span> → add up to 5 photos → tick the expenses it caused → Save. Then Reports → <span class="path">Activity report</span> prints it with photos and costs.'],
  ['Find things fast', 'Every list has filters (dates, project, category, account). Contacts has <span class="path">All / Donors / Vendors</span>, Categories has <span class="path">Income / Expense</span>.'],
  ['Switch light / dark', 'Use the ☀ / 🌙 button at the top right. Your choice is remembered on this device.'],
];
?>
<div class="modal" id="help-modal" hidden>
  <div class="modal-backdrop" data-help-close></div>
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="help-title">
    <div class="modal-head">
      <h2 id="help-title">How to use LIPA</h2>
      <button type="button" class="icon-btn" data-help-close aria-label="Close">✕</button>
    </div>
    <div class="modal-body">
      <p class="help-intro">Quick everyday tasks — follow the <span class="path">path</span> shown.</p>
      <?php foreach ($recipes as [$title, $body]): ?>
        <div class="help-recipe"><b><?= e($title) ?></b><span><?= $body ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

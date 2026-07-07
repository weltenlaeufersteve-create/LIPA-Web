# Money Flow (Sankey) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans. Steps use `- [ ]`.

**Goal:** Admin-only Sankey under Reports: income sources → accounts (+transfers) → expense
categories, for a period. Spec: `docs/superpowers/specs/2026-07-08-money-flow-sankey-design.md`.

**Tech:** Plain PHP 8.3, PDO/MariaDB, PHPUnit; inline SVG + vanilla JS engine. No new deps.

## Global Constraints
- Admin-only: view hides the card for non-admins **and** the route calls `requireRole('admin')`.
- Money is fungible → account is the hub (footer states it). UK English UI.
- Colours from app tokens; transfer blue `--flow-transfer` (light `#2a78d6` / dark `#3987e5`).
- Local runner: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/phpunit`.

---

### Task 1: `MoneyFlow::build()` aggregator (TDD)

**Files:** Create `src/Reports/MoneyFlow.php`; Test `tests/MoneyFlowTest.php`.

**Produces:** `MoneyFlow::build(string $from,string $to): array` with keys `from,to,nodes,links,totals`
(node ids `src:`/`acc:`/`exp:`; link `kind` ∈ in|transfer|out). See spec for aggregation rules.

- [ ] Write failing tests: (a) income source→account with donor name + "Other income" bucket;
  (b) transfer link present, and none when no transfers; (c) expense account→category with
  "(uncategorised)"; (d) nodes unique/columned, only-linked emitted; (e) date range respected.
- [ ] Run → fail (class missing).
- [ ] Implement with three `Database::pdo()` prepared aggregate queries + node/link assembly.
- [ ] Run → pass. Commit.

Reference query shapes (date-filtered, prepared `:from/:to`):
```sql
-- income
SELECT CASE WHEN c.type='donor' THEN c.name ELSE 'Other income' END AS source,
       COALESCE(a.name,'Unassigned') AS acct, SUM(i.amount_tzs) AS total
FROM income i LEFT JOIN contacts c ON c.id=i.contact_id LEFT JOIN accounts a ON a.id=i.account_id
WHERE i.date BETWEEN :from AND :to GROUP BY source, acct HAVING total>0;
-- transfers
SELECT COALESCE(af.name,'Unassigned') AS f, COALESCE(at2.name,'Unassigned') AS t, SUM(tr.amount_tzs) AS total
FROM transfers tr LEFT JOIN accounts af ON af.id=tr.from_account_id LEFT JOIN accounts at2 ON at2.id=tr.to_account_id
WHERE tr.date BETWEEN :from AND :to GROUP BY f, t HAVING total>0;
-- expenses
SELECT COALESCE(a.name,'Unassigned') AS acct, COALESCE(cat.name,'(uncategorised)') AS category, SUM(e.amount_tzs) AS total
FROM expenses e LEFT JOIN accounts a ON a.id=e.account_id LEFT JOIN categories cat ON cat.id=e.category_id
WHERE e.date BETWEEN :from AND :to GROUP BY acct, category HAVING total>0;
```

### Task 2: Controller + route (admin-gated)

**Files:** Modify `src/Controllers/ReportController.php`, `public/index.php`.

- [ ] Add `ReportController::sankey(): string` — `Auth::requireRole('admin')`; validate `date_from`
  /`date_to` (default current year like `index()`); `$d = MoneyFlow::build(...)`;
  `$s = Setting::all()`; `ob_start(); include views/reports/sankey.php; return ob_get_clean();`.
- [ ] Add route `GET /reports/sankey` → `sankey()` in `public/index.php` (near other report routes).
- [ ] `php -l` both. Commit.

### Task 3: Sankey view + JS engine

**Files:** Create `views/reports/sankey.php`, `public/assets/js/sankey.js`.

- [ ] `views/reports/sankey.php`: full HTML page — pre-paint theme script (saved `lipa_theme`),
  `theme.css`, inline `<style>` for Sankey (defines `--flow-transfer` light/dark), header
  (org name, "Money flow", period, Print + Back), a `.card` hosting `<svg id="sankey">`, an
  empty-state message when `links` is empty, a flow `<table>` placeholder, the payload in
  `<script type="application/json" id="sankey-data"><?= json_encode(['nodes'=>$d['nodes'],'links'=>$d['links']]) ?></script>`,
  then `<script src="<?= asset('/assets/js/sankey.js') ?>"></script>`.
- [ ] `public/assets/js/sankey.js`: IIFE — read JSON, compute node values + one width scale,
  centred column stacks, ribbons (in=`--pos`, out=`--neg`, transfer=`--flow-transfer`, transfer
  bows right between account nodes), hover highlight+tooltip, build the flow table, respect
  `prefers-reduced-motion`. (Generalises the approved prototype.)
- [ ] Manual render check via local server. Commit.

### Task 4: Reports index card (admin-only)

**Files:** Modify `views/reports/index.php`.

- [ ] Add a `report-card` wrapped in `<?php if (\App\Auth::is('admin')): ?>…<?php endif; ?>`
  with a form (`GET /reports/sankey`, `target="_blank"`, date_from/date_to) and a distinct icon.
- [ ] Manual: card shows for admin, hidden for viewer. Commit.

## Final verification
- [ ] `php vendor/bin/phpunit` all green (85 + new).
- [ ] Local: open `/reports/sankey` as admin — green/blue/red flows, hover, table, dark mode,
  print; card hidden for non-admin. Then user confirms → deploy (push + server `git pull`).

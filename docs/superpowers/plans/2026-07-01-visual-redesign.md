# LIPA Web — Visual Redesign Implementation Plan

> **For agentic workers:** Implement task-by-task. Steps use checkbox (`- [ ]`). This is a **restyle**, not a rewrite: keep the server-rendered PHP architecture. Source of truth for exact CSS values is `lipa-dashboard-mockup.html` (the handoff artifact) — lift its `<style>` selectors rather than reinventing.

**Goal:** Apply the `lipa-dashboard-mockup.html` design to the real LIPA app — token-driven light/dark, a single per-NGO `--accent`, ledger tables, cards, badges, icon actions, report cards, self-hosted fonts — screen by screen, keeping controllers/models/routing/DB unchanged except two additive changes.

**Architecture:** Design lives in `theme.css` (tokens + `@font-face`) and `app.css` (components). Views keep their per-page server-rendered structure; only markup **classes/wrappers** change to match the mockup. One inline `:root{--accent}` override in `layout.php` reads the new `accent_color` setting.

**Tech Stack:** PHP 8.3, server-rendered views, vanilla CSS/JS, PDO. No build step, no framework.

## Global Constraints (verbatim from handoff)
- **Restyle, not rewrite.** No SPA/client router; each page stays its own `views/*.php`. Do not copy the mockup's hardcoded data or its `data-view` switching JS.
- **Never hardcode "Pepea"** or a raw accent hex — `org_name`, logo, and `accent_color` come from Settings.
- **Do not touch** controllers, models, routing, `Auth`, `Csrf`, DB schema, printable report output (`reports/statement.php`, `org_statement.php`, `activity_report.php`), Excel export, receipt/photo storage — **except** the two additive changes: the `accent_color` setting (Task 3) and the role **display** labels (Task 4).
- **Every accent tint derives from `--accent` via `color-mix()`.** Component CSS references tokens, never a raw accent hex.
- **Self-host fonts** (no Google Fonts `<link>`), for DSGVO.
- Keep the existing theme toggle mechanism (client JS, `localStorage` key `lipa_theme`, `data-theme` on `<html>`, pre-paint script in `_shell.php`).
- **Out of scope:** full multi-currency (money stays TZS-formatted), multi-tenant, any change to reports' printable output/Excel/auth/routing.
- **Working method:** branch; restyle **one screen at a time**; user verifies locally (`php -S 127.0.0.1:8000 -t public`, both light+dark) before moving on; `composer test` green before merge to `master`.

Toolchain (local shell): prefix with
`export PATH="$PATH:/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64:/c/laragon/bin/composer:/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin"`.

---

## File structure

```
public/assets/fonts/            NEW — self-hosted woff2 (Bricolage Grotesque, Inter)
public/assets/css/theme.css     REWRITE token blocks (mockup tokens) + @font-face; keep transitions/scrollbars/PDF-fallback tokens
public/assets/css/app.css       REWRITE component library from the mockup (sidebar, topbar/user-chip, cards, kpi, flow, ledger, badges, rowact, forms, report cards, search, subtabs, swatches, responsive)
public/assets/js/app.js         ADD: expense-picker search filter; keep existing theme toggle + nav drawer
views/layout.php                ADD: inline :root{--accent} from Setting::get('accent_color'); ADD csrf_field already present; ADD role_label() helper include
views/_shell.php                RESTYLE chrome to mockup (brand, nav-group + icons, user chip + role pill, theme toggle icon, hamburger+scrim)
views/dashboard.php             RESTYLE (KPIs, flow bar, ledger tables)
views/income/*, expenses/*      RESTYLE list (ledger/badges/rowact) + form (form-card); views/_filters.php → .filterbar
views/contacts/*, projects/*, transfers/*, accounts/*, categories/*, users/*   RESTYLE ledger + form-card
views/reports/index.php         RESTYLE → report-grid cards (printable pages themselves untouched)
views/settings/index.php + views/admin/_tabs.php   RESTYLE → subtabs; Organisation form gains Accent color + Base currency (display); role badges
views/activities/form.php       RESTYLE form-card + photo grid + expense picker search box
src/Helpers.php (or layout.php)  NEW role_label(); hex guard
bin/migrate.php                 ADD idempotent seed of settings row accent_color = #C0175B
tests/                          role_label + accent hex guard + migration presence (PHPUnit)
```

---

### Task 1: Self-host fonts

**Files:** Create `public/assets/fonts/*.woff2`; modify `public/assets/css/theme.css` (add `@font-face`).

- [ ] **Step 1: Download the woff2 files** (Bricolage Grotesque 600/700/800, Inter 400/500/600/700). Use the Google CSS API to resolve current gstatic woff2 URLs, then fetch each:
```bash
cd "/c/Tools/LIPA Web 26"; mkdir -p public/assets/fonts
UA='Mozilla/5.0'
curl -s -A "$UA" "https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" -o /tmp/gf.css
grep -oE "https://fonts.gstatic.com/[^)]+\.woff2" /tmp/gf.css | sort -u
# download each resolved URL into public/assets/fonts/ with a stable name (see next step)
```
- [ ] **Step 2: Save them** as `bricolage-600.woff2 … -700 … -800`, `inter-400 … -500 … -600 … -700` in `public/assets/fonts/`. (Latin subset is enough; grab the `latin` range files.)
- [ ] **Step 3: Add `@font-face` at the top of `theme.css`** (one block per weight), e.g.:
```css
@font-face{font-family:"Inter";font-style:normal;font-weight:400;font-display:swap;src:url("/assets/fonts/inter-400.woff2") format("woff2");}
/* …500,600,700… */
@font-face{font-family:"Bricolage Grotesque";font-style:normal;font-weight:600;font-display:swap;src:url("/assets/fonts/bricolage-600.woff2") format("woff2");}
/* …700,800… */
```
- [ ] **Step 4: Verify** `curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8000/assets/fonts/inter-400.woff2` → `200`; page network shows local fonts, no `fonts.googleapis.com`.
- [ ] **Step 5: Commit** `git add public/assets/fonts public/assets/css/theme.css && git commit -m "feat(ui): self-host Bricolage Grotesque + Inter (DSGVO)"`

---

### Task 2: Design tokens (theme.css)

**Files:** Modify `public/assets/css/theme.css`.

**Interfaces produced:** tokens `--accent, --accent-ink, --accent-soft, --accent-line, --accent-quiet, --pos, --neg, --bg, --surface, --surface-2, --ink, --muted, --faint, --line, --line-soft, --shadow, --radius, --radius-sm, --sidebar-w, --font-display, --font-body` in both themes.

- [ ] **Step 1: Replace the two theme blocks** with the mockup's `:root` (light) and `[data-theme="dark"]` token sets (copy the exact values from the mockup `<style>` — see handoff §1). Light on `:root` (works with the existing default `<html data-theme="light">`), dark on `[data-theme="dark"]`.
- [ ] **Step 2: Preserve** the theme-independent block (`--radius*`), the `* { transition … }` block, and the scrollbar rules — but repoint scrollbar colors to `--line-soft`/`--faint`. **Keep the PDF-fallback tokens** `--color-dark`/`--color-mid` (used by print branding) so printable reports are unaffected.
- [ ] **Step 3: Set base typography** in `app.css` body (Task 5) to `--font-body`; headings use `--font-display`.
- [ ] **Step 4: Verify** on the running server: `data-theme` toggle still flips light↔dark; the pre-paint script + `lipa_theme` key still work (unchanged).
- [ ] **Step 5: Commit** `git commit -am "feat(ui): adopt mockup design tokens (light/dark, accent color-mix system)"`

> Note: this task will visually "break" component styling until Task 5 lands (old class rules reference old token names). That's expected; do Task 5 next before asking for screen sign-off.

---

### Task 3: `accent_color` setting (migration + hex guard + inline apply)

**Files:** `bin/migrate.php`, `views/layout.php`, `src/Helpers.php` (new, for the hex guard), `tests/HelpersTest.php`.

**Interfaces produced:**
- `App\hex_color(?string $v, string $fallback = '#C0175B'): string` — returns `$v` if it matches `/^#[0-9a-fA-F]{6}$/`, else `$fallback`.
- `settings.accent_color` row (default `#C0175B`).

- [ ] **Step 1: Write failing test** `tests/HelpersTest.php`:
```php
<?php
namespace Tests;
use PHPUnit\Framework\TestCase;
use function App\hex_color;
final class HelpersTest extends TestCase {
    public function test_hex_color_accepts_valid_and_rejects_invalid(): void {
        $this->assertSame('#C0175B', hex_color('#C0175B'));
        $this->assertSame('#0e7c7b', hex_color('#0e7c7b'));
        $this->assertSame('#C0175B', hex_color('red'));           // fallback
        $this->assertSame('#C0175B', hex_color('#FFF'));          // 3-digit rejected
        $this->assertSame('#C0175B', hex_color('#12345g'));       // non-hex rejected
        $this->assertSame('#000000', hex_color(null, '#000000')); // custom fallback
        $this->assertSame('#C0175B', hex_color('#c0175b"><script>')); // injection rejected
    }
}
```
- [ ] **Step 2: Run → fail:** `vendor/bin/phpunit tests/HelpersTest.php` (function not found).
- [ ] **Step 3: Create `src/Helpers.php`:**
```php
<?php
namespace App;
if (!function_exists('App\\hex_color')) {
    function hex_color(?string $v, string $fallback = '#C0175B'): string {
        return (is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v)) ? $v : $fallback;
    }
}
```
Add to composer autoload `files` so it's always loaded:
```json
"autoload": { "psr-4": { "App\\": "src/" }, "files": ["src/Helpers.php"] },
```
then `composer dump-autoload`.
- [ ] **Step 4: Run → pass.**
- [ ] **Step 5: Migration** — append to `bin/migrate.php` before the final echo (idempotent; `settings` is key-value):
```php
$stmt = $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES ('accent_color', :v)
    ON DUPLICATE KEY UPDATE `key`=`key`");
$stmt->execute([':v' => '#C0175B']);
echo "accent_color setting ok\n";
```
(Confirm the `settings` column names/PK match the existing table — adjust `key`/`value` to the real column names and unique key. If `settings` has no unique key on the name column, use a `SELECT`-then-`INSERT` guard instead.)
- [ ] **Step 6: Apply inline** in `views/layout.php` `render()` output head — inject after the stylesheets are linked (the `_shell.php` `<head>`), simplest via `_shell.php`: add inside `<head>`:
```php
<style>:root{--accent: <?= e(\App\hex_color(\App\Models\Setting::all()['accent_color'] ?? null)) ?>;}</style>
```
(Place it **after** the `theme.css` link so it overrides the token default. Uses `hex_color()` to guard against bad input; `$org`/`Setting::all()` is already loaded in `_shell.php`.)
- [ ] **Step 7: Run migration locally** `php bin/migrate.php` and verify the row exists; `composer test` green.
- [ ] **Step 8: Commit** `git add -A && git commit -m "feat(ui): per-NGO accent_color setting (migration, hex guard, inline apply)"`

---

### Task 4: Role display labels (Admin / Coordinator / Accountant)

**Files:** `src/Helpers.php` (add `role_label`), `views/_shell.php`, `views/users/*` (+ `views/activity/*` if it shows roles), `tests/HelpersTest.php`.

**Interfaces produced:** `App\role_label(string $role): string` — `admin→Admin`, `editor→Coordinator`, `viewer→Accountant`, unknown→`ucfirst($role)`.

- [ ] **Step 1: Add failing test** to `tests/HelpersTest.php`:
```php
    public function test_role_label_maps_enum_to_display(): void {
        $this->assertSame('Admin', \App\role_label('admin'));
        $this->assertSame('Coordinator', \App\role_label('editor'));
        $this->assertSame('Accountant', \App\role_label('viewer'));
        $this->assertSame('Something', \App\role_label('something'));
    }
```
- [ ] **Step 2: Run → fail.**
- [ ] **Step 3: Add to `src/Helpers.php`:**
```php
if (!function_exists('App\\role_label')) {
    function role_label(string $role): string {
        return ['admin'=>'Admin','editor'=>'Coordinator','viewer'=>'Accountant'][$role] ?? ucfirst($role);
    }
}
```
- [ ] **Step 4: Run → pass.**
- [ ] **Step 5: Use it (display only)** — in `_shell.php` user chip and the Users list role cell, replace raw `$user['role']` / `$row['role']` output with `\App\role_label(...)`. **Do not** change the `users.role` enum, DB, or any `Auth::is/requireRole` call.
- [ ] **Step 6: Verify** Users list + user chip read Admin/Coordinator/Accountant; login and guards still work (`viewer` still blocked from writes).
- [ ] **Step 7: Commit** `git commit -am "feat(ui): display role labels Admin/Coordinator/Accountant (labels only, guards unchanged)"`

---

### Task 5: Component library (app.css) + base typography

**Files:** Rewrite `public/assets/css/app.css`.

- [ ] **Step 1: Port the mockup's component `<style>` into `app.css`**, translating class names where the app already has equivalents. Bring over, verbatim in values (from the mockup): `body`/`.num`, `.app`→ keep `.app-shell` grid, `.sidebar`, `.brand*`, `.nav`, `.nav-group`, `.nav-item`, `.sidebar-foot`/`.powered`, `.topbar`, `.page-title`, `.user-chip`/`.avatar`/`.role`, `.icon-btn`, `.content`, `.field`, `.btn`(+`.ghost`), `.filterbar`, `.kpis`/`.kpi`(+`.hero`), `.flow*`, `.section-title`, `.row-between`, `.total-chip`, `.card`, `.table-card`, `.ledger`(+`thead th`, `.r`, hover, `.name`, `.money`, `.muted-cell`), `.acct-ico`, `.tag`, `.pill`, `.rowact`(+`.edit`/`.del`), `.cat-bar*`, `.report-grid`/`.report-card`/`.rc-*`, `.swatches`/`.sw`, `.subtabs`/`.subtab`/`.settings-panel`, `.form-card`/`.form-field`/`.form-grid`/`.form-hint`/`.accent-picker`/`.accent-hex`/`.upload*`/`.logo-preview`, `.badge`/`.role-badge`(+`.income`/`.expense`/`.on`/`.off`/`.role-admin`/`.role-coord`/`.role-acct`), `.count`, `.search`/`.check`/`.no-results`, `.form-actions`, `.fieldset-label`/`.fieldset-hint`.
- [ ] **Step 2: Keep app-specific rules** that have no mockup equivalent, retokenised to the new names: `.photo-grid`/`.photo-thumb`/`.picker-scroll` (activities), `.alert-error` (→ use `--neg`), `#toast-container`/`.toast` (→ `--accent`/`--neg`), `.ngo-logo`, login classes.
- [ ] **Step 3: Responsive** — copy the mockup's media queries **and keep the scrim `position:fixed; display:none` by default** (as fixed in the mockup). Map the existing `.app-shell.nav-open .sidebar`/`.scrim` drawer to the mockup's `.sidebar.open` + `.scrim.show` (or keep the app's `nav-open` toggling — see Task 6; be consistent with app.js).
- [ ] **Step 4: Base font** — `body{font-family:var(--font-body)}`, headings/`.page-title`/`.section-title`/`.kpi-value` use `var(--font-display)`.
- [ ] **Step 5: Grep for stale tokens** `grep -rn "var(--bg-primary\|--text-primary\|--accent-subtle\|--bg-secondary\|--bg-sidebar\|--border\b" public views` and fix any remaining references to the old token names (in app.css and inline view styles) to the new tokens.
- [ ] **Step 6: Verify** the shell renders on `/` in both themes with the new look (sidebar, cards). Screen sign-off comes per-screen in later tasks, but the chrome should look right after Task 6.
- [ ] **Step 7: Commit** `git commit -am "feat(ui): component library from mockup (cards, ledger, badges, forms, reports)"`

---

### Task 6: App shell (`_shell.php`)

**Files:** `views/_shell.php`, possibly `public/assets/js/app.js` (drawer class names).

- [ ] **Step 1: Rebuild the sidebar** to the mockup structure: `.brand` (logo/`org_name`/`LIPA` fallback + TIN/No. meta), `.nav` with `.nav-group`s and inline SVG icons per item (lift the mockup's `<svg>`s), a bottom `.nav-group` (Settings, Activity log), `.sidebar-foot .powered`. Keep role-aware `<?php if (Auth::is(...)) ?>` around Projects/Settings/Activity log exactly as now. Keep hrefs (`/`, `/income`, `/expenses`, `/transfers`, `/contacts`, `/projects`, `/activities`, `/reports`, `/settings`, `/activity`).
- [ ] **Step 2: Topbar** → `.page-title` (pass `$title` through) + `.topbar-right` with the **user chip** (`avatar` initial, name, `role` pill via `role_label`) and the **theme toggle** `.icon-btn` (keep `id="theme-toggle"`). Add the hamburger `.icon-btn.hamburger` on mobile. Remove the old `.account-bar` (its logout moves into a menu or stays as a small ghost button in the chip area — keep a `POST /logout` form; CSRF auto-injected).
- [ ] **Step 3: Active nav** — mark the current item `.active` by comparing the request path to each href (server-side, since there's no SPA). A small helper: `nav_active(string $href): string` returning `' active'` when `$_SERVER['REQUEST_URI']` path matches.
- [ ] **Step 4: Keep** the pre-paint theme script, `data-theme="light"` default, favicon, stylesheet links, and the `<style>` accent injection from Task 3.
- [ ] **Step 5: Verify** on several pages, both themes, desktop + mobile drawer (hamburger opens sidebar, scrim closes it), logout works, active item highlights.
- [ ] **Step 6: Commit** `git commit -am "feat(ui): restyle app shell (sidebar, nav icons, user chip, topbar)"`  → **user verifies before continuing.**

---

### Task 7: Dashboard (`views/dashboard.php`)

- [ ] **Step 1:** Wrap the period filter in `.filterbar`. Render the three KPIs as `.kpis` → `.card.kpi` (Income green dot, Expenses rust dot, Balance = `.kpi.hero`), money in `.kpi-value.num` with `<span class="cur">TZS</span>` + `<span class="dec">`.
- [ ] **Step 2:** Add the **flow bar** (`.flow`): `spent% = expenses/income` (guard divide-by-zero → 0%); render track + legend from the period totals already in scope.
- [ ] **Step 3:** Convert the existing tables (Balances by account, By project, Expenses by category) to `.card.table-card > table.ledger` with right-aligned `.money`, the `.pill` for project balance, and `.cat-bar` share bars for categories (compute share % from the max/total already computed).
- [ ] **Step 4: Verify** numbers match pre-restyle values exactly; both themes; the By-project/Accounts tables handled for mobile in Task 13.
- [ ] **Step 5: Commit** `git commit -am "feat(ui): restyle dashboard (KPIs, flow bar, ledgers)"` → **user verifies.**

---

### Task 8: Income & Expenses (lists + filters + forms)

**Files:** `views/income/index.php`, `views/expenses/index.php`, `views/_filters.php`, `views/income/form.php`, `views/expenses/form.php`.

- [ ] **Step 1:** `_filters.php` → `.filterbar` with `.field`s and `.actions` (Filter / Clear ghost).
- [ ] **Step 2:** List → `.row-between` (total chip + New button) then `.card.table-card > table.ledger`; donor/vendor in `.name`, category as `.tag`, amount right-aligned coloured with `--pos`/`--neg`, actions as `.rowact` icon buttons (Edit pencil / Delete trash) wrapping the existing links/forms (keep the `POST …/delete` forms + `data-confirm`; CSRF stays auto-injected).
- [ ] **Step 3:** Forms → `.form-card` with `.form-field`/`.form-grid`; keep every existing `name=`, `required`, receipt upload field, and the account/project/category selects unchanged (only wrappers/classes change). Save = `.btn`, Cancel = `.btn.ghost`.
- [ ] **Step 4: Verify** create/edit/delete still work for both, filters work, both themes.
- [ ] **Step 5: Commit** `git commit -am "feat(ui): restyle income & expenses (ledger lists + form cards)"` → **user verifies.**

---

### Task 9: Contacts, Projects, Transfers, Accounts, Categories, Users

**Files:** the `index.php` + `form.php` under `views/contacts`, `projects`, `transfers`, `accounts`, `categories`, `users`.

- [ ] **Step 1:** Each list → `.card.table-card > table.ledger`; status → `.badge.on/.off`; type → `.tag` or `.badge.income/.expense` (categories); role → `.role-badge` via `role_label` (users); actions → `.rowact` icons. Header row → `.row-between` (count/total + New button).
- [ ] **Step 2:** Each form → `.form-card` + `.form-field`/`.form-grid`, preserving all field names/validation.
- [ ] **Step 3: Verify** CRUD on each; both themes.
- [ ] **Step 4: Commit** `git commit -am "feat(ui): restyle contacts/projects/transfers/accounts/categories/users"` → **user verifies.**

---

### Task 10: Reports (`views/reports/index.php`)

- [ ] **Step 1:** Replace the current stacked forms with the `.report-grid` (2×2) of `.report-card`s: Income & Expenditure statement, Excel export, Project/donor statement, Activity report — each with `.rc-ico` (lift mockup SVGs), `.rc-title`, `.rc-desc`, `.rc-fields` (the existing GET forms/inputs), `.rc-foot` (hint + `.btn`). **Keep the existing form `action`s, GET method, `target="_blank"`, and field names** — printable pages themselves are untouched.
- [ ] **Step 2: Verify** all four open correctly; both themes.
- [ ] **Step 3: Commit** `git commit -am "feat(ui): restyle reports as report cards"` → **user verifies.**

---

### Task 11: Settings (subtabs + Organisation form)

**Files:** `views/settings/index.php`, `views/admin/_tabs.php` (+ the panels for Accounts/Categories/Users if rendered within Settings).

- [ ] **Step 1:** Tabs → `.subtabs`/`.subtab` (Organisation · Accounts · Categories · Users), panels `.settings-panel`. If the app currently links out to `/accounts`,`/categories`,`/users` pages rather than in-tab panels, keep that navigation but style the tab bar consistently; **do not** re-architect routing.
- [ ] **Step 2: Organisation form** (`.form-card`): keep org name/address/email/tax id/ngo no./logo fields. Add **Base currency** `<select>` (display options TZS/KES/UGX/USD/EUR — the field already exists as `base_currency`; only widen options, no storage change) and **Accent color**: the `.accent-picker` with `.accent-hex` readout + preset `.sw` swatches (`#C0175B #0E7C7B #2456B0 #C77A0A #2E7D4F`) writing a hidden/`<input name="accent_color">`; a native `<input type="color">` is an acceptable fallback. Saves through the existing `Setting` model POST (CSRF auto-injected). Server stores via `hex_color()` guard on read (Task 3) — also guard on save in `SettingController` **only if** it already whitelists keys (add `accent_color`, `base_currency` to the allowed keys list; that is a settings-key addition, permitted).
- [ ] **Step 3: Verify** saving accent + picking a swatch re-themes the whole app on reload; base currency saves; both themes.
- [ ] **Step 4: Commit** `git commit -am "feat(ui): restyle settings (subtabs + accent picker + base currency)"` → **user verifies.**

---

### Task 12: Activity form (`views/activities/form.php`) + expense search

**Files:** `views/activities/form.php`, `public/assets/js/app.js`.

- [ ] **Step 1:** Form → `.form-card` (max 820px); Date/Title/Description/Project as `.form-field`s; Photos section `.fieldset-label` + `.upload` dashed button (keep `name="photos[]"`, multiple, accept). Keep `.photo-grid` thumbnails.
- [ ] **Step 2:** Linked expenses → `.fieldset-label` + `.fieldset-hint` + a `.search` box (`#expSearch`) above the `.card.table-card > table.ledger` picker. Give each expense `<tr>` a `data-text` of lowercased `date + category + description` (server-rendered from the real `expenses` data) and each checkbox `.check`. Keep `name="expense_ids[]"` and pre-checked state.
- [ ] **Step 3:** In `app.js`, add the filter handler (lift the mockup's `#expSearch` logic): on input, show/hide rows by `data-text.includes(q)`, toggle a `.no-results` element, and toggle `tr.checked` on checkbox change. Pure client-side on rendered rows; no endpoint.
- [ ] **Step 4: Verify** typing filters the picker (by description + category + date), "no matches" shows, ticking still links on save; both themes.
- [ ] **Step 5: Commit** `git commit -am "feat(ui): restyle activity form + type-to-filter expense picker"` → **user verifies.**

---

### Task 13: Responsive money tables

**Files:** `public/assets/css/app.css`, and the wide tables' markup (dashboard By-project, accounts list).

- [ ] **Step 1:** Wrap wide multi-numeric tables in a `.table-scroll{overflow-x:auto}` wrapper **or** add a `@media (max-width:640px)` stacked/card layout so nothing clips. Single-value tables (Balances by account) need no change.
- [ ] **Step 2: Verify** at 375px width nothing is clipped; horizontal scroll or stacked layout works; both themes.
- [ ] **Step 3: Commit** `git commit -am "feat(ui): responsive money tables (no clipping on mobile)"` → **user verifies.**

---

### Task 14: Final pass

- [ ] **Step 1:** `composer test` → all green.
- [ ] **Step 2:** Grep confirms **no** `fonts.googleapis.com`/`gstatic` link, **no** stale `--bg-primary`/`--text-primary`/`--accent-subtle` refs, **no** hardcoded "Pepea" or raw accent hex in components.
- [ ] **Step 3:** Full click-through in **both** themes; change accent in Settings → whole app re-themes.
- [ ] **Step 4:** Update local `CLAUDE.md` (design tokens, accent_color setting, role labels) — gitignored, local only.
- [ ] **Step 5:** Merge to `master` after user's final confirmation; deploy is a separate, user-approved step (`git pull` + `composer install --no-dev` + `php bin/migrate.php` for the accent_color row).

---

## Self-Review
- **Handoff §1 tokens** → Task 2 (+ fonts Task 1). **§2 components** → Task 5 (+ per-screen 6–12). **§3 accent_color** → Task 3 (migration, hex guard, inline apply) + Task 11 (picker UI). **§4 role labels** → Task 4. **§5 activity search** → Task 12. **§6 self-host fonts** → Task 1. **§7 responsive tables** → Task 13. **Out-of-scope** honoured: no multi-currency storage, no routing/controller/print changes.
- **Placeholder scan:** the two `(Confirm …)` notes (settings column names in Task 3 migration; in-tab vs linked settings panels in Task 11) are genuine "inspect the real file at execution" checks, resolved by reading the file first — not deferred work.
- **Type consistency:** `hex_color()`/`role_label()` signatures match their call sites; token names match between Task 2 (theme.css) and Task 5 (app.css). Exact CSS values come from the mockup verbatim.
- **Risk:** Task 2 alone leaves a half-styled app; always pair Task 2 → Task 5 → Task 6 before the first screen sign-off.

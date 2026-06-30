# LIPA Web — Activities (activity reporting) — Design Spec

**Date:** 2026-06-30
**Status:** Approved design — pending implementation plan
**Branch:** `feature-activities`

## Purpose
NGOs must write **activity reports** (what was done, with photos), often alongside the
financials. This adds an **Activities** module: a coordinator records an activity (date,
title, description, photos), links the **expenses** it incurred, and can print an **Activity
report** for a period. Nothing gets lost between "what happened" and "what it cost."
TZS context; English UI.

## Scope decisions (confirmed)
- **Photos appear in the printed report** (Option A), **max 5 per activity**, **resized on
  upload** (GD) to keep storage sane.
- **Linking direction: Activity → Expense.** The **Activity** form has the expense selector;
  there is **no** activity field on the expense form. Relationship is **one activity → many
  expenses** (`expenses.activity_id`).
- **Nav:** "Activities" directly under **Projects**. View: all roles; create/edit/delete:
  **editor + admin** (the coordinators). viewer = read-only.
- **Out of scope (v1):** videos, photo reordering, photo captions, per-activity budgets, an
  expense-side activity dropdown.

## Data model
Idempotent migration in `bin/migrate.php` (CREATE TABLE IF NOT EXISTS + guarded ALTER) and
`db/schema.sql` updated for fresh installs.

### `activities` (new)
| column | type | notes |
|---|---|---|
| id | INT PK | |
| date | DATE NOT NULL | |
| title | VARCHAR(190) NOT NULL | |
| description | TEXT NULL | |
| project_id | INT NULL FK→projects ON DELETE SET NULL | optional |
| created_by | INT NULL FK→users ON DELETE SET NULL | |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |

### `activity_photos` (new)
| column | type | notes |
|---|---|---|
| id | INT PK | |
| activity_id | INT NOT NULL FK→activities **ON DELETE CASCADE** | |
| filename | VARCHAR(255) NOT NULL | stored basename in `storage/activity_photos/` |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |

### `expenses` — add column
- `activity_id INT NULL`, FK→activities **ON DELETE SET NULL**. (One activity, many expenses.)

## Photos
- **`App\ImageStorage`** (new) — `validate(array $file): ?string` (JPG/PNG, ≤10 MB upload),
  and `store(array $file, string $prefix): string` which **resizes via GD** to a max long
  edge of **1600 px** (keep aspect; skip upscaling), re-encodes **JPEG ~80 %**, writes to
  `storage/activity_photos/` (outside web root, like receipts), returns the basename.
- Up to **5** photos per activity (enforced in the controller; extra uploads ignored with a
  notice). Photos deletable individually.
- Served via authed route **`GET /activities/:id/photo/:photoId`** (role check, streams the
  file with the right image content-type). Never web-served directly.
- The printed report embeds `<img src="/activities/:id/photo/:photoId">` (loads under the
  logged-in session) at a modest display size.

## Linking expenses (Activity → Expense)
On the **Activity** form, an **expense selector** (multi-select) lists expenses that are
**unassigned** (no `activity_id`) **plus** those already linked to this activity, newest
first. Saving sets `expenses.activity_id = this activity` for the checked ones and clears it
for unchecked ones (so an expense never belongs to two activities). The activity view/report
shows its expenses + a **total cost** (Σ `amount_tzs`).

## Components
- **`App\Models\Activity`** — `create/all(filters)/find/update/delete`; `photos(int $id)`,
  `addPhoto(int $id, string $filename)`, `deletePhoto(int $photoId)`, `findPhoto(int $photoId)`,
  `photoCount(int $id)`; `expenses(int $id)` (linked expenses + names), `cost(int $id)`
  (Σ amount_tzs of linked expenses); `setExpenses(int $id, array $expenseIds)` (assign/clear).
  `all()` joins `project_name`; filters `date_from`/`date_to`/`project_id`.
- **`App\ImageStorage`** — resize + store (above).
- **`App\Models\Expense`** — `availableForActivity(?int $activityId)` returning unassigned
  expenses + those linked to `$activityId` (for the picker); `all()` may also expose
  `activity_id` (no new expense-form field).
- **`App\Controllers\ActivityController`** — `index|create|store|edit|update|delete`
  (editor/admin to write; view all), plus `photo(id, photoId)` (serve), `deletePhoto`,
  and expense linking inside store/update. Photo uploads handled on store/update.
- **`App\Reports\ActivityReport`** — `build(from, to, ?projectId): array` → activities in the
  period, each with photos, linked expenses, cost; plus a grand total.
- **`ReportController::activityReport()`** + standalone `views/reports/activity_report.php`
  (print styles + page-break rules like the other statements).
- Views: `views/activities/index.php`, `views/activities/form.php`.
- Nav (`_shell.php`): add **Activities** after Projects. Routes in `public/index.php`.
- Reports index: add an **Activity report** form (period + optional project).

## Activity report (print, like the others)
Standalone printable page: org header + period, then for each activity:
**date · title · description · photos (embedded, modest size) · linked expenses (list + total
cost)**. A **grand total** of activity costs at the end. Reuses the statements' print CSS
(repeat headers, no row split, `@page` margins). Access: all roles.

## Testing
- `ActivityTest` — CRUD; `setExpenses` assigns/clears `expenses.activity_id` (and never leaves
  an expense on two activities); `cost` sums linked expenses; `photos`/`addPhoto`/`photoCount`.
- `ImageStorageTest` — `validate` (accept JPG/PNG, reject others/oversize); `store` resizes a
  generated large test image down to ≤1600 px and produces a smaller JPEG (run against a
  temp file via GD).
- `ActivityReportTest` — activities in period with linked expenses → correct per-activity cost
  + grand total.
- Controllers verified e2e on the dev server (role guards: editor/admin write, viewer 403 on
  write; photo upload + serve; expense linking; report renders with photos).

## Out of scope (v1)
Videos, photo reordering/captions, expense-side activity field, per-activity budgets,
non-image attachments.

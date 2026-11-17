# Scratch Production Test Data — Where We Left Off (2026-11-13)

Paste this into any new chat to continue the scratch-data work.

## Goal
Build repeatable test data for the Production module (Work Center → Routing → BOM) in `scratch_production/`.
Positive scripts create correct data the way a real user would; negative scripts (later) send wrong data and
check the app rejects it with the right message. The data for each file is discussed and agreed with the user
BEFORE the file is written.

## Standing rules (from the user — follow them)
- Discuss first, record decisions, implement only when asked. Mark tasks "🛠️ Applied (code working)"; only the user marks Verified.
- Do NOT change payroll / HRMS / other modules' logic. Additive helpers are OK.
- The user edits the working tree in parallel: never revert diffs you didn't make; never stash or checkout.
- Every money value in the DB is BASE currency (USD). The UI works in INR (rate 85): forms take ₹ and controllers convert with `convert_to_base`. Services do not convert. From the CLI there is no session, so the rate is 1.
- Display rule: 4 decimals only when there are decimals; whole numbers show none; under 1 second shows "< 1s".
- `scratch_production/` files are mandatory project assets — never delete them unless the user says so.
- Nothing is committed; the user reviews everything at the end.
- "Leave ECO for later."

## Environment
- Laravel 12 multi-tenant ERP, MySQL (XAMPP). Tenant 1, slug `warrgyizmorsch`, settings currency INR.
- App served at `http://localhost:8000` (the user's `php artisan serve`). Also reachable at `http://localhost/wm-product-saas/public`.
- Test user for scripts: `owner@warrg.com` (tenant_owner). Password lives in `scratch_production/browser/login.local.json` (git-ignored, value from the HRMS demo seeder).

## Files in scratch_production/ and their state

| File | How it writes | State |
|---|---|---|
| `00_reset_production.php` | Direct DB delete of tenant-1 Production tables | Done. Keeps products, employees, shifts. Does NOT remove `test-*` products/employees. |
| `_http.php` | Shared PHP helper: real web requests through the HTTP kernel (login, X-Tenant, session INR, CSRF, values sent as strings like a browser). Returns ok / message / field errors / json. | Done |
| `01_seed_shifts_and_paygrades.php` | Web: `production.shifts.store/update`, `production.pay-grades.store/update` | Done, runs clean. 7 shifts (SH-00-06, SH-04-09, SH-11-18, SH-10-20, SH-19-04, SH-07-19, SH-16-20) + existing DAY/MRN/EVE. 6 pay grades test-pg-1..6 typed ₹80/120/175/225/300/380 → stored base (₹80 → 0.9412). Re-run updates by code. |
| `02_seed_products.php` | Web: `inventory.products.store` (Inventory converts ₹ → base and records opening stock via `warehouse_stocks[wh][quantity|unit_cost]`) | Done. 38 products: test-raw-01..25 (Buy), test-semi-01..08, test-semifinal-01..03, test-final-01..02 (Manufacture). Existing SKUs are skipped. |
| `03_seed_employees.php` | DIRECT model writes (on purpose: the HRMS form creates a login user + sends a welcome email per employee) | Done. 30 employees test-emp-1..30, CTC typed in ₹ and saved as base (₹ ÷ 85). |
| `04_seed_work_centers_and_sections.php` | Web, in UI order: machines → work centers → sections → WC staff → section staff → external allocations → manual batches (linking staff + allocations) → "Recalculate now" (scope=all) | Done, runs clean. 15 machines test-mah-1..15 (8, 10, 11, 12 unassigned), 3 WCs test-wc-1..3, 10 sections test-sec-1..10, 9 batches, snapshot #1 per WC. |
| `05`–`08_test_*_failures.php` | Negative tests | NOT reviewed by the user yet. Do not touch until the positive flow (incl. routing + BOM) is finished. |
| `browser/` | Headless Chrome (playwright-core, uses the INSTALLED Chrome, no Chromium download) | Done: `config.mjs`, `session.mjs` (`openApp()`, `newTab(path)`, auto-login), `login.mjs` (optional `--check/--fresh/--logout`), `.gitignore` (profile + login file). Own profile folder `.chrome-profile/` = separate Chrome process, no clash with the user's Chrome/Guest windows. Verified: auto-login + Create Routing page loads in ₹. |

Full clean run verified: `00_reset` → `01` → `02` → `03` → `04`, all exit 0.

## Why routing and BOM go through the browser
The routing page's JavaScript calculates the operation figures (processing time, labor/machine rates, cost per unit,
setup cost). On save, the server recalculates them (`ValidatesRoutingOperations.php:312/344`) and REJECTS any mismatch
over 0.0001. The user wants the figures to come from the browser JS, re-runnable after every reset without an
assistant, so routing and BOM scripts are Node + Playwright scripts that fill the real pages (`/production/routing/create`, BOM create).

## What the WC structure looks like (resources a routing op can use)
- test-wc-1: sec-1 [MRN, EVE, SH-00-06] machines mah-1 laser, mah-5 saw, mah-9 plasma · sec-2 [DAY, MRN, SH-10-20] mah-2 press brake, mah-14 forge · sec-3 [DAY, SH-04-09, SH-11-18] batch-1 (deburring, ext workers only)
- test-wc-2: sec-4 [DAY, MRN] staff only, NO machine/batch · sec-5 [DAY, EVE] batch-5 · sec-6 [MRN] batch-6 · sec-7 [DAY, EVE] mah-3 VMC, mah-13 5-axis, batch-7 · sec-8 [DAY, SH-10-20] mah-4 robotic welder, mah-6 powder booth, batch-8
- test-wc-3: sec-9 [DAY, MRN, SH-11-18] mah-15 heat exchanger, batch-9A, batch-9B · sec-10 [DAY, EVE, SH-16-20] mah-7 packaging line, batch-10A, batch-10B

## App findings noticed while seeding (not fixed — for the user's decision)
- Shifts created via the shift form get no `company_id`.
- Codes are saved uppercase (TEST-WC-1, TEST-MAH-1).
- Products via the form get `planning_type = stock` (Production doesn't read it).
- Energy source "gas" isn't allowed by the machine form (electricity/petrol/diesel/others) — test-mah-15 uses "others".
- test-emp-8 (4h shift) and test-emp-25 were accepted into WC/section with no warning.
- Earlier audit (docs/short_full_review.md, "Point 3") items F1–F9, M1–M5, D1–D6 await the user's decisions.

## Next step
Agree the ROUTING data with the user (routings, operations, resources, setup times, yield, alternatives, external ops,
approval status), then write the headless routing script; then the same for BOMs; then review the negative scripts 05–08.

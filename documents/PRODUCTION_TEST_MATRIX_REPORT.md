# Production-completion test matrix — run report (2026-10-15)

Baseline SHA `25c05c4` (branch `ui`); fixes on branch `fix/production-matrix`. Environment: disposable MySQL 8.4.0
(`127.0.0.1:33084`), fresh-install databases `zolo_test_completion_uat` (baseline code) and `zolo_test_uat2` (fixed code),
app served by `php -S`, real Chrome via Playwright (scripts in the session scratchpad, `e2e/*.mjs`). Companies created:
Alpha Traders / Delta Traders (general trading, TN), Beta Foods / Echo Foods (FMCG, KA), Gamma Textiles (textile, TN).
All GSTINs are synthetic, checksum-valid.

## Gate status after fixes

| Gate | Result | Notes |
|---|---|---|
| 1 Automated regression | PASS | all six suites green on the final code (baseline: 5 of 6 red, mostly test-environment leakage B41) |
| 2 Fresh install | PASS | migrate + seed clean; picker creates first company with COA, FY, series, GST slabs; no demo catalog |
| 3 Upgrade / replay | PASS | baseline-built UAT DB upgraded: documents, journals (Dr = Cr 21,97,149.90), stock unchanged; GRN branches repaired; rollback 10 + replay clean |
| 4 Sales E2E | PASS | quotation (with GST) → unified sales page → posted, quotation converted; IGST sale with batch + serial; idempotent payments; credit note; A4 / thermal / PDF prints |
| 5 Purchase E2E | PASS | GRN → bill (entry visible), supplier payment idempotent and race-safe, debit note with serial; AP ledger = open items |
| 6 Unified Sales/Purchase UI | PASS | /sales, /purchases, ?new=1, drafts, lots, tax-inclusive items, quotation/GRN/DC conversions on the same pages |
| 7 Inventory | PASS | transfer list includes every item; adjustments post Dr/Cr Inventory vs Variance; ledger = warehouse = product total; inventory value = ledger |
| 8 Accounting | PASS | TB balanced; P&L/BS/GL/ageing agree with journals; journal posting needs accounting permission; company-wide statements (`All branches`) |
| 9 GST | PASS | CGST/SGST intra, IGST inter (sale + purchase), notes reverse tax, HSN on print, GST review lists B2B/CDNR/inward |
| 10 Multi-company | PASS | 36 guessed-ID URLs, 15 lists/searches, 6 writes, body `company_id` injection: no leakage; user / notification lists scoped |
| 11 Multi-branch | PASS | branch access editable on the user form and enforced; GRN/DC branch-scoped; new branches number their documents |
| 12 Permissions/security | PASS | clerk cannot post/read journals, wipe data, grant permissions or pay other-branch bills; CSRF/API/file probes pass |
| 13 Concurrency | PASS* | last-stock race, same-key retry, parallel payments, unique numbers. *php -S serialises requests; true parallelism is covered by the suites' concurrency tests |
| 14 General trading UAT | needs operator | all flows above run in a real browser |
| 15 FMCG UAT | PASS (automated) | two lots, IGST purchase, 10+1 scheme, server-side FEFO, expiry write-off with GL, damage capability on |
| 16 Textile UAT | PASS (automated) | MTR, job work order/dispatch/receipt (loss limit enforced), capitalised cost, GST challan A4 + dot-matrix |
| 17 Timber UAT | automated only | `test_sawing_volume_*`, `test_piece_picker_*`, `test_dimensions_*` |
| 18 Solar UAT | automated only | `test_solar_*` |
| 19 Browser/UI matrix | PASS | 27 pages × desktop/tablet/mobile × light/dark: no 4xx/5xx, no sideways scroll, dark-mode labels readable |
| 20 Performance | BLOCKED | no agreed p95 budgets; see "Performance" |
| 21 Queue/integration failure | PASS | provider missing → failed/configuration, posting untouched, duplicate request idempotent, retry re-attempts the same row |
| 22 Printing | BLOCKED | HTML/PDF/dot-matrix text verified; physical output needs printers |
| 23 Backup | PASS (local) | AES-256 archive verified, now includes images and attachments; off-site needs a real disk |
| 24 Restore | PASS (local) | scoped `zolo_test_restore_*` target; per-company health identical to source |
| 25 Rollback | PASS (schema) | down/up of the newest migrations; previous-release code rollback needs the deployment rehearsal |
| 26 Production environment | BLOCKED | needs the server; root `.htaccess` hardened (B39) — verify on real Apache |
| 27 Real-data reconciliation | BLOCKED | needs customer data |
| 28 Sign-off | BLOCKED | operator, accountant, security, deployment reviewers |

## Findings and fixes

Severity: **C** critical, **H** high, **M** medium, **L** low. All marked *Fixed* were re-verified in a real browser on a fresh
install of the fixed code (`e2e/20-verify-fixes.mjs` 27/27, `e2e/21-verify-fixes2.mjs` 18/18) unless noted.

| ID | Sev | Finding | Fix |
|---|---|---|---|
| B23 | C | Journal screens had no permission check: a zero-permission clerk posted a manual journal | Fixed — `accounting.reports.view` / `accounting.voucher.post` on every journal action; test added |
| B21 | H | Empty database wiped every company; any Owner could run it | Fixed — refused while more than one company exists; test added |
| B42 | H | `USER_VERIFIED` read with `env()` in 70 places and missing from `.env.example` (fresh installs demo-locked; breaks under `config:cache`) | Fixed — `config('app.user_verified')`, default true; activation flag likewise |
| B28 | H | Stock adjustments moved stock but posted no journal | Fixed — Dr/Cr Inventory vs Inventory Variance, corrections on edit/delete |
| B29 | H | Transfer and POS item lists hid quick-created items (`is_imei = 0`) | Fixed |
| B11 | H | Quotation → sale/purchase posted without an idempotency key (422); quotation never marked converted | Fixed — conversion opens the unified pages (`?from_quotation=`); posting marks it converted; legacy views removed |
| B20 | H | `accounting.voucher.post` / `journal.reverse` did not exist; accounting permissions not on the Role page | Fixed — migration + Role page |
| B10 | H | Tax-inclusive items charged GST on top of the inclusive price | Fixed — master price shown as pre-tax rate |
| B12 | H | GRN/DC saved without branch/FY and not branch-scoped | Fixed — stamped, scoped, existing rows repaired |
| B35 | H | Starter-masters migration failed on replay (duplicate default series) | Fixed — one default per type, idempotent `seedSeries()` |
| B41 | H (test) | Suites read the developer `.env` feature flags, so 34 tests failed locally | Fixed — flags pinned in every phpunit config |
| B4 | M | Quick customers got the global group 1 and a company-1 fallback | Fixed + data repair |
| B6 B9 | M | Quotation price list 500 → NaN totals → "Database temporarily unavailable" | Fixed — null group = 0, amounts validated |
| B7 | M | Quotation lines ignored GST | Fixed |
| B18 | M | Quotation item list 500 for a warehouse with no stock | Fixed |
| B19 | M | No way to limit a user to branches | Fixed — branch access on the user form |
| B22 | M | Activity log not company-scoped | Fixed — `company_id` column, filtered |
| B43 B44 | M | Notification and user lists showed every company's users; users of other companies editable | Fixed |
| B24 B5 | M | New branches / new years / production used fallback numbers (`ERP-JE-2-7-2-…`) | Fixed — readable series per branch and year |
| B25 | M | Statements only per branch | Fixed — `All branches` option for users holding every branch |
| B27 | M | Ageing as of FY end (bills looked 137 days overdue) | Fixed — as of today |
| B31 | M | FMCG preset lacked damage-stock capability | Fixed + enabled for existing FMCG companies |
| B32 B33 | M | Backups skipped images/attachments; failures gave no reason | Fixed |
| B37 | M | Report pages and lists scrolled sideways | Fixed |
| B13 | M | GRN conversion opened behind the register view | Fixed |
| B2 | M | Fresh install seeded the SalePro demo catalog without a company | Fixed — `SEED_DEMO_DATA=true` only |
| B1 B3 B14 B17 B30 B36 | L | Fresh-install message, GST slabs at creation, missing placeholder image, 500 on unknown IDs, FEFO label, `/operations` landing | Fixed |
| B39 | Risk | Root `.htaccess` lacked explicit denies | Fixed — verify on the real web server |
| B38 | L | Cash ledger could go negative without any warning | Fixed — payment recorded with a "cash ledger is now negative" warning |
| B40 | L | Dark mode: light labels on white filter panels (/products, register filters) | Fixed |
| B34 | Hygiene | Old demo database dump committed under `database/backups/` | Fixed — untracked and ignored; local copy kept in `storage/app/private/db-backups/` |
| PO | M | Purchase orders shared the bill series, could be over-received and never closed | Fixed — `PO-` series, receipts limited to open quantity per item, order shows Fulfilled when fully received |
| T1 | Test | `ComplianceHttpTest::test_web_and_api_note_posting_share_retry_and_approval_contracts` failed in its fixture (full layout without its middleware) | Fixed — stand-in layout for that check |
| B45 | M | Every page (web screens included) sent `Access-Control-Allow-Origin: *` | Fixed — API only, origin configurable (`API_CORS_ORIGIN`) |
| B46 | L | 86 labels had no translation and showed raw keys (`db.Stock`) | Fixed — missing `db.*` keys show their readable text |
| B48 | M | Legacy purchase/sale screens swapped an unknown unit for whatever unit came first (a BOX line could post as pieces) | Fixed — named unit must exist; no unit = item's own unit |
| B47 | L | Stock refusals ("insufficient stock") were logged as application errors | Fixed — not reported |

Not bugs (verified): unregistered suppliers / job workers carry 0 GST; job-work cost 65.00/m (20 m loss expensed);
FMCG FEFO is applied server-side when no lot is picked; GRN/DC have no `create` action by design; dot-matrix 403 for general
trading (capability); statutory GST export refused until a verified schema adapter exists.

## Suite results

Baseline (`25c05c4`, developer `.env` present): company 315 tests 15 F + 1 E; commercial 72 OK; compliance 79 / 1 F (T1);
operations 31 / 1 F (stale label); delivery 76 / 1 E (`mysqldump` not on PATH); legacy-mysql 44 / 17 F (B41).

After fixes (final code, flags pinned like CI, MySQL tools and a scoped restore target configured): **all six green** —
company 317 OK; commercial 72 OK (1 skipped, as baseline); compliance 79 OK; operations 31 OK; delivery 76 OK (2 skipped:
SQLite-only path and Windows symlink permission; the MySQL restore proof ran); legacy-mysql 44 OK. Browser re-verification on a
fresh install of the final code: 48/48 (`e2e/20-verify-fixes.mjs`, `21-verify-fixes2.mjs`, `26-last.mjs`).

## Performance

No p95 budgets have been agreed, so this gate stays open. Measured on the development server (`php -S`, no OPcache, 12 requests
each, fresh-install data):

| Page / endpoint | p50 | p95 |
|---|---|---|
| Dashboard | 996 ms | 1,098 ms |
| New sales bill (`/sales?new=1`) | 1,154 ms | 1,306 ms |
| New purchase bill | 1,071 ms | 1,179 ms |
| Sales register | 1,140 ms | 1,229 ms |
| Trial balance | 1,095 ms | 1,183 ms |
| Profit & loss | 920 ms | 1,029 ms |
| Products | 937 ms | 1,001 ms |
| Item autocomplete search | 823 ms | 928 ms |
| Lot lookup | 776 ms | 847 ms |

Even the smallest JSON endpoint costs ~750 ms here, so framework boot without OPcache dominates; `documents/PAGE_PERFORMANCE.md`
shows ~400–500 ms with OPcache. Agree budgets and re-measure on the production stack.

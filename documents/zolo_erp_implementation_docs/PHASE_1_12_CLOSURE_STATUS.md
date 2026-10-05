# Phase 1–12 integration and completion rehearsal

Status: **INTEGRATED ON CLOSURE BRANCH — FINAL ACCEPTANCE BLOCKED**. This record does not declare any phase complete or authorize production activation.

## Integration baseline, 2026-10-05

- Closure branch: `codex/phase1-12-final-closure`.
- Starting cumulative SHA: `0e7b6f2e3d59929964bee4d005211acaf7fb26ac` (`codex/phase9-10-operations-profiles`).
- Main base SHA: `549d2a9c9b2a023c442e799ea5da2f1b91f999cb`.
- History integration commit: `d6d0070`. It merges `codex/phase6-shared-commercial` without changing the cumulative application tree.
- All six local branches, including `main`, are ancestors of the closure branch. Phase 1 accounting and Phase 4c were already included; Phase 7–8 was already merged into the cumulative branch. Duplicate Phase 6 snapshots caused 23 conflicts; newer cumulative accounting, tax, operations, UI and security code was retained. No force push, history rewriting or branch deletion was used.
- The original checkout's uncommitted Phase 5 accounting files remain untouched. They are not evidence that the entire closure plan is implemented.
- Runtime: PHP 8.3.32, Laravel 10.49.1, disposable MySQL 8.4.11.
- Registered route baseline: 955 routes in the local testing application without an installation `.env`; installer route registration is conditional.
- Browser baseline: historical Phase 5–12 screenshots remain evidence of their original builds only. The required whole-application device/browser matrix has not been repeated or accepted here.
- Gates remain closed: `CapabilityCatalog::OPTIONAL_ACTIVATION_READY=false`, shared commercial/compliance/operations disabled by default, GST export not filing-ready. Test-only gate bypasses do not change these defaults.

## Integration defects fixed

The full migration chain creates new permission records before the legacy tenant seeder runs. The seeder previously inserted fixed IDs starting at 4, causing a fresh installation to fail with a duplicate permission primary key. It now preserves existing permission IDs and grants, inserts missing permissions without collisions, and translates legacy preset/package IDs through each permission's name and guard. A MySQL migration/seeding regression verifies correct grants and unchanged permissions on replay.

The old commercial browser fixture constructed the Phase 6 view directly without the newer industry/profile variables. It now renders both fast-entry modes through the actual controller and authorized HTTP context. The capability bypass remains explicitly confined to the fixture.

The old accounting web fixture expected an operator without `accounting.reports.view` to read Voucher Hub. It now asserts that web/API reads fail until that named permission is granted, then checks that posting controls remain hidden until the separate posting grant exists. Production authorization is preserved.

CI now runs all six required configurations against separate disposable MySQL services: company, commercial, compliance, operations, delivery and legacy-mysql. Delivery alone creates a restricted restore target. Pull requests trigger the matrix on each head update; main pushes and manual dispatch also run it. The initial closure push was additionally verified, but that branch trigger was removed after PR creation to avoid duplicate push/PR matrices.

## Sample rehearsal created at the user's request

`tests/Support/prepare_completion_rehearsal.php` creates a sample rehearsal using the original migration chain and repository seeders. It refuses every target except the explicitly opted-in `zolo_test_completion_uat` database. **Running it replaces that sample database.** It replaces fixture contact details and admin credentials, executes the existing company backfill, records source row counts/hash, posts opening stock through the existing command, and captures stock/accounting reconciliation without rebuilding quantities.

The local sample uses MySQL at `127.0.0.1:33329`, company 1, branch 1, FY 1. Private generated evidence and credentials are kept under ignored `scratch/completion-rehearsal/manifest.json` and `access.json`. Do not publish the access file or use its credentials outside the sample database.

To repeat against an existing restricted disposable MySQL server, create/grant only the named sample database, then run:

```powershell
$env:APP_ENV = 'testing'
$env:APP_KEY = 'base64:dGVzdC1vbmx5LWF1ZGl0LWZpeHR1cmUta2V5LTEyMzQ='
$env:ERP_TEST_MYSQL = '1'
$env:ERP_TEST_MYSQL_DATABASE = 'zolo_test_completion_uat'
$env:ERP_TEST_MYSQL_HOST = '127.0.0.1'
$env:ERP_TEST_MYSQL_PORT = '33329'
$env:ERP_TEST_MYSQL_USER = 'zolo_test'
$env:ERP_TEST_MYSQL_PASSWORD = 'fixture-only'
php tests/Support/prepare_completion_rehearsal.php
```

These are disposable fixture credentials. The script creates a different random application password and records it privately. The sample is not a copy of customer production data and cannot establish customer retained-data acceptance.

## Stop condition reached

The sample source hash before opening stock is `a3264240443ae14683c30cf8f925a944cbd4c70f99dfaedd71ac994379e4d0c4`. Opening stock completes, but reconciliation returns **21 stock differences**. For example, product 1 has warehouse/ledger quantity 10.0000 and product projection 624.7000, a difference of -614.7000. Product 2 has projection -152.5000 without attributable ledger history. These pre-existing seed discrepancies are preserved for review; no rebuild or fabricated transaction is used to hide them.

Accounting journals/open items in this sample reconcile at zero. That does not establish historical AR/AP acceptance or prove inventory GL agrees with opening stock valuation.

The existing `erp:health` command returns failure for this sample: missing COGS mapping, missing default sale/purchase/journal/payment series, stock quantity differences and inventory accounting value differing from the stock ledger. Its private report is `scratch/completion-rehearsal/health.json`. These are review/setup blockers, not approvals.

The requested closure plan explicitly says to stop on unexplained stock/accounting differences and missing historical stock attribution. Functional cutover and final main merge therefore remain blocked. Sample generation cannot invent the missing business history or reviewer decisions.

## Remaining closure work and acceptance

- Workstreams A/Phase 1: whole legacy route/read/write/cache/import/export/file isolation and reviewed ownership/uniqueness/FK/backfill acceptance remain open. Known examples include `SaleController` customer/warehouse queries and the broad legacy business route group.
- Workstream B/Phase 3: `SaleController::generateInvoiceName` and other active timestamp/random/count generators still require authoritative cutover.
- Workstream C/Phase 4: Sale, Purchase, Return and ReturnPurchase remain in `LegacyStockShadow::WRITERS`; direct projection writes remain. The gated adapters do not establish cutover while their gates are false.
- Workstream E/Phase 11: the complete operator screen, permission, keyboard, device and browser matrix remains pending.
- Workstream F/Phase 12: customer retained-data source, reviewed explanations for discrepancies, real accountant/provider/printer acceptance, private off-site backup/restore and signed cutover remain pending.
- Do not merge to main, enable optional capabilities, tag an accepted release or declare 12/12 complete until the specified software and external acceptance gates pass.

## Validation record

PHP syntax for cumulative changed files, JavaScript syntax for cumulative changed files, and diff whitespace pass. The permission-seeding regression passes on MySQL: 1 test, 11 assertions. The corrected commercial HTTP fixture passes on SQLite and MySQL: 1 test, 8 assertions each. The corrected accounting permission case passes on SQLite: 1 test, 24 assertions. Compliance MySQL passes: 62 tests, 361 assertions. Operations MySQL passes: 28 tests, 227 assertions. Delivery MySQL passes: 29 tests, 147 assertions, with the SQLite-only archive test skipped; the separate MySQL restore case runs. Legacy seeded MySQL passes: 28 tests, 204 assertions. The initial commercial suite exposed the obsolete browser fixture (28 tests, 141 assertions, one error, one optional performance skip); the initial company suite exposed the obsolete accounting permission expectation (280 tests, 2,861 assertions, one failure). Their focused corrections are verified above. The sample preparation guard also refuses a non-fixture database before application bootstrap. Full acceptance for the corrected head is reported in [PR #3](https://github.com/vigneshsinna/zolo-erp-pro/pull/3), without replacing these historical results.

## Closure fixes after the baseline (software only)

This section supersedes the "Stop condition reached" and "Remaining closure work" paragraphs above where they differ. It records software changes only. No customer data, accountant review, provider/printer hardware or signature is involved, and none is claimed.

- **21 sample stock differences: resolved at the source.** The upstream demo dump carried `products.qty` totals (some negative) with no warehouse row or ledger history, e.g. product 1 total 624.7 against a warehouse row of 10. `TenantDatabaseSeeder` now sets each seeded product total to the sum of its seeded `product_warehouse` rows. The rehearsal now reports **0 stock differences** and `erp:health` returns `ok`. This is a synthetic seed fix; it proves nothing about retained customer stock, which still needs its own reviewed reconciliation.
- **Sample setup.** `SemanticAccountResolver::seedCompany` picks the single system account when several leaves share a sub-type (COGS products vs. shrinkage); other ambiguity stays unmapped. `prepare_completion_rehearsal.php` performs explicit, labelled synthetic setup (default series, opening inventory journal against owner capital) so mappings, series, stock and inventory value agree. A real cutover uses reviewed mappings and `erp:import-opening`.
- **Seed parents.** The demo products no longer reference brands/units that were never exported, so a fresh install can run `erp:backfill-company-context` without fabricating parents.
- **Phase 4 commercial stock cutover.** `SaleController`, `PurchaseController`, `ReturnController` and `ReturnPurchaseController` (store, update, CSV import, destroy, bulk delete, `updateFromClient`) no longer write product, warehouse, variant or batch quantities. Each document posts or reverses one `InventoryMovementService` movement through `LegacyInventoryPosting` in a single transaction; pre-cutover documents reverse from their persisted lines. `LegacyStockShadow::WRITERS` is empty. Stock-policy rejections now surface as an operator message. `SaleController::genInvoice` no longer leaks a transaction level. Known limit: legacy sale returns receive at current average cost; restoring the original sale cost is done by the gated shared `ReturnService`.
- **Phase 3 numbering cutover.** Sale, purchase, sale credit note, purchase debit note, transfer, adjustment, damage, exchange, expense and sale/purchase payment numbers come from `DocumentNumberService` inside the posting transaction (web, POS, CSV import, opening-stock purchase, due clearing, challan payment). Not converted because their tables have no company ownership column yet (see the ownership matrix): quotations, deliveries/packing slips, income, money transfers, payroll, customer opening-balance marker sales (`cob-`), café/repair/booking/water prototypes and the manual `purchase:auto` command (not scheduled).
- **Phase 1 company isolation (request scope).** Business routes resolve a trusted company/branch/financial year (`ResolveLegacyCompanyContext`; an install without companies gets an actionable message to run `erp:backfill-company-context`). While a request carries that context every Eloquent read of a company-owned model is scoped to it and new rows are stamped (a row naming another company is refused). New users created on the user screen join the creator's company and branches. Proven by a two-company HTTP test on seeded MySQL. **Still open:** raw `DB::table()` readers in reports/exports are not covered by the model scope; company-aware uniqueness/FKs on remaining tables; branch-restricted legacy lists; public file paths. These stay in the ownership matrix.

Gates are unchanged: optional capabilities, shared commercial, compliance and operations remain disabled by default, GST export is review-only, and customer retained-data reconciliation, accountant/provider/printer acceptance and signed cutover are still required.

## Second closure pass (software)

- **Raw queries.** A company-scoped query builder adds `company_id` to `DB::table()` selects, updates and deletes of company-owned tables (and to their joins) while a request carries a company context. Subqueries compiled into a parent are not rewritten. The inventory movement service opts out (`withoutCompanyScope`) only to detect foreign or duplicate stock rows.
- **Document numbers.** Quotations, deliveries/packing-slip deliveries, income, money transfers and payroll gained company keys (`2026_10_11_000001`) and use the company series. Damage, exchange, inventory-close and journal numbers are unique per company (`2026_10_11_000002`). A processed payroll keeps its number when regenerated, and a delivery number is never taken from the form.
- **Return cost.** Legacy sale returns restore stock at the original sale's issue cost when the sale has an applied issue.
- **`purchase:auto`** runs for one authorized company (`--company`, `--actor`) and refuses to run across companies. The installer runs the company backfill (with the app briefly down) after seeding.
- **Attachments.** Company document attachments are delivered by `secure-documents/{folder}/{file}` only to signed-in members of the owning company, and only when a document of that company references the file. `public/.htaccess` refuses direct `documents/<folder>/` requests. **Nginx or other servers need the equivalent rule**: deny `^/documents/(sale|purchase|sale_return|purchase_return|quotation|expense|delivery|transfer|adjustment|add-payment)/`. Notification and production attachments, and images (`public/images`), remain public.
- **Still open.** Same-company foreign keys; company-aware uniqueness for tables whose ownership is derived (variants, discounts, HR); subquery scoping; the unscoped notification/production/image folders; all external acceptance. Second-company activation remains OFF (`CapabilityCatalog::OPTIONAL_ACTIVATION_READY=false`) and Phase 12 acceptance remains Pending.

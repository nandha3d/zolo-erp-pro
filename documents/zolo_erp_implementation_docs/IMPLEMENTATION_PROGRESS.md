# Implementation progress

Current implementation state, reviewed 2026-10-03. This file describes the latest behavior; historical audit snapshots and package evidence are retained in [the audit resolution log](../auditing/COMPANY_FOUNDATION_AUDIT_RESOLUTION.md).

Execution follows [document 26](26_CODEX_EXECUTION_SEQUENCE_AND_CHECKLIST.md), using one shared ERP core. The implementation pack includes authoritative specifications 00–33; docs 31–33 define the modern UI, Optech workflow mapping and common component contracts.

## Current phase and activation gates

Phase 0's delivered regression package is complete. Phase 1 remains in progress: schema/backfill, authorized context/setup and bounded API reader isolation are delivered. Full legal-company isolation is not complete.

Do not activate a second company or Phase 2 capabilities until all Phase 1 acceptance gates pass. Audit F-13 Option B permits inactive additive foundations while the execution plan's D1–D12 stabilization work remains assigned to its owning phases; it does not permit unsafe writer activation.

No production migration, backfill or deployment has been performed. Existing posted numbers, fiscal-year dates, opening balances, stock quantities and journal values have not been rewritten.

## Confirmed business policies

- Extend existing `fiscal_years`; preserve calendar-year and other historical ranges. Do not introduce `financial_years` or automatically change dates to April–March.
- Migrate artificial 1970 opening documents separately. Current backfill assigns ownership while preserving their dates and balances.
- A supplier bill recognizes its full payable/payment. Received value posts to inventory; unreceived value posts to an active `goods_in_transit` asset account.
- Purchase status 1 Received persists full ordered quantity in `product_purchases.recieved`; status 2 Partial requires validated line-level `received_qty`; status 3 Pending persists zero received quantity. Status 4 Ordered is an unbilled PO with no stock, journal or payment effect at creation.
- Partial/Pending purchases require the goods-in-transit account when unreceived value exists. Missing required mappings roll back all purchase effects. Charges/discounts are apportioned by line value; free lines use quantity. Free goods avoid zero-value journals. Bank payments credit bank.

Subsequent receipt release, legacy web update parity, service-item posting, tax/valuation semantics and idempotency remain gated shared-engine work.

## Phase 0: delivered transaction regression baseline

Existing service transactions retain their document, stock, payment and accounting effects while returning their intended relationships:

- Sale line/product, purchase line/payment and transfer line relationships are repaired.
- Required purchase receipt fields reflect the approved Received/Partial/Pending/Ordered behavior above.
- Regressions exercise original commercial/accounting table definitions, service atomicity, stock, payments, balanced journals, receipt quantities and rollback on accounting failure.
- Locked Composer dependencies are installed locally without upgrades or lockfile changes.

Current ERP service proof: 14 tests, 79 assertions in the isolated suite. Fixtures support SQLite for isolated logic and an explicitly opted-in disposable MySQL database for schema/runtime proof.

Unresolved stabilization work includes web/API writer convergence, stock identities, authoritative numbering, posting configuration/precision, periods, idempotency and source-of-truth ledgers. Owning phases and cutover gates are listed in [the execution plan](../ZOLO_ERP_EXECUTION_PLAN.md).

## Phase 1: company schema, migration and backfill

Delivered foundation:

- Company, branch, company membership and user-branch tables. Membership IDs respect legacy unsigned INT users; composite FKs prevent cross-company branch membership.
- Nullable indexed `company_id` on 32 audited core tables when present, plus nullable warehouse `branch_id`. Other operational/industry ownership migrations remain pending.
- Existing fiscal years gain company, status and lock/close metadata; original dates and `is_closed` are preserved.
- Core company-key DDL validates all existing artifacts before mutation and resumes missing columns/indexes after partially committed MySQL DDL.
- Company currency defaults must exist during dry-run. Migration 000003 widens `base_currency_id` to match the BIGINT currency ID and adds a validated restricting FK.
- `erp:backfill-company-context --dry-run` performs zero writes. Real backfill requires maintenance mode and paused writers/workers/scheduler, initializes DEFAULT/MAIN, and assigns audited rows/memberships in one transaction with batches of 500.
- Unexpected ownership, invalid branches, orphan references and overlapping/inverted fiscal years block assignment. Existing legal names and role overrides survive repeat runs; known legitimate zero sentinels are preserved.
- The unsafe scheduled full-database reset is removed. Manual reset requires the demo environment, explicit confirmation and a readable nonempty dump.

Proof: 21 foundation tests, 97 assertions; three MySQL migration/recovery tests, 16 assertions; three scheduler/reset safety tests, 11 assertions. The MySQL proof includes the full source migration chain, injected failure after committed ALTER, resumption, rollback without row loss, currency width/FK validation, and backfill dry-run/write/repeat.

Use [the runbook](COMPANY_BACKFILL_RUNBOOK.md) for explicit disposable database guards, rehearsal and reviewed rollback order.

## Phase 1: authorized context and financial-year setup

- Immutable `App\Services\Platform\CompanyContext` contains company, branch and FY IDs.
- Resolver checks active/non-deleted user, active company, membership, branch grants and company-owned fiscal years.
- Default company selection requires exactly one default membership. Implicit branch selection uses the sole authorized active branch, including non-MAIN branches. Multiple authorized branches require explicit selection.
- Company-local date must match exactly one existing FY unless an authorized FY is explicitly selected. Closed historical years remain readable; posting assertions reject closed/non-open periods, out-of-year dates and inclusive lock dates.
- Header/session tuple uses `company_id`, `branch_id`, `financial_year_id`. A company-header switch discards stale dependent session values. Body IDs are not trusted as context.
- Middleware exposes context through request attributes and clears it after success/failure.
- Authenticated web `/company/financial-years/setup` and API `/api/v1/company-context/financial-years` remain reachable before branch/FY resolution.
- Setup requires active Admin/Owner membership after any company role override. Creation locks the company, rejects inclusive overlap and preserves historical dates. Inputs respect the existing 100-character name column and MySQL date range.
- Missing current FY redirects a web administrator to setup or returns HTTP 409 with an authorized setup URL; staff receives an administrator-required response.

The combined context/setup/catalog/transaction-reader tests currently contain 61 tests and 256 assertions. Setup form checks pass in headless Chrome at desktop/mobile widths, including labels, native validation, CSRF field and keyboard focus. These checks do not establish live legacy transaction browser parity.

## Phase 1: implemented API reader isolation

Ten authenticated API GET routes currently enforce trusted company context:

- Product list, search and detail.
- Customer and supplier lists.
- Stock valuation.
- Sales and purchase lists and details, including payments, product lines and associated journals.

Explicit query scopes constrain root/nested master ownership. Stock rows must agree with their product and warehouse company, and their warehouse must belong to the selected branch. Catalog quantity is calculated from visible branch stock; stored aggregate quantities are preserved. Valuation rejects other-company/branch warehouse IDs. Real HTTP tests cover guessed IDs, header spoofing, authorized company switching, corrupt nested masters/stock and restricted branches.

Sales/purchase root documents require company-owned warehouses in the selected branch and owned customer/biller or linked supplier. Legitimate null/zero purchase suppliers remain readable. Detail lines require company-owned products; nested product quantities use visible branch stock. Payments require matching company ownership, owned linked accounts and visible sale/purchase sources. Journal headers, items and accounts are company-scoped. Corrupt/foreign nested rows are excluded, and guessed foreign document IDs return 404.

Seven transaction HTTP tests cover list/filter/pagination isolation, branch restrictions, party/warehouse corruption, line/payment/journal ownership, authorized company switching, closed-year historical reads and preserved Partial receipt quantities. The two original service tests retain relationship serialization assertions; HTTP response proof now belongs to the context suite.

These read routes require migration/backfill and authorized memberships. Transaction writers and legacy/raw routes still require integration. Financial-year business-date mapping for historical transactions/openings remains a separate gate.

## Current validation evidence

| Suite | Latest verified result | Scope |
|---|---|---|
| `phpunit.company.xml` in CI on MySQL 8.4 | 102 tests, 459 assertions | Full source migrations/recovery, backfill, commercial services, context/setup, ten bounded HTTP readers and reset safety |
| Targeted company context + ERP regression suites on local MySQL 8.4 | 75 tests, 335 assertions | Current context/setup, catalog and sales/purchase HTTP isolation, commercial services and preserved receipt/accounting behavior |
| `phpunit.legacy-mysql.xml` | 16 tests, 54 assertions | Original seeded accounting services/pages, POS/dashboard, API auth/catalog/accounting |
| Headless Chrome setup form checks | Passed | Rendered desktop/mobile form, inputs, labels, CSRF field, keyboard focus, no page errors |

[CI run 37134384890](https://github.com/vigneshsinna/zolo-erp-pro/actions/runs/37134384890) passes both PHPUnit suites for code commit `523fb93`. Fixture clearing requires explicit disposable database opt-in/name and a fixture-only account.

The original seed export lacks brand IDs 10/16/17 and unit IDs 4/9. The legacy bootstrap proves dry-run rejection, supplies labelled fixture-only parents, then rehearses dry-run/write/dry-run. Retained installations need reviewed master data; fixture placeholders are not production repairs.

Fresh empty-fixture migration time was 3.617 seconds; migration/seed/backfill preparation took 4.878 seconds locally. These are fixture timings, not production lock/downtime estimates.

The initial seeded-suite connection failure is resolved by the disposable MySQL rehearsal. Frontend dependency/build configuration remains unverified; the historical build baseline failed with absent dependencies/configuration. No frontend dependency changes were needed for the delivered backend packages.

## Open Phase 1 work

[Company table ownership matrix](COMPANY_TABLE_OWNERSHIP_MATRIX.md) inventories all 125 application tables: 123 active literal creators and two configured Spatie creators. Discovery is complete; ownership migration and runtime isolation are not.

Remaining gates:

1. Convert remaining transaction/accounting readers and all writers, actual web routes and raw queries; stamp trusted ownership and validate every parent, branch and posting date.
2. Complete operational/settings ownership migrations/backfill, including imported schemas. Active references to floors/kitchens/menu_type/services lack creators in this repository.
3. Convert applicable business uniqueness and validators; add reviewed same-company FKs and mandatory ownership after writer/backfill parity.
4. Integrate scheduled writers, company caches/invalidation, imports, exports, downloads and public file storage/access.
5. Rehearse retained representative data at production scale, including locks, concurrency, reconciliation and live transaction browser flows.

Full F-02/F-03/F-04/F-05 acceptance remains open. F-11 local/CI fixture proof is delivered; production-scale retained-data acceptance is pending.

## Phase 4a: stock movement ledger and generic services

Delivered on branch `feature/phase-4-stock-ledger`, built from `d2bdbcc` independently of the in-progress Phase 2/3 work. It is not merged or activated. Legacy web controllers (4b) and the remaining writers (4c) still mutate quantities directly.

Schema (`2026_10_04_000001_create_stock_ledger_tables`):

- `stock_movements` and signed `stock_movement_lines`: quantities are base-unit `decimal(18,4)`. Each line persists its unit cost and value. Every movement has a company, source reference, unique idempotency key, and either a reversal link or a reversal.
- `stock_identities` holds serials and dimensioned pieces, with a single warehouse and status. `stock_dimensions` holds piece sizes and computed volume. The plan's `stock_serials` concept is implemented as identities of type `serial`.
- `product_uom_conversions` adds normalized per-product factors. `product_batches` gains `company_id`, `mfg_date`, `mrp` and `status` instead of a parallel batch table.
- Model guards make posted movements and lines immutable. Changes are made by posting reversals.

`App\Services\Inventory\InventoryMovementService` provides `receive`, `issue`, `transfer`, `adjust`, `opening` and `reverse`. Each call is one transaction:

- It locks the affected products in ID order, then the warehouse rows.
- It enforces negative-stock and expired-batch policy, plus the serial, piece, batch and variant rules.
- It converts units, posts weighted-average cost, and updates `products`, `product_warehouse`, `product_variants` and `product_batches` quantities.

Other components:

- `InventoryPolicy` reads `companies.settings_json.inventory`: `negative_stock` allow/warn/block, `expired_batch` allow/warn/block, and `valuation` weighted_average/standard. When the company sets no `negative_stock` policy, `without_stock=yes` means allow and anything else means block. Warnings are stored on the movement.
- `UomConversionService` uses product conversions first and falls back to the legacy `units` operator/value.
- `InventoryAvailabilityService` reports on-hand from projections, the ledger balance, and stock reserved by Pending/Processing sales.
- `InventoryReconciliationService` and `erp:stock-reconcile {--company} {--rebuild} {--force}` compare the ledger with all four projections. A rebuild changes only products that have ledger history.
- `erp:stock-opening {--date} {--dry-run}` records each nonzero `product_warehouse` row once (key `opening:pw:{id}`) without changing projections. It imports the IMEI list as serials only when the list accounts for every unit. Run it with stock writers paused, after the company backfill and before converted services write.

`SaleService`, `PurchaseService` and `InventoryService` now post through the ledger, using source keys `sale:{id}`, `purchase:{id}` and `transfer:{id}`. Behaviour changes for API callers:

- Overselling is blocked by default.
- Serial, batch and variant products must identify the stock they move.
- An explicit `sale_unit_id` or `purchase_unit_id` is converted to the base unit.
- Sale COGS uses the cost posted on the issue movement, not the current `products.cost`.
- Purchases still overwrite `products.cost` with the last unit cost (legacy behaviour retained).

Proof: 21 new tests; 20 run on local SQLite fixtures. They cover:

- receipt→issue reconciliation at weighted-average cost, and transfer netting;
- serial single-location rules, expired-batch block/warn, and negative-stock block/allow/warn atomicity;
- reversal history, idempotent replay, unit conversion (BOX/CASE/125.375 MTR), variants and dimensioned pieces;
- immutability, availability and company checks;
- the opening and reconcile commands, and the converted services.

The full `phpunit.company.xml` suite gives 123 tests and 603 assertions, with 4 MySQL-only tests skipped locally. `StockLedgerConcurrencyTest` has four barrier-synchronized processes compete for the last unit while holding a row lock, and asserts that exactly one succeeds. That test and the MySQL migration chain run only in CI and are not yet verified there.

Merge notes:

- Phase 2/3 rewrites the same stock blocks in the three ERP services. When merging, pass its `CompanyContext` into `StockMovementCommand::context` and drop its direct `CompanyWriteGuard::stock` quantity writes in favour of the movement service.
- The sale, purchase and transfer documents keep their Phase 3 series numbers. Movements use the internal reference `SM-{id}`.

Next: 4b shadow recording in Sale/Purchase/Return/ReturnPurchase controllers, then a per-method authoritative cutover after clean UAT reconciliation. After that, 4c covers the remaining writers.

## UI modernization direction

Docs [31](31_MODERN_UI_DESIGN_SYSTEM_AND_SCREEN_MIGRATION.md), [32](32_OPTECH_SCREEN_TO_ZOLOERP_UI_MAPPING.md) and [33](33_COMMON_UI_COMPONENT_LIBRARY.md) are specifications, not claims that their components/screens exist.

Retain Blade/Bootstrap/jQuery and extend `public/css/salepro-neo.css`. Preserve Optech operator workflows/shortcuts using shared backend services, General Trading terminology, capability/profile extensions, and common components. Migrate UI incrementally with responsive, keyboard, accessibility, state and visual proof. UI planning does not bypass company/engine gates.

## Delivery order

Canonical phases remain: 0 regression baseline; 1 Company/Branch/FY; 2 capabilities; 3 atomic series; 4 inventory ledger; 5 accounting/open items; 6 shared commercial services; 7 tax; 8 returns/documents; 9 manufacturing/job work; 10 profiles; 11 UI/API/security completion; 12 migration/UAT/deployment.

Every completed work package is validated, committed and pushed before dependent work starts. Package completion does not establish full phase completion. Historical commit-by-commit corrections belong in the audit resolution log.

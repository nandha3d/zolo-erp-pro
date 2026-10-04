# Implementation progress

Current implementation state, reviewed 2026-10-04. This file describes the latest behavior; historical audit snapshots and package evidence are retained in [the audit resolution log](../auditing/COMPANY_FOUNDATION_AUDIT_RESOLUTION.md).

Execution follows [document 26](26_CODEX_EXECUTION_SEQUENCE_AND_CHECKLIST.md), using one shared ERP core. The implementation pack includes authoritative specifications 00–33; docs 31–33 define the modern UI, Optech workflow mapping and common component contracts.

## Current phase and activation gates

Phase 0's delivered regression package is complete. Phase 1 remains in progress: schema/backfill, authorized context/setup, bounded API readers, shared commercial writers and double-entry accounting paths are delivered. Full legal-company isolation is not complete.

Do not activate a second company or Phase 2 capabilities until all Phase 1 acceptance gates pass. Audit F-13 Option B permits inactive additive foundations while the execution plan's D1–D12 stabilization work remains assigned to its owning phases; it does not permit unsafe writer activation.

The [Phase 2/3 implementation package](PHASE_2_3_IMPLEMENTATION.md) adds capability/profile tables, a legacy module adapter, dependency/configuration checks, navigation and route guards, and atomic numbering for the shared commercial/accounting writers. Optional capabilities remain inactive behind the reviewed Phase 1 gate. Document 12 printing and dispatch are not part of this numbering package.

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

These read routes require migration/backfill and authorized memberships. The shared writers and double-entry accounting paths below now consume the same context; raw legacy/operational routes still require integration. Financial-year business-date mapping for historical transactions/openings remains a separate gate.

## Phase 1: shared writers and double-entry accounting

The reviewed shared services now require an authorized actor and company/branch/FY context. Explicit service tuples are revalidated; missing actors and forged company tuples are rejected. Request-body ownership fields never select the company or actor.

- Sales and purchases validate owned parties, billers, products, units and selected-branch warehouses. Transfers require authorized source and destination branches in the same company.
- Company/FY row locks serialize reviewed postings. Company-local business dates must be valid ISO dates inside the selected open FY and after its inclusive lock date.
- Documents, lines, payments, stock projections, numbers and journals commit together. Invalid ownership, shortages, ambiguous/corrupt stock, missing accounts and posting failures roll back every effect.
- `PaymentService` owns subsequent customer and supplier settlements, reused by both commercial services. The purchase payment endpoint is `POST /api/v1/purchases/{id}/payments`; the existing sale endpoint remains compatible. Payments cannot exceed outstanding amounts, precede the source document, or settle unbilled purchase orders. Settlement amounts and outstanding/paid totals use the existing four-decimal journal precision, avoiding binary-float residue on final-cent payments. Payments carry their own validated posting date and do not move stock. Cash, Bank, Cheque and Credit Card use reviewed mappings; wallet/gift-card/other methods require their owned settlement integration.
- Five accounting API readers and manual journal posting now require company context. Chart children, posted journal headers/items, account resolution and report aggregates are scoped. Account lookup never falls back to a different company. Manual and automatic journals must balance at the existing four-decimal line precision before any number or journal is written.
- Double-entry web charts, journal list/detail/posting, trial balance, P&L, balance sheet, general ledger, cash-flow summary and mapping metadata use the same context. Chart setup and mapping changes require the effective company Admin/Owner role. Foreign parents and accounts are rejected, and multi-mapping updates are atomic.
- Scoped web pages resolve context before shared middleware. Company reads bypass mutable shared caches; legacy cache keys retain their existing invalidation contract. Company identity, currency and timezone come from trusted context. Navigation respects membership role overrides.
- Global quick-create, notification and report-selector modals are omitted from company-context pages until their owning modules are isolated. Shared identities do not provide notification ownership.
- Inventory-close POST aliases consistently return HTTP 409 before any writes. The preview is current authorized-branch stock at product master cost; it makes no historical valuation, weighted-average, period-lock or company-wide GL reconciliation claim.
- New ledger accounts start at zero. Existing undated openings and historical FY dates are preserved for the separate opening migration. Semantic mappings are company-owned metadata; automatic postings continue to use system account codes/subtypes until reviewed mapping integration.

Serial inventory and operational references without reviewed ownership paths remain rejected. Native company-aware uniqueness/FKs, reversals, open items, dated openings and complete raw legacy integration remain owning-phase work. This package does not activate a second company.

## Current validation evidence

| Suite | Latest verified result | Scope |
|---|---|---|
| Previous `phpunit.company.xml` CI baseline on MySQL 8.4 | 102 tests, 459 assertions | Foundation and original ten API readers; later writer/accounting packages have additional proof below |
| Local MySQL 8.4 combined foundation suite before the final supplier-settlement addition | 160 tests, 918 assertions | Foundation/context, shared writers, accounting API/web isolation, capabilities, numbering and invoice concurrency |
| Final targeted shared-writer/accounting/numbering/migration suites on disposable MySQL 8.4 | 55 tests, 462 assertions | Customer and supplier settlements, owned accounting paths, atomic numbers, migration recovery and six-process invoice concurrency |
| Concurrent supplier-settlement test on disposable MySQL 8.4 | 1 test, 9 assertions | Two processes race against one payable; exactly one posts, with no overpayment or failed number reservation |
| Targeted SQLite commercial writer/accounting suites before the final method guard | 33 tests, 374 assertions | Commercial policies, supplier settlements, posting dates, owned reports, mapping/chart guards and web context |
| Final SQLite commercial writer/method-guard suite | 29 tests, 277 assertions; one MySQL concurrency case skipped | Reviewed payment methods and source/account/period rejection |
| `phpunit.company.xml`, merged phases 2/3/4 and shared-writer accounting, disposable official MySQL 8.4.0 | 225 tests, 2,458 assertions, 0 skipped | Foundation/context, capabilities, atomic numbering, stock ledger incl. 4-process last-unit race, MySQL migration chain and ledger-migration recovery after DDL interrupted at eight statement boundaries, ledger-backed ERP services, legacy writer shadow recording, supplier/customer settlement incl. two-process race, owned accounting paths, whole-database wipe lockdown |
| `phpunit.legacy-mysql.xml` on the same disposable MySQL 8.4.0 | 28 tests, 200 assertions | Original 16 seeded smoke tests, 9 legacy web E2E shadow tests (full middleware stack) and 3 audited known-gap tests |
| Headless Chrome accounting and setup checks | Passed | Accounting close disabled at desktop/mobile widths, unsafe modals and foreign rows absent, chart modal/zero openings, mapping policy, no page errors; rendered desktop/mobile form, inputs, labels, CSRF field, keyboard focus, no page errors |

[CI run 37134384890](https://github.com/vigneshsinna/zolo-erp-pro/actions/runs/37134384890) is the previous passing baseline for code commit `523fb93`; it does not certify later changes. Fixture clearing requires explicit disposable database opt-in/name and a fixture-only account.

The original seed export lacks brand IDs 10/16/17 and unit IDs 4/9. The legacy bootstrap proves dry-run rejection, supplies labelled fixture-only parents, then rehearses dry-run/write/dry-run. Retained installations need reviewed master data; fixture placeholders are not production repairs.

Fresh empty-fixture migration time was 3.617 seconds; migration/seed/backfill preparation took 4.878 seconds locally. These are fixture timings, not production lock/downtime estimates.

The initial seeded-suite connection failure is resolved by the disposable MySQL rehearsal. Frontend dependency/build configuration remains unverified; the historical build baseline failed with absent dependencies/configuration. No frontend dependency changes were needed for the delivered backend packages.

## Open Phase 1 work

[Company table ownership matrix](COMPANY_TABLE_OWNERSHIP_MATRIX.md) inventories the original 125 application tables plus six capability/numbering and five stock-ledger tables. Discovery is complete; ownership migration and runtime isolation are not.

Remaining gates:

1. Convert raw legacy sales/purchase/transfer/payment controllers, legacy cash-account/money-transfer paths, remaining operational readers/writers and public/module routes; preserve the reviewed shared-service ownership and posting-date invariants.
2. Complete operational/settings ownership migrations/backfill, including imported schemas. Active references to floors/kitchens/menu_type/services lack creators in this repository.
3. Convert applicable business uniqueness and validators; add reviewed same-company FKs and mandatory ownership after writer/backfill parity.
4. Integrate scheduled writers, remaining module caches/invalidation, imports, exports, downloads and public file storage/access.
5. Rehearse retained representative data at production scale, including locks, concurrency, reconciliation and live transaction browser flows.

Full F-02/F-03/F-04/F-05 acceptance remains open. F-11 local/CI fixture proof is delivered; production-scale retained-data acceptance is pending.

## Phase 4 completion status

Phase 4 is **not complete**. Its exit criterion (execution plan, Phase 4 verification) is that the repository-wide direct-quantity search returns only `InventoryMovementService`; legacy controllers still write quantities directly.

- **Done:** the ledger, generic ERP services and the Phase 4c operational writers use `InventoryMovementService` authoritatively. The four Phase 4b commercial controllers retain direct stock writes with shadow history. See [the Phase 4c cutover package](PHASE_4C_AUTHORITATIVE_CUTOVER.md).
- **Open 1 (4b cutover):** convert each legacy controller method to `InventoryMovementService` and remove it from `LegacyStockShadow::WRITERS`, one method per commit, after clean UAT reconciliation.
- **Open 2 (known gaps):** commercial gaps #5, #6, #7 and #11 of [the shadow audit](LEGACY_STOCK_SHADOW_AUDIT.md) remain assigned to Phase 4b. Phase 4c removes the operational writers responsible for #3, #4, #8, #9, #10, #12 and #13. Historical inconsistencies still require reviewed reconciliation.
- **Delivered implementation (4c):** Adjustment, Transfer, PackingSlip, Production, Exchange, DamageStock, CafeOperations, Product opening/auto-purchase, `purchase:auto` and Warehouse initialization. Retained-data UAT and production activation remain gated.
- **Open 4:** production-scale reconciliation on retained data, and `product_batches.company_id` backfill.
- **Resilience:** the ledger migration is resumable after committed MySQL DDL (see the ownership matrix).

## Phase 4a: stock movement ledger and generic services

Merged into `main` together with the Phase 2/3 package and the shared-writer accounting package. It is not activated in production. The four Phase 4b commercial controllers still mutate quantities directly and record shadow movements. Phase 4c operational writers use applied movements (see below).

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

On the 4a branch alone, the full `phpunit.company.xml` suite gave 123 tests and 603 assertions, with 4 MySQL-only tests skipped locally. After the Phase 2/3 merge (`868ad0b`) it gives 184 tests and 1,046 assertions, with 6 skipped. `StockLedgerConcurrencyTest` has four barrier-synchronized processes compete for the last unit while holding a row lock, and asserts that exactly one succeeds. That test and the MySQL migration chain run only in CI and are not yet verified there.

Merged state with Phase 2/3:

- The three ERP services keep `CompanyWriteGuard` validation and Phase 3 numbering, then post stock with their authorized `CompanyContext` and business date. Movements carry company, branch and FY; the internal movement reference is `SM-{id}`.
- `CompanyWriteGuard::stock` and its direct quantity writes are removed. Its rejection of duplicate or other-company `product_warehouse` rows now lives in the movement service and fails as a stock policy error (HTTP 400 through the API, like a shortage).
- The guard no longer rejects `imei_number`; serial counts and locations are validated by the ledger.
- `ErpServiceRegressionTest` and `ErpServiceStockLedgerTest` share `CompanyErpServiceTestCase` (company A/MAIN fixtures and mapped accounts).

## Phase 4b: remaining commercial shadow recording

The merged shadow package originally covered both commercial and operational writers. Phase 4c now removes its operational writers from the shadow map and converts them to applied movements. Sale, Purchase, Return and ReturnPurchase remain in shadow mode. The original inventory and historical findings are retained in [the legacy stock shadow audit](LEGACY_STOCK_SHADOW_AUDIT.md).

`App\Services\Inventory\LegacyStockShadow` runs each legacy writer unchanged inside one database transaction:

- Eloquent `created`, `updated` and `deleted` events on `product_warehouse` capture quantity and `imei_number` serial changes. Each change is keyed by product, warehouse, variant and batch. Partial models such as `select('id', 'qty')` reload their keys by ID.
- Each change is tagged with its transaction level. A rolled-back transaction or savepoint discards its changes; a committed savepoint hands them to its parent.
- On success the net change becomes one movement with `projection_mode = shadow`. It is a receipt if every change is inbound (valued at `products.cost`, which legacy purchases maintain), an issue if every change is outbound, a transfer when matched quantities leave one warehouse and enter one other, and otherwise a signed adjustment. The source is `legacy:{route name}`, with the first created document of the writer's class or the route's document ID.
- Shadow movements never change projections. Expiry and identity rules only add warnings, so the legacy result stays authoritative. A shadow record that fails is logged and never blocks the legacy write.
- A thrown exception, or a response carrying a rendered exception, rolls back all of the writer's changes. This closes the partial-write gap (D5) for these routes.
- **Behaviour change on errors:** previously, a writer that failed mid-request kept whatever its inner transactions had already committed. For example, `sales.store` without `paid_by_id` returns HTTP 500 but leaves the sale row and its stock decrement. In shadow mode the whole request rolls back.
- A writer that leaves a transaction open loses only the work after the unclosed `BEGIN`, as at disconnect. Everything before it is recorded and committed. `SaleController::store` hits this on every completed non-AJAX sale, because `genInvoice` begins a transaction it never commits.
- Any other response commits, preserving legacy partial-success behaviour.
- `InventoryMovementService` pauses capture while it updates projections itself, so applied postings inside a wrapped request are not counted twice. Reversing a shadow movement does not touch projections.

Current coverage: `LegacyStockShadow::WRITERS` lists 18 methods across Sale, Purchase, Return and ReturnPurchase. `AppServiceProvider` attaches `legacy.stock` centrally, including cached routes. Phase 4c controller methods and `purchase:auto` no longer use shadow recording. A test fails if a remaining listed method is unwrapped.

Related changes:

- Applied serial postings now keep the legacy `product_warehouse.imei_number` list current.
- Phase 4c product updates reject removal of variants with nonzero warehouse quantities or serials; only empty projection metadata may be deleted.
- Phase 4c `AutoPurchase` posts receipts through the movement service and reevaluates stock under product locks.
- `INVENTORY_LEGACY_LEDGER_MODE=off` stops the remaining commercial shadow recording; its ledger then drifts. The flag never disables Phase 4c applied movements.

Current commercial gaps are audit #5, #6, #7 and #11, including missing-row guards and the batch sign in sale deletion. Operational gaps are repaired by Phase 4c code; this does not repair previously retained discrepancies.

Historical shadow-package proof before Phase 4c: `LegacyStockShadowTest` contains 13 tests with 97 assertions. They cover:

- capture of saves, increments, creates and deletes;
- savepoint rollback, thrown and rendered failures, and leaked transactions;
- no double counting of applied postings, signed adjustments, partial models and price-only saves;
- serial identities and the serial projection;
- logged failures, off mode, shadow reversal, middleware attribution and route coverage.

Before Phase 4c, the full local SQLite `phpunit.company.xml` gives 197 tests and 1,143 assertions, with 6 skipped. On disposable official MySQL 8.4.0 at `ffdddde` it gives 197 tests and 1,196 assertions with none skipped, so `StockLedgerConcurrencyTest` and the MySQL migration chain ran.

`LegacyStockShadowWebTest` (in `phpunit.legacy-mysql.xml`, seeded original schema, full middleware stack, after `erp:stock-opening`) runs each request twice: first with recording off (then rolled back), then with shadow on. It asserts the same HTTP status, identical `products`/`product_warehouse` results, exactly one shadow movement of the expected type, source and lines, and unchanged reconciliation differences. Covered: web (via `genInvoice`) and POS sales, sale destroy, purchase, sale return, purchase return, completed transfer, pending transfer completed by `changeStatus`, mixed quantity adjustment, and a purchase whose controller rolls back its own transaction (nothing recorded). The seeded data already disagrees with itself (product 1: `qty` 624.7 against a warehouse row of 10), so the tests compare against the pre-request baseline. Three known-gap tests assert that audited gaps surface as exactly one reconciliation difference each. #5: a POS sale from a warehouse without a stock row records no movement and leaves a product-level difference. #7: a batch sale deletion decrements the batch and leaves a batch-level difference. #3: cafe raw-material consumption changes only the product. The cafe, damage-stock, exchange and production writers are wrapped but unreachable until optional capability activation; the #3 test opens the gate with a labelled fixture-only override. The suite gives 28 tests and 200 assertions at `38c9395`. The MySQL proof found the `genInvoice` leaked-transaction defect, now fixed. These are historical results: Phase 4c updates transfer/adjustment/café assertions to applied movements and removes the café product-only gap assertion. Current proof is recorded below.

Cutover gate, per writer method, in this order:

1. Run `erp:stock-opening` after backfill, with writers paused.
2. Run `erp:stock-reconcile` daily in UAT until the method's documents show zero unexplained differences.
3. In one commit per method, replace the direct writes with `InventoryMovementService` calls and remove the method from `WRITERS`.

The phase is complete when the §0.5 regeneration grep finds only `InventoryMovementService` projection writes.

Security findings from the audit were resolved in `ff9310e`: `/update-coupon` and `CouponController::updateCoupon` were removed. `/setting/empty-database` is POST-only, restricted to active Owner/Admin (roles 1/2), and requires typed `DELETE ALL DATA` confirmation. See [the resolved audit findings](LEGACY_STOCK_SHADOW_AUDIT.md#security-findings-outside-shadow-scope---resolved-in-ff9310e).

## Phase 4c: operational authoritative cutover

The [Phase 4c package](PHASE_4C_AUTHORITATIVE_CUTOVER.md) converts all ten remaining controller/command responsibilities to the movement service. Operational direct stock writes are removed, source documents are locked for edits/deletes, reversals use posted quantities/costs, and missing-row, adjustment-update, production-delete and combo-packing defects are corrected. No production data or migrations were executed.

The owner confirmed their SalePro license permits the fork/rebranding and removal of verification. The unused always-successful verifier is removed. The unsafe remote add-on installer is retired: all four endpoints return 410 without downloads, ZIP extraction or migrations. The separate legacy auto-updater remains assigned to Phase 11 review.

Validation: targeted SQLite inventory, shadow, command-safety and destructive-route tests pass. The cutover suite includes actual controller/command effects, rollback, historical compatibility, UOM, variants, serials, recipe changes, company mismatch and product opening stock. Local targeted proof: 75 tests, 1,315 assertions; PHP syntax and diff whitespace checks pass. [MySQL 8.4 CI at `dfd10c4`](https://github.com/vigneshsinna/zolo-erp-pro/actions/runs/37184497149) passes the full foundation suite (251 tests, 2,609 assertions, none skipped), including the cutover controllers and row-lock concurrency, and the seeded original-schema web/API/accounting suite (28 tests, 204 assertions). The in-app browser verified the rendered café form using labelled fixtures: required warehouse selector, valid selection and no console errors. Full authenticated/UAT browser proof remains gated by optional-capability activation.

Phase 4c implementation is delivered; retained-data reconciliation/UAT and production activation are not signed off. Phase 4b commercial cutover and the repository-wide Phase 4 exit criterion remain open. The shadow flag cannot roll back Phase 4c applied postings.

## UI modernization direction

Docs [31](31_MODERN_UI_DESIGN_SYSTEM_AND_SCREEN_MIGRATION.md), [32](32_OPTECH_SCREEN_TO_ZOLOERP_UI_MAPPING.md) and [33](33_COMMON_UI_COMPONENT_LIBRARY.md) are specifications, not claims that their components/screens exist.

Retain Blade/Bootstrap/jQuery and extend `public/css/zolo-erp-neo.css`. Preserve Optech operator workflows/shortcuts using shared backend services, General Trading terminology, capability/profile extensions, and common components. Migrate UI incrementally with responsive, keyboard, accessibility, state and visual proof. UI planning does not bypass company/engine gates.

## Phase 5: accounting hardening and open items

The [Phase 5 implementation package and setup runbook](PHASE_5_ACCOUNTING_HARDENING.md) refactors the existing accounting facade into one posting service, company semantic mappings, exact journal arithmetic, signed AR/AP open items and immutable allocations/reversals. It adds Voucher Hub and shortcuts, bill allocation, settlement-account links, FY/branch reports and ageing, audited period controls and ledger/cache reconciliation. Existing atomic numbering and company/branch/FY authorization are reused.

Validation on 2026-10-04: final full SQLite company suite **280 tests, 2,473 assertions, 18 MySQL-only skips**; focused MySQL 8.4.11 accounting, accounting web and migration suite **35 tests, 272 assertions, none skipped**; seeded original-schema MySQL smoke **28 tests, 204 assertions**. The broader MySQL accounting/writer/numbering/report run passed its functional tests; a required subtype in the new migration fixture was corrected and separately verified (**1 test, 10 assertions**) before the final 35-test MySQL run. Additional command-safety proof: **4 tests, 17 assertions**. The final suite also proves manual API idempotency across numeric/string account IDs, and the existing manual journal form now supplies a retry key. PHP/JavaScript syntax and whitespace checks pass. Concurrency proves one journal for duplicate requests and no double allocation of a bill.

Authenticated browser proof uses a separate disposable MySQL database: Contra, Payment, Receipt and Journal; F4–F7/Ctrl+Enter; exact balance and bill selection; a 10.0000 bill partially settled by 3.0000 leaving 7.0000; cheque fields and ageing; desktop/mobile layout; no console errors. Screenshots: [Voucher Hub desktop](evidence/phase5/voucher-hub-desktop.png), [mobile](evidence/phase5/voucher-hub-mobile.png), [ageing](evidence/phase5/ageing-desktop.png).

Core Phase 5 implementation is delivered. Production migration, reviewed opening items/history, retained-data control reconciliation and UAT remain unsigned. This does not declare the preceding isolation/commercial cutover gates complete or activate optional routes. Legacy commercial/POS and purchase-payment adapter convergence remains Phase 6; unowned optional journal callers remain gated pending their operational conversion. Dated inventory-close posting remains blocked. The runbook records changed responsibilities, migration/rollback behavior and these limits.

## Delivery order

Canonical phases remain: 0 regression baseline; 1 Company/Branch/FY; 2 capabilities; 3 atomic series; 4 inventory ledger; 5 accounting/open items; 6 shared commercial services; 7 tax; 8 returns/documents; 9 manufacturing/job work; 10 profiles; 11 UI/API/security completion; 12 migration/UAT/deployment.

Every completed work package is validated, committed and pushed before dependent work starts. Package completion does not establish full phase completion. Historical commit-by-commit corrections belong in the audit resolution log.

# Company foundation audit resolution

Source audit: [2026-10-03 company foundation audit](ZOLO_ERP_COMPANY_FOUNDATION_COMMIT_AUDIT_2026-10-03.md). This log records corrections and proof without changing the original audit snapshot.

## Contract normalization package

F-10 and F-14: execution plan and documents 03/26 now use canonical phases 0–12, existing `fiscal_years`, `App\Services\Platform\CompanyContext`, combined FY resolution, session keys `company_id`/`branch_id`/`financial_year_id`, and DEFAULT/MAIN creation inside `erp:backfill-company-context`. Index/README/manifest distinguish specifications, runbooks and execution evidence.

F-13: the execution plan formally adopts Option B. Inactive additive foundations may precede unresolved stabilization defects. Each D1–D12 defect has an owning phase and cutover gate. This does not permit unsafe transaction activation, second-company activation or capability work before full company isolation acceptance.

Validation: canonical phase headings, obsolete-contract search, manifest/file parity and Markdown link checks. No runtime behavior changes in this package.

## Current finding status

| Findings | Current disposition |
|---|---|
| F-01 | Corrected and proven with committed-DDL recovery on local/CI MySQL |
| F-06/F-07/F-08/F-09 | Setup, selection, tuple switching and currency validation/FK corrected with targeted proof |
| F-10/F-13/F-14 | Contract, explicit stabilization disposition and document inventory corrected |
| F-12 | Audited purchase-creation defect corrected under the confirmed supplier-bill policy; subsequent receipt/web-update cutover remains gated |
| F-02/F-03 | Ten real API reader routes isolated; full transaction/legacy/raw/job/cache/file boundary remains open |
| F-04 | Complete source ownership matrix delivered; remaining operational migrations/backfill and imported-schema decisions remain open |
| F-05 | Company-aware business uniqueness, mandatory ownership and same-company FKs remain open until writers/backfill are ready |
| F-11 | Full source chain, seeded backfill, failure recovery and original smoke suites pass on local/CI MySQL; retained-data/production-scale lock and reconciliation proof remains open |

F-02/F-03/F-04/F-05 cannot be closed merely by enabling middleware. The active source also references floors, kitchens, menu_type and services without creators; their supported scope or authoritative imported schemas must be resolved before complete operational ownership cutover.

Second-company activation and dependent capability work remain blocked. New evidence is appended below per delivered package.

## Scheduler safety correction

Source inspection during the ownership audit found `reset:db` scheduled every minute, dropping every database table without a demo guard. Removed that scheduled event. Manual reset now requires the demo environment, an explicit `--confirm-demo-reset` option and a readable nonempty dump before any cache/database work.

Validation: `ConsoleSafetyTest` verifies schedule exclusion, rejection outside demo even with confirmation, and mandatory explicit confirmation in demo. Three tests, 11 assertions pass using the targeted PHPUnit file. This closes the discovered reset activation hazard; other legacy scheduled writers still need company-context integration.

## F-12 purchase-creation correction

The user selected full supplier-bill AP recognition with received inventory and goods-in-transit separation. Received/Partial/Pending quantities now match native status meanings; status 4 Ordered remains an unbilled PO. Required `received_qty` is validated at API and service boundaries. Missing required accounts roll back all effects. Bank payment classification and zero-value goods are covered.

Proof: 14 ERP regression tests, 82 assertions pass on isolated SQLite fixtures. Purchase creation is corrected. Subsequent receipt release, legacy web update parity, company isolation and valuation/tax integration remain phase gates; this is not full commercial cutover.
## F-01/F-09 migration and currency correction

The 32-table migration preflights all existing planned columns and indexes before applying DDL, validates their exact structure, and resumes missing artifacts after a committed MySQL ALTER. Currency defaults must exist during dry-run. Migration 000003 widens the company currency reference to match `currencies.id` and adds a restricting FK after orphan validation; rollback preserves the widened type.

Proof: isolated official MySQL 8.4.0, 74 tests and 249 assertions, including the full source migration chain, repeated migration, injected failure after committed DDL, recovery/rollback without row loss, backfill dry-run/write/repeat, ID widths, orphan rejection, company context and commercial service regressions. GitHub Actions now runs this same suite against MySQL 8.4. Fixture clearing requires an explicit disposable database name and opt-in. No existing XAMPP data or production database was changed. Production-scale migration duration/locking and original seeded browser flows remain outstanding F-11 acceptance checks.
## F-07/F-08 context selection correction

Implicit branch selection uses the sole active authorized branch regardless of its code. Multiple authorized branches require explicit selection. A company header switch discards dependent session branch/FY values from the previous company; explicit headers remain validated. Invalid company timezone is rejected as setup validation.

Proof: 33 context tests, 43 assertions pass on MySQL in the combined suite. They cover non-MAIN selection, ambiguity, company switching, explicit spoofed dependencies, invalid timezone, no-FY rejection and prior authorization/period cases. Real legacy routes and writers remain gated; this does not close F-02/F-03.
## F-06 authorized financial-year setup

Web `/company/financial-years/setup` and API `/api/v1/company-context/financial-years` provide authorized setup before branch/FY context exists. They reuse company membership/active-user authorization, the existing active Admin/Owner role policy, and company role overrides. Creation serializes on the company row and rejects inclusive overlaps; dates remain user-selected and historical years/openings are preserved. Missing current FY returns HTTP 409 with an authorized setup URL, or redirects a web administrator to the setup form. Staff receives an administrator-required response.

Proof: 46 context/setup tests, 86 assertions on SQLite and MySQL. Real HTTP tests cover first-year creation, historical-only installations, other-company access, role overrides, invalid dates, overlaps, body-ID spoofing, authentication, web creation and setup responses. Headless Chrome verifies the rendered form at desktop/mobile sizes, required inputs, labels, CSRF field and keyboard focus with no page errors. Live original zoloERP Pro browser-flow parity remains a separate F-11 gate. This closes the setup-path defect; broad business middleware activation remains dependent on F-02/F-03/F-04/F-05.

## CI evidence

GitHub Actions passed the 74-test MySQL suite for migration commit 339576d and context commit daf96b6: [migration run](https://github.com/vigneshsinna/zolo-erp-pro/actions/runs/37125075054), [context run](https://github.com/vigneshsinna/zolo-erp-pro/actions/runs/37125118388). These establish CI proof for the tested fixtures; they do not establish production-scale locking or complete legacy isolation.
## F-04 complete source ownership inventory

[Ownership matrix](../zolo_erp_implementation_docs/COMPANY_TABLE_OWNERSHIP_MATRIX.md) accounts for 125 application tables: 123 active literal creators plus two configured Spatie creators. It records company/global/mixed/tenant ownership, parent/branch evidence, readers/writers, backfill rules, uniqueness/FK changes and required tests. Missing imported/dormant schemas and known ID-type mismatches are explicit. The manifest/index now include this execution evidence.

This completes the audit's source inventory requirement. Operational ownership migrations, same-company constraints, raw-query/service/job/cache/file integration and actual route isolation remain open F-02/F-03/F-04/F-05 acceptance gates. Do not activate a second company or dependent capabilities.
## FY input/schema parity review

Read-only onboarding review found two setup validation gaps. FY names now match the existing 100-character column; dates below MySQL's supported year 1000 are rejected before insertion. The web form uses the same limits. A targeted regression verifies both limits and zero writes after rejection.
## F-11 original seeded rehearsal

The original AccountingServiceTest, AccountingWebTest, ApiV1Test and UserTest pass on disposable MySQL: 16 tests, 54 assertions. The original tenant/account seeders expose missing brand IDs 10/16/17 and unit IDs 4/9. The repeatable legacy bootstrap first proves that dry-run rejects these orphans, then inserts explicitly labelled fixture-only parent masters and runs dry-run/write/dry-run before the original smoke suite. No production data or original seeder content is changed.

A dedicated `phpunit.legacy-mysql.xml` and second CI step repeat this proof. Fresh empty-fixture migration time was 3.617 seconds; fresh migration, seeders and backfill rehearsal took 4.878 seconds locally. These are fixture timings, not production lock/downtime estimates. Real retained installations need reviewed missing master data and representative scale/locking reconciliation.
## F-02/F-03 bounded API reader isolation

Six actual authenticated GET routes now require authorized company context: product list/search/detail, customer/supplier lists and stock valuation. Explicit model query scopes constrain roots and nested masters. Stock rows must agree with their product/warehouse company and selected branch. Catalog `qty` reflects visible branch stock while the stored legacy product aggregate is preserved. Product detail's missing stock relationship is repaired. Valuation rejects another company/branch warehouse before reading.

Proof: seven real HTTP isolation tests cover guessed product IDs, header spoofing, two-company switching, nested-master corruption, stock parent/row corruption, restricted-branch stock and valuation IDs. The combined MySQL suite passes 95 tests, 354 assertions; seeded legacy parity passes 16 tests, 54 assertions after reviewed fixture preparation.

This proves the bounded reader package. Sales/purchase/journal writers and HTTP details/reports/exports/downloads, legacy web/raw readers, operational migrations, global unique-key conversion, ownership constraints, jobs/caches and public file access remain open. No global model scope or second-company activation is asserted.
## Latest combined CI proof

[Reader-isolation CI run](https://github.com/vigneshsinna/zolo-erp-pro/actions/runs/37126819051) passes for code commit 7fa23eb: 95 foundation/isolation tests with 354 assertions, then 16 original seeded smoke tests with 54 assertions. All audit work packages were pushed independently. User-deleted old architecture files and the untracked archive were preserved. No production migration/backfill or deployment occurred.

## F-02/F-03 sales/purchase read boundary package

Four further authenticated GET routes require trusted company context: sales and purchase lists/details. Root documents require an owned warehouse in the selected branch and owned linked parties, preserving legitimate absent purchase suppliers. Detail lines require owned products; nested product quantities reuse the branch catalog scope. Payments require company ownership, owned linked accounts and visible source documents, including rejection of mixed links to foreign or restricted-branch documents. Associated journal headers/items/accounts are company-scoped. Foreign/unassigned nested rows are excluded and guessed foreign document IDs return 404.

Seven real HTTP regressions cover pagination/filter counts, guessed IDs, branch restrictions, corrupt parents/lines/payments/journals, company selection/switching and closed-year reads. Partial purchase detail preserves ordered 10/received 4 without stock/journal mutations. Original service regression tests retain relationship serialization assertions; real authenticated HTTP serialization is now exercised in the context suite.

Validation: SQLite context suite 61 tests/256 assertions; disposable MySQL context plus ERP service suite 75 tests/335 assertions. No migration or production data mutation is required by this package. Existing API envelopes and filters are retained. This is a read boundary; transaction FY assignment, all writers, legacy/raw/operational routes and final ownership constraints remain gated. Second-company/capability activation remains blocked.

Progress evidence is consolidated into current behavior, including the approved supplier-bill policy. User-added UI specifications 31–33 are indexed in the README/master index/manifest and engineering reference. They retain Blade/Bootstrap/jQuery and the existing theme with shared component contracts; UI implementation remains staged behind its owning gates.

### Transaction-reader CI proof

[CI run 37134384890](https://github.com/vigneshsinna/zolo-erp-pro/actions/runs/37134384890) passes on code commit `523fb93`: the combined company foundation suite has 102 tests/459 assertions, followed by 16 original seeded smoke tests/54 assertions. Current progress/reference evidence is updated to these results. The local disposable MySQL server was stopped after validation; retained fixture files remain isolated under ignored scratch storage.

### Shared commercial writers and double-entry accounting — 2026-10-04

The reviewed shared sales, purchases, customer/supplier payments and transfers now revalidate company/branch/FY membership and posting actor, validate owned parents, stamp ownership from context, and enforce open-period business dates. Company/FY locks protect posting and atomic number allocation. Required stock, payment and journal failures roll back their whole document transaction.

`PaymentService` consolidates customer and supplier settlement. Both API paths reject foreign sources/accounts, overpayment, settlement before the source date, and payments against unbilled purchase orders. Supplier settlements reduce payable without changing inventory.

Accounting API readers/manual posting and double-entry web charts/journals/reports now scope root and nested ownership. Company chart setup and mapping metadata changes require effective Admin/Owner membership; mapping batches are atomic. Existing undated opening balances remain unchanged, new chart accounts start at zero, and mapping metadata does not silently replace reviewed system posting codes/subtypes.

Both previously broken inventory-close POST aliases now return HTTP 409 before any effect, matching the user's approved deferral until the inventory-ledger phase. The view describes only current authorized-branch stock and product cost. It no longer promises weighted-average historical valuation, GL reconciliation or a period lock.

The accounting layout omits global notification, quick-create and report-selection modals. Context resolves before shared middleware; company-owned reads stay fresh instead of using mutable shared caches. Legacy cache names preserve existing invalidation, and sidebar permissions follow company membership role overrides.

Proof: combined disposable MySQL foundation run 160 tests/918 assertions before final supplier settlement; final targeted MySQL shared-writer/accounting/numbering/migration run 55 tests/462 assertions; targeted SQLite run 33 tests/374 assertions. Actual accounting HTML passes headless Chrome checks for disabled close on desktop/mobile, omitted unsafe selectors, zero-opening chart modal, mapping policy and no page errors.

Full F-02/F-03/F-04/F-05 acceptance remains open for raw legacy controllers, operational/imported schemas, jobs, files, remaining caches and final native uniqueness/FKs. Second-company and optional-capability activation are not authorized by this package's tests.
The concurrent supplier-settlement proof passes on disposable MySQL: one test/nine assertions. Two processes race against the same outstanding payable; one settlement posts and the other rolls back without overpayment, duplicate numbers or orphan journals. The original seeded compatibility suite passes again: 16 tests/54 assertions.

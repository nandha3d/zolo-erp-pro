# Implementation progress

This execution follows document 26, using the shared services and staged migrations specified in documents 01–05. The implementation pack supersedes the earlier textile-only engineering reference.

## Phase 0: baseline and transaction regressions

Source baseline: `edecfa1`, branch `main`, 2026-10-03. The working tree was clean before execution. A Codebase Onboarding Engineer performed read-only source inspection.

Observed behavior: API sale, purchase and transfer services write their documents and effects inside database transactions, then eager-load missing model relationships. The resulting exceptions roll back otherwise valid transactions. Purchases also omit the mandatory `product_purchases.recieved` field. The existing `ProductPurchase` class and import are valid.

Implemented behavior:

- `Sale.productSales` and `Product_Sale.product` support transaction results and API detail reads.
- `Purchase.productPurchases` and `Purchase.payments` support transaction results, list and detail reads.
- `Transfer.productTransfers` reuses the existing `products` relationship.
- Received purchase lines persist `recieved = qty`; pending lines persist zero, matching existing web receipt semantics.
- Dedicated in-memory SQLite fixtures exercise real ERP/accounting services, reuse original commercial/accounting migrations, and preserve required receipt constraints. They never access the configured MySQL database.
- Regression checks cover completed sales, received/pending purchases, warehouse transfers, journal balance, API detail/list serialization and rollback after accounting failure.

Baseline validation:

- Initial Artisan commands could not run because `vendor/autoload.php` was absent. Locked Composer dependencies were installed locally, without dependency upgrades or lockfile edits.
- `php artisan about`: Laravel 10.49.1, PHP 8.3.32; application boots.
- `php artisan route:list --except-vendor`: succeeds, 843 routes. Route listing does not prove every handler is callable.
- Original `php artisan test`: 2 passed, 16 failed. Feature tests require a seeded MySQL database; connection is refused. Original tests are not a clean-room migration suite and some write journals without automatic rollback.
- `npm run production`: fails because `cross-env`/`node_modules` are absent. The root Mix configuration and JavaScript lockfile are also absent. No frontend source changed in this phase.
- Before repairs, the new regression tests reproduced missing sale/transfer relations and the required purchase receipt constraint.

Effect review: company scope, capabilities and permission rules remain legacy behavior. Existing quantity projections and journal rules are preserved; the receipt field now records the quantity already added to stock. Tax calculation and document numbering are unchanged. No open-item or audit subsystem is introduced. API responses now load the intended existing records. No migration or historical-data rewrite is needed for these repairs.

Known limits remain: web and API transaction writers differ; generic stock writes ignore batch/variant identities; numbering uses timestamps/counts; missing accounting configuration can skip posting; payment/accounting precision and period controls need later phases. SQLite fixtures do not prove MySQL migration compatibility, concurrent posting safety, full HTTP authorization, or production-data parity.

## Confirmed financial-year policy

The user selected: extend existing `fiscal_years`, preserve existing date ranges, and migrate artificial 1970 opening-balance documents separately. Do not create a parallel `financial_years` authority or automatically convert history to April–March.

## Phase 1, package A: additive schema and legacy backfill

Implemented under documents 03/05:

- Company, branch, membership and user-branch tables. Membership user IDs retain the existing unsigned INT type. Composite foreign keys prevent a membership from selecting another company's branch.
- Nullable indexed company keys on 32 audited core tables when present, and a nullable warehouse branch key. Existing core keys, uniqueness and quantities remain unchanged. Operational/industry tables still need their own audited migrations.
- Existing `fiscal_years` gains company ownership, status and lock/close metadata. Its table name, legacy boolean and date ranges remain available.
- `erp:backfill-company-context --dry-run` reports counts and reference problems without creating even the default company. The real command initializes DEFAULT from safe general settings, creates MAIN, maps warehouses/users and assigns legacy core rows in batches of 500 inside one transaction. Existing legal names and role overrides are preserved. Closed legacy FYs become `closed`; their dates never change.
- Real backfill requires maintenance mode; workers and the scheduler must also be paused. Unexpected company ownership, invalid branches, orphan references and overlapping/inverted FYs stop assignment. Failures roll back company creation and legacy assignments together. Source-confirmed zero sentinels for digital/service UOMs and opening-stock payment accounts are preserved. Default metadata uses the latest settings, and dry-run validates its timezone before reporting success.
- Artificial 1970 opening records are reported and receive company ownership only. Their dates and balances remain unchanged; their later financial-year/open-item migration remains separate.

Validation: 16 isolated integration tests, 83 assertions, cover dry-run, latest settings import, timezone validation, repeatability, legacy sentinels, orphan rejection, multi-company rejection, maintenance mode, FY overlap, branch isolation, migration rollback, interrupted backfill rollback, multiple batches and opening-date preservation. Phase 0 adds another 7 tests and 50 assertions. These are SQLite proofs; the MySQL migration rehearsal and original seeded feature suite remain unverified because no test MySQL connection is available.

Effects: company/branch ownership metadata is added; stock quantities, journal values, tax, document references and payment balances are not rewritten. No capability, permission or request-scoping activation occurs in this package. No production database migration/backfill was executed. Package A is complete; phase 1 as a whole remains pending.

Next gate: rehearse on disposable MySQL with representative legacy data; fix reported ownership/schema problems; integrate context resolution and convert both Eloquent and raw-query readers/writers, jobs and cache keys; then verify isolation and company-aware uniqueness. Do not start dependent capability/ledger cutover or activate a second company before those gates pass.

## Phase 1, package B: company/FY context primitives

Implemented under documents 03/22:

- Immutable `CompanyContext` holds company, branch and financial-year IDs.
- `CompanyContextResolver` verifies active/non-deleted user identity, membership, active company and explicit user-branch assignments. Default selection requires exactly one default company and an authorized MAIN branch. Financial years are selected only inside the company; implicit selection requires exactly one matching existing date range, using the company's timezone.
- Historical reads may select closed years. Explicit `assertPostingDate` checks the actual FY record, validates ISO dates, rejects dates outside the year, and blocks legacy closed, soft-closed and locked periods. Lock dates are inclusive. Closing Company A does not lock Company B.
- `company.context` middleware validates integer context headers or session IDs, rejects inactive/deleted users and spoofed company/branch/FY selection, exposes the immutable context through request attributes and clears it after success or failure. Body `company_id` is not trusted as context.

Validation: 27 isolated tests, 34 assertions, exercise company/branch/FY spoofing, branch restrictions, default ambiguity, company closure, period locks, malformed dates, overlapping FY selection, header/session validation and precedence, timezone rollover, deleted identities and request cleanup. These are resolver/middleware tests, not evidence that existing sale/journal routes are isolated.

Effects: middleware is registered but not attached to legacy routes; transaction services do not yet enforce the new context or period assertion. Existing quantities, journals, tax, numbering, history and user flows remain unchanged. Controllers/services must consume the context only after membership middleware runs. Permissions, capabilities, company scopes, raw queries, job context and concurrency protection still require integration before activation.

Total targeted proof so far: 50 tests, 167 assertions across ERP regressions and company packages A/B. Full phase 1 and all dependent phases remain pending. The requested disposable MySQL rehearsal connection is still needed; the original feature suite and production-schema/data cutover remain unverified.

## Delivery order

1. Phase 0: baseline, writer inventory and passing transaction regressions.
2. Phase 1: additive company/branch/membership schema; extend `fiscal_years`; dry-run backfill; validate ownership and uniqueness before enabling isolation.
3. Phase 2: capabilities and profiles with legacy module compatibility.
4. Phase 3: atomic document series.
5. Phase 4: inventory movement ledger and reconciliation.
6. Phase 5: accounting hardening and open items.
7. Phase 6: converge sales/purchase application services and web/API writers.
8. Phases 7–12: tax, returns/documents, operations, industry profiles, UI/API/security, migration/UAT/deployment.

Each completed work package is validated, committed and pushed before dependent work starts. Full phase completion requires its documented acceptance criteria; an additive schema package alone does not establish company isolation.

## Audit correction: purchase receipt and supplier-bill recognition

Audit F-12 identified status 2 as Partial, not Pending. `PurchaseService` now validates per-line `received_qty` for Partial, records only actual receipts in stock and `product_purchases.recieved`, and uses status 3 for Pending. Received records full quantity. Ordered is an unbilled PO with no stock/journal/payment effect at creation.

Confirmed policy: the supplier bill recognizes full AP/payment, with received value in inventory and unreceived value in an active `goods_in_transit` asset account. Missing required mappings roll back purchase, stock, cost and payment. Bill charges/discounts are apportioned by line value; free lines use quantity. Free goods avoid zero-value journals. Initial bank payments credit bank rather than cash.

Validation: 14 isolated ERP regression tests, 82 assertions pass. New proof covers 4-of-10 receipts, billed inventory/transit/AP split, pending transit, missing-account rollback, invalid receipt quantities, API validation, unbilled orders, free goods and bank payment classification.

This closes the audited purchase-creation regression. Subsequent receipt release, legacy web update parity, service-item posting, company scope, posting-cost valuation, tax semantics and idempotency still belong to their gated shared-engine phases. No historical purchase or journal was rewritten.
## Audit correction: migration recovery and MySQL proof

F-01/F-09: resumable core company-key DDL validates existing artifacts before mutation. Company currency defaults are checked during dry-run; migration 000003 aligns the reference to BIGINT and adds a validated FK. MySQL 8.4.0 proof passes 74 tests, 249 assertions, including the full source chain, committed-DDL fault recovery, backfill and receipt/accounting regressions. A dedicated GitHub Actions workflow repeats the suite. This supersedes the earlier lack of a disposable MySQL connection. Production-scale locking/duration, representative retained production data and original seeded UI smoke flows remain unverified. No production migration was executed.
## Audit correction: coherent context selection

F-07/F-08: the sole authorized active branch is now the implicit branch, including non-MAIN branches. Company-header changes discard stale dependent session branch/FY IDs; explicit IDs remain authorized. Invalid company timezone fails setup validation. Context proof is now 33 tests, 43 assertions on MySQL. No-FY resolver failure is covered; the authorized setup path is the next correction. Legacy business route activation remains blocked on full isolation.

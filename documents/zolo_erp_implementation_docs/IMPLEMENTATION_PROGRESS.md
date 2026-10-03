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

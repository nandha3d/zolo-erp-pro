# 01 - Repository Audit and Gap Analysis

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP Pro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Freeze the facts about the current codebase before changing it. The repository is already a modified zoloERP Pro system with generic ERP services and multiple vertical experiments; the implementation should reuse working assets and remove duplication.

## Current repository observations

- Laravel 10 / PHP 8.2 with `nwidart/laravel-modules`, Sanctum and Spatie permissions.
- Real generic `SaleService`, `PurchaseService`, `InventoryService` and `AccountingService` already exist.
- Double-entry tables and `semantic_account_mappings` already exist.
- `Modules/Manufacturing` is substantive; seven `Optech*` modules are mostly generated scaffolds with no real module migrations.
- Recent migrations directly add water logistics, cafe/bakery, repair, projects, damage stock and exchange features.
- Core Product, Customer, Supplier, Warehouse, Sale, Purchase and accounting records do not consistently expose a legal `company_id`.
- `general_settings.modules` is a comma-separated feature switch used by the sidebar and API status.
- CustomFieldController performs runtime `ALTER TABLE` on business tables.
- Multiple services/controllers directly increment/decrement product and warehouse quantities.
- Accounting posting uses hard-coded account codes in places and sequence generation based on count/time patterns.

## Target rules

- Preserve existing useful zoloERP Pro flows; wrap/refactor rather than rewrite everything.
- Do not complete every Optech scaffold as an independent subsystem.
- Treat existing industry tables as optional pack prototypes, not the shared data model.
- Stop adding new runtime schema mutations for customer-defined fields.
- Introduce an auditable stock movement source before expanding inventory complexity.

## Implementation sequence

1. Record baseline `php artisan test`, routes and build status before changes.
2. Search all writers to sales, purchases, product quantities, payments and journals.
3. Create tests around current generic service results.
4. Build company/capability foundation.
5. Refactor high-risk direct stock/accounting writers incrementally with reconciliation.

## Acceptance and verification

- Baseline failures are documented separately from new failures.
- No Optech scaffold is used as a second core ledger/master/transaction system.
- Every direct stock mutation is inventoried before stock-ledger cutover.

## Repository paths to inspect first

```text
README.md
composer.json
routes/api.php
resources/views/backend/layout/sidebar.blade.php
app/Services/ERP/SaleService.php
app/Services/ERP/PurchaseService.php
app/Services/ERP/InventoryService.php
app/Services/Accounting/AccountingService.php
Modules/Manufacturing/
Modules/Optech*/
database/migrations/2026_09_19_*.php
```

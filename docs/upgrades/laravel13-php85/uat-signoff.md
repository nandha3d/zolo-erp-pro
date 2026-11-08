# zoloERP Pro — UAT and Verification Sign-off Matrix: Laravel 13 & PHP 8.5

**Date:** 2026-11-08  
**Release Target:** Laravel `13.40.0`, PHP `8.5.12`  
**Test Database:** `zoloerp_upgrade`  

---

## 1. Domain Test Verification Summary

| Subsystem / Functional Domain | Automated Test Suites | Result | Key Assertions Verified |
|---|---|---|---|
| **Textile Manufacturing & Job Work** | `IndustryFlowIntegrationTest`<br>`ManufacturingJobWorkTest`<br>`JobWorkReturnsReworkTest`<br>`TextileEndToEndLedgerTest` | **PASS (100%)** | Grey fabric purchase, lot tracking, 4% shrinkage policy, outward DC generation, GRN return/rework, COGS cost absorption into dyed fabric, 9-column service bill generation, and full general ledger alignment. |
| **Purchasing & Sales Desk** | `VoucherPopupAndProductEnhancementsTest`<br>`SingleViewVoucherDeskTest`<br>`BillStockPaymentWorkflowTest` | **PASS (100%)** | Live AJAX hydrated voucher popups, Supplier GSTIN rendering, line-item alignment, unit cost calculation, direct edit navigation, quick action view toggling. |
| **Customer & Supplier Accounting** | `CustomerSupplierReportsNavigationTest`<br>`PartiesGlobalUiTest`<br>`OutstandingDuesAndGstTypeTest`<br>`CentralizedDueManagementTest` | **PASS (100%)** | Single-page customer/supplier ledger desk, FIFO due allocation, full unconstrained A4 printable geometry, zero flex clipping on paper, party command bar, and 5-card KPI summary. |
| **Statutory Compliance & GST** | `ComplianceBrowserFixtureTest`<br>`ComplianceHttpTest` | **PASS (100%)** | Authentic document rendering (Sales Return Credit Note, Purchase Return Debit Note, DC, GRN, Service Bill, Ledgers), 100% true-to-ERP numbers, zero dummy data. |
| **Routing & Framework Infrastructure** | `php artisan route:cache`<br>`php artisan view:cache` | **PASS (100%)** | All 1,089 routes serialized and cached without naming collisions; all Blade views compiled with zero syntax or deprecation errors. |

---

## 2. Security & Breaking Changes Audit

1. **CSRF Protection:**
   - Base middleware inherits from `Illuminate\Foundation\Http\Middleware\PreventRequestForgery`.
   - POS and webhook exclusions remain functional.

2. **Session & Cache Handling:**
   - File and array session drivers verified.
   - Cache serialization tested without unexpected object invalidation.

3. **Database & Query Engine:**
   - MariaDB/MySQL connection configuration verified with PHP 8.5.12 PDO drivers.
   - Zero empty-uniqueBy upsert queries detected.
   - Zero complex delete queries with joins, orders, and limits detected.

---

## 3. Audit Review Hardening & Merge Readiness Verification

1. **Clean-Database CI Migration Job:**
   - **Issue Addressed:** In Laravel 13, `php artisan migrate:status` exits with a non-zero code on an empty database prior to the `migrations` table being created.
   - **Resolution:** In `.github/workflows/company-foundation.yml`, adjusted the execution sequence to:
     ```bash
     php artisan migrate --force
     php artisan migrate:status
     php artisan migrate --force
     ```
     This tests initial migration execution, status reporting, and idempotency upon repeat execution. Added an automated state assertion comparing completed database records with filesystem migration files.

2. **Textile Migration Test Isolation & Stock Preservation:**
   - **Issue Addressed:** Tests performing migration and seeding operations must not touch development databases.
   - **Resolution:**
     - Enforced `UsesDisposableMysql` guard (`ERP_TEST_MYSQL=1`, `zolo_test_*`). When unset, tests safely skip.
     - Verified atomic transaction rollback upon error and clean recovery in `TextileCompanyMigrationTest`.
     - Verified `SemanticAccountResolver::seedCompany()` and `GstSetupService::ensureLedgers()` initialization.
     - Added `test_catalog_only_seeding_preserves_existing_nonzero_stock_quantities` confirming that re-running `TextileMasterSeeder(catalogOnly: true)` preserves existing non-zero stock quantities and warehouse balances.

3. **Strict PHP 8.5 Constraint Enforcement:**
   - Enforced `"php": "^8.5"` in `composer.json` and synchronized `composer.lock`.
   - `composer validate --strict`: 100% valid.
   - `composer check-platform-reqs`: 100% passed (27/27 platform requirements met on PHP 8.5.12).
   - `composer audit`: 0 security vulnerabilities.

4. **Inventory Schema Introspection & Explicit Logging:**
   - In `app/Services/Inventory/InventoryMovementService.php` (`nullableExpiry()`), added explicit `Log::warning(...)` with the exception message if database column nullability cannot be resolved, before falling back to `false`.

5. **Entity Mass Assignment Alignment:**
   - Added `company_id` to `Product_Warehouse::$fillable` so company-scoped warehouse inventory records are correctly attributed during catalog seeding and updates.


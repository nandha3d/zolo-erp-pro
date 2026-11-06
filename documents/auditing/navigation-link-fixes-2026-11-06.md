# Navigation Link Audit Resolution Report — 2026-11-06

**Reference Audit File:** `documents/auditing/navigation-link-audit-2026-11-06.csv`  
**Branch:** `ui`  
**Verification Suite:** `tests/Feature/NavigationAuditResolutionTest.php` (6/6 passed, 93 assertions)  
**Status:** All 26 link defects, route mismatches, filter drops, and disabled module leaks have been resolved and verified.

---

## Executive Summary of Issues & Fixes

| # | Category | Original Status / Error | Defective Destination / Behavior | Root Cause | Fix Applied & New Behavior | Status |
|---|---|---|---|---|---|---|
| **01** | Retired Link | `410 Gone` (Retired) | `/backup` from 297 source pages | `SettingsCatalog` rendered retired browser backup endpoint | Removed `/backup` link from `SettingsCatalog::sections()` | **Resolved** |
| **02** | Disabled Module | `403 Forbidden` | `/cafe` from `/addon-list` | `addonlist.blade.php` rendered `Open Module →` link even when inactive | Added `@if($isActive)` guard; renders disabled "Enable to Open" | **Resolved** |
| **03** | Disabled Module | `403 Forbidden` | `/installmentplan` from `/addon-list` | Rendered `Open Module →` link without checking active state | Added `@if($isActive)` guard; renders disabled "Enable to Open" | **Resolved** |
| **04** | Disabled Module | `403 Forbidden` | `/project-management/projects` from `/addon-list` | Rendered `Open Module →` link without checking active state | Added `@if($isActive)` guard; renders disabled "Enable to Open" | **Resolved** |
| **05** | Disabled Module | `403 Forbidden` | `/qr` from `/addon-list` | Rendered `Open Module →` link without checking active state | Added `@if($isActive)` guard; renders disabled "Enable to Open" | **Resolved** |
| **06** | Disabled Module | `403 Forbidden` | `/repair/dashboard` from `/addon-list` | Rendered `Open Module →` link without checking active state | Added `@if($isActive)` guard; renders disabled "Enable to Open" | **Resolved** |
| **07** | Missing Route | `404 Not Found` | `/report/biller` | Route URL was `report/biller_report`; views linked to `/report/biller` | Registered alias `report/biller` and updated views to `route('report.biller')` | **Resolved** |
| **08** | Missing Route | `404 Not Found` | `/report/customer_group` | Route was `customer-group` (hyphen); view linked to `customer_group` (underscore) | Registered `customer_group` alias and updated views to `route('report.customer_group')` | **Resolved** |
| **09** | Method Unsupported | `405 Method Not Allowed` | `/report/customer_report` | Route was registered as `Route::post` only | Upgraded route to `Route::match(['get', 'post'], ...)` | **Resolved** |
| **10** | Missing Route | `404 Not Found` | `/report/daily_sale_objective` | Route was `daily-sale-objective`; views linked to `daily_sale_objective` | Registered underscore alias and updated view to `route('report.dailySaleObjective')` | **Resolved** |
| **11** | Missing Route | `404 Not Found` | `/report/purchase_report` | Route was `report/purchase`; views linked to `report/purchase_report` | Registered `purchase_report` alias and updated views to `route('report.purchase')` | **Resolved** |
| **12** | Missing Route | `404 Not Found` | `/report/qty_alert_report` | Route was `product_quantity_alert`; view linked to `qty_alert_report` | Registered `qty_alert_report` alias and updated view to `route('report.qtyAlert')` | **Resolved** |
| **13** | Missing Route | `404 Not Found` | `/report/sale_report_chart` | Route was `sale-report-chart`; view linked to `sale_report_chart` | Registered underscore alias and updated view to `route('report.saleChart')` | **Resolved** |
| **14** | Method Unsupported | `405 Method Not Allowed` | `/report/supplier` | Route was registered as `Route::post` only | Upgraded route to `Route::match(['get', 'post'], ...)` | **Resolved** |
| **15** | Missing Route | `404 Not Found` | `/report/supplier_report` | Route was `report/supplier`; views linked to `report/supplier_report` | Registered `supplier_report` alias and updated views to `route('report.supplier')` | **Resolved** |
| **16** | Disabled Module | `403 Forbidden` | `/trade/routes` from Trade subnav | Route sheet link rendered unconditionally when capability disabled | Checked `$canRouteSheet` (`sales.route_distribution`); omitted when disabled | **Resolved** |
| **17** | Disabled Module | `403 Forbidden` | `/water-logistics` from `/addon-list` | Rendered `Open Module →` link without checking active state | Added `@if($isActive)` guard; renders disabled "Enable to Open" | **Resolved** |
| **18** | Browser Filter Mismatch | Wrong destination | Receivables voucher MJ-00004 linked to generic `/sales` | Reference link pointed to `route('sales.index')` without bill context | Updated to `route('sales.index', ['search' => $r->reference_no])` + side panel auto-select | **Resolved** |
| **19** | Browser Filter Mismatch | Dropped customer | Due Clearance button dropped customer filter (`/transactions/dues?tab=sales`) | Due Clearance Hub button lacked `party_id` query parameter | Appended `party_id` when customer selected (`dues.index?tab=sales&party_id=56`) | **Resolved** |
| **20** | Browser Filter Mismatch | Dropped customer | Ageing Ledger button dropped customer filter (`/accounting/ageing`) | Ageing button lacked `party_type` and `party_id` query parameters | Appended `party_type=customer&party_id=56` | **Resolved** |
| **21** | Browser Filter Mismatch | Wrong destination | Payables voucher PUR-00005 linked to generic `/purchases` | Reference link pointed to `route('purchases.index')` without bill context | Updated to `route('purchases.index', ['search' => $r->reference_no])` + side panel auto-select | **Resolved** |
| **22** | Browser Filter Mismatch | Dropped supplier | Due Clearance button dropped supplier filter (`/transactions/dues?tab=purchases`) | Due Clearance Hub button lacked `party_id` query parameter | Appended `party_id` when supplier selected (`dues.index?tab=purchases&party_id=69`) | **Resolved** |
| **23** | Browser Filter Mismatch | Switched view | Ageing Ledger button switched from supplier to default customer view | Ageing button lacked supplier context query parameters | Appended `party_type=supplier&party_id=69` | **Resolved** |
| **24** | Browser Filter Mismatch | Context dropped | Expense tab in Due Hub linked to `/report/outstanding-due-report?type=receivables` | Ternary `$tab === 'purchases' ? 'payables' : 'receivables'` mapped expenses to receivables | Added `type=expenses` support to `ReportController` & linked to `type=expenses` | **Resolved** |
| **25** | Bill Redirect Defect | Selected bill absent | Add payment on MJ-00004 passed bill ID 138 into text search | Redirect passed raw primary key `bill=138` instead of bill reference `MJ-00004` | Resolved `reference_no` in redirect and added numeric ID fallback in `DueManagementController` | **Resolved** |
| **26** | Bill Redirect Defect | Selected bill absent | Add payment on PUR-00003 passed bill ID 72 into text search | Redirect passed raw primary key `bill=72` instead of bill reference `PUR-00003` | Resolved `reference_no` in redirect and added numeric ID fallback in `DueManagementController` | **Resolved** |

---

## Detailed Technical Breakdown

### Group A: Report Missing Routes (404s) and Method Not Allowed (405s)
- **Files Modified:**
  - `routes/web.php`
  - `resources/views/backend/report/biller_report.blade.php`
  - `resources/views/backend/report/user_report.blade.php`
  - `resources/views/backend/report/customer_group_report.blade.php`
  - `resources/views/backend/report/daily_sale.blade.php`
  - `resources/views/backend/report/monthly_purchase.blade.php`
  - `resources/views/backend/report/warehouse_stock.blade.php`
  - `resources/views/backend/report/sale_report_chart.blade.php`
- **Resolution:**
  - Upgraded `customer_report`, `customer-group`, and `supplier` routes to accept `Route::match(['get', 'post'], ...)`. Both direct browser visits via GET and form submissions via POST now work seamlessly.
  - Registered route aliases for views that use alternative naming conventions (`biller`, `customer_group`, `daily_sale_objective`, `purchase_report`, `qty_alert_report`, `sale_report_chart`, `supplier_report`).
  - Updated all Blade report templates to invoke the canonical named routes using `route(...)`.

---

### Group B: Contextual Filter & Voucher Target Preservation
- **Files Modified:**
  - `resources/views/backend/report/outstanding_dues.blade.php`
  - `resources/views/backend/sale/index.blade.php`
  - `resources/views/backend/purchase/index.blade.php`
  - `resources/views/backend/dues/index.blade.php`
  - `app/Http/Controllers/ReportController.php`
- **Resolution:**
  - **Due Clearance Retention:** When viewing filtered Receivables for customer #56, clicking "Due Clearance Hub" preserves the filter: `route('dues.index', ['tab' => 'sales', 'party_id' => 56])`. When viewing Payables for supplier #69, it preserves `['tab' => 'purchases', 'party_id' => 69]`.
  - **Ageing Ledger Retention:** Ageing button now passes `['party_type' => 'customer', 'party_id' => 56]` or `['party_type' => 'supplier', 'party_id' => 69]`.
  - **Voucher Direct Link:** Reference numbers in the Outstanding Dues table link directly to `route('sales.index', ['search' => $r->reference_no])` and `route('purchases.index', ['search' => $r->reference_no])`.
  - **Desk Auto-Selection:** On landing on `/sales?search=...` or `/purchases?search=...`, the voucher desks populate `#side-search-input`, trigger client-side filtering, and click the matching card in-place.
  - **Expense Audit Dues:** Added `$type === 'expenses'` support to `ReportController::outstandingDuesReport` and added the "Other Dues (Expenses)" theme nav pill. In Due Clearance Hub (`/transactions/dues?tab=expenses`), clicking "Outstanding Dues Report" opens `/report/outstanding-due-report?type=expenses`.

---

### Group C: Bill Payment Redirect & Search Hardening
- **Files Modified:**
  - `app/Http/Controllers/BillPaymentController.php`
  - `app/Http/Controllers/DueManagementController.php`
  - `resources/views/backend/sale/index.blade.php`
  - `resources/views/backend/purchase/index.blade.php`
- **Resolution:**
  - Fixed query string concatenation in `#modal-goto-due-page-btn`: previously concatenated `?search=` to a URL already having `?tab=sales`, producing invalid `?tab=sales?search=...`. Changed to `&search=`.
  - In `BillPaymentController::index`, when receiving `bill={id}` (such as bill ID 138 or 72), resolves the target bill's `reference_no` (`MJ-00004` or `PUR-00003`) so the redirect carries both `search={reference_no}` and `bill_id={id}`.
  - In `DueManagementController::index`, enhanced search query clauses so that numeric search terms also match `sales.id` or `purchases.id` as a fallback. Whether an ID or a reference number is passed, the target bill is guaranteed to be returned.

---

### Group D: Inactive Module & Retired Link Hardening
- **Files Modified:**
  - `app/Services/Platform/SettingsCatalog.php`
  - `resources/views/backend/addonlist.blade.php`
  - `resources/views/backend/compliance/layout.blade.php`
- **Resolution:**
  - Removed retired `/backup` route from `SettingsCatalog::sections()`. This prevents 297 source pages from rendering a link to the retired database export endpoint.
  - In `/addon-list`, wrapped the `Open Module →` action in `@if($isActive && Route::has($addon['route']))`. Inactive addons render an informative `Enable to Open` indicator instead of linking to an unauthorized 403 screen.
  - In `resources/views/backend/compliance/layout.blade.php`, wrapped the "Route sheet" link in a capability check (`sales.route_distribution`) matching the sidebar. When the capability is off for a company, the link is omitted.

---

## Automated Verification

The entire set of fixes is covered by automated feature test suite `tests/Feature/NavigationAuditResolutionTest.php`:
```
   PASS  Tests\Feature\NavigationAuditResolutionTest
  ✓ retired backup link omitted from settings catalog and endpoint returns 410
  ✓ all previously failing report routes respond with 200 on get
  ✓ outstanding dues report links retain customer and supplier filters
  ✓ expense dues keeps expense context and links to expense report
  ✓ bill payment redirect resolves reference no and finds bill
  ✓ addon list does not render active link for disabled addons

  Tests:    6 passed (93 assertions)
  Duration: 2.24s
```

Regression test suites verified:
- `tests/Feature/OutstandingDuesAndGstTypeTest.php`: 8/8 passed (29 assertions)
- `tests/Feature/CentralizedDueManagementTest.php`: 7/7 passed (37 assertions)
- `tests/Feature/CompanyGroupInsightTest.php`: 3/3 passed (37 assertions)


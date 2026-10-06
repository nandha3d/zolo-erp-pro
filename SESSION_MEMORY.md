# zoloERP Pro — Persistent Session Memory & State

> **CRITICAL AGENT INSTRUCTION (Crash Recovery & Session Persistence)**:
> This document is the single persistent source of truth for the active development session.
> If the IDE, machine, or assistant crashes or resets, **immediately read this file** to restore full working context into memory.
> Keep this file updated after every milestone, commit, or architectural decision.

---

## 1. Active Session Metadata
- **Last Updated:** 2026-10-06 15:45:00 (+05:30)
- **Active Git Branch:** enhanced-ui
- **Upstream Remote:** nandha-origin/enhanced-ui (Synced)
- **Latest Commit:** in progress — "feat(ui): eliminate double boxes across dropdowns, selectpickers, and pagination"
- **Working Tree State:** Single Compact Box UI Normalization (Sales & Purchase)
- **Test Suite Status:** 21/21 tests passing (OptechMasterWebTest, OptechVoucherWebTest, AccountingWebTest — 103 assertions)

---

## 2. Last Session Summary & Recovered Context

### A. Root Cause Resolution for 403 Forbidden Errors
- **Problem:** User reported that most web pages were returning 403 Forbidden after navigation.
- **Root Cause:** In app/Providers/AppServiceProvider.php, custom Blade @can and @cannot directives were improperly registered or overriding the native authorization handler when checking permissions for roles.
- **Fix:** Corrected @can / @cannot gate handling in app/Providers/AppServiceProvider.php and verified role/permission evaluation across menus, sidebars, and authenticated screens.

---

### B. Optech Modern Master Management (Zero Hardcoding Enforced)
Implemented backend tables, models, controllers, and inline creation ([+] / Alt+C) modal endpoints for all Optech screen masters:
1. **Agents / Brokers (agents):**
   - Model: app/Models/Agent.php
   - Controller: app/Http/Controllers/AgentController.php
   - View: resources/views/backend/master/agent.blade.php
2. **Areas / Regions (areas):**
   - Model: app/Models/Area.php
   - Controller: app/Http/Controllers/AreaController.php
   - View: resources/views/backend/master/area.blade.php
3. **Bill Sundries (bill_sundries):**
   - Model: app/Models/BillSundry.php
   - Controller: app/Http/Controllers/BillSundryController.php
   - View: resources/views/backend/master/bill_sundry.blade.php
4. **Sale Types (sale_types):**
   - Model: app/Models/SaleType.php
   - Controller: app/Http/Controllers/SaleTypeController.php
   - View: resources/views/backend/master/sale_type.blade.php
5. **Purchase Types (purchase_types):**
   - Model: app/Models/PurchaseType.php
   - Controller: app/Http/Controllers/PurchaseTypeController.php
   - View: resources/views/backend/master/purchase_type.blade.php
6. **Standard Remarks (standard_remarks):**
   - Model: app/Models/StandardRemark.php
   - Controller: app/Http/Controllers/StandardRemarkController.php
   - View: resources/views/backend/master/standard_remark.blade.php
7. **Document Series (document_series):**
   - Model: app/Models/DocumentSeries.php
   - Controller: app/Http/Controllers/DocumentSeriesController.php
   - View: resources/views/backend/master/series.blade.php
8. **Delivery Challans & Goods Received Notes:**
   - Models: app/Models/DeliveryChallan.php, app/Models/GoodsReceivedNote.php
   - Migration: database/migrations/2026_10_13_000004_create_optech_dc_and_grn_tables.php

---

### C. Commercial Billing Entry Modernization
- **Files Modified:**
  - public/js/commercial-entry.js
  - resources/views/backend/commercial/entry.blade.php
  - app/Http/Controllers/CommercialController.php
- **Key Enhancements:**
  - Added Series selection and dynamic document numbering.
  - Added Sale Type and Agent dropdowns with inline [+] creation.
  - Added Transport Details popup (Bale No, No of Bales, LR No, LR Date, Transporter, Station To, Order No).
  - Added Rate History modal (Alt+UpArrow) querying historical party item purchase cost and selling rates.
  - Added Bill Sundries grid for taxes, discounts, and custom freight adjustments.
  - Added keyboard navigation shortcuts (F2, F12, F6, Alt+C, Alt+Y, Ctrl+S, Ctrl+B, Ctrl+Enter).

---

### D. Keyboard-Driven Multi-Voucher Entry
- **File:** resources/views/backend/accounting/voucher_entry.blade.php
- **Controller:** app/Http/Controllers/Accounting/JournalEntryController.php
- **Features:**
  - Tabbed support for Payment, Receipt, Journal, Contra, Sales Voucher, Purchase Voucher, Debit Note, and Credit Note.
  - Live Debit vs Credit balancing status.
  - Real-time current balance indicator for selected ledger accounts.
  - Bill-by-bill allocation dialog for outstanding invoice settlement.
  - Inline account creation modal ([+] / Alt+C).

---

### E. Modern Desk Workspace Billing UI (Sales & Purchase)
- **Files Modified:**
  - `resources/views/backend/commercial/entry.blade.php`
  - `public/css/commercial-entry.css`
  - `public/js/commercial-entry.js`
  - `app/Http/Controllers/CommercialController.php`
  - `resources/views/backend/sale/index.blade.php`
  - `resources/views/backend/purchase/index.blade.php`
  - `tests/Feature/OptechMasterWebTest.php`
- **Features & Visual Alignment:**
  - **Left Desk Sidebar:** Collapsible dark sidebar (`#111827`), Selling and Buying modules with purple active pill badges, fast navigation across Desk modules.
  - **Top Desk Navigation Bar:** Breadcrumb badges (Selling / Buying), global search with ⌘K badge, company badge with pulsing status dot (`● Sri Murugan Textiles` / active entity), user profile pill.
  - **Multi-Tab Document Strip:** Tabs for `Bills • Browse list`, `Draft 1`, and `+ New bill`.
  - **Toggleable & Dockable Side Panel ("Bill list" / "Purchase list"):**
    - Toggleable via button (`[ 📖 Bill list ]` / `[ 📖 Purchase list ]`), top tab, or `✕` close.
    - Dockable on **EITHER side** (arrangeable on LEFT or RIGHT via `⇄ Dock Right` / `⇄ Dock Left` button).
    - Preference persisted across sessions via `localStorage` (`zolo_panel_dock` and `zolo_panel_open`).
    - Search input, bill number filter, tabs (`All`, `Draft`, `Date`, `Range`), bill card list with load-to-form capability.
  - **Header Controls & Segmented Pills:**
    - Cash / Credit pill toggle (Credit selected in bright blue/purple).
    - Product / Service / Mixed line nature pill toggle.
    - Bill number with live Series preview, party search with address loader note, tax classification (Sale Type / Purchase Type) with GST badge.
  - **12-Column Items Grid Table with Vibrant Purple Header (`#7c3aed`):**
    - `S.NO`, `ITEM`, `SALES TYPE`, `UNIT`, `QTY`, `RATE + TAX`, `RATE`, `TAXABLE AMOUNT`, `GST / IGST %`, `TAX AMOUNT`, `LINE TOTAL`, `ACTIONS`.
    - Density selector pills (`Compact`, `Cozy`, `Large`).
    - Clean empty-state placeholder rows matching the reference ERP screen.
    - Live calculation of taxable amounts, taxes, and line totals.
  - **Charges, Transport & Remarks Slide-Over Drawer:**
    - Opens via `[ Charges & remarks  (count) ]` button or header `[ ⚙ Details ]`.
    - Tabs for Bill Sundries, Transport & Bales (Bale No, No of Bales, LR No, LR Date, Transporter, Station To, Order No, Credit Days), Remarks & Notes, Settlement.
  - **Fixed Bottom Summary & Action Bar:**
    - Action buttons: `[ ↺ Discard ]`, `[ 💾 Save as ]`, `[ 💾 Save ]` (primary purple), `[ Review ]`.
    - Summary totals: `NET`, `GST / TAX`, and `GRAND TOTAL`.
  - **Zero Regression Rule:** 100% of existing backend fields, models, migrations, and inline creation modals ([+]) are preserved intact.
 
---
 
### F. Single Compact Box UI Normalization (Elimination of Double Boxes)
- **Problem:** User reported nested/double boxes on:
  1. Filter dropdowns (Warehouse, Purchase Status, Payment Status).
  2. Opened dropdown menus (outer container vs inner list).
  3. DataTables pagination at footer (`<`, `1`, `>`).
  4. Export / action buttons (PDF, Excel, Colvis) and row action buttons.
- **Root Causes Identified & Fixed:**
  1. **Bootstrap Select Wrapper:** `<select class="form-control">` causes bootstrap-select to clone `.form-control` onto the outer `.btn-group.bootstrap-select`. Both outer container and inner `.btn.dropdown-toggle` had borders/shadows, creating a nested double box. Neutralized outer container border, background, and padding across `commercial-workspace.css` and `zolo-erp-neo.css`.
  2. **Dropdown Menus:** In bootstrap-select, `.dropdown-menu` wraps `.inner` and `ul.inner`. Stripped borders/shadows/padding from inner elements, retaining a single crisp 1px bordered card container (`border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 6px 18px rgba(15,23,42,0.08)`).
  3. **DataTables Pagination:** In Bootstrap 4 DataTables markup (`<li class="paginate_button page-item"><a class="page-link">1</a></li>`), outer `<li>` had borders/padding and inner `<a>` had borders/padding. Stripped all styling from outer `li` and normalized `.page-link` to a single compact box (`26px height, 1px solid #cbd5e1, 5px radius`).
  4. **DataTables Buttons:** DataTables wrapped nested arrays in `.btn-group`. Flattened `buttons.push(...)` in `purchase/index.blade.php` and `sale/index.blade.php`, swapped Excel export icon to `fa fa-file-excel-o`, and added single compact box styles to action buttons, search box, and length selector.
  5. **Dynamic Cache-Busting:** Added dynamic `?v={{ filemtime(...) }}` query parameters to CSS links in `purchase/index.blade.php` and `sale/index.blade.php` to guarantee immediate browser cache invalidation.

---

## 3. Verification & Testing Evidence
- Automated feature tests executed and passed:
  - `vendor/bin/phpunit tests/Feature/OptechMasterWebTest.php tests/Feature/OptechVoucherWebTest.php tests/Feature/AccountingWebTest.php`
  - Results: 21 passed (103 assertions, 100%), Duration: 11.58s

---

## 4. Crash Recovery Protocol for Any Agent
1. **Never start from scratch:** When reopened after a crash or system reboot, inspect SESSION_MEMORY.md first.
2. **Check Git Status:** Verify branch is enhanced-ui (git status and git branch -vv).
3. **Verify Database & Dependencies:** Check migrations are up to date.
4. **Continue Next Steps:**
   - Review pending screens in documents/zolo_erp_implementation_docs/32_OPTECH_SCREENS_AUDIT_AND_BACKEND_GAP_REPORT.md.
   - Implement Delivery Challan (DC) and Goods Received Note (GRN) web management and entry UIs.
   - Continue audit and modernization of remaining modules (Job Work, Production, GST).

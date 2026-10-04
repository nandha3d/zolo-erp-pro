# Optech Express ERP Modernization & Extension Suite
## Complete Engineering Reference & Modular Architecture Specification
### Target Platform: zoloERP Pro (Laravel 9/10/11) with Zero Core Modification Doctrine

---

## Executive Overview

This engineering reference defines the technical blueprint for extending **zoloERP Pro POS / Inventory** into a high-performance, domain-adapted web ERP that fully replicates the battle-tested capabilities of **Optech Express ERP (Legacy Textile ERP)** without modifying or corrupting zoloERP Pro's core codebase.

The legacy Optech system has evolved over 15+ years across major textile manufacturing, wholesale, and job-work hubs (Erode, Tirupur, Surat, Ahmedabad). It incorporates domain-specific behaviors—such as 3-decimal meter/kilogram precision, non-taxable outward Delivery Challan (DC) to bleaching/dyeing mills, inward Goods Received Note (GRN) reconciliation, continuous tractor-feed Dot-Matrix printing (68 lines per page), high-speed keyboard shortcuts (F2 counter sales, F12 purchase, Alt+C customer creation), and statutory Indian GST compliance (1-click GSTIN lookup, GSTR-1, GSTR-3B B2B summary).

By enforcing the **Zero Core Modification Doctrine**, all Optech enhancements are encapsulated within modular extensions under `Modules/` via `nwidart/laravel-modules`. This ensures the base zoloERP Pro software remains 100% upgradeable, maintainable, and stable.

---

## Master Document Index

| Doc ID | Specification Document | Description & Key Coverage |
| :--- | :--- | :--- |
| **00** | [00_ZERO_CORE_MODIFICATION_ARCHITECTURE_RULES.md](file:///d:/PROJECTS/WEBSITES/zolo-erp-pro/documents/00_ZERO_CORE_MODIFICATION_ARCHITECTURE_RULES.md) | **The Core Rulebook:** Non-invasive extension patterns, macroable models, event dispatchers, database namespace isolation (`optech_*`), middleware hooks, and view composers. |
| **01** | [01_MODULE_COMPANY_AND_TENANCY_SPECIFICATION.md](file:///d:/PROJECTS/WEBSITES/zolo-erp-pro/documents/01_MODULE_COMPANY_AND_TENANCY_SPECIFICATION.md) | **Company & Fiscal Boundaries:** Multi-company entities, fiscal year switching (2026-2027), server vs. client terminal tax locks, and access permissions. |
| **02** | [02_MODULE_TEXTILE_MASTER_DATA_SPECIFICATION.md](file:///d:/PROJECTS/WEBSITES/zolo-erp-pro/documents/02_MODULE_TEXTILE_MASTER_DATA_SPECIFICATION.md) | **Master Catalog:** Physical goods vs. job-work service items, 3-decimal unit precision (`0.000`), GST UQC mapping, fabric group varieties, and regional city-prefixed ledgers (`ERODE - ...`). |
| **03** | [03_MODULE_SERIES_AND_DOTMATRIX_PRINTING_SPECIFICATION.md](file:///d:/PROJECTS/WEBSITES/zolo-erp-pro/documents/03_MODULE_SERIES_AND_DOTMATRIX_PRINTING_SPECIFICATION.md) | **Series & Printing:** Document series (Ctrl+F9), auto-numbering, annual reset, continuous tractor-feed Dot-Matrix (ESC/P 68 lines), Laser A4 formats, transport parcel carriers, and bundle counts. |
| **04** | [04_MODULE_COUNTER_SALES_BILLING_F2_SPECIFICATION.md](file:///d:/PROJECTS/WEBSITES/zolo-erp-pro/documents/04_MODULE_COUNTER_SALES_BILLING_F2_SPECIFICATION.md) | **High-Speed Counter Billing (F2):** Real-time customer balance (Prv/Cur/Total Dr), Spacebar search, inline modals (Alt+C Customer, Alt+A Alter), last customer rates (Ctrl+S), pending bills (Ctrl+B), WhatsApp bill dispatch. |
| **05** | [05_MODULE_INWARD_PROCUREMENT_F12_SPECIFICATION.md](file:///d:/PROJECTS/WEBSITES/zolo-erp-pro/documents/05_MODULE_INWARD_PROCUREMENT_F12_SPECIFICATION.md) | **Inward Procurement Hub (F12):** Live vendor outstanding balance, automated GST tax split (CGST/SGST vs IGST), bill sundry (-10% trade discounts, freight), Reverse Charge (RCM), PO & GRN loading, duplicate invoice (Xerox). |
| **06** | [06_MODULE_TEXTILE_JOB_WORK_DC_GRN_SPECIFICATION.md](file:///d:/PROJECTS/WEBSITES/zolo-erp-pro/documents/06_MODULE_TEXTILE_JOB_WORK_DC_GRN_SPECIFICATION.md) | **Textile Job-Work Loop:** Outward Delivery Challan (DC) to processing mills, non-taxable inventory movement, inward Goods Received Note (GRN), process charges, shrinkage/wastage, triangular reconciliation (DC -> GRN -> Service Purchase). |
| **07** | [07_MODULE_TRANSACTION_REGISTRY_AND_RETURNS_SPECIFICATION.md](file:///d:/PROJECTS/WEBSITES/zolo-erp-pro/documents/07_MODULE_TRANSACTION_REGISTRY_AND_RETURNS_SPECIFICATION.md) | **Returns & Orders:** Sales return (Credit Notes) linked to original bill, Purchase return (Debit Notes), Sales/Purchase orders, and bill alteration audit trails. |
| **08** | [08_MODULE_DOUBLE_ENTRY_VOUCHERS_F9_SPECIFICATION.md](file:///d:/PROJECTS/WEBSITES/zolo-erp-pro/documents/08_MODULE_DOUBLE_ENTRY_VOUCHERS_F9_SPECIFICATION.md) | **Double-Entry Vouchers (F9):** Contra (F4 Bank/Cash), Payment (F5), Receipt (F6), Journal (F7), bill-by-bill allocation engine (Agst Ref, New Ref, Advance, On Account). |
| **09** | [09_MODULE_GENERAL_LEDGER_AND_ACCOUNTS_SPECIFICATION.md](file:///d:/PROJECTS/WEBSITES/zolo-erp-pro/documents/09_MODULE_GENERAL_LEDGER_AND_ACCOUNTS_SPECIFICATION.md) | **General Ledger & Financials:** Ledger book (Ctrl+F5), 12-month calendar debit/credit matrix (April-March) with voucher drill-down, Day Book, Cash Book, Bank Reconciliation, Trial Balance, P&L, Balance Sheet. |
| **10** | [10_MODULE_STATUTORY_GST_AND_STOCK_REPORTS_SPECIFICATION.md](file:///d:/PROJECTS/WEBSITES/zolo-erp-pro/documents/10_MODULE_STATUTORY_GST_AND_STOCK_REPORTS_SPECIFICATION.md) | **GST Automation & Stock Analytics:** 1-click GSTIN auto-lookup API, statutory GSTR-1 (B2B, B2C, HSN) with CSV/Excel government portal export, GSTR-3B, Item/HSN stock register, moving/non-moving stock, negative stock alert. |
| **11** | [11_MODULE_SYSTEM_PARAMETERS_AND_SECURITY_SPECIFICATION.md](file:///d:/PROJECTS/WEBSITES/zolo-erp-pro/documents/11_MODULE_SYSTEM_PARAMETERS_AND_SECURITY_SPECIFICATION.md) | **System Controls:** Data auto-lock dates, admin unlock override, Sunday transaction locking, negative stock enforcement (allow/warn/block), stock valuation (Average Purchase Rate vs FIFO), WhatsApp/SMS gateway. |
| **12** | [12_DATABASE_SCHEMA_AND_MIGRATION_DICTIONARY.md](file:///d:/PROJECTS/WEBSITES/zolo-erp-pro/documents/12_DATABASE_SCHEMA_AND_MIGRATION_DICTIONARY.md) | **Data Architecture:** Unified schema definition for all `optech_*` tables, foreign key constraints to core zoloERP Pro tables, indexing strategy, and migration sequencing. |
| **13** | [13_LARAVEL_MODULES_SCAFFOLDING_AND_DEPLOYMENT_GUIDE.md](file:///d:/PROJECTS/WEBSITES/zolo-erp-pro/documents/13_LARAVEL_MODULES_SCAFFOLDING_AND_DEPLOYMENT_GUIDE.md) | **Implementation & Deployment:** Nwidart modules directory layout, ServiceProvider registration, Artisan commands, frontend assets, testing procedures, and deployment checklist. |

---

## Architectural Principles Quick Reference

```
+-----------------------------------------------------------------------------------+
|                           zoloERP Pro CORE (UNTOUCHED)                                |
|  Controllers / Models / Migrations / Routes / Vendor Dependencies                 |
+-----------------------------------------------------------------------------------+
                                         |
                                         | Extends via Contracts, Events, & Decorators
                                         v
+-----------------------------------------------------------------------------------+
|                        OPTECH EXTENSION SUITE (MODULES/)                          |
|                                                                                   |
|  [OptechJobWork]     [OptechAccounting]    [OptechGST]      [OptechSpeedBilling]  |
|  - Delivery Challan  - Vouchers (F4-F7)    - GSTIN Fetch    - F2 Counter Screen   |
|  - Inward GRN        - Bill-by-Bill        - GSTR-1 / 3B    - Live Balances       |
|  - Triangular Recon  - 12-Month Matrix     - Tax Split      - Spacebar Search     |
|                                                                                   |
|  [OptechMaster]      [OptechPrint]         [OptechSecurity]                       |
|  - 3-Decimals (MTR)  - Dot-Matrix (68 L)   - Auto-Lock Date                       |
|  - City Ledgers      - Laser A4 Layouts    - Negative Stock                       |
+-----------------------------------------------------------------------------------+
                                         |
                                         v
+-----------------------------------------------------------------------------------+
|                         DATA LAYER (ISOLATED NAMESPACE)                           |
|  Core Tables: `sales`, `purchases`, `products`, `customers`, `suppliers`          |
|  Optech Tables: `optech_challans`, `optech_grn`, `optech_vouchers`, etc.          |
+-----------------------------------------------------------------------------------+
```

All implementation teams must read [00_ZERO_CORE_MODIFICATION_ARCHITECTURE_RULES.md](file:///d:/PROJECTS/WEBSITES/zolo-erp-pro/documents/00_ZERO_CORE_MODIFICATION_ARCHITECTURE_RULES.md) before writing code.
# Optech ERP Screen Audit & Backend Gap Analysis

This document provides a comprehensive audit of all **1,168 screens across 11 modules** from the reference system (`optech_screens`), evaluating every screen option against the current `zolo-erp-pro` backend database schema, enforcing the **Zero Hardcoding Rule**, and identifying what is missing in the backend before UI implementation.

---

## 1. Core Architectural Rules

1. **Zero Hardcoding Rule:**
   - Dropdown menus and selectable lists must **strictly originate from database tables** (no static mock arrays or hardcoded options in templates or JS).
   - If an entity does not exist in the database, it must not appear in a dropdown.
2. **User Input vs. Master Entity Rule:**
   - **Plain User Inputs:** Numeric values (rates, quantities, discount percentages, amounts, bale count), dates, and free-text remarks do not require master tables.
   - **Master Entities:** Every dropdown entity (Customers, Suppliers, Items, Units, Agents, Areas, Sale Types, Purchase Types, Bill Sundries, Voucher Types, Series, Accounts) must afford an inline **`[+]` creation button** (or `Alt+C` shortcut) so users can create records on the fly without leaving the screen.

---

## 2. Module-by-Module Audit & Backend Gap Report

### Module 1: MASTER_07 — Master Creation & Management (118 Screens)
| Screen Option / Field | Input Type | Backend Status | Database Table / Column | Notes & Gaps |
| :--- | :--- | :--- | :--- | :--- |
| **Item Name** | User Input | Available | `products.name` | Required |
| **Print Name** | User Input | Available | `products.print_name` | Added in migration `2026_10_13_000001` |
| **Item Group (Category)** | Dropdown | Available | `categories` | Has `[+]` inline create |
| **Brand** | Dropdown | Available | `brands` | Has `[+]` inline create |
| **Unit** | Dropdown | Available | `units` | Has `[+]` inline create |
| **Purc Rate, Cost, Sale Rate, MRP** | Numeric Input | Available | `products` rates | User inputs |
| **GST Sales / Purch Rates** | Dropdown | Available | `taxes` / `tax_rates` | DB-backed tax rates |
| **HSN Code** | Dropdown / Search | Available | `hsn_sac_codes` | DB-backed HSN table |
| **A/c Head** | Dropdown | Available | `chart_of_accounts` | Linked COA ledger |
| **Negative Stock Policy** | Select / Toggle | **Missing** | `products.allow_negative_stock` | Column needed on `products` table |
| **Opening Stock & Value** | User Input | Available | `product_warehouse` | Initial stock balance |
| **Ledger / Party Name** | User Input | Available | `customers.name`, `suppliers.name` | |
| **Area** | Dropdown | Available | `areas` | Created table & inline `[+]` API |
| **Agent / Broker / Through** | Dropdown | Available | `agents` | Created table & inline `[+]` API |
| **Credit Days & CD %** | User Input | Available | `cd_days`, `cd_percent` | Added columns |
| **Bill by Bill** | Checkbox | Available | `bill_by_bill` | Added column |
| **Is SEZ** | Checkbox | Available | `is_sez` | Added column |
| **Bill Sundries** | Master CRUD | Available | `bill_sundries` | Created table & management UI |
| **Sale Types & Purchase Types** | Master CRUD | Available | `sale_types`, `purchase_types` | Created tables & management UI |
| **Standard Remarks** | Master CRUD | Available | `standard_remarks` | Created table & management UI |

---

### Module 2: MJ_SALES_02 — Sales Bill & Invoicing (135 Screens)
| Screen Option / Field | Input Type | Backend Status | Database Table / Column | Notes & Gaps |
| :--- | :--- | :--- | :--- | :--- |
| **Voucher Series (e.g. MJ-22)** | Dropdown | **Partially Missing** | `document_series` | Table exists in DB, but has **no web management controller or API to select active series** |
| **Bill Date** | Date Input | Available | `sales.created_at` | Plain input |
| **Bill Type (Cash / Credit)** | Button / Select | Available | `sales.payment_status` / `sale_type` | DB state |
| **Sales Type** | Dropdown | Available | `sale_types` (`sales.sale_type_id`) | DB-backed with `[+]` inline create |
| **Through (Agent / Broker)** | Dropdown | Available | `agents` (`sales.agent_id`) | DB-backed with `[+]` inline create |
| **Customer Name** | Dropdown / Search | Available | `customers` (`sales.customer_id`) | DB-backed with `[+]` inline create |
| **SMS Alert** | Checkbox | Available | `sales.attributes_json` | Option toggle |
| **Item Grid (Sno, Name, Unit, Rate, Qty, Amount, Taxes)** | Data Grid | Available | `product_sales` | Fully supported |
| **Closing Stock Display** | Live Indicator | Available | `product_warehouse` | Calculated from current stock |
| **Rate History Lookup** (`Alt+UpArrow`) | Modal / Flyout | **Missing Backend API** | Needs API `sales/rate-history` | Endpoint to query last sale rate & purchase cost for party + item |
| **Add-Ins / Transport Details** (Bale No, No of Bales, LR No, LR Date, Transporter, Station To, Order No) | Form Popup | **Partially Missing** | `sales.attributes_json` | Currently stored in JSON; needs dedicated columns or formal schema validation |
| **Bill Sundry Grid** (Sundry, %, Amount) | Data Grid | Available | `bill_sundries` | DB-backed with `[+]` inline create |
| **Today's Sales Display** | Indicator | Available | Computed query | Sum of sales for current date |
| **Bill Clerk / Salesman** | Dropdown | Available | `users` (`sales.user_id`) | DB-backed users table |
| **Remarks** | User Input / Dropdown | Available | `standard_remarks` (`sales.sale_note`) | Predefined standard remarks with auto-copy |
| **Bottom Toolbar** (1-Alter, Print, New Bill, Xerox, Party Stmt, Options, 2-DC, 3-Order, 4-Quot, 5-Purc, 6-GRN, Save) | Actions | Available | Controller actions | Fully mappable |

---

### Module 3: MJ_PURCHASE_01 — Purchase Bill / Material Inward (246 Screens)
| Screen Option / Field | Input Type | Backend Status | Database Table / Column | Notes & Gaps |
| :--- | :--- | :--- | :--- | :--- |
| **S.No / Voucher No** | Auto / Text | Available | `purchases.reference_no` | |
| **Invoice Date** | Date Input | Available | `purchases.created_at` | |
| **Supplier Inv. No** | User Input | Available | `purchases.supplier_invoice_no` | Added column in migration |
| **Supplier Inv. Date** | Date Input | Available | `purchases.supplier_invoice_date` | Added column in migration |
| **Bill Type (Cash / Credit)** | Button / Select | Available | `purchases.payment_status` | |
| **Purchase Type** | Dropdown | Available | `purchase_types` (`purchases.purchase_type_id`) | DB-backed with `[+]` inline create |
| **Through (Agent)** | Dropdown | Available | `agents` (`purchases.agent_id`) | DB-backed with `[+]` inline create |
| **Supplier Name** | Dropdown / Search | Available | `suppliers` (`purchases.supplier_id`) | DB-backed with `[+]` inline create |
| **Update HSN / Cost / Rack** | Checkbox | Available | `purchases.update_item_hsn`, `update_item_cost` | Added columns in migration |
| **Reverse Charge** | Checkbox | Available | `purchases.is_reverse_charge` | Added column in migration |
| **Item Grid & Taxes** | Data Grid | Available | `product_purchases` | Fully supported |
| **Bill Sundries Grid** | Data Grid | Available | `bill_sundries` | DB-backed with `[+]` inline create |
| **Action Buttons** (GRN, Order, Xerox, Options, Import, Cost, Multi Item, Clear, Save) | Actions | Available | Controller actions | Fully mappable |

---

### Module 4: VOUCHER_ENTRY_03 — Financial Voucher Entry (159 Screens)
| Screen Option / Field | Input Type | Backend Status | Database Table / Column | Notes & Gaps |
| :--- | :--- | :--- | :--- | :--- |
| **Voucher Types** (Contra F4, Payment F5, Receipt F6, Journal F7, Sales F8, Purchase F9, Memo F10) | Tabs / Key shortcuts | Available | `Accounting\VoucherController` | Backend supports Contra, Payment, Receipt, Journal; **Sales, Purchase, Memo voucher types are separate modules** |
| **Vch No** | Auto / Text | Available | `journal_entries.entry_number` | |
| **Posting Date** | Date Input | Available | `journal_entries.entry_date` | |
| **GST Nature** (Advance, Reverse Charge, Export, etc.) | Dropdown | **Missing** | `journal_entries.gst_nature` | Column needed to track GST nature on financial vouchers |
| **Dr / Cr Line Grid** | Data Grid | Available | `journal_items` | `chart_of_account_id`, `debit`, `credit`, `memo` |
| **Party Selection (AR/AP)** | Dropdown | Available | `customers`, `suppliers` | DB-backed party picker |
| **Live Account Balance (`Bal: ... Dr/Cr`)** | Live Display | **Missing Backend API** | Needs endpoint `accounting/account-balance/{id}` | Real-time balance lookup on row focus |
| **Bill Allocation Mode** (New Ref, Against Ref, Advance, On Account) | Modal Popup | Available | `account_open_items`, `account_allocations` | Fully backed by accounting allocations |
| **Carry Voucher / Multi Entry** | Checkbox | Available | UI state | |
| **Narration & Narration Templates** | Input / Dropdown | Available | `journal_entries.description` | DB-backed standard remarks |
| **Hotkeys** (`Alt+C` Create Ledger, `Alt+A` Alter Ledger, `Alt+S` Save, `Alt+X` Duplicate) | Key handlers | Available | Can invoke inline master modals | |

---

### Module 5: DC_GRN_05 — Delivery Challan & Goods Receipt Note (47 Screens)
| Screen Option / Field | Input Type | Backend Status | Database Table / Column | Notes & Gaps |
| :--- | :--- | :--- | :--- | :--- |
| **Delivery Challan (DC)** (Independent outbound dispatch before sale bill) | Transaction Module | **MISSING IN BACKEND** | No `delivery_challans` table | Existing `deliveries` table only tracks courier delivery *after* sale. Optech requires pre-sale DC that later converts into Sales Bill. |
| **Goods Receipt Note (GRN)** (Independent inbound receipt before purchase bill) | Transaction Module | **MISSING IN BACKEND** | No `grns` table | No material inward note before purchase bill. Optech requires pre-purchase GRN that converts to Purchase Bill. |
| **Pending DC to Invoicing** | Workflow | **MISSING IN BACKEND** | No status column / linking table | Ability to pull multiple DCs into one Sales Bill |
| **Pending GRN to Invoicing** | Workflow | **MISSING IN BACKEND** | No status column / linking table | Ability to pull multiple GRNs into one Purchase Bill |

---

### Module 6: ENTRY_06 — Sales Return, Purchase Return & Quotation (44 Screens)
| Screen Option / Field | Input Type | Backend Status | Database Table / Column | Notes & Gaps |
| :--- | :--- | :--- | :--- | :--- |
| **Sales Return Entry** | Transaction | Available | `returns` table | Has `sale_id`, `customer_id`, `item`, `note_type` |
| **Credit Note Checkbox** | Checkbox | Available | `returns.note_type = 'credit_note'` | Supported |
| **Bill Sundries on Returns** | Data Grid | **Missing** | Needs `returns.sundries_json` | Sundry calculations on credit notes |
| **Purchase Return Entry** | Transaction | Available | `return_purchases` table | Has `purchase_id`, `supplier_id`, `item` |
| **Debit Note Checkbox** | Checkbox | Available | `return_purchases.note_type = 'debit_note'` | Supported |
| **Quotation (Alt+F10)** | Transaction | Available | `quotations` table | Has `customer_id`, `item`, `grand_total` |
| **Next Follow-up Date on Quotation** | Date Input | **Missing** | `quotations.next_follow_up_date` | Column needed on `quotations` table |

---

### Module 7: SERIES_04 — Voucher Series Management (51 Screens)
| Screen Option / Field | Input Type | Backend Status | Database Table / Column | Notes & Gaps |
| :--- | :--- | :--- | :--- | :--- |
| **Voucher Series List** | Table Grid | **Partially Available** | `document_series` | Table exists with `document_type`, `code`, `prefix`, `suffix`, `next_number`, `reset_policy`. **Needs web controller and Blade view**. |
| **Series Type** (Sales, Purchase, Receipt, Payment, DC, GRN, etc.) | Dropdown | Available | `document_series.document_type` | |
| **Start Number & Prefix/Suffix** | User Input | Available | `document_series.next_number`, `prefix`, `suffix` | |
| **Re-Arrange / Renumber Vouchers** | Action | **Missing** | Renumbering service | Audit-safe renumbering tool |
| **Voucher Statistics** | Report | **Missing** | Count per series query | Series usage metrics |

---

### Module 8: ACCOUNTS_08 — Financial Books & Registers (198 Screens)
| Screen Option / Field | Input Type | Backend Status | Database Table / Column | Notes & Gaps |
| :--- | :--- | :--- | :--- | :--- |
| **Day Book** | Report View | Available | `Accounting\VoucherController@book` | Backed by `journal_entries` and `journal_items` |
| **Ledger Statement (`Ctrl+F5`)** | Report View | Available | `Accounting\FinancialReportController@generalLedger` | Backed by `chart_of_accounts` |
| **Cash Book & Bank Book** | Report View | Available | `Accounting\VoucherController@book` | Backed by cash/bank control types |
| **Trial Balance** | Report View | Available | `Accounting\FinancialReportController@trialBalance` | Backed by chart of accounts |
| **Balance Sheet & Profit/Loss** | Report View | Available | `Accounting\FinancialReportController@balanceSheet` / `profitLoss` | Fully backed by accounting engine |
| **Merge Cash Sales in Daybook** | Option | **Missing** | Query parameter in daybook | Toggle to aggregate individual cash counter sales into a single line |

---

### Module 9: REPORT_09 — Stock & Inventory Reports (205 Screens)
| Screen Option / Field | Input Type | Backend Status | Database Table / Column | Notes & Gaps |
| :--- | :--- | :--- | :--- | :--- |
| **Item Wise Stock Report (F7)** | Multi-filter Report | Available | `products`, `product_warehouse`, `stock_movements` | Queryable |
| **Report Filters** (HSN, Group, Brand, GST rate, Negative stock, Rate ranges) | Dropdowns / Inputs | Available | DB tables (`categories`, `brands`, `hsn_sac_codes`, `taxes`) | Strictly DB-backed |
| **Export to Excel** | Button Action | Available | Report export | Supported |

---

### Module 10: FEATURES_010 — Settings & Feature Toggles (55 Screens)
| Screen Option / Field | Input Type | Backend Status | Database Table / Column | Notes & Gaps |
| :--- | :--- | :--- | :--- | :--- |
| **Feature Configuration** (Multi Series, Multi Sales Type, Negative Stock Warning, Allow Rate Alteration, Cash Receipt generation) | Setting Toggles | **Partially Missing** | `company_industry_settings`, `pos_setting`, `general_settings` | Needs unified Optech settings table/keys |

---

### Module 11: COMPANY_011 — Company & Financial Year Selection (10 Screens)
| Screen Option / Field | Input Type | Backend Status | Database Table / Column | Notes & Gaps |
| :--- | :--- | :--- | :--- | :--- |
| **Company Selection (`F3`)** | Modal / Switcher | Available | `companies`, `company_user` | Tenant switching |
| **Change Financial Year (`Alt+F2`)** | Modal / Switcher | Available | `fiscal_years` | FY switching |

---

## 3. Missing Backend Items Summary (Required Before Full UI)

To complete the full ERP interface without hardcoding or missing backend tables, the following items must be implemented in the backend:

1. **Independent Delivery Challan (DC) & Goods Receipt Note (GRN) Module**:
   - Create tables `delivery_challans`, `delivery_challan_items`, `grns`, `grn_items`.
   - Add status (`pending`, `invoiced`, `cancelled`) to track conversion into Sales Bills and Purchase Bills.
2. **Voucher Series Web Management (`SERIES_04`)**:
   - Create `DocumentSeriesController` and view `resources/views/backend/master/series.blade.php`.
   - Seed default series for Company 1 (`SALES`, `PURCHASE`, `RECEIPT`, `PAYMENT`, `CONTRA`, `JOURNAL`).
3. **Transport & Add-Ins Schema for Sales**:
   - Add explicit columns or formal schema to `sales`: `bale_no`, `no_of_bales`, `lr_no`, `lr_date`, `transport_name`, `station_to`, `order_no`.
4. **Rate History Lookup & Real-time Balance APIs**:
   - Endpoint: `GET /api/v1/commercial/rate-history?product_id={id}&customer_id={id}`
   - Endpoint: `GET /api/v1/accounting/account-balance/{id}`
5. **GST Nature on Financial Vouchers**:
   - Add column `gst_nature` (`normal`, `rcm`, `advance`, `export`) to `journal_entries`.

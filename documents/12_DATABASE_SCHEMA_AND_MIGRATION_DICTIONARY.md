# Section 12: Unified Database Schema & Migration Data Dictionary
## Complete Physical Schema Specification for the `optech_*` Tables

---

## 1. Schema Design Strategy

In accordance with the **Zero Core Modification Doctrine**:
- Every Optech table is strictly prefixed with `optech_`.
- All foreign keys to core zoloERP Pro tables (`products`, `customers`, `suppliers`, `sales`, `purchases`, `units`, `warehouses`, `users`) use standard indexing and cascade rules.
- Column types strictly match their business realities:
  - Currency: `DECIMAL(14,2)` or `DECIMAL(16,2)`
  - Textile Length / Weight: `DECIMAL(12,3)` (3-decimal precision for meters/kgs)
  - Percentages: `DECIMAL(5,2)` (e.g. `18.00`, `2.50`)
  - Identifiers: `BIGINT UNSIGNED AUTO_INCREMENT`

---

## 2. Master Entity Relationship Architecture

```
[optech_companies]
       |
       +---> [optech_financial_years]
       |
       +---> [optech_voucher_series]
       |
       +---> [optech_system_parameters]

[core: products] <--(1:1)--> [optech_product_attributes]
[core: units]    <--(1:1)--> [optech_units_extension]
[core: customers]<--(1:1)--> [optech_party_attributes (CUSTOMER)]
[core: suppliers]<--(1:1)--> [optech_party_attributes (SUPPLIER)]

[core: sales]    <--(1:1)--> [optech_sales_extension]
[core: purchases]<--(1:1)--> [optech_purchases_extension]

[optech_delivery_challans] <---(1:N)---> [optech_delivery_challan_items]
       |
       +---(1:N)---> [optech_goods_received_notes]

[optech_vouchers] <---(1:N)---> [optech_voucher_lines]
                                        |
                                        +---(1:N)---> [optech_bill_allocations]
```

---

## 3. Complete Data Dictionary

### Table: `optech_companies`
| Column | Type | Nullable | Default | Description |
| :--- | :--- | :---: | :--- | :--- |
| `id` | BIGINT UNSIGNED | No | AUTO_INC | Primary Key |
| `company_name` | VARCHAR(150) | No | | Registered entity name (e.g. MJ EXPORTS) |
| `legal_status` | ENUM | No | PROPRIETORSHIP | PROPRIETORSHIP, PARTNERSHIP, PVT_LTD, LLP |
| `gstin` | VARCHAR(15) | No | | 15-digit GSTIN (Unique Index) |
| `pan` | VARCHAR(10) | No | | 10-digit PAN |
| `state_code` | VARCHAR(2) | No | 33 | State code (e.g. 33 Tamil Nadu) |
| `registered_address` | TEXT | No | | Legal street address |
| `city` | VARCHAR(50) | No | ERODE | Primary trading city |
| `pincode` | VARCHAR(10) | No | | Postal code |
| `phone` | VARCHAR(30) | Yes | | Phone / Mobile number |
| `email` | VARCHAR(100) | Yes | | Official email |
| `bank_name` | VARCHAR(100) | Yes | | Default company bank |
| `bank_branch` | VARCHAR(100) | Yes | | Bank branch |
| `bank_account_no` | VARCHAR(50) | Yes | | Account number printed on invoice footer |
| `bank_ifsc` | VARCHAR(20) | Yes | | IFSC code printed on invoice footer |
| `is_active` | TINYINT(1) | No | 1 | Entity status |

---

### Table: `optech_financial_years`
| Column | Type | Nullable | Default | Description |
| :--- | :--- | :---: | :--- | :--- |
| `id` | BIGINT UNSIGNED | No | AUTO_INC | Primary Key |
| `company_id` | BIGINT UNSIGNED | No | | FK -> `optech_companies.id` |
| `title` | VARCHAR(50) | No | | Fiscal year title (e.g. 2026-2027) |
| `start_date` | DATE | No | | Fiscal start (April 1st) |
| `end_date` | DATE | No | | Fiscal end (March 31st) |
| `is_locked` | TINYINT(1) | No | 0 | 1 = Audited & closed period |
| `is_active` | TINYINT(1) | No | 1 | Status |

---

### Table: `optech_voucher_series`
| Column | Type | Nullable | Default | Description |
| :--- | :--- | :---: | :--- | :--- |
| `id` | BIGINT UNSIGNED | No | AUTO_INC | Primary Key |
| `company_id` | BIGINT UNSIGNED | No | | FK -> `optech_companies.id` |
| `financial_year_id` | BIGINT UNSIGNED | No | | FK -> `optech_financial_years.id` |
| `series_name` | VARCHAR(50) | No | | Name (e.g. SALES MJ-22, DC GENERAL) |
| `transaction_type` | ENUM | No | | SALES, PURCHASE, DC, GRN, PAYMENT, etc. |
| `prefix` | VARCHAR(20) | No | '' | Invoice prefix (e.g. MJ-22/) |
| `suffix` | VARCHAR(20) | No | '' | Invoice suffix |
| `zero_padding` | TINYINT | No | 4 | Padding width (e.g. 4 -> 0001) |
| `next_number` | INT UNSIGNED | No | 1 | Next counter sequence |
| `print_format` | ENUM | No | DOT_MATRIX_ESC_P | DOT_MATRIX_ESC_P, LASER_A4, THERMAL_POS |
| `paper_height_lines` | TINYINT | No | 68 | Continuous paper height in lines (68 lines) |
| `print_copies` | TINYINT | No | 3 | Copy count presets (Original, Duplicate, Triplicate) |

---

### Table: `optech_product_attributes`
| Column | Type | Nullable | Default | Description |
| :--- | :--- | :---: | :--- | :--- |
| `id` | BIGINT UNSIGNED | No | AUTO_INC | Primary Key |
| `product_id` | INT UNSIGNED | No | | FK -> `products.id` (1-to-1) |
| `item_type` | ENUM | No | GOODS | GOODS (physical cloth) vs. SERVICE (jobwork charges) |
| `hsn_code` | VARCHAR(20) | No | | HSN/SAC code (e.g. 5208, 9988) |
| `tax_slab` | DECIMAL(5,2) | No | 5.00 | GST percentage (5.00, 12.00, 18.00, 0.00) |
| `fabric_group` | VARCHAR(100) | Yes | | Dhotis, Bedspreads, Mull, Bleached |
| `cut_piece_available` | TINYINT(1) | No | 0 | 1 = Sold in variable meter cuts |
| `rolls_tracking` | TINYINT(1) | No | 0 | 1 = Tracked by individual roll/thaan number |
| `standard_cut_length` | DECIMAL(8,3) | Yes | 0.000 | Standard length per piece in meters |
| `rack_location` | VARCHAR(50) | Yes | | Godown rack location |
| `default_job_rate` | DECIMAL(12,2) | Yes | 0.00 | Processing charge per meter |

---

### Table: `optech_sales_extension`
| Column | Type | Nullable | Default | Description |
| :--- | :--- | :---: | :--- | :--- |
| `id` | BIGINT UNSIGNED | No | AUTO_INC | Primary Key |
| `sale_id` | INT UNSIGNED | No | | FK -> `sales.id` (1-to-1) |
| `company_id` | BIGINT UNSIGNED | No | | FK -> `optech_companies.id` |
| `series_id` | BIGINT UNSIGNED | No | | FK -> `optech_voucher_series.id` |
| `voucher_no` | VARCHAR(50) | No | | Formatted bill number (e.g. MJ-22/0142) |
| `series_number` | INT UNSIGNED | No | | Sequence counter |
| `salesman_id` | INT UNSIGNED | Yes | | Commission clerk |
| `previous_balance` | DECIMAL(14,2) | No | 0.00 | Snapshot of customer debit balance before bill |
| `current_bill_amount` | DECIMAL(14,2) | No | 0.00 | Active net invoice total |
| `closing_balance` | DECIMAL(14,2) | No | 0.00 | Combined closing balance ($Prv + Cur$) |
| `transport_agency` | VARCHAR(100) | Yes | | Parcel carrier (e.g. APS Transport, KRS) |
| `bundles_count` | VARCHAR(30) | Yes | 1 BUNDLE | Total bales/bundles packed |
| `whatsapp_dispatched` | TINYINT(1) | No | 0 | 1 = WhatsApp PDF message sent |

---

### Table: `optech_delivery_challans`
| Column | Type | Nullable | Default | Description |
| :--- | :--- | :---: | :--- | :--- |
| `id` | BIGINT UNSIGNED | No | AUTO_INC | Primary Key |
| `company_id` | BIGINT UNSIGNED | No | | FK -> `optech_companies.id` |
| `financial_year_id` | BIGINT UNSIGNED | No | | FK -> `optech_financial_years.id` |
| `challan_no` | VARCHAR(50) | No | | Formatted DC number (e.g. DC-26/0084) |
| `challan_date` | DATE | No | | Outward movement date |
| `job_worker_id` | INT UNSIGNED | No | | Processing factory party ID |
| `process_type` | ENUM | No | | BLEACHING, DYEING, PRINTING, IRONING |
| `from_warehouse_id` | INT UNSIGNED | No | | Origin raw fabric warehouse |
| `to_jobworker_warehouse_id` | INT UNSIGNED | No | | Virtual outside warehouse |
| `total_meters_dispatched` | DECIMAL(12,3) | No | 0.000 | Outward fabric meters |
| `status` | ENUM | No | OPEN | OPEN, PARTIALLY_RECEIVED, COMPLETED |

---

### Table: `optech_goods_received_notes`
| Column | Type | Nullable | Default | Description |
| :--- | :--- | :---: | :--- | :--- |
| `id` | BIGINT UNSIGNED | No | AUTO_INC | Primary Key |
| `company_id` | BIGINT UNSIGNED | No | | FK -> `optech_companies.id` |
| `financial_year_id` | BIGINT UNSIGNED NOT NULL | | FK -> `optech_financial_years.id` |
| `grn_no` | VARCHAR(50) | No | | Formatted GRN number |
| `grn_date` | DATE | No | | Inward return date |
| `challan_id` | BIGINT UNSIGNED | No | | FK -> `optech_delivery_challans.id` |
| `job_worker_id` | INT UNSIGNED | No | | Processing factory party ID |
| `to_finished_warehouse_id` | INT UNSIGNED | No | | Inward finished fabric warehouse |
| `received_meters` | DECIMAL(12,3) | No | 0.000 | Inward finished cloth meters |
| `wastage_shrinkage_meters` | DECIMAL(12,3) | No | 0.000 | Shrinkage / process loss meters |
| `process_charge_per_meter` | DECIMAL(8,2) | No | 0.00 | Processing charge per meter |
| `is_invoiced` | TINYINT(1) | No | 0 | 1 = Linked to Service Purchase Bill |

---

### Table: `optech_vouchers` & `optech_voucher_lines`
| Column | Type | Nullable | Default | Description |
| :--- | :--- | :---: | :--- | :--- |
| `id` | BIGINT UNSIGNED | No | AUTO_INC | Primary Key |
| `voucher_type` | ENUM | No | | CONTRA, PAYMENT, RECEIPT, JOURNAL |
| `voucher_number` | VARCHAR(50) | No | | Voucher series number |
| `voucher_date` | DATE | No | | Transaction date |
| `total_debit` | DECIMAL(14,2) | No | | Total Debit amount (Must equal Total Credit) |
| `total_credit` | DECIMAL(14,2) | No | | Total Credit amount |
| `narration` | TEXT | Yes | | Accounting transaction narration |
# Section 05: Inward Procurement & Supplier Bill Entry (F12)
## Technical Specification & Implementation Guide
### Based on Video Walkthrough: `MJ PURCHASE 01.mp4`

---

## 1. Domain Overview & Optech Legacy Behavior

In the textile supply chain, raw fabrics (grey cloth, yarn, bleached mull) are procured from weavers, spinning mills, and grey cloth merchants. The Inward Procurement Hub (`MJ PURCHASE 01`) manages:

- **F12 Access Key:** Direct keyboard jump to Inward Procurement Entry.
- **Supplier Live Debit/Credit Balance:** Shows current outstanding supplier balance (e.g. `229025.00 Dr` or `Cr`) immediately upon vendor selection.
- **Automated GST Splitting (Local vs. Interstate):**
  - **Local (Intrastate):** Rate splits into **2.5% CGST** and **2.5% SGST** (for 5% fabric).
  - **Interstate:** Rate calculates as **5% IGST** if supplier state code $\\neq$ company state code.
- **Reverse Charge Mechanism (RCM):** Checkbox to record inward procurement from unregistered weavers (URP) where tax is payable on reverse charge.
- **Bill Sundry Accounting:**
  - Trade discount percentage (e.g. `-10%` applied across items).
  - Inward Freight / Lorry Transport charges added to bill cost.
  - Round-off adjustment to nearest integer rupee (`0.00`).
- **Inward Purchase Order (PO) & GRN Bill Loading:** Allows 1-click loading of items from an authorized PO or processed Job-Work Goods Received Note (GRN).
- **Xerox (Duplicate Bill):** Clones supplier bill line items for repeat recurring yarn/fabric contracts.

---

## 2. Database Schema Specification

```sql
CREATE TABLE `optech_purchases_extension` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_id` INT UNSIGNED NOT NULL COMMENT 'Foreign key to core purchases.id',
  `company_id` BIGINT UNSIGNED NOT NULL,
  `series_id` BIGINT UNSIGNED NOT NULL,
  `supplier_invoice_no` VARCHAR(50) NOT NULL COMMENT 'Vendor original bill number',
  `supplier_invoice_date` DATE NOT NULL,
  `purchase_type` ENUM('LOCAL_GST', 'INTERSTATE_IGST', 'EXEMPT', 'RCM') NOT NULL DEFAULT 'LOCAL_GST',
  `through_agent_broker` VARCHAR(100) NULL COMMENT 'Yarn broker / Fabric commission agent',
  `trade_discount_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'e.g. 10.00 for 10% discount',
  `trade_discount_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `freight_charges` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `round_off` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `is_rcm_applicable` TINYINT(1) NOT NULL DEFAULT 0,
  `supplier_previous_balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `po_reference_id` BIGINT UNSIGNED NULL COMMENT 'Linked PO if loaded from order',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_optech_purch_id` (`purchase_id`),
  KEY `idx_optech_supplier_inv` (`supplier_invoice_no`, `supplier_invoice_date`),
  CONSTRAINT `fk_optech_purch_ext` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `optech_purchase_order_loading` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_id` INT UNSIGNED NOT NULL,
  `po_number` VARCHAR(50) NOT NULL,
  `po_date` DATE NOT NULL,
  `total_ordered_meters` DECIMAL(12,3) NOT NULL,
  `total_received_meters` DECIMAL(12,3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_optech_po_load` (`purchase_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Automated Tax Calculation Formula Engine

```
For each line item $i$:
  Gross Amount: $G_i = \\text{Rate}_i \\times \\text{Qty}_i$
  Line Discount: $D_i = G_i \\times \\frac{\\text{TradeDiscount}\\%}{100}$
  Taxable Value: $T_i = G_i - D_i$

If Intrastate (Supplier State == Company State):
  $\\text{CGST}_i = T_i \\times \\frac{\\text{TaxRate}_i / 2}{100}$
  $\\text{SGST}_i = T_i \\times \\frac{\\text{TaxRate}_i / 2}{100}$
  $\\text{IGST}_i = 0.00$
Else (Interstate):
  $\\text{CGST}_i = 0.00$
  $\\text{SGST}_i = 0.00$
  $\\text{IGST}_i = T_i \\times \\frac{\\text{TaxRate}_i}{100}$

Total Net Bill Amount:
  $\\text{NET} = \\sum(T_i) + \\sum(\\text{CGST}_i) + \\sum(\\text{SGST}_i) + \\sum(\\text{IGST}_i) + \\text{Freight} + \\text{RoundOff}$
```

---

## 4. Verification Checklist

- [ ] Inward purchase entry calculates tax split into 2.5% CGST and 2.5% SGST when supplier state code matches company state code (e.g. 33 Tamil Nadu).
- [ ] If supplier is from Karnataka (29) or Gujarat (24), entire 5% applies as IGST.
- [ ] Loading a PO automatically pre-fills fabric items, ordered meters, agreed rates, and prevents double-invoicing.
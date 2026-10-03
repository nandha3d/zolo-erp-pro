# Section 07: Transaction Registry, Orders & Sales/Purchase Returns
## Technical Specification & Implementation Guide
### Based on Video Walkthrough: `ENTRY 06.mp4`

---

## 1. Domain Overview & Optech Legacy Behavior

In the Optech Entry subsystem:
1. **Sales Return (Credit Note):**
   - Must strictly link to the **Original Invoice Number**.
   - Pulls the original billing rate, tax rate, and party details.
   - Generates a statutory GST Credit Note formatted for GSTR-1 Section 9B.
   - Automatically restocks returned fabric batches into inventory.
2. **Purchase Return (Debit Note):**
   - Links to supplier purchase bill.
   - Creates a statutory Debit Note deducting the supplier's payable ledger and reversing Input Tax Credit (ITC).
3. **Pending Orders Tracking:**
   - Tracks Sales Orders and Purchase Orders with pending balance delivery quantities before invoicing.
4. **Bill Alteration Controls:**
   - Altering or cancelling a saved bill is strictly restricted by role permissions and transaction lock dates to maintain legal audit integrity.

---

## 2. Database Schema Specification

```sql
CREATE TABLE `optech_returns_extension` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `return_type` ENUM('SALES_RETURN_CREDIT_NOTE', 'PURCHASE_RETURN_DEBIT_NOTE') NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `financial_year_id` BIGINT UNSIGNED NOT NULL,
  `note_number` VARCHAR(50) NOT NULL COMMENT 'Credit/Debit Note series number',
  `note_date` DATE NOT NULL,
  `original_invoice_no` VARCHAR(50) NOT NULL,
  `original_invoice_date` DATE NOT NULL,
  `party_id` INT UNSIGNED NOT NULL,
  `taxable_amount` DECIMAL(14,2) NOT NULL,
  `cgst_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `sgst_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `igst_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `net_credit_debit_amount` DECIMAL(14,2) NOT NULL,
  `reason_for_return` ENUM('DEFECTIVE_FABRIC', 'ORDER_CANCELLED', 'RATE_DIFFERENCE', 'SHORTAGE') NOT NULL DEFAULT 'DEFECTIVE_FABRIC',
  `gst_portal_status` ENUM('PENDING', 'UPLOADED', 'ACCEPTED') NOT NULL DEFAULT 'PENDING',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_optech_note_num` (`company_id`, `note_number`),
  KEY `idx_optech_orig_inv` (`original_invoice_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Verification Checklist

- [ ] Creating a Sales Return reverses tax liability (CGST/SGST/IGST) and appears in GSTR-1 Table 9B.
- [ ] Stock quantities return to designated warehouse automatically.
- [ ] Cannot issue a return against an invoice that is already fully returned.
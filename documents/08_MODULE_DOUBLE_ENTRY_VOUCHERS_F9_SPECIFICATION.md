# Section 08: Double-Entry Financial Voucher Suite & Bill-by-Bill Allocation
## Technical Specification & Implementation Guide
### Based on Video Walkthrough: `VOUCHER ENTRY 03.mp4`

---

## 1. Domain Overview & Optech Legacy Behavior

In Indian accounting and textile merchant practice, financial transactions are executed via strict double-entry vouchers accessible via the **F9 Key Suite**:
- **F4 (Contra Voucher):** Pure bank/cash movements (e.g., Cash withdrawal from *Tamil Nadu Mercantile Bank*, deposit from counter cash to current account). No third-party party ledgers are permitted.
- **F5 (Payment Voucher):** Outgoing disbursements for supplier bill settlements, transport payments, utility expenses, salaries.
- **F6 (Receipt Voucher):** Incoming collections from customers (cheque, cash, RTGS/NEFT/UPI).
- **F7 (Journal Voucher):** Non-cash adjusting entries (e.g. rate adjustments, quality damage discounts, interest provisions, annual depreciation).
- **Bill-by-Bill Allocation Engine:** When paying a supplier or collecting from a customer, the operator does not simply enter a lump-sum amount. The system triggers a **Bill Allocation Popup**:
  - `Against Reference (Agst Ref):` Lists all unpaid invoices for that party. The operator allocates the collection against specific invoice numbers. The system calculates remaining balance and overdue interest.
  - `New Reference (New Ref):` Used for fresh advance bookings with a unique token reference.
  - `Advance:` Customer advance before invoice generation.
  - `On Account:` Lump-sum collection when bill matching is deferred.

---

## 2. Database Schema Specification

```sql
CREATE TABLE `optech_vouchers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `financial_year_id` BIGINT UNSIGNED NOT NULL,
  `voucher_type` ENUM('CONTRA', 'PAYMENT', 'RECEIPT', 'JOURNAL') NOT NULL,
  `voucher_number` VARCHAR(50) NOT NULL COMMENT 'e.g. RCP-26/0512',
  `voucher_date` DATE NOT NULL,
  `total_debit` DECIMAL(14,2) NOT NULL,
  `total_credit` DECIMAL(14,2) NOT NULL,
  `narration` TEXT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_optech_vouch_no` (`company_id`, `voucher_type`, `voucher_number`),
  KEY `idx_optech_vouch_date` (`voucher_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `optech_voucher_lines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `voucher_id` BIGINT UNSIGNED NOT NULL,
  `entry_type` ENUM('DR', 'CR') NOT NULL,
  `account_id` INT UNSIGNED NOT NULL COMMENT 'Links to chart of accounts / party',
  `amount` DECIMAL(14,2) NOT NULL,
  `line_narration` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `fk_optech_vl_vouch` (`voucher_id`),
  KEY `idx_optech_vl_acc` (`account_id`),
  CONSTRAINT `fk_optech_vl_vouch` FOREIGN KEY (`voucher_id`) REFERENCES `optech_vouchers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `optech_bill_allocations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `voucher_line_id` BIGINT UNSIGNED NOT NULL,
  `party_id` INT UNSIGNED NOT NULL,
  `ref_type` ENUM('AGAINST_REF', 'NEW_REF', 'ADVANCE', 'ON_ACCOUNT') NOT NULL,
  `ref_bill_number` VARCHAR(50) NOT NULL,
  `allocated_amount` DECIMAL(14,2) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_optech_ba_vl` (`voucher_line_id`),
  KEY `idx_optech_ba_ref` (`party_id`, `ref_bill_number`),
  CONSTRAINT `fk_optech_ba_vl` FOREIGN KEY (`voucher_line_id`) REFERENCES `optech_voucher_lines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Double-Entry Balance Invariant Rule

$$\\Delta = \\left| \\sum \\text{Line Amount}_{\\text{DR}} - \\sum \\text{Line Amount}_{\\text{CR}} \\right|$$
**Invariant:** If $\\Delta > 0.00$, the system throws `UnbalancedVoucherException` and rolls back transaction.

---

## 4. Verification Checklist

- [ ] Creating an unbalanced voucher (e.g. Debit = Rs. 10,000, Credit = Rs. 9,500) triggers an immediate blocking error before database commit.
- [ ] Contra voucher rejects any account that is not tagged as Cash or Bank.
- [ ] In Against Reference allocation, allocating Rs. 25,000 against a Rs. 30,000 bill marks Rs. 5,000 as pending balance on customer statement.
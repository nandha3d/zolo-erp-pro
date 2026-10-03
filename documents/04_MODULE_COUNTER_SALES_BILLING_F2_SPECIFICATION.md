# Section 04: High-Speed Counter Sales Billing Engine (F2)
## Technical Specification & Implementation Guide
### Based on Video Walkthrough: `MJ SALES 02.mp4`

---

## 1. Domain Overview & Optech Legacy Behavior

In high-volume textile trade, counter operators bill between 300 and 1,000 invoices per day. Operators **never use the mouse**. The entire billing lifecycle is executed exclusively via physical keyboard accelerators:

- **F2 Global Shortcut:** Opens the Sales Billing Screen from anywhere in the application.
- **Series Selection:** Automatically defaults to the primary sales series (e.g. `SALES MJ-22`).
- **Live Customer Balances in Header:**
  - `Previous Balance (Prv Dr):` Outstanding balance owed by customer prior to this bill.
  - `Current Bill (Cur Dr):` Active running total of items currently in the table.
  - `Total Balance (Total Dr):` Live sum of $(Prv + Cur)$.
- **Spacebar Party Search:** Pressing Spacebar in the customer field opens an instant autocomplete dropdown sorted by city prefix (`ERODE - ...`).
- **Inline Modals Without Loss of Focus:**
  - `Alt+C (New Customer):` Opens a popup modal to create a new customer. Upon saving, drops the new customer into the active invoice row **without clearing typed items**!
  - `Alt+A (Alter Customer):` Opens inline alteration for the active customer to edit phone, transport, or credit limit.
  - `F6 (New Item):` Inline item creation popup if a fabric barcode/code is not found.
  - `Ctrl+S (Previous Customer Rates):` Displays the last 5 invoices where this customer purchased the selected fabric line, showing rates and discounts given.
  - `Ctrl+B (Pending Bills):` Opens a drawer showing unpaid bills for this customer with aging.
  - `Alt+Y (Party Statement):` Launches on-screen ledger statement without quitting the invoice.
- **Real-Time Big Total Banner:** Ultra-prominent gross, tax, and net payable display visible from 10 feet away.
- **Xerox / Copy Bill Function:** Duplicates the item lines of a previous bill number with 1 keystroke.
- **Automated WhatsApp / SMS Toggle:** Checked by default to dispatch digital PDF bill on save.

---

## 2. SalePro Architectural Alignment

Standard SalePro POS is tailored to supermarket scanning with mouse clicks and cart drawers. For Optech compliance:
1. We implement a dedicated high-speed blade view under `Modules/OptechSpeedBilling/Resources/views/counter_sales.blade.php`.
2. We link keyboard listeners using `Hotkeys.js` / `Mousetrap`.
3. Saving a counter sale creates a standard SalePro `Sale` and `ProductSale` record, but enhances it with Optech Series numbering, customer live balance snapshots, and automated ledger postings.

---

## 3. Database Schema Specification

```sql
CREATE TABLE `optech_sales_extension` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_id` INT UNSIGNED NOT NULL COMMENT 'Foreign key to core sales.id',
  `company_id` BIGINT UNSIGNED NOT NULL,
  `series_id` BIGINT UNSIGNED NOT NULL,
  `voucher_no` VARCHAR(50) NOT NULL COMMENT 'Formatted bill no e.g. MJ-22/0142',
  `series_number` INT UNSIGNED NOT NULL COMMENT 'Raw sequence counter',
  `salesman_id` INT UNSIGNED NULL COMMENT 'Sales clerk commission tracking',
  `previous_balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `current_bill_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `closing_balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `transport_agency` VARCHAR(100) NULL,
  `bundles_count` VARCHAR(30) NULL DEFAULT '1 BUNDLE',
  `lr_number` VARCHAR(50) NULL COMMENT 'Lorry Receipt / Parcel tracking no',
  `whatsapp_dispatched` TINYINT(1) NOT NULL DEFAULT 0,
  `print_format_used` ENUM('DOT_MATRIX_ESC_P', 'LASER_A4') NOT NULL DEFAULT 'DOT_MATRIX_ESC_P',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_optech_sale_id` (`sale_id`),
  UNIQUE KEY `idx_optech_series_vouch` (`series_id`, `series_number`),
  CONSTRAINT `fk_optech_sale_ext` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `optech_sales_autosave` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `terminal_ip` VARCHAR(45) NOT NULL,
  `draft_payload` LONGTEXT NOT NULL COMMENT 'JSON snapshot of un-saved counter bill',
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_optech_draft_user` (`user_id`, `terminal_ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Complete Keyboard Shortcut Map

| Shortcut Key | Function Name | Action Description |
| :--- | :--- | :--- |
| **F2** | `New Sales Bill` | Clears table and initializes next sequential bill in active series. |
| **Spacebar** | `Open Party Lookup` | When on Customer field, expands autocomplete city-filtered drawer. |
| **Alt + C** | `Inline Customer Create`| Launches modal to register new party without losing in-progress bill rows. |
| **Alt + A** | `Inline Customer Alter` | Modifies active customer's credit limit, address, or phone. |
| **F6 / Alt + F6**| `Inline Item Create` | Registers new fabric item with HSN, tax rate, and standard cut length. |
| **Ctrl + S** | `Last Sale Rate History`| Opens popup with last 5 sales prices charged to this customer for active item. |
| **Ctrl + B** | `Pending Invoices Drawer`| Displays list of unpaid bills and overdue aging for active customer. |
| **Alt + Y** | `Party Statement` | Opens ledger statement for active customer in popup dialog. |
| **F10 / End** | `Save & Print` | Atomically commits bill, updates stock & ledger, and triggers Dot-Matrix/Laser print. |
| **Alt + X** | `Xerox Bill` | Prompts for previous bill number to duplicate its line items. |
| **F12** | `Switch to Purchase` | Switches from Sales Billing directly to Inward Purchase screen. |
| **F9** | `Switch to Vouchers` | Switches to Double-Entry Financial Voucher Suite. |

---

## 5. High-Speed Counter Frontend Architecture (Vue.js / Vanilla JS)

In `Modules/OptechSpeedBilling/Resources/assets/js/counter_sales.js`:

```javascript
// High-Speed Keyboard-Only Navigation Engine
class OptechBillingEngine {
  constructor() {
    this.items = [];
    this.customer = null;
    this.previousBalance = 0.00;
    this.initKeyboardListeners();
    this.initAutoSave();
  }

  initKeyboardListeners() {
    // Mousetrap / Native keydown binding
    window.addEventListener('keydown', (e) => {
      // F2: New Bill
      if (e.key === 'F2') {
        e.preventDefault();
        this.resetBill();
      }
      // Alt+C: New Customer
      if (e.altKey && (e.key === 'c' || e.key === 'C')) {
        e.preventDefault();
        $('#inlineCustomerModal').modal('show');
      }
      // Alt+A: Alter Customer
      if (e.altKey && (e.key === 'a' || e.key === 'A')) {
        e.preventDefault();
        if (this.customer) this.openAlterCustomerModal(this.customer.id);
      }
      // Ctrl+S: Last Sale History
      if (e.ctrlKey && (e.key === 's' || e.key === 'S')) {
        e.preventDefault();
        this.fetchLastSaleRates();
      }
      // Ctrl+B: Pending Bills
      if (e.ctrlKey && (e.key === 'b' || e.key === 'B')) {
        e.preventDefault();
        this.fetchPendingBills();
      }
      // F10: Save & Print
      if (e.key === 'F10') {
        e.preventDefault();
        this.submitAndPrint();
      }
    });
  }

  calculateTotals() {
    let gross = 0.00;
    let totalCgst = 0.00;
    let totalSgst = 0.00;
    let totalIgst = 0.00;

    const isInterstate = this.customer && this.customer.state_code !== window.OPTECH_COMPANY_STATE;

    this.items.forEach(item => {
      const lineTaxable = (item.rate * item.qty) - (item.discount || 0);
      gross += lineTaxable;

      if (isInterstate) {
        totalIgst += lineTaxable * (item.tax_rate / 100);
      } else {
        totalCgst += lineTaxable * ((item.tax_rate / 2) / 100);
        totalSgst += lineTaxable * ((item.tax_rate / 2) / 100);
      }
    });

    const net = gross + totalCgst + totalSgst + totalIgst;
    const curDr = net;
    const totalDr = this.previousBalance + curDr;

    // Real-Time DOM Update
    document.getElementById('cur_dr_banner').innerText = curDr.toFixed(2);
    document.getElementById('total_dr_banner').innerText = totalDr.toFixed(2);
    document.getElementById('net_payable_big').innerText = net.toFixed(2);
  }

  initAutoSave() {
    setInterval(() => {
      if (this.items.length > 0) {
        localStorage.setItem('optech_sales_draft', JSON.stringify({
          customer: this.customer,
          items: this.items,
          timestamp: Date.now()
        }));
      }
    }, 5000); // Auto-save draft locally every 5 seconds
  }
}
```

---

## 6. Verification Checklist

- [ ] Typing an invoice item row takes `< 30ms` with zero lag on a 100-row wholesale bill.
- [ ] Pressing `Alt+C` creates a customer and immediately updates the active bill without refreshing or losing existing line items.
- [ ] If customer has pending balance, `Prv Dr` banner turns amber/red; saving bill prints combined balance on receipt.
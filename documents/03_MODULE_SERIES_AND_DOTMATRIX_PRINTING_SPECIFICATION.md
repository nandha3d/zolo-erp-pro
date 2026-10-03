# Section 03: Voucher Series & Dot-Matrix Printing Engine
## Technical Specification & Implementation Guide
### Based on Video Walkthrough: `SERIES 04.mp4`

---

## 1. Domain Overview & Optech Legacy Behavior

In the Optech legacy environment:
1. **Series Configuration (Ctrl+F9):** Invoice numbers are not global counters. They belong to isolated series partitioned by transaction type (*Wholesale Sales MJ-22, Retail Cash Sales, Inward Purchase PR-26, Job-Work DC, Service Purchase*).
2. **Automated Numbering Rules:**
   - Prefix (e.g. `MJ-`, `EXP-`, `DC-`)
   - Zero-padded width (e.g. 4 digits: `0001`, `0002` to `9999`)
   - Annual reset: On April 1st of each fiscal year, bill numbers reset automatically to 1.
   - Duplicate prevention: The engine enforces that no duplicate invoice number can ever be saved within the same series and financial year.
3. **Continuous Tractor-Feed Dot-Matrix Printing (ESC/P 68 Lines):**
   - High-volume textile markets rely on continuous stationery Dot-Matrix printers (Epson LX-300, LX-310, LQ-1150) using 2-part and 3-part carbon paper.
   - Laser A4 printing is too slow and expensive for 500 bills/day.
   - **Page Height Invariant:** Standard continuous paper has exactly **68 lines** per form (11 inches at 6 LPI). A laser print driver causes paper creep where each subsequent page starts 1 inch lower until printing misaligns completely. The engine must emit native ASCII ESC/P commands that advance paper to the exact perforation.
4. **Transport & Bundle Notes in Footer:** Bills must print the carrier transport name (e.g. *APS Transport, KRS, MSS*), total bundle/bale counts (e.g. *5 Bundles*), and the company's designated bank account with IFSC code before the signature block.

---

## 2. Database Schema Specification

```sql
CREATE TABLE `optech_voucher_series` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `financial_year_id` BIGINT UNSIGNED NOT NULL,
  `series_name` VARCHAR(50) NOT NULL COMMENT 'e.g. SALES MJ-22, DC GENERAL, CASH RETAIL',
  `transaction_type` ENUM('SALES', 'PURCHASE', 'DELIVERY_CHALLAN', 'GOODS_RECEIVED_NOTE', 'SERVICE_PURCHASE', 'PAYMENT', 'RECEIPT', 'JOURNAL') NOT NULL,
  `prefix` VARCHAR(20) NOT NULL DEFAULT '',
  `suffix` VARCHAR(20) NOT NULL DEFAULT '',
  `zero_padding` TINYINT NOT NULL DEFAULT 4 COMMENT 'e.g. 4 -> 0001',
  `next_number` INT UNSIGNED NOT NULL DEFAULT 1,
  `print_format` ENUM('DOT_MATRIX_ESC_P', 'LASER_A4', 'THERMAL_POS') NOT NULL DEFAULT 'DOT_MATRIX_ESC_P',
  `paper_height_lines` TINYINT NOT NULL DEFAULT 68 COMMENT '68 lines for continuous 11 inch paper at 6 LPI',
  `print_copies` TINYINT NOT NULL DEFAULT 3 COMMENT '1=Original, 2=Duplicate, 3=Triplicate',
  `print_rate_with_tax` TINYINT(1) NOT NULL DEFAULT 0,
  `print_bank_details` TINYINT(1) NOT NULL DEFAULT 1,
  `print_transport_bundles` TINYINT(1) NOT NULL DEFAULT 1,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_optech_series_unique` (`company_id`, `financial_year_id`, `series_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. High-Speed Atomic Number Generator Service

Located in: `Modules/OptechPrinting/Services/VoucherSeriesService.php`

```php
namespace Modules\OptechPrinting\Services;

use Modules\OptechPrinting\Entities\OptechVoucherSeries;
use Illuminate\Support\Facades\DB;
use Exception;

class VoucherSeriesService
{
    /**
     * Atomically increments and allocates the next bill number for a series.
     * Guaranteed thread-safe with row-level lock (FOR UPDATE).
     */
    public function allocateNextNumber(int $seriesId): array
    {
        return DB::transaction(function () use ($seriesId) {
            $series = OptechVoucherSeries::where('id', $seriesId)
                ->lockForUpdate()
                ->firstOrFail();

            $currentNum = $series->next_number;
            $padded = str_pad($currentNum, $series->zero_padding, '0', STR_PAD_LEFT);
            $fullNumber = "{$series->prefix}{$padded}{$series->suffix}";

            // Advance pointer
            $series->next_number = $currentNum + 1;
            $series->save();

            return [
                'raw_number' => $currentNum,
                'formatted_number' => $fullNumber,
                'series_name' => $series->series_name,
                'print_format' => $series->print_format,
                'paper_height_lines' => $series->paper_height_lines
            ];
        });
    }
}
```

---

## 4. Native Dot-Matrix ESC/P Stream Generator

Located in: `Modules/OptechPrinting/Services/DotMatrixEscpGenerator.php`

```php
namespace Modules\OptechPrinting\Services;

class DotMatrixEscpGenerator
{
    const ESC = "\x1B";
    const INITIALIZE = "\x1B\x40";          // ESC @
    const SET_PAGE_LENGTH_68 = "\x1B\x43\x44"; // ESC C 68 (68 lines per page)
    const BOLD_ON = "\x1B\x45";             // ESC E
    const BOLD_OFF = "\x1B\x46";            // ESC F
    const CONDENSED_ON = "\x0F";             // SI (17 CPI condensed font)
    const CONDENSED_OFF = "\x12";            // DC2 (Normal 10 CPI)
    const FORM_FEED = "\x0C";                // FF (Advance to next perforation)

    public function generateSalesInvoiceRawStream(array $billData): string
    {
        $out = "";
        $out .= self::INITIALIZE;
        $out .= self::SET_PAGE_LENGTH_68;
        $out .= self::CONDENSED_ON; // Enables 132 columns on 80-column paper

        // Header
        $out .= str_pad($billData['company_name'], 80, " ", STR_PAD_BOTH) . "\n";
        $out .= str_pad("GSTIN: " . $billData['company_gstin'] . " | State: " . $billData['company_state'], 80, " ", STR_PAD_BOTH) . "\n";
        $out .= str_repeat("=", 80) . "\n";

        // Invoice Meta
        $out .= sprintf("Bill No: %-20s Date: %-15s Mode: %-15s\n", 
            $billData['bill_no'], $billData['date'], $billData['payment_mode']);
        $out .= sprintf("Customer: %-40s GSTIN: %-15s\n", 
            $billData['customer_name'], $billData['customer_gstin'] ?? 'URP');
        $out .= str_repeat("-", 80) . "\n";

        // Table Header
        $out .= sprintf("%-4s %-32s %-8s %-10s %-8s %-12s\n", 
            "S.No", "Fabric Item Description", "Unit", "Rate", "Qty", "Amount");
        $out .= str_repeat("-", 80) . "\n";

        // Item Rows
        foreach ($billData['items'] as $idx => $item) {
            $out .= sprintf("%-4d %-32s %-8s %10.2f %8.3f %12.2f\n",
                $idx + 1,
                substr($item['name'], 0, 32),
                $item['unit'],
                $item['rate'],
                $item['qty'],
                $item['amount']
            );
        }

        $out .= str_repeat("-", 80) . "\n";
        $out .= sprintf("%56s Total: %15.2f\n", "", $billData['gross_total']);
        $out .= sprintf("%56s CGST:  %15.2f\n", "", $billData['cgst_amt']);
        $out .= sprintf("%56s SGST:  %15.2f\n", "", $billData['sgst_amt']);
        $out .= sprintf("%56s NET:   %15.2f\n", "", $billData['net_total']);
        $out .= str_repeat("=", 80) . "\n";

        // Footer Transport and Bank Notes
        $out .= sprintf("Transport: %-30s Bundles/Bales: %-10s\n", 
            $billData['transport'] ?? 'Direct', $billData['bundles'] ?? '1');
        $out .= sprintf("Bank: %s A/c: %s IFSC: %s\n", 
            $billData['bank_name'], $billData['bank_ac'], $billData['bank_ifsc']);

        // Form feed to advance to exact paper perforation
        $out .= self::FORM_FEED;

        return $out;
    }
}
```

---

## 5. Verification Checklist

- [ ] Creating two bills concurrently in the same series never produces identical bill numbers (zero race conditions).
- [ ] Dot-Matrix printing advances to the next page perforation without paper creeping over 50 consecutive printed sheets.
- [ ] Laser A4 print preview renders Original, Duplicate, and Triplicate copies with transport carrier and bank footer details.
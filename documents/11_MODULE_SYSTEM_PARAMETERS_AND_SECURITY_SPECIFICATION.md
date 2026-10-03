# Section 11: System Parameters, Operational Controls & Security
## Technical Specification & Implementation Guide
### Based on Video Walkthrough: `FEATURES 010.mp4`

---

## 1. Domain Overview & Optech Legacy Behavior

In the Optech Express ERP parameters setup:
1. **Data Auto-Lock Date:**
   - A cutoff date (e.g. `31-08-2026`).
   - No user (including supervisors) may add, edit, or delete any voucher, sales invoice, or purchase invoice on or before this lock date.
   - Only the designated System Administrator can temporarily unlock with an administrative override code.
2. **Negative Stock Enforcement:**
   - 3 configurable levels:
     - `ALLOW:` Allows inventory to enter negative numbers without warning.
     - `WARN:` Displays a prompt showing available quantity vs. requested quantity, but allows counter operator to proceed.
     - `BLOCK:` Hard block; prevents saving the invoice line until stock is replenished.
3. **Stock Costing Valuation Logic:**
   - Supports **Average Purchase Rate** (weighted average cost) or **FIFO** (First-In, First-Out) for accurate P&L calculation.
4. **WhatsApp & SMS Gateway Automation:**
   - Automatic bill dispatch to customer mobile via WhatsApp Business Cloud API immediately upon invoice commitment.

---

## 2. Database Schema Specification

```sql
CREATE TABLE `optech_system_parameters` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `data_lock_date` DATE NULL COMMENT 'Transactions on or before this date are frozen',
  `allow_backdated_vouchers` TINYINT(1) NOT NULL DEFAULT 0,
  `max_backdated_days` INT NOT NULL DEFAULT 0,
  `lock_sunday_transactions` TINYINT(1) NOT NULL DEFAULT 0,
  `negative_stock_mode` ENUM('ALLOW', 'WARN', 'BLOCK') NOT NULL DEFAULT 'WARN',
  `stock_valuation_method` ENUM('AVERAGE_PURCHASE_RATE', 'FIFO', 'LAST_PURCHASE_PRICE') NOT NULL DEFAULT 'AVERAGE_PURCHASE_RATE',
  `whatsapp_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `whatsapp_api_endpoint` VARCHAR(255) NULL,
  `whatsapp_api_token` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_optech_param_comp` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Transaction Lock Middleware

Located in: `Modules/OptechSecurity/Http/Middleware/CheckTransactionLockDate.php`

```php
namespace Modules\OptechSecurity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\OptechSecurity\Entities\OptechSystemParameter;
use Exception;

class CheckTransactionLockDate
{
    public function handle(Request $request, Closure $next)
    {
        $companyId = session('optech_active_company_id');
        $params = OptechSystemParameter::where('company_id', $companyId)->first();

        if ($params && $params->data_lock_date) {
            $transDate = $request->input('date') ?? $request->input('voucher_date') ?? now()->toDateString();
            if (strtotime($transDate) <= strtotime($params->data_lock_date)) {
                if (!$request->user()->can('admin_unlock_override')) {
                    throw new Exception("Transaction date ({$transDate}) is locked. Prior period data is frozen as of {$params->data_lock_date}.");
                }
            }
        }

        return $next($request);
    }
}
```

---

## 4. Verification Checklist

- [ ] Operator cannot save or edit any transaction on or before `data_lock_date`.
- [ ] If `negative_stock_mode` is set to `BLOCK`, adding an item with 0 stock blocks saving with an error toast.
- [ ] WhatsApp bill message triggers automatically with downloadable invoice PDF link.
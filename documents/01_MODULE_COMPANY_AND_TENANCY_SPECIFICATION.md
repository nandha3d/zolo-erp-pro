# Section 01: Company Selection & Multi-Tenancy Management
## Technical Specification & Implementation Guide
### Based on Video Walkthrough: `COMPANY 011.mp4`

---

## 1. Domain Overview & Optech Legacy Behavior

In legacy Optech Express ERP:
- **Multiple Business Entities:** A single textile proprietor or partnership frequently operates 3 to 10+ distinct legal business entities (e.g., *MJ Exports*, *Tex Stores*, *Angalamman Textiles*) to segregate wholesale, retail, agency, and job-work processing.
- **Fiscal Year Segmentation:** Each company is partitioned by financial years (e.g., `01-04-2025 To 31-03-2026`, `01-04-2026 To 31-03-2027`). Accounts and inventory balances carry forward on April 1st via automated yearly closing.
- **Server vs. Client Terminal Security:** In high-speed retail counters, multiple billing client machines connect over LAN to a central server machine. 
  - **Server Mode:** Only the server machine has authorization to alter tax masters, delete company profiles, modify voucher series structures, or change financial year dates.
  - **Client Mode:** Client billing terminals are strictly restricted to operational document entry (Sales, Purchase, Payments). They cannot alter company parameters or master tax tables.

---

## 2. zoloERP Pro Architectural Alignment

zoloERP Pro offers multi-warehouse and role-based permissions, and in SaaS configurations, tenant databases via `stancl/tenancy`. For our non-invasive extension:
1. We introduce `optech_companies` and `optech_financial_years` as an organization layer above zoloERP Pro's `warehouses` and `general_settings`.
2. A single physical zoloERP Pro installation supports multiple active companies and fiscal years.
3. Active company and fiscal period are maintained in session state (`session('optech_active_company_id')`, `session('optech_active_fy_id')`).

---

## 3. Database Schema Specification

```sql
CREATE TABLE `optech_companies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_name` VARCHAR(150) NOT NULL COMMENT 'e.g. MJ EXPORTS',
  `trade_name` VARCHAR(150) NULL,
  `legal_status` ENUM('PROPRIETORSHIP', 'PARTNERSHIP', 'PRIVATE_LIMITED', 'LLP') NOT NULL DEFAULT 'PROPRIETORSHIP',
  `gstin` VARCHAR(15) NOT NULL COMMENT '15-digit GST identification number',
  `pan` VARCHAR(10) NOT NULL,
  `state_code` VARCHAR(2) NOT NULL DEFAULT '33' COMMENT 'e.g. 33 for Tamil Nadu, 24 for Gujarat',
  `registered_address` TEXT NOT NULL,
  `city` VARCHAR(50) NOT NULL DEFAULT 'ERODE',
  `pincode` VARCHAR(10) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `email` VARCHAR(100) NULL,
  `bank_name` VARCHAR(100) NULL,
  `bank_branch` VARCHAR(100) NULL,
  `bank_account_no` VARCHAR(50) NULL,
  `bank_ifsc` VARCHAR(20) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_optech_company_gstin` (`gstin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `optech_financial_years` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(50) NOT NULL COMMENT 'e.g. 2026-2027',
  `start_date` DATE NOT NULL COMMENT 'e.g. 2026-04-01',
  `end_date` DATE NOT NULL COMMENT 'e.g. 2027-03-31',
  `is_locked` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Fiscal year closed and audited',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_optech_fy_company` (`company_id`),
  CONSTRAINT `fk_optech_fy_company` FOREIGN KEY (`company_id`) REFERENCES `optech_companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Middleware & Context Switcher Implementation

### Context Middleware: `VerifyOptechCompanySession`
Located in: `Modules/OptechCompany/Http/Middleware/VerifyOptechCompanySession.php`

```php
namespace Modules\OptechCompany\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\OptechCompany\Entities\OptechCompany;
use Modules\OptechCompany\Entities\OptechFinancialYear;

class VerifyOptechCompanySession
{
    public function handle(Request $request, Closure $next)
    {
        if (!session()->has('optech_active_company_id')) {
            $defaultCompany = OptechCompany::where('is_active', 1)->first();
            if ($defaultCompany) {
                session(['optech_active_company_id' => $defaultCompany->id]);
                session(['optech_company_name' => $defaultCompany->company_name]);
                session(['optech_company_gstin' => $defaultCompany->gstin]);
                session(['optech_company_state_code' => $defaultCompany->state_code]);
            }
        }

        if (!session()->has('optech_active_fy_id')) {
            $currentDate = now()->toDateString();
            $fy = OptechFinancialYear::where('company_id', session('optech_active_company_id'))
                ->where('start_date', '<=', $currentDate)
                ->where('end_date', '>=', $currentDate)
                ->first();
            if ($fy) {
                session(['optech_active_fy_id' => $fy->id]);
                session(['optech_fy_title' => $fy->title]);
            }
        }

        return $next($request);
    }
}
```

---

## 5. UI Component: Fast Topbar Company & FY Switcher

In `Modules/OptechCompany/Resources/views/components/company_switcher.blade.php`:

```html
<div class="optech-company-widget" style="display: flex; align-items: center; gap: 8px;">
  <div class="dropdown">
    <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" id="companyDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
      <i class="fa fa-building"></i> {{ session('optech_company_name', 'Select Company') }}
    </button>
    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="companyDropdown">
      <h6 class="dropdown-header">Active Business Entities</h6>
      @foreach(\Modules\OptechCompany\Entities\OptechCompany::where('is_active', 1)->get() as $comp)
        <a class="dropdown-item @if($comp->id == session('optech_active_company_id')) active @endif" 
           href="{{ route('optech.switch.company', $comp->id) }}">
          <strong>{{ $comp->company_name }}</strong> <br>
          <small class="text-muted">GSTIN: {{ $comp->gstin }} (State: {{ $comp->state_code }})</small>
        </a>
      @endforeach
    </div>
  </div>

  <div class="dropdown">
    <button class="btn btn-sm btn-outline-info dropdown-toggle" type="button" id="fyDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
      <i class="fa fa-calendar"></i> FY: {{ session('optech_fy_title', '2026-2027') }}
    </button>
    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="fyDropdown">
      <h6 class="dropdown-header">Financial Years</h6>
      @foreach(\Modules\OptechCompany\Entities\OptechFinancialYear::where('company_id', session('optech_active_company_id'))->get() as $fy)
        <a class="dropdown-item @if($fy->id == session('optech_active_fy_id')) active @endif" 
           href="{{ route('optech.switch.fy', $fy->id) }}">
          {{ $fy->title }} ({{ date('d-m-Y', strtotime($fy->start_date)) }} to {{ date('d-m-Y', strtotime($fy->end_date)) }})
        </a>
      @endforeach
    </div>
  </div>
</div>
```

---

## 6. Verification & Automated Test Checklist

- [ ] Switching active company immediately filters ledgers, sales, purchases, and stock without data leakage.
- [ ] Attempting to record backdated transactions before the financial year start date triggers a blocking validation error.
- [ ] Client terminal sessions running from IP addresses not designated as `Server IP` receive `403 Forbidden` if attempting to access `/optech/company/settings/tax`.
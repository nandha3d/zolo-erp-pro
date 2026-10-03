# Optech Express ERP Modernization Suite
# Complete Master Engineering Reference & Architectural Specification
### Non-Invasive Extension for SalePro ERP (Laravel 10)
### Architecture Doctrine: Zero Core Modification & Isolated Modular Encapsulation

---

## Document Metadata
- **Project Name:** Optech Express ERP Modernization
- **Base Software Platform:** SalePro POS / Inventory / Billing (Laravel 10)
- **Extension Framework:** `nwidart/laravel-modules`
- **Database Namespace:** Isolated `optech_*` Tables & Companion Schemas
- **Domain Focus:** Textile Manufacturing, Wholesale, Processing Job-Work, and High-Speed Counter Distribution
- **Target OS & Printers:** Linux/Windows Server, Epson Continuous Dot-Matrix (ESC/P 68 Lines), Laser A4 Multi-Copy
- **Status:** Production-Ready Engineering Blueprint & Scaffolding Complete

---

## Master Table of Contents
1. [Section 00: The Zero Core Modification Architecture Rules](#section-00-the-zero-core-modification-architecture-rules)
2. [Section 01: Company Selection & Multi-Tenancy Management](#section-01-company-selection--multi-tenancy-management)
3. [Section 02: Textile Master Data Catalog (Items, Units, Ledgers)](#section-02-textile-master-data-catalog)
4. [Section 03: Voucher Series & Dot-Matrix Printing Engine](#section-03-voucher-series--dot-matrix-printing-engine)
5. [Section 04: High-Speed Counter Sales Billing Engine (F2)](#section-04-high-speed-counter-sales-billing-engine-f2)
6. [Section 05: Inward Procurement & Supplier Bill Entry (F12)](#section-05-inward-procurement--supplier-bill-entry-f12)
7. [Section 06: Textile Job-Work Loop (Delivery Challan & Goods Received Note)](#section-06-textile-job-work-loop-dc--grn)
8. [Section 07: Transaction Registry, Orders & Sales/Purchase Returns](#section-07-transaction-registry-orders--salespurchase-returns)
9. [Section 08: Double-Entry Financial Voucher Suite & Bill-by-Bill Allocation](#section-08-double-entry-financial-voucher-suite--bill-by-bill-allocation)
10. [Section 09: General Ledger, 12-Month Calendar Breakup & Financial Statements](#section-09-general-ledger-12-month-calendar-breakup--financial-statements)
11. [Section 10: Automated GST Engine, Statutory Reporting & Stock Analytics](#section-10-automated-gst-engine-statutory-reporting--stock-analytics)
12. [Section 11: System Parameters, Operational Controls & Security](#section-11-system-parameters-operational-controls--security)
13. [Section 12: Unified Database Schema & Migration Data Dictionary](#section-12-unified-database-schema--migration-data-dictionary)
14. [Section 13: Modular Scaffolding, Artisan Generators & Deployment](#section-13-modular-scaffolding-artisan-generators--deployment)

---


# Section 00: The Zero Core Modification Architecture Rules
## Non-Invasive Extension Doctrine for SalePro ERP

---

## 1. The Core Invariant Principle

> **RULE #1:** Under no circumstances shall any developer edit, overwrite, modify, or delete existing core files in:
> - `app/` (Models, Controllers, Requests, Middleware)
> - `database/migrations/` (Core table migrations)
> - `routes/` (`web.php`, `api.php`)
> - `resources/views/` (Except via registered View Hooks or View Composers)
> - `vendor/` (Composer dependencies)

### Why This Rule Is Strict
1. **Vendor Updates:** SalePro releases upstream security patches, feature packs, and bug fixes. Any direct modification to core controllers (`SaleController`, `ProductController`, etc.) will result in merge conflicts or overwritten code during updates.
2. **System Stability:** The existing SalePro logic (POS, standard retail, inventory deductions, permissions) must continue running normally for existing standard tenants.
3. **Module Isolation:** Optech features can be enabled or disabled cleanly per tenant using SalePro's modular license/module toggle mechanism (`general_setting->modules`).

---

## 2. Extension Mechanisms: How We Extend Without Modifying

### Pattern A: Modular Encapsulation via `nwidart/laravel-modules`
SalePro already incorporates `nwidart/laravel-modules` (proven by `Modules/Manufacturing`). All Optech capabilities are packaged inside dedicated modules under `Modules/`:

```
d:/PROJECTS/WEBSITES/salepro-new/Modules/
├── Manufacturing/              # Pre-existing SalePro module
├── OptechJobWork/              # Delivery Challan (DC) & Goods Received Note (GRN)
├── OptechAccounting/           # Vouchers, Bill-by-Bill, General Ledger, 12-Month Matrix
├── OptechGST/                  # Automated GSTIN Lookup, GSTR-1 & GSTR-3B Engines
├── OptechSpeedBilling/         # F2 Counter Sales, F12 Inward Purchase, Keyboard HUD
└── OptechPrinting/             # Continuous Dot-Matrix (ESC/P 68 Lines) & Laser Layouts
```

Each module possesses its own:
- `Config/config.php`
- `Database/Migrations/` (Creating tables with the `optech_` prefix)
- `Entities/` (Eloquent Models extending or decorating core models)
- `Http/Controllers/` (Dedicated controllers handling Optech workflows)
- `Providers/` (ModuleServiceProvider, RouteServiceProvider)
- `Resources/views/` (Custom Blade views using the `module::` namespace)
- `Routes/web.php` (Isolated route groups)

---

### Pattern B: Model Extension via Traits, Macros, and Decorator Pattern
When an Optech module requires relationships to core SalePro models (e.g., `Sale`, `Purchase`, `Product`, `Customer`), **do not edit the model file**. Use Laravel Model Macros or Module Entity Subclasses.

#### Example: Attaching Optech Delivery Challan & Series to Core `Sale` Model
In `Modules/OptechJobWork/Providers/OptechJobWorkServiceProvider.php`:

```php
namespace Modules\OptechJobWork\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Sale;
use Modules\OptechJobWork\Entities\OptechDeliveryChallan;

class OptechJobWorkServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // 1. Dynamic Relationship Macro on core Sale model
        Sale::macro('challans', function () {
            return $this->hasMany(OptechDeliveryChallan::class, 'sale_id', 'id');
        });

        // 2. Dynamic Attribute Macro on core Sale model
        Sale::macro('getIsTextileAttribute', function () {
            return $this->optech_type === 'TEXTILE_FABRIC';
        });
    }
}
```

#### Example: Specialized Entity Extension
Instead of modifying `App\Models\Customer`, create `Modules\OptechSpeedBilling\Entities\OptechCustomer`:

```php
namespace Modules\OptechSpeedBilling\Entities;

use App\Models\Customer;

class OptechCustomer extends Customer
{
    protected $table = 'customers'; // uses same underlying table

    // Add Optech specific scopes and relations
    public function pendingBills()
    {
        return $this->hasMany(\Modules\OptechAccounting\Entities\OptechBillAllocation::class, 'customer_id')
                    ->where('status', 'PENDING');
    }

    public function getLiveBalanceAttribute()
    {
        // Real-time calculation: Previous balance + unallocated vouchers
        $dr = \Modules\OptechAccounting\Entities\OptechVoucherLine::where('account_id', $this->account_id)
            ->where('type', 'DR')->sum('amount');
        $cr = \Modules\OptechAccounting\Entities\OptechVoucherLine::where('account_id', $this->account_id)
            ->where('type', 'CR')->sum('amount');
        return $dr - $cr;
    }
}
```

---

### Pattern C: Database Schema Isolation (`optech_*` Namespace)
To prevent collisions with existing SalePro schema and future core migrations:
1. **Table Prefix:** Every new table must begin with `optech_`.
2. **Nullable Extensions:** If an existing core table (`products`, `customers`, `units`) requires extra Optech attributes, create a companion table (`optech_product_details`, `optech_customer_details`) with a 1-to-1 foreign key, OR run a non-destructive add-column migration strictly inside the module's `Database/Migrations` directory with `nullable()` defaults:

```php
// In Modules/OptechMaster/Database/Migrations/2026_10_03_000001_add_optech_fields_to_products_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'optech_item_type')) {
                $table->enum('optech_item_type', ['GOODS', 'SERVICE'])->default('GOODS')->after('name');
            }
            if (!Schema::hasColumn('products', 'hsn_code')) {
                $table->string('hsn_code', 20)->nullable()->after('code');
            }
            if (!Schema::hasColumn('products', 'rack_location')) {
                $table->string('rack_location', 50)->nullable()->after('alert_quantity');
            }
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['optech_item_type', 'hsn_code', 'rack_location']);
        });
    }
};
```

---

### Pattern D: Event-Driven Decoupling
Core actions trigger domain events. Optech modules listen to these events without touching the core controller:

```
[SalePro SaleController::store]
               |
               v (Fires Event)
      `SaleCreatedEvent`
               |
      +--------+--------------------------+
      |                                   |
      v (Listener in OptechAccounting)    v (Listener in OptechPrinting)
 [GenerateDoubleEntryLedgerVoucher]   [DispatchDotMatrixContinuousPrintJob]
```

---

### Pattern E: UI Injection Without Modifying Core Blade Views
To inject the Optech Top HUD Bar (`F2 Counter Sales`, `F12 Inward`, `F9 Vouchers`, `Spacebar Search`) into SalePro pages without editing `resources/views/backend/layout/main.blade.php`:

1. **Global Middleware Injection:** An Optech HTTP Middleware inspects responses. For HTML responses on backend routes, it injects the lightweight Optech Keyboard HUD and shortcuts script just before `</body>`.
2. **View Composers:** Register a View Composer in `OptechSpeedBillingServiceProvider`:

```php
View::composer('backend.layout.top-head', function ($view) {
    $view->getFactory()->startPush('custom_scripts', view('optechspeedbilling::partials.keyboard_shortcuts'));
    $view->getFactory()->startPush('custom_css', view('optechspeedbilling::partials.keyboard_styles'));
});
```

---

## 3. The 10 Golden Rules for Developers

1. **Never edit `app/Http/Controllers/`:** Always create a controller in `Modules/<ModuleName>/Http/Controllers/`.
2. **Prefix all routes:** Routes must use `optech/` prefix or distinct names (e.g. `route('optech.sales.counter')`).
3. **Strict 3-Decimal Handling:** Currency is always 2 decimals (`DECIMAL(16,2)`), but textile fabric quantities (meters, kilograms, rolls) must use `DECIMAL(12,3)`.
4. **No Raw SQL on Core Tables:** Use Eloquent or query builder via repository interfaces.
5. **Safe Foreign Keys:** When referencing `sales.id` or `purchases.id`, always specify `onDelete('cascade')` or `onDelete('restrict')` explicitly to prevent orphaned ledger records.
6. **Double-Entry Balance Invariant:** In `optech_voucher_lines`, `SUM(debit) === SUM(credit)` for every transaction voucher. No voucher may save if the debit-credit difference exceeds `0.00`.
7. **Zero Network Latency on Counter Bills:** Counter billing (F2) must operate with local cached data (IndexedDB / LocalStorage) so that entering items and calculating totals takes `< 50ms`.
8. **Statutory GST Formula Rigor:** Tax is computed per item line using:
   $$\text{Taxable Value} = (\text{Rate} \times \text{Qty}) - \text{Trade Discount}$$
   $$\text{CGST} = \text{Taxable Value} \times \frac{\text{CGST}\%}{100}$$
   $$\text{SGST} = \text{Taxable Value} \times \frac{\text{SGST}\%}{100}$$
   $$\text{IGST} = \text{Taxable Value} \times \frac{\text{IGST}\%}{100}$$
9. **Printer Port Compatibility:** ESC/P dot-matrix printing must produce raw ASCII/printer command streams compatible with standard 80-column and 132-column tractor-feed printers without depending on browser PDF rendering.
10. **Automated Rollback on Error:** Every transaction spanning multi-table updates (e.g., Sale + Ledger + Bill Allocation + Stock) must be wrapped in `DB::transaction(function() { ... })`.


---


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

## 2. SalePro Architectural Alignment

SalePro offers multi-warehouse and role-based permissions, and in SaaS configurations, tenant databases via `stancl/tenancy`. For our non-invasive extension:
1. We introduce `optech_companies` and `optech_financial_years` as an organization layer above SalePro's `warehouses` and `general_settings`.
2. A single physical SalePro installation supports multiple active companies and fiscal years.
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


---


# Section 02: Textile Master Data Catalog
## Technical Specification & Implementation Guide
### Based on Video Walkthrough: `MASTER 07.mp4`

---

## 1. Domain Overview & Optech Legacy Behavior

In textile manufacturing and distribution hubs:
1. **Goods vs. Services in Item Master:** The product master manages both physical fabrics (*1001 Mull Grey, 40s Combed Cotton, Satin Bleached Bedspread*) and job-work processing services (*Bleaching Charges, Dyeing Charges, Ironing & Folding Charges, Stitching Charges*). Service items do not track physical inventory stock but carry tax slabs (typically 5% or 12%) and link to job-work billing.
2. **3-Decimal Precision for Units:** While standard retail uses integer pieces, fabrics are measured in meters with 3 decimal places (e.g. `142.375 MTR`, `25.500 KG`). SalePro's default 2 decimals causes severe truncation losses across 10,000-meter consignments.
3. **Regional City-Prefixed Ledgers:** Over decades, textile operators in Erode/Tirupur organize Sundry Debtors and Creditors with their town prefix (*ERODE - BAPNA TEXTILES*, *ERNAKULAM - ATM TEX*, *SURAT - RADHEY SILK*). When an operator presses the spacebar at counter billing, typing `ERO` or `SUR` instantly narrows 2,000 parties down to the relevant local market.
4. **Textile Classification Groups:** Products are categorized into fabric varieties (*Dhotis, Bedspreads, Mull, Grey Cloth, Bleached Cloth, Towels*) so that stock registers can be filtered in 1 click by fabric line.

---

## 2. SalePro Architectural Extension

Without modifying SalePro's `products`, `units`, or `customers` tables:
- We create companion table `optech_product_attributes` linked to `products.id`.
- We add unit precision attributes supporting 3 decimals.
- We implement standardized customer/supplier ledger decorators that enforce market/city prefixes and credit limits.

---

## 3. Database Schema Specification

```sql
CREATE TABLE `optech_product_attributes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `item_type` ENUM('GOODS', 'SERVICE') NOT NULL DEFAULT 'GOODS' COMMENT 'Goods track physical inventory; Services track job charges',
  `hsn_code` VARCHAR(20) NOT NULL COMMENT 'e.g. 5208 for woven cotton fabric, 9988 for job-work manufacturing services',
  `tax_slab` DECIMAL(5,2) NOT NULL DEFAULT 5.00 COMMENT '5.00, 12.00, 18.00, 28.00, 0.00',
  `fabric_group` VARCHAR(100) NULL COMMENT 'e.g. Bedspreads, Dhotis, Mull, Bleached, Grey',
  `cut_piece_available` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = sold in variable cut meter lengths',
  `rolls_tracking` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = tracked per roll/thaan number',
  `standard_cut_length` DECIMAL(8,3) NULL DEFAULT 0.000 COMMENT 'Standard meter length per piece',
  `rack_location` VARCHAR(50) NULL COMMENT 'Godown / Rack / Shelf ID',
  `default_job_rate` DECIMAL(12,2) NULL DEFAULT 0.00 COMMENT 'Default job charges per meter',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_optech_prod_attr` (`product_id`),
  CONSTRAINT `fk_optech_prod_attr` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `optech_units_extension` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` INT UNSIGNED NOT NULL,
  `gst_uqc` VARCHAR(10) NOT NULL COMMENT 'Government GST Unique Quantity Code: MTR, PCS, KGS, DOZ, THD',
  `decimal_places` TINYINT NOT NULL DEFAULT 3 COMMENT '3 decimals for meters and kilograms (0.000)',
  `conversion_factor` DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_optech_unit_id` (`unit_id`),
  CONSTRAINT `fk_optech_unit_ext` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `optech_party_attributes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `party_type` ENUM('CUSTOMER', 'SUPPLIER', 'JOB_WORKER') NOT NULL,
  `reference_id` INT UNSIGNED NOT NULL COMMENT 'Links to customers.id or suppliers.id',
  `city_market_prefix` VARCHAR(50) NOT NULL COMMENT 'e.g. ERODE, ERNAKULAM, SURAT, COIMBATORE',
  `formatted_display_name` VARCHAR(200) NOT NULL COMMENT 'e.g. ERODE - BAPNA TEXTILES',
  `gstin` VARCHAR(15) NULL,
  `pan` VARCHAR(10) NULL,
  `state_code` VARCHAR(2) NOT NULL DEFAULT '33',
  `credit_limit` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `credit_days` INT NOT NULL DEFAULT 30,
  `transport_agency` VARCHAR(100) NULL COMMENT 'Default transport parcel service: APS, KRS, MSS',
  `whatsapp_number` VARCHAR(20) NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_optech_party_prefix` (`city_market_prefix`),
  KEY `idx_optech_party_ref` (`party_type`, `reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Eloquent Decorator & Service Contract

Located in: `Modules/OptechMaster/Services/TextileMasterService.php`

```php
namespace Modules\OptechMaster\Services;

use App\Models\Product;
use App\Models\Customer;
use Modules\OptechMaster\Entities\OptechProductAttribute;
use Modules\OptechMaster\Entities\OptechPartyAttribute;
use Illuminate\Support\Facades\DB;

class TextileMasterService
{
    public function createTextileProduct(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            // 1. Create standard SalePro Product
            $product = new Product();
            $product->name = $data['name'];
            $product->code = $data['code'];
            $product->type = 'standard';
            $product->barcode_symbology = 'code128';
            $product->category_id = $data['category_id'];
            $product->unit_id = $data['unit_id'];
            $product->cost = $data['cost'];
            $product->price = $data['price'];
            $product->alert_quantity = $data['alert_quantity'] ?? 0;
            $product->save();

            // 2. Attach Optech Textile Attributes
            $attr = new OptechProductAttribute();
            $attr->product_id = $product->id;
            $attr->item_type = $data['item_type'] ?? 'GOODS';
            $attr->hsn_code = $data['hsn_code'];
            $attr->tax_slab = $data['tax_slab'] ?? 5.00;
            $attr->fabric_group = $data['fabric_group'] ?? 'General';
            $attr->cut_piece_available = $data['cut_piece_available'] ?? 0;
            $attr->rolls_tracking = $data['rolls_tracking'] ?? 0;
            $attr->rack_location = $data['rack_location'] ?? null;
            $attr->default_job_rate = $data['default_job_rate'] ?? 0.00;
            $attr->save();

            return $product;
        });
    }

    public function formatPartyName(string $city, string $tradeName): string
    {
        $city = strtoupper(trim($city));
        $tradeName = strtoupper(trim($tradeName));
        return "{$city} - {$tradeName}";
    }
}
```

---

## 5. Verification Checklist

- [ ] Creating an item with 3-decimal meter quantities (e.g. `125.750`) saves and displays without rounding to `125.75` or `126.00`.
- [ ] Service items (e.g. *Bleaching Charges*) can be added to invoices without decreasing physical inventory stock.
- [ ] In party search autocomplete, typing city prefixes like `ERODE` returns all Erode parties sorted alphabetically with their live debit/credit balance.


---


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


---


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


---


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


---


# Section 06: Textile Job-Work Loop (DC & GRN)
## Technical Specification & Implementation Guide
### Based on Video Walkthrough: `DC AND GRN 05.mp4`

---

## 1. Domain Overview & Optech Legacy Behavior

In the textile manufacturing hub of Tamil Nadu, goods undergo external specialized processing:
$$\\text{Grey Fabric (Mulls/Yarn)} \\xrightarrow{\\text{Delivery Challan (DC)}} \\text{Processing Factory (Bleaching/Dyeing/Ironing/Centering)} \\xrightarrow{\\text{Goods Received Note (GRN)}} \\text{Finished Fabric (Bedspreads/Dhotis)}$$

### Critical Domain Invariants:
1. **Non-Commercial Tax Movement:** An outward Delivery Challan is **NOT** a sale. No sales tax (GST) is levied when fabric leaves for a processing mill. Under Section 143 of the CGST Act, goods may be moved to a job worker under a Delivery Challan without paying tax, provided they return within 1 year.
2. **Outside Stock Godown Tracking:** When 5,000 meters of Grey Mull are sent to *Radhakrishna Bleaching Works* on a DC, inventory drops from *Main Godown* and increases in *Jobwork Outside Godown (Radhakrishna Bleaching)*.
3. **Goods Received Note (GRN):** When processed goods return, a GRN is recorded noting the inward quantity, shrinkage/wastage percentage (typically 2% to 4%), and received finished pieces.
4. **Triangular Reconciliation:** The system links **DC No** $\\rightarrow$ **GRN No** $\\rightarrow$ **Service Purchase Bill**. When the dyer submits their tax invoice for processing charges (e.g. 5,000 meters at Rs. 2.50/meter = Rs. 12,500 + 5% GST), the invoice is booked under Service Purchase, relieving the GRN without altering fabric inventory quantity a second time.

---

## 2. Database Schema Specification

```sql
CREATE TABLE `optech_delivery_challans` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `financial_year_id` BIGINT UNSIGNED NOT NULL,
  `challan_no` VARCHAR(50) NOT NULL COMMENT 'e.g. DC-26/0084',
  `challan_date` DATE NOT NULL,
  `job_worker_id` INT UNSIGNED NOT NULL COMMENT 'Links to suppliers/job workers',
  `process_type` ENUM('BLEACHING', 'DYEING', 'PRINTING', 'IRONING', 'CENTERING', 'STITCHING') NOT NULL,
  `from_warehouse_id` INT UNSIGNED NOT NULL COMMENT 'Origin raw godown',
  `to_jobworker_warehouse_id` INT UNSIGNED NOT NULL COMMENT 'Virtual outside godown',
  `transport_agency` VARCHAR(100) NULL,
  `vehicle_no` VARCHAR(30) NULL,
  `total_meters_dispatched` DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `total_pieces_dispatched` INT UNSIGNED NOT NULL DEFAULT 0,
  `expected_return_date` DATE NULL,
  `status` ENUM('OPEN', 'PARTIALLY_RECEIVED', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'OPEN',
  `remarks` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_optech_dc_no` (`company_id`, `challan_no`),
  KEY `idx_optech_dc_worker` (`job_worker_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `optech_delivery_challan_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `challan_id` BIGINT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL COMMENT 'Raw grey fabric',
  `hsn_code` VARCHAR(20) NOT NULL,
  `dispatched_qty` DECIMAL(12,3) NOT NULL,
  `unit_id` INT UNSIGNED NOT NULL,
  `rate_per_unit` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Notional cost for insurance',
  `received_qty` DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `pending_qty` DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  PRIMARY KEY (`id`),
  KEY `fk_optech_dc_items` (`challan_id`),
  CONSTRAINT `fk_optech_dc_items` FOREIGN KEY (`challan_id`) REFERENCES `optech_delivery_challans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `optech_goods_received_notes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `financial_year_id` BIGINT UNSIGNED NOT NULL,
  `grn_no` VARCHAR(50) NOT NULL COMMENT 'e.g. GRN-26/0045',
  `grn_date` DATE NOT NULL,
  `challan_id` BIGINT UNSIGNED NOT NULL COMMENT 'Origin DC reference',
  `job_worker_id` INT UNSIGNED NOT NULL,
  `to_finished_warehouse_id` INT UNSIGNED NOT NULL COMMENT 'Destination finished godown',
  `received_meters` DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `wastage_shrinkage_meters` DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `process_charge_per_meter` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `estimated_service_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `is_invoiced` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Linked to Service Purchase Bill',
  `service_purchase_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_optech_grn_no` (`company_id`, `grn_no`),
  KEY `idx_optech_grn_dc` (`challan_id`),
  CONSTRAINT `fk_optech_grn_dc` FOREIGN KEY (`challan_id`) REFERENCES `optech_delivery_challans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Reconciliation State Machine

```
              +--------------------------+
              | 1. Issue Outward DC      |  Inventory moves from Raw Godown
              |    (Status: OPEN)        |  to "Jobworker Outside Godown"
              +--------------------------+
                           |
                           v
              +--------------------------+
              | 2. Inward GRN Receipt    |  Processed cloth returns.
              |    (Status: PARTIAL/DONE)|  Moves to "Finished Godown".
              +--------------------------+
                           |
                           v
              +--------------------------+
              | 3. Service Purchase Bill |  Jobworker submits invoice for charges.
              |    (F12 Service Type)    |  Books processing expense & GST.
              +--------------------------+  No duplicate stock addition!
```

---

## 4. Verification Checklist

- [ ] Creating an outward DC decreases available stock in Main Godown and increases stock in the job-worker's virtual warehouse.
- [ ] No sales tax or GST invoice is generated upon DC issuance.
- [ ] When recording a GRN, specifying `4,850 MTR` received with `150 MTR` shrinkage closes out a `5,000 MTR` DC cleanly.
- [ ] Booking the job-worker's service invoice links the GRN without double-counting fabric inventory.


---


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


---


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


---


# Section 09: General Ledger, 12-Month Calendar Breakup & Financial Statements
## Technical Specification & Implementation Guide
### Based on Video Walkthrough: `ACCOUNTS 08.mp4`

---

## 1. Domain Overview & Optech Legacy Behavior

In Optech Express ERP:
- **Ctrl+F5 Ledger Shortcut:** Instantly launches the General Ledger screen from anywhere.
- **12-Month Calendar Matrix (April to March):**
  - Displays a 12-row calendar grid representing each month of the Indian fiscal year (April, May, June... March).
  - Columns: `Month`, `Monthly Debit Total`, `Monthly Credit Total`, and `Closing Balance`.
  - **Click-Through Drilldown:** Clicking on any month (e.g. *August 2026*) immediately expands the detailed transaction ledger for that specific month. Clicking any transaction row opens the original voucher or invoice!
- **Day Book & Cash Book:** Real-time daily timeline of all cash and bank movements with running cash-in-hand balance.
- **Financial Statements:** Instant Trial Balance, Profit & Loss, and Balance Sheet derived strictly from double-entry voucher lines.

---

## 2. Mathematical 12-Month Breakup Aggregator Engine

Located in: `Modules/OptechAccounting/Services/LedgerReportService.php`

```php
namespace Modules\OptechAccounting\Services;

use Illuminate\Support\Facades\DB;

class LedgerReportService
{
    public function get12MonthBreakup(int $accountId, int $financialYearId): array
    {
        $months = [
            ['name' => 'April',     'm' => 4],
            ['name' => 'May',       'm' => 5],
            ['name' => 'June',      'm' => 6],
            ['name' => 'July',      'm' => 7],
            ['name' => 'August',    'm' => 8],
            ['name' => 'September', 'm' => 9],
            ['name' => 'October',   'm' => 10],
            ['name' => 'November',  'm' => 11],
            ['name' => 'December',  'm' => 12],
            ['name' => 'January',   'm' => 1],
            ['name' => 'February',  'm' => 2],
            ['name' => 'March',     'm' => 3],
        ];

        // Fetch opening balance
        $openingBalance = $this->getOpeningBalance($accountId, $financialYearId);
        $runningBalance = $openingBalance;

        $results = [];
        foreach ($months as $mo) {
            $monthData = DB::table('optech_voucher_lines as vl')
                ->join('optech_vouchers as v', 'vl.voucher_id', '=', 'v.id')
                ->where('vl.account_id', $accountId)
                ->where('v.financial_year_id', $financialYearId)
                ->whereMonth('v.voucher_date', $mo['m'])
                ->selectRaw("
                    SUM(CASE WHEN vl.entry_type = 'DR' THEN vl.amount ELSE 0 END) as total_debit,
                    SUM(CASE WHEN vl.entry_type = 'CR' THEN vl.amount ELSE 0 END) as total_credit
                ")->first();

            $dr = (float)($monthData->total_debit ?? 0.00);
            $cr = (float)($monthData->total_credit ?? 0.00);
            $runningBalance += ($dr - $cr);

            $results[] = [
                'month_name' => $mo['name'],
                'month_num' => $mo['m'],
                'debit' => $dr,
                'credit' => $cr,
                'closing_balance' => $runningBalance,
                'balance_type' => $runningBalance >= 0 ? 'Dr' : 'Cr'
            ];
        }

        return [
            'account_id' => $accountId,
            'opening_balance' => $openingBalance,
            'monthly_rows' => $results,
            'final_balance' => $runningBalance
        ];
    }
}
```

---

## 3. Verification Checklist

- [ ] Sum of monthly debits and credits across all 12 months exactly equals the fiscal year total on Trial Balance.
- [ ] Clicking any month expands the Day Book entries for that month without full page reload.


---


# Section 10: Automated GST Engine, Statutory Reporting & Stock Analytics
## Technical Specification & Implementation Guide
### Based on Video Walkthrough: `REPORT 09.mp4`

---

## 1. Domain Overview & Optech Legacy Behavior

Compliance with the Goods and Services Tax (GST) in India requires automated reconciliation:
1. **1-Click GSTIN Auto-Fetch API:**
   - Operators type a 15-digit GSTIN (e.g. `33AAAAA0000A1Z5`).
   - The engine queries the GST Public Portal API, instantly returning:
     - Legal Business Name & Trade Name
     - Registered Address, City, State Code
     - Active / Suspended Taxpayer Status
     - Composition vs. Regular Taxpayer Flag
   - Pre-fills customer/supplier creation in `< 500ms`, eliminating manual typing errors.
2. **Statutory GSTR-1 Generation:**
   - **Table 4 (B2B Invoices):** Regular taxable sales to registered businesses with customer GSTIN, invoice number, taxable value, and tax split.
   - **Table 7 (B2C Small):** Consolidated net taxable sales to unregistered counter customers.
   - **Table 9B (Credit / Debit Notes):** Sales return credit notes.
   - **Table 12 (HSN/SAC Summary):** Fabric line HSN codes, UQC unit, quantity, taxable value, and integrated tax.
   - **Government Portal Format:** 1-click export to CSV / Excel matching the exact column layout required by the GST Offline Tool.
3. **Multi-Dimensional Stock Reports (F7):**
   - Item-wise, HSN-wise, Group-wise, and Brand-wise stock quantities.
   - Negative Stock Highlight (red indicator for godowns with oversold stock).
   - Moving vs. Non-Moving Stock aging analysis (items with zero transactions in 60/90 days).

---

## 2. GSTIN 1-Click Auto-Fetch Service Architecture

Located in: `Modules/OptechGST/Services/GstinLookupService.php`

```php
namespace Modules\OptechGST\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

class GstinLookupService
{
    protected string $apiKey;
    protected string $apiBaseUrl;

    public function __construct()
    {
        $this->apiKey = config('optechgst.api_key', '');
        $this->apiBaseUrl = config('optechgst.api_url', 'https://sheet.gstincheck.co.in/check/');
    }

    public function lookup(string $gstin): array
    {
        $gstin = strtoupper(trim($gstin));
        if (strlen($gstin) !== 15) {
            throw new Exception("Invalid GSTIN length. Must be exactly 15 alphanumeric characters.");
        }

        return Cache::remember("gstin_{$gstin}", 86400 * 7, function () use ($gstin) {
            $response = Http::timeout(5)->get("{$this->apiBaseUrl}{$this->apiKey}/{$gstin}");
            if ($response->failed()) {
                throw new Exception("GSTIN lookup service unavailable or timed out.");
            }

            $data = $response->json();
            if (!isset($data['flag']) || $data['flag'] !== true) {
                throw new Exception($data['message'] ?? 'GSTIN not found or invalid.');
            }

            $taxpayer = $data['data'];
            return [
                'gstin' => $gstin,
                'legal_name' => $taxpayer['lgnm'] ?? '',
                'trade_name' => $taxpayer['tradeNam'] ?? $taxpayer['lgnm'] ?? '',
                'state_code' => substr($gstin, 0, 2),
                'status' => $taxpayer['sts'] ?? 'Active',
                'taxpayer_type' => $taxpayer['dty'] ?? 'Regular',
                'address' => implode(', ', array_filter([
                    $taxpayer['pradr']['addr']['bno'] ?? null,
                    $taxpayer['pradr']['addr']['st'] ?? null,
                    $taxpayer['pradr']['addr']['loc'] ?? null,
                    $taxpayer['pradr']['addr']['dst'] ?? null,
                    $taxpayer['pradr']['addr']['pncd'] ?? null,
                ]))
            ];
        });
    }
}
```

---

## 3. Statutory GSTR-1 Schema Aggregator

```
B2B (Table 4A, 4B, 6B, 6C):
  Columns: [GSTIN/UIN of Recipient, Receiver Name, Invoice Number, Invoice date, 
            Invoice Value, Place Of Supply, Reverse Charge, Applicable % of Tax Rate, 
            Invoice Type, E-Commerce GSTIN, Rate, Taxable Value, Cess Amount]

HSN (Table 12):
  Columns: [HSN, Description, UQC, Total Quantity, Total Value, Taxable Value, 
            Integrated Tax Amount, Central Tax Amount, State/UT Tax Amount, Cess Amount]
```

---

## 4. Verification Checklist

- [ ] Entering valid GSTIN auto-fills customer legal name, address, state code in `< 500ms`.
- [ ] GSTR-1 CSV export matches government offline tool column sequence without manual editing.
- [ ] Stock report marks items with negative inventory in prominent red warning banner.


---


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


---


# Section 12: Unified Database Schema & Migration Data Dictionary
## Complete Physical Schema Specification for the `optech_*` Tables

---

## 1. Schema Design Strategy

In accordance with the **Zero Core Modification Doctrine**:
- Every Optech table is strictly prefixed with `optech_`.
- All foreign keys to core SalePro tables (`products`, `customers`, `suppliers`, `sales`, `purchases`, `units`, `warehouses`, `users`) use standard indexing and cascade rules.
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


---


# Section 13: Modular Scaffolding, Artisan Generators & Deployment
## Implementation Guide for `nwidart/laravel-modules`

---

## 1. Modular Directory Layout

The Optech extensions are scaffolded into 6 targeted modules:

```
d:/PROJECTS/WEBSITES/salepro-new/Modules/
│
├── OptechCompany/              # Multi-Company & FY Switching
│   ├── Config/
│   ├── Database/Migrations/
│   ├── Entities/
│   ├── Http/Controllers/
│   ├── Providers/
│   └── Routes/web.php
│
├── OptechMaster/               # Textile Item Master, 3-Decimals & City Ledgers
│   ├── Database/Migrations/
│   ├── Entities/
│   └── Services/TextileMasterService.php
│
├── OptechSpeedBilling/         # Counter Sales (F2), Inward Purchase (F12) & Keyboard HUD
│   ├── Http/Controllers/CounterSalesController.php
│   ├── Http/Controllers/InwardPurchaseController.php
│   ├── Resources/views/counter_sales.blade.php
│   └── Resources/assets/js/keyboard_engine.js
│
├── OptechJobWork/              # Delivery Challan (DC) & Goods Received Note (GRN)
│   ├── Http/Controllers/DeliveryChallanController.php
│   ├── Http/Controllers/GoodsReceivedNoteController.php
│   └── Services/JobWorkReconciliationService.php
│
├── OptechAccounting/           # Vouchers (F4-F7), Bill-by-Bill, 12-Month Matrix
│   ├── Http/Controllers/VoucherEntryController.php
│   ├── Http/Controllers/LedgerReportController.php
│   └── Services/BillAllocationService.php
│
├── OptechGST/                  # 1-Click GSTIN Auto-Fetch, GSTR-1, GSTR-3B
│   ├── Http/Controllers/GstReportController.php
│   └── Services/GstinLookupService.php
│
└── OptechPrinting/             # Continuous Dot-Matrix (ESC/P 68 Lines) & Laser A4
    ├── Services/VoucherSeriesService.php
    └── Services/DotMatrixEscpGenerator.php
```

---

## 2. Artisan Module Scaffolding Commands

Execute the following commands to generate the scaffolding:

```bash
# 1. Generate Modules via nwidart/laravel-modules
php artisan module:make OptechCompany
php artisan module:make OptechMaster
php artisan module:make OptechSpeedBilling
php artisan module:make OptechJobWork
php artisan module:make OptechAccounting
php artisan module:make OptechGST
php artisan module:make OptechPrinting

# 2. Run Module Migrations (creates optech_* tables without touching core tables)
php artisan module:migrate OptechCompany
php artisan module:migrate OptechMaster
php artisan module:migrate OptechSpeedBilling
php artisan module:migrate OptechJobWork
php artisan module:migrate OptechAccounting
php artisan module:migrate OptechGST
php artisan module:migrate OptechPrinting

# 3. Compile Modular Assets
npm run dev
```

---

## 3. Activation in `general_settings`

In SalePro, modules are activated via the `modules` column in `general_settings`:

```php
// In a dedicated seeder: Modules/OptechCompany/Database/Seeders/ActivateOptechModulesSeeder.php
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ActivateOptechModulesSeeder extends Seeder
{
    public function run()
    {
        $setting = DB::table('general_settings')->first();
        if ($setting) {
            $existing = explode(',', $setting->modules ?? '');
            $optechModules = [
                'optech_company',
                'optech_master',
                'optech_speed_billing',
                'optech_job_work',
                'optech_accounting',
                'optech_gst',
                'optech_printing'
            ];
            $merged = array_unique(array_filter(array_merge($existing, $optechModules)));
            DB::table('general_settings')
                ->where('id', $setting->id)
                ->update(['modules' => implode(',', $merged)]);
        }
    }
}
```

---

## 4. Production Deployment & Verification Sequence

1. **Database Backup:** Run full MySQL dump of existing SalePro database.
2. **Migration Run:** Execute `php artisan module:migrate`. All new tables are prefixed with `optech_`.
3. **Cache Clearing:**
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```
4. **Smoke Testing Checklist:**
   - [ ] Core POS (`/pos`) remains 100% operational with existing functionality.
   - [ ] Pressing `F2` anywhere opens the high-speed counter sales bill.
   - [ ] Creating customer with 15-digit GSTIN auto-fetches trade name and address.
   - [ ] Issuing outward DC moves fabric to outside godown without generating tax invoice.
   - [ ] Generating Dot-Matrix print job produces raw 68-line form-feed stream.


---


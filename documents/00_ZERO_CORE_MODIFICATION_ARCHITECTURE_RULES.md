# Section 00: The Zero Core Modification Architecture Rules
## Non-Invasive Extension Doctrine for zoloERP Pro ERP

---

## 1. The Core Invariant Principle

> **RULE #1:** Under no circumstances shall any developer edit, overwrite, modify, or delete existing core files in:
> - `app/` (Models, Controllers, Requests, Middleware)
> - `database/migrations/` (Core table migrations)
> - `routes/` (`web.php`, `api.php`)
> - `resources/views/` (Except via registered View Hooks or View Composers)
> - `vendor/` (Composer dependencies)

### Why This Rule Is Strict
1. **Vendor Updates:** zoloERP Pro releases upstream security patches, feature packs, and bug fixes. Any direct modification to core controllers (`SaleController`, `ProductController`, etc.) will result in merge conflicts or overwritten code during updates.
2. **System Stability:** The existing zoloERP Pro logic (POS, standard retail, inventory deductions, permissions) must continue running normally for existing standard tenants.
3. **Module Isolation:** Optech features can be enabled or disabled cleanly per tenant using zoloERP Pro's modular license/module toggle mechanism (`general_setting->modules`).

---

## 2. Extension Mechanisms: How We Extend Without Modifying

### Pattern A: Modular Encapsulation via `nwidart/laravel-modules`
zoloERP Pro already incorporates `nwidart/laravel-modules` (proven by `Modules/Manufacturing`). All Optech capabilities are packaged inside dedicated modules under `Modules/`:

```
d:/PROJECTS/WEBSITES/zolo-erp-pro/Modules/
├── Manufacturing/              # Pre-existing zoloERP Pro module
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
When an Optech module requires relationships to core zoloERP Pro models (e.g., `Sale`, `Purchase`, `Product`, `Customer`), **do not edit the model file**. Use Laravel Model Macros or Module Entity Subclasses.

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
To prevent collisions with existing zoloERP Pro schema and future core migrations:
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
[zoloERP Pro SaleController::store]
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
To inject the Optech Top HUD Bar (`F2 Counter Sales`, `F12 Inward`, `F9 Vouchers`, `Spacebar Search`) into zoloERP Pro pages without editing `resources/views/backend/layout/main.blade.php`:

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
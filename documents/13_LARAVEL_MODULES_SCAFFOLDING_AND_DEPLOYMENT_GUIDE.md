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
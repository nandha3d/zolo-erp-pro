# Section 02: Textile Master Data Catalog
## Technical Specification & Implementation Guide
### Based on Video Walkthrough: `MASTER 07.mp4`

---

## 1. Domain Overview & Optech Legacy Behavior

In textile manufacturing and distribution hubs:
1. **Goods vs. Services in Item Master:** The product master manages both physical fabrics (*1001 Mull Grey, 40s Combed Cotton, Satin Bleached Bedspread*) and job-work processing services (*Bleaching Charges, Dyeing Charges, Ironing & Folding Charges, Stitching Charges*). Service items do not track physical inventory stock but carry tax slabs (typically 5% or 12%) and link to job-work billing.
2. **3-Decimal Precision for Units:** While standard retail uses integer pieces, fabrics are measured in meters with 3 decimal places (e.g. `142.375 MTR`, `25.500 KG`). zoloERP Pro's default 2 decimals causes severe truncation losses across 10,000-meter consignments.
3. **Regional City-Prefixed Ledgers:** Over decades, textile operators in Erode/Tirupur organize Sundry Debtors and Creditors with their town prefix (*ERODE - BAPNA TEXTILES*, *ERNAKULAM - ATM TEX*, *SURAT - RADHEY SILK*). When an operator presses the spacebar at counter billing, typing `ERO` or `SUR` instantly narrows 2,000 parties down to the relevant local market.
4. **Textile Classification Groups:** Products are categorized into fabric varieties (*Dhotis, Bedspreads, Mull, Grey Cloth, Bleached Cloth, Towels*) so that stock registers can be filtered in 1 click by fabric line.

---

## 2. zoloERP Pro Architectural Extension

Without modifying zoloERP Pro's `products`, `units`, or `customers` tables:
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
            // 1. Create standard zoloERP Pro Product
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
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
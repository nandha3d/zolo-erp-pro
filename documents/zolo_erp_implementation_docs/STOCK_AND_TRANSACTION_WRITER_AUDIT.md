# Stock and transaction writer audit

Source baseline: `edecfa1`, 2026-10-03. Supports documents 01, 02, 09 and 26. This inventory identifies migration boundaries, not verified production usage.

## Current authorities

Commercial documents use existing `sales`/`product_sales`, `purchases`/`product_purchases`, `payments`, and `transfers`/`product_transfer` tables. API V1 delegates to `app/Services/ERP`; web controllers implement separate logic. Quantity history has no shared movement ledger: `products.qty`, warehouse, variant and batch quantities are directly mutated. `AccountingService` owns journal/header/line writes and cached account balances. Seven Optech modules are largely scaffolds; Manufacturing contains real production behavior.

## Persisted inventory writers

Convert generic ERP services first, legacy writers second, then operational modules. Review create, import, edit, delete and reversal paths in each file.

- `app/Services/ERP/SaleService.php`
- `app/Services/ERP/PurchaseService.php`
- `app/Services/ERP/InventoryService.php`
- `app/Console/Commands/AutoPurchase.php`
- `app/Http/Controllers/SaleController.php`
- `app/Http/Controllers/PurchaseController.php`
- `app/Http/Controllers/TransferController.php`
- `app/Http/Controllers/AdjustmentController.php`
- `app/Http/Controllers/ReturnController.php`
- `app/Http/Controllers/ReturnPurchaseController.php`
- `app/Http/Controllers/PackingSlipController.php`
- `app/Http/Controllers/ProductController.php`
- `app/Http/Controllers/DamageStockController.php`
- `app/Http/Controllers/ExchangeController.php`
- `app/Http/Controllers/CafeOperationsController.php`
- `Modules/Manufacturing/Http/Controllers/ProductionController.php`

Searches also match report accumulators, serialized line payloads and commented code. Those matches must not be treated as persisted stock changes without tracing `save`, `update`, or query execution. Rerun repository searches before ledger cutover, including query-builder updates and raw SQL:

```powershell
rg -n --glob '*.php' '(increment|decrement)\(|->qty\s*=|\[.qty.\]\s*=|qty\s*[+\-]=' app Modules
rg -n --glob '*.php' 'product_warehouse|product_variants|product_batches|UPDATE.*products' app Modules
```

## Commercial and payment writers

- Sales: `SaleService`, `SaleController`, and `CustomerController` opening balances.
- Purchases: `PurchaseService`, `PurchaseController`, `ProductController` opening stock, `AutoPurchase`, and `SupplierController` opening balances.
- Payments: sale/purchase services and controllers, `PaymentService`, `CustomerController`, `SupplierController`, `ProductController`, and `ChallanController`.
- Journals: `AccountingService`, called by generic services, accounting/API controllers, periodic inventory close, damage/exchange, repair, water logistics and cafe paths.

## Company-isolation readers requiring conversion

Query-builder readers bypass Eloquent scopes in `Common` middleware; Home, Report, Customer, Supplier, Product, Sale, Purchase, ReturnPurchase, Delivery, Adjustment, StockCount and CafeOperations controllers; `DsoAlert`; Manufacturing's `RecipeController`; and `TenantInfo`. Global cache keys include `general_setting`, `currency` and role-permission lists. Adding model scopes alone cannot prove isolation.

Company and membership keys must match existing unsigned INT user/warehouse IDs. Existing accounting IDs are BIGINT. The initial warehouse-stock schema has a string product ID and signed integer warehouse ID; inspect actual deployed schema before adding cross-table constraints.

## Subsequent phase hazards

- Preserve `fiscal_years` as the financial-year authority under the user-confirmed policy.
- Opening-balance dummy sales/purchases use `1970-01-01`; do not silently derive an operational FY from that artificial date.
- Generic stock queries select only the first product/warehouse row and ignore batch/variant identities.
- Journals use hard-coded account codes; periodic inventory close references `1040`, while sale/purchase journals use `1200`.
- Inventory-close routes target a missing `store` method; `postClose` calls the journal service with an incompatible payload.
- Posting tolerance differs from `JournalEntry::isBalanced` tolerance.
- `CustomFieldController` performs runtime `ALTER TABLE`/schema mutation.
- Sales/purchase posting can return no journal when required accounts are missing.
- Background jobs, imports, cache keys, raw queries and downloads require context enforcement before multi-company activation.

Full migration and isolation gates require an isolated MySQL database and representative data. No production data was inspected or rewritten during this audit.


## Phase 9–10 gated writer disposition

New `ProductionService`, `JobWorkService`, `FmcgInventoryService` and `ProjectService` construct shared inventory commands and shared accounting/commercial postings. They do not update quantity projections or create a second ledger. Legacy Manufacturing/Recipe, OptechJobWork and ProjectManagement HTTP boundaries redirect authorized reads and reject legacy writes, including cached module routes; historical tables remain.

Stock owner extensions add production consume/output/scrap, explicit expiry disposal, reserved-serial protection and persisted normalized dimensions. Shared commercial/document owners freeze profile precision, transport metadata and dimensional invoice results. [Phase 9–10 delivery notes](PHASE_9_10_OPERATIONS_AND_PROFILES.md) record normal/retry/failure/reversal effects, company/branch/FY/permission guards and tests. Production activation and retained-data reconciliation remain gated.

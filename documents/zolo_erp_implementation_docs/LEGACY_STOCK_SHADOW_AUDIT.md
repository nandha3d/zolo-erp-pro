# Legacy stock shadow audit (Phase 4b/4c prep)

Baseline: `868ad0b`. Read-only audit; no code changed. Supports the Phase 4b shadow design: a `Product_Warehouse` observer buffers `qty`/`imei_number` deltas while a `LegacyStockShadow` scope is active. Route middleware wraps each legacy stock-writing request in one transaction and flushes one record-only movement.

Line numbers are at `868ad0b`; re-check before editing. Commented-out code is excluded.

Current disposition (2026-10-04): [Phase 4c](PHASE_4C_AUTHORITATIVE_CUTOVER.md) converts operational writers to applied movements and removes their shadow wrappers. Findings #3, #4, #8, #9, #10, #12 and #13 are repaired in those writers; #1 now rejects removal of nonempty stock metadata. Commercial findings #5, #6, #7 and #11 remain Phase 4b work. The findings and route inventory below retain their historical baseline; they do not describe the current shadow map.

## Summary

- **(a)** No `DB::table('product_warehouse')` writes and no raw SQL writes exist outside the Phase 4 inventory services. Two whole-database truncations exist; see the security section.
- **(b)** One query-builder mass operation exists: `ProductController::updateProduct` mass-deletes variant rows.
- **(c)** `Product_Warehouse::insert` is used twice in `ProductController::importProduct`. Both insert qty 0, so they carry no stock delta.
- **(d)** Eleven findings (#3-#13 below) change `products`, `product_variants` or `product_batches` quantity without a matching warehouse-row change. Most are guards with no `else` branch, so a missing warehouse row means only the product total moves. The shadow cannot see these; reconciliation will report them as product-, variant- or batch-level differences.
- **(e)** The only non-HTTP writer is `purchase:auto` (`AutoPurchase`), scheduled every five minutes. It has no jobs, imports or listeners; legacy imports are HTTP controller methods.
- **(f)** 39 routed writer methods (40 routes) are listed below. Two writer methods have no route and are dead code: `PurchaseController::updateFromClient` and `ProductionController::storebackup`.

Design constraints found:

1. **Hook `updated`, not `saved`.** Model-instance `increment()`/`decrement()` fires only `updating`/`updated` (Laravel 10 `Model::incrementOrDecrement`). `ExchangeController::store` and `DamageStockController::store` rely on this. Also hook `created` and `deleted`.
2. **Reload key columns for partial models.** `Product_Warehouse::select('id', 'qty')` rows are saved in `AutoPurchase.php:104-111` and `ProductController::autoPurchase:712-719`. They lack `product_id`, `warehouse_id`, `variant_id` and `product_batch_id`. The observer must re-read those columns by `id` when they are absent from the attributes.
3. **The buffer must be rollback-aware.** Many writers call `DB::beginTransaction()`/`rollBack()` themselves. Under the middleware's outer transaction these become savepoints. `SaleController::store` rolls back a savepoint and then returns `redirect()->back()` normally. An in-memory buffer would then record deltas that were rolled back.
   - Safest fix: have the observer write delta rows to a staging table inside the same transaction, so database rollback discards them, and flush at the end.
   - Alternative: tag buffered entries with `DB::transactionLevel()` and drop deeper entries on `TransactionRolledBack`.
4. **A successful response is not necessarily a successful operation.** Writers that rolled back their inner savepoint (`SaleController::store`, `PurchaseController::store`, `ProductController::updateProduct`) still return 302 redirects. Commit the outer transaction on any non-exception response, because legacy semantics already persist partial work. Only flush the deltas that survived.
5. **Exclude the movement service's own writes.** `InventoryMovementService::warehouseRow()->save()` writes through Eloquent and must be excluded. `InventoryReconciliationService::rebuild` writes via `DB::table` and bypasses the observer, which is the desired behaviour.
6. **Ignore saves that change neither qty nor IMEI.** `ProductController::updateProduct:1482-1505` saves price-only rows. Zero-qty `firstOrCreate`/`create`/`insert` rows also carry no delta.
7. **`ProductionController::store:343` calls `dd($e)`.** This exits mid-request, so the connection drops and rolls back, and no flush runs. That outcome is consistent, but the `dd` should still be removed (D9).

## Findings

| # | File:line (method) | Kind | Effect on shadow | Suggested fix |
|---|---|---|---|---|
| 1 | `ProductController.php:1472-1474` (`updateProduct`) | (b) mass `Product_Warehouse::where(variant)->delete()` | Deleted rows' qty and IMEI are invisible. The variant-qty guard checks `product_variants.qty`, not warehouse rows, which may be nonzero. | `->get()->each->delete()`, or buffer `-qty` per row before deleting. |
| 2 | `ProductController.php:2060,2071` (`importProduct`) | (c) `Product_Warehouse::insert` with qty 0 | No delta | None. Note that row creation itself is invisible. |
| 3 | `CafeOperationsController.php:57` (`storeRawMaterial`) | (d) product-only `decrement('qty')` | Consumption is invisible; product-level difference | Decrement the warehouse row too, or post an `issue` explicitly (4c). |
| 4 | `DamageStockController.php:65-68` (`store`) | (d) warehouse row guarded with no else; product always decremented | Missing row means a product-only delta | Create the row, or post an `issue` (4c). |
| 5 | `SaleController.php:993` (`store`, combo child) and `:1042` (`store`) | (d) guard with no else | A missing row means the sale deducts only product/variant/batch qty | Shadow cannot fix; reconciliation flags it. At cutover, the movement service creates the row and applies the negative policy. |
| 6 | `SaleController.php:4631,4667` (`deleteBySelection`) and `:4828,4865` (`destroy`) | (d) warehouse restore skipped when the row is missing; `4631/4667` also skip when `without_stock == 'yes'` | Product/variant restored without the warehouse row | Same as #5. Treat `without_stock=yes` deletes as expected product-level differences. |
| 7 | `SaleController.php:4657` (`deleteBySelection`) and `:4855` (`destroy`) | (d) legacy bug: batch qty decremented when a sale is deleted, while warehouse/product are incremented | Batch projection diverges from the ledger (shadow takes the batch delta from the warehouse row = +) | Fix the sign at cutover; expect batch-level differences in UAT. |
| 8 | `AdjustmentController.php:379-382` (`update`, revert of old lines) | (d) variant qty changed (with the original sign, not reversed); warehouse row and product not reverted (lines 398-406 commented out) | Variant-only change is invisible | Rewrite at cutover as reverse-old + post-new movements. |
| 9 | `AdjustmentController.php:428-449` (`update`, new lines) | (d) warehouse row and variant changed; `products.qty` never changed | Shadow records the warehouse delta; product-level difference | Same as #8. |
| 10 | `PackingSlipController.php:224` (`store`) | (d) guard with no else; variant and product always decremented | Missing row means an invisible delta | Same as #5. |
| 11 | `PurchaseController.php:1274` (`update`) and `:2063` (`updateFromClient`, unrouted) | (d) revert of old received qty skipped when the row is missing; product reverted | Product-only delta | Same as #5. |
| 12 | `ProductionController.php:331` (`store`, ingredient) | (d) guard with no else; child product/variant always reduced | Ingredient consumption invisible when the row is missing | Fix at 4c Manufacturing refactor (D9). |
| 13 | `AutoPurchase.php:113-117` (`handle`) | (d) new `Product_Warehouse` built but never `save()`d; `products.qty += 10` | Product-only delta every 5 minutes for products without a row | Add the missing `save()`, or post a `receipt` explicitly; wrap the command in the shadow scope (e). |
| 14 | `AutoPurchase.php:104-111` and `ProductController.php:712-719` (`autoPurchase`) | Partial model `select('id','qty')` saved | The observer sees no key columns | The observer reloads keys by `id` (constraint 2). |
| 15 | `ProductController.php:615` (`store`) | Initial stock: product += sum; the warehouse rows are written in `autoPurchase` within the same request | Visible if the route is wrapped | Wrap `products.store`. |
| 16 | `WarehouseController.php:41` (`store`) | `Product_Warehouse::create` qty 0 for every product | No delta; `created` events fire once per product | No fix needed; exclude or ignore zero-qty creates for performance. |
| 17 | `SettingController.php:42-90` (`emptyDatabase`) and `CouponController.php:83-104` (`updateCoupon`) | Truncate every table, including `product_warehouse` and the ledger | Ledger and projections are wiped together | Security fix; see below. |
| 18 | `TransferController.php` `changeStatus` (1395-1467) | Writes warehouse rows on status change | Visible if wrapped | Wrap `transfers.changeStatus` (easy to miss: not a CRUD verb). |

## Non-HTTP writers (e)

| Writer | Trigger | Action |
|---|---|---|
| `app/Console/Commands/AutoPurchase.php` (`purchase:auto`) | Scheduler, every five minutes (`app/Console/Kernel.php`) | Wrap `handle()` in the shadow scope plus one transaction per product/purchase, then flush. Fix #13 first. |
| `app/Console/Commands/ResetDB.php`, `InstallController` | Demo reset / install SQL dump | Out of scope. They replace the whole database; re-run `erp:stock-opening` afterwards. |

There are no `app/Jobs`, `app/Imports`, `app/Listeners`, module `Jobs`/`Console` writers or queued listeners. The API stock paths go through the ERP services, which already use the movement service.

## Routes to wrap (f)

All routes below carry `web, Authenticate, Common, Active`. The module and operational routes also carry `ResolveCompanyContext` plus a capability check.

| Controller@method | Method | URI | Route name |
|---|---|---|---|
| SaleController@store | POST | `/sales` | `sales.store` |
| SaleController@update | PUT/PATCH | `/sales/{sale}` | `sales.update` |
| SaleController@importSale | POST | `/importsale` | `sale.import` |
| SaleController@deleteBySelection | POST | `/sales/deletebyselection` | (unnamed) |
| SaleController@destroy | DELETE | `/sales/{sale}` | `sales.destroy` |
| PurchaseController@store | POST | `/purchases` | `purchases.store` |
| PurchaseController@update | PUT/PATCH | `/purchases/{purchase}` | `purchases.update` |
| PurchaseController@importPurchase | POST | `/importpurchase` | `purchase.import` |
| PurchaseController@deleteBySelection | POST | `/purchases/deletebyselection` | (unnamed) |
| PurchaseController@destroy | DELETE | `/purchases/{purchase}` | `purchases.destroy` |
| ReturnController@store | POST | `/return-sale` | `return-sale.store` |
| ReturnController@update | PUT/PATCH | `/return-sale/{return_sale}` | `return-sale.update` |
| ReturnController@deleteBySelection | POST | `/return-sale/deletebyselection` | (unnamed) |
| ReturnController@destroy | DELETE | `/return-sale/{return_sale}` | `return-sale.destroy` |
| ReturnPurchaseController@store | POST | `/return-purchase` | `return-purchase.store` |
| ReturnPurchaseController@update | PUT/PATCH | `/return-purchase/{return_purchase}` | `return-purchase.update` |
| ReturnPurchaseController@deleteBySelection | POST | `/return-purchase/deletebyselection` | (unnamed) |
| ReturnPurchaseController@destroy | DELETE | `/return-purchase/{return_purchase}` | `return-purchase.destroy` |
| AdjustmentController@store | POST | `/qty_adjustment` | `qty_adjustment.store` |
| AdjustmentController@update | PUT/PATCH | `/qty_adjustment/{qty_adjustment}` | `qty_adjustment.update` |
| AdjustmentController@deleteBySelection | POST | `/qty_adjustment/deletebyselection` | (unnamed) |
| AdjustmentController@destroy | DELETE | `/qty_adjustment/{qty_adjustment}` | `qty_adjustment.destroy` |
| TransferController@store | POST | `/transfers` | `transfers.store` |
| TransferController@update | PUT/PATCH | `/transfers/{transfer}` | `transfers.update` |
| TransferController@importTransfer | POST | `/importtransfer` | `transfer.import` |
| TransferController@deleteBySelection | POST | `/transfers/deletebyselection` | (unnamed) |
| TransferController@destroy | DELETE | `/transfers/{transfer}` | `transfers.destroy` |
| TransferController@changeStatus | PUT | `/transfers/change-status/{id}` | `transfers.changeStatus` |
| PackingSlipController@store | POST | `/packing-slips/store` | `packingSlip.store` |
| PackingSlipController@delete | POST | `/packing-slips/delete/{id}` | `packingSlip.delete` |
| ProductController@store | POST | `/products` | `products.store` |
| ProductController@updateProduct | POST | `/products/update` | (unnamed) |
| ProductController@importProduct | POST | `/importproduct` | `product.import` |
| DamageStockController@store | POST | `/damage-stock` | `damage-stock.store` |
| ExchangeController@store | POST | `/exchange` | `exchange.store` |
| CafeOperationsController@storeRawMaterial | POST | `/cafe/raw-material` and `/cafe/raw-materials` | `cafe.raw-material.store`, `cafe.raw-materials.store` |
| WarehouseController@store | POST | `/warehouse` | `warehouse.store` (zero-qty only; optional) |
| ProductionController@store | POST | `/manufacturing/productions` | `productions.store` |
| ProductionController@destroy | DELETE | `/manufacturing/productions/{production}` | `productions.destroy` |

Unrouted writer methods, which are dead code: `PurchaseController::updateFromClient` (1981-2192) and `ProductionController::storebackup` (433-472).

Seven writer routes are unnamed: the six `deletebyselection` routes and `/products/update`. Attach the middleware in `routes/web.php` by controller action, or use a controller `middleware(...)->only([...])` in each constructor, rather than by route name.

## Security findings (outside shadow scope) - resolved in `ff9310e`

The findings below describe `868ad0b`. Both are fixed on `main`; the text is kept as the audit record.

- **`GET /update-coupon` → `CouponController::updateCoupon` (CouponController.php:83-104).** Any authenticated, active user can trigger it with a GET request. It does three things:
  - disables FK checks;
  - truncates **every** table, including users, ledger and settings;
  - recursively deletes the directory named by the request parameter `data` (arbitrary path, `unlink`/`rmdir`).

  There is no permission check and no CSRF protection, because the route is a GET. It came with the initial import (`38f265f`). **Resolved in `ff9310e`: the route and method were removed.**
- **`GET /setting/empty-database` → `SettingController::emptyDatabase`.** The only gate is `env('USER_VERIFIED')`; there is no role permission check, and it is a GET. It truncates all business tables, including `product_warehouse` and every Phase 4 ledger table. **Resolved in `ff9310e`:** POST only, restricted to active Owner/Admin (role 1/2), and requires the typed phrase `DELETE ALL DATA`. Companies, branches, memberships, fiscal years, capabilities and the chart of accounts are kept; the ledger tables are emptied together with the business data.

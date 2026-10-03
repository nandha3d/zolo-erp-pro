# 09 - Inventory, Stock Ledger, Batch, Serial and Dimensions

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP on SalePro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Create an immutable/auditable stock movement source of truth and keep existing product/warehouse quantities as rebuildable compatibility projections during migration.

## Target rules

- Every posted physical stock change has a stock movement.
- `products.qty` and `product_warehouse.qty` are projections, not historical truth.
- Movement cost is persisted at posting time; historical COGS is not recomputed from current product cost.
- Negative stock policy is enforced centrally.
- Serial/batch/dimension identities are validated centrally.

## Data model / contracts

Core tables:

```text
stock_movements
  company_id, branch_id, financial_year_id
  movement_no/date/type, source_type/id/no
  warehouse_from_id, warehouse_to_id
  status, reversal_of_id, idempotency_key, created_by, posted_at

stock_movement_lines
  stock_movement_id, product_id, variant_id, uom_id
  qty_base, unit_cost, value, batch_id, stock_identity_id, attributes_json

stock_batches
  company_id, product_id, batch_no, mfg_date, expiry_date, mrp

stock_serials
  company_id, product_id, serial_no, status, warehouse_id, batch_id
  warranty dates, source document

stock_dimensions
  stock_identity_id, length, width, thickness, dimension_uom
  pieces, computed_volume, volume_uom, grade
```

## Services and ownership

`InventoryMovementService`:

```text
receive(command)
issue(command)
transfer(command)
adjust(command)
reverse(movement, reason)
```

`InventoryAvailabilityService` calculates on-hand/reserved/available.  
`InventoryReconciliationService` compares movement totals to projections.

## Implementation sequence

1. Create movement/identity tables and indexes.
2. Implement projection updates in the same transaction as movement posting.
3. Adapt SaleService, PurchaseService and InventoryService first.
4. Adapt legacy SalePro controllers next.
5. Adapt Manufacturing and vertical controllers last.
6. Add reconciliation/rebuild commands.

## Acceptance and verification

- Purchase receipt then sale issue reconciles on-hand and value.
- Transfer out/in nets correctly across warehouses.
- Serial cannot be simultaneously present in two locations.
- Expired batch is blocked when policy says block.
- Concurrent last-stock sales cannot both succeed when negative stock is blocked.
- Reversal restores stock with traceable history.

## Codex guardrails

- Repository-wide search for every `increment/decrement('qty')`, `$qty +=`, `$qty -=` writer before cutover.
- Do not remove existing quantity columns until all dependent reports are migrated.

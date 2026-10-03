# 08 - Purchase and Procure-to-Pay

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP on SalePro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Use one procurement engine for normal purchases, rapid inward entry, batch receipts, timber lots, serialized solar equipment and service purchases.

## Target rules

- GRN is a generic goods-receipt concept; job-work receipt is a related but distinct workflow.
- Inventory receipt and AP journal/open item use shared services.
- Service purchases do not create stock.
- Landed cost allocation is explicit and auditable.

## Data model / contracts

Optional chain:

```text
Purchase Requisition → Purchase Order → Goods Receipt → Supplier Invoice → Payment
```

Landed cost methods:

```text
by value
by quantity
by weight
manual
```

## Services and ownership

`PurchaseApplicationService` validates supplier, tax/RCM, UOM, stock tracking identities, landed cost and company context, then writes purchase data, stock receipts, AP journal and payable open item atomically.

## Implementation sequence

1. Make current PurchaseService company/FY-aware.
2. Use atomic document numbering.
3. Move stock increments to InventoryMovementService.
4. Create AP open item from posted unpaid balance.
5. Add PO/receipt linkage without making it mandatory for simple businesses.
6. Add batch/serial/dimension capture through capability extensions.

## Acceptance and verification

- Simple purchase, service-only purchase and partial-paid purchase reconcile.
- Batch/expiry, serial and dimension receipts preserve identities.
- Freight allocation persists inventory valuation cost.
- Purchase return reverses stock/AP/tax correctly.

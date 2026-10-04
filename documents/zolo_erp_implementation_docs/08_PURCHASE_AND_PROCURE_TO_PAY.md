# 08 - Purchase and Procure-to-Pay

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP Pro / Laravel 10  
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

## Confirmed supplier-bill recognition and receipt status

The user selected supplier-bill recognition for the combined legacy purchase record. Status 1 Received records full ordered quantity; status 2 Partial requires validated per-line `received_qty`; status 3 Pending records zero received quantity. Status 4 Ordered is an unbilled PO and does not post a journal or accept payment through this creation command.

For a billed purchase, recognize the full bill as payment plus accounts payable. Debit received value to inventory and unreceived value to an active asset account with `sub_type = goods_in_transit`. Bill-level charges/discounts are allocated proportionally by billed line value; zero-value lines use quantity. Missing required accounts fail the entire transaction. Account configuration uses the existing chart UI; do not seed guessed account IDs.

`PurchaseService` persists the compatible physical column `product_purchases.recieved` while the API accepts `received_qty`. Free received goods can have a quantity effect without a zero-value journal. Cash/bank posting follows the recorded initial payment method.

Subsequent receipt must release goods-in-transit into inventory without recognizing AP again. The later shared receipt/movement integration must implement this event and reconcile valuation. This creation correction does not establish legacy web receipt-update parity, movement-ledger authority, company isolation or landed-cost valuation projections.

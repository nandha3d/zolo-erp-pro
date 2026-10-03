# 06 - Master Data: Products, Parties, UOM and Attributes

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP on SalePro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Keep one understandable master model that works for traders, FMCG, textile, timber and solar.

## Target rules

- Shared product table holds stable common fields; industry details use attributes or capability sidecars.
- Transactional identities such as serial, batch and actual dimensions do not belong only on product master.
- Units and conversion factors are normalized, not comma-separated.
- Party tax/contact/credit data is reusable across customer, supplier, job-worker and subcontractor roles.

## Data model / contracts

Target product concepts:

```text
item_kind: goods | service | consumable | asset | non_stock
stock_tracking: none | quantity | batch | serial | batch_serial | dimension
hsn_sac_code
tax_category_id
base_uom_id / purchase_uom_id / sale_uom_id
valuation_method
allow_negative_stock override
shelf_life_days
```

Normalize product UOM conversions:

```text
product_uom_conversions
  product_id, from_uom_id, to_uom_id, factor, rounding_scale
  is_purchase_default, is_sale_default
```

Industry attributes:

```text
FMCG: MRP, pack size, case pack, shelf life
Textile: construction, width, GSM, color, design
Timber: species, grade, standard dimensions, moisture class
Solar: wattage, voltage, phase, panel/inverter type, warranty
```

## Services and ownership

Create shared `ProductQueryService`, `PartyQueryService` and `UomConversionService`.  
GSTIN lookup is supplied by IndiaCompliance.  
Inline creation returns the new entity to the active transaction without clearing entered lines.

## Implementation sequence

1. Add/normalize shared product tracking fields through additive migrations.
2. Create attribute definition/value store.
3. Create normalized UOM conversions.
4. Introduce PartyResolver abstraction over current customer/supplier tables.
5. Refactor fast search to one query service.
6. Migrate hot custom fields to explicit sidecar columns/tables only when needed for indexed calculation.

## Acceptance and verification

- FMCG supports PCS/BOX/CASE conversion.
- Textile accepts 125.375 MTR without precision loss.
- Timber can attach dimensions to stock identity instead of changing product master.
- Solar serialized item cannot be treated as anonymous quantity at issue time.
- Service item posts commercial/accounting data without stock movement.

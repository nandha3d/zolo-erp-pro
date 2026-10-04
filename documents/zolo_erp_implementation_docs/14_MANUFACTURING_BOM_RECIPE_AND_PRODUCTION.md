# 14 - Manufacturing, BOM/Recipe and Production

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP Pro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Reuse the real existing Manufacturing module, but normalize BOM/recipe data and route stock/accounting changes through shared engines.

## Current repository observations

- Manufacturing module already has production/recipe controllers, migrations and substantial views.
- Current production paths directly mutate Product and Product_Warehouse quantities.
- Existing product manufacturing fields include string/comma-separated patterns that should not become the future normalized model.

## Target rules

- Manufacturing is optional capability, not part of every company's menu.
- Production consumes and outputs stock movements.
- BOM versions are explicit/effective-dated.
- Wastage/yield use numeric quantities, not string percentages as authoritative data.
- Support multiple outputs for timber-style conversion.

## Data model / contracts

Target:

```text
boms
  company_id, product_id, code, version, effective dates
  output_qty, output_uom_id, status, is_default

bom_lines
  bom_id, component_product_id, qty, uom_id, scrap_percent

production_orders
  company_id, branch_id, warehouse_id, bom_id
  planned_qty, completed_qty, status, dates, cost_method
```

Stock movement types:

```text
production_consume
production_output
production_scrap
```

## Implementation sequence

1. Inventory current recipe/production records and forms.
2. Add company context.
3. Introduce normalized BOM tables alongside legacy fields.
4. Migrate recipe arrays/comma-separated data with validation.
5. Adapt existing screens to BOM services.
6. Route production consume/output to InventoryMovementService.
7. Post production accounting when perpetual inventory is enabled.
8. Remove direct qty mutation only after parity/reconciliation tests.

## Acceptance and verification

- Production output cost = consumed valuation + direct/overhead cost under configured policy.
- Reversal uses opposite stock movements, not deleting production history.
- FMCG recipe, textile in-house process and timber multi-output conversion work.

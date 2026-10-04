# 18 - Industry Pack: Timber / Wood Trading and Processing

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP Pro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Support timber pieces/lots whose sale quantity/value depends on physical dimensions and volume.

## Target rules

- Actual dimensions live on stock identity/movement, not only product master.
- Volume formula and normalized units are versioned/persisted with transaction result.
- Sales can select actual pieces/lots.
- Sawing/conversion uses multi-output production with yield/waste.

## Data model / contracts

Default profile:

```text
core.sales / purchases / inventory / accounting / gst
inventory.multi_uom
inventory.dimension_tracking
inventory.lot_tracking
sales.wholesale
manufacturing.production       optional
operations.job_work            optional
```

Attributes: species, grade, moisture class, standard dimensions, source.

Per stock identity:

```text
length, width, thickness, pieces
computed_cft, computed_cbm
grade, lot, warehouse/rack
formula_version
```

## Services and ownership

Create `DimensionCalculationService` with explicit unit conversions and persisted result. Historical invoices never recalculate from a later formula version.

## Implementation sequence

1. Seed timber profile and dimension attributes.
2. Implement dimensional stock identity and calculation service.
3. Add purchase receipt by piece/CFT/CBM basis.
4. Add sale picker by species/grade/dimensions.
5. Add dimensional invoice line rendering.
6. Extend Manufacturing for multi-output conversion and offcut/scrap.

## Acceptance and verification

- Receive pieces, compute CFT/CBM, sell selected subset and reconcile remaining identity/volume.
- Conversion input volume approximately reconciles outputs + documented waste.
- Historical volume stays unchanged when formula configuration changes.

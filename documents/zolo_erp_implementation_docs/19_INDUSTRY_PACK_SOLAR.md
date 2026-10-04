# 19 - Industry Pack: Solar / EPC / Installation

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP Pro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Combine trading, project/site execution, serial tracking, system kits, installation, project costing and warranty without creating a separate solar ERP.

## Target rules

- Inventory remains item/serial based even when sold as a project/system.
- Project/site links commercial and operational documents but does not duplicate them.
- Serial history follows purchase → allocation → dispatch → install → replacement.
- Project profitability aggregates shared stock/accounting/expense data.

## Data model / contracts

Default profile:

```text
core.sales / purchases / inventory / accounting / gst
inventory.serial_tracking
operations.projects
operations.installation
manufacturing.bom              for system kits
service.warranty_amc
communications.whatsapp
```

Site/project data: address, contact, system size, roof/site notes, survey attachments.  
Product attributes: wattage, voltage, phase, panel/inverter type, warranty duration.

## Implementation sequence

1. Seed solar profile and product attributes.
2. Extend projects with site details and status.
3. Implement system-template/BOM expansion into quotation/order lines.
4. Use stock reservation/serial allocation for project materials.
5. Implement dispatch/install/commission status and installed serial history.
6. Aggregate material/service/expense/revenue for project profitability.
7. Create warranty/AMC records from installed serials.

## Acceptance and verification

- 5kW quotation → project → missing-item purchase → serial allocation → dispatch → commission → invoice/payment works.
- Project margin reconciles to journal/stock costs.
- Serial replacement keeps full warranty/service history.

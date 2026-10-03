# 05 - Database Scoping and Migration Strategy

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP on SalePro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Introduce company scoping and new sources of truth without a big-bang destructive migration.

## Target rules

- Add columns nullable first; backfill separately; enforce later.
- Large backfills run through commands/jobs with dry-run output.
- Unique keys become company-aware only after all writers are context-aware.
- Never run DDL from ordinary user requests.
- Historical vertical data is preserved during refactors.

## Data model / contracts

Review company scope for at least:

```text
products/categories/brands/units
customers/customer_groups/suppliers
warehouses/billers/users as applicable
sales/product_sales/payments/returns
purchases/product_purchases/purchase_returns
transfers/adjustments/stock counts
expenses/incomes/payroll
chart_of_accounts/fiscal_years/journal_entries/journal_items
semantic_account_mappings/inventory_closes
manufacturing productions/BOM data
projects/repair/water/cafe domain tables
document/print/communication settings
```

Replace runtime custom-field DDL with:

```text
attribute_definitions
  company_id nullable, entity_type, key, label, data_type
  validation_json, display_json, is_searchable, is_required

entity_attribute_values
  attribute_definition_id, entity_type, entity_id
  typed value columns / value_json
```

## Implementation sequence

1. Create platform foundation tables.
2. Add nullable company keys in small migrations.
3. Implement dry-run backfill command with row counts and invalid FK report.
4. Backfill default company in batches.
5. Change unique indexes to include company ID where required.
6. Add constraints and non-null after validation.
7. Freeze creation of new runtime DDL custom fields and migrate important existing custom fields.

## Acceptance and verification

- No company-owned transaction remains with null company after cutover.
- No duplicate business key exists within a company after index migration.
- Migration can be rehearsed on a production-sized copy.
- Custom attribute creation no longer executes ALTER TABLE.

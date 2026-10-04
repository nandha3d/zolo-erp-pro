# 23 - Data Migration, Parallel Run and Cutover

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP Pro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Provide a repeatable process for current zoloERP Pro data and legacy customer systems such as Optech.

## Target rules

- Imports stage, validate and reconcile before touching authoritative transaction tables.
- Ambiguous customers/products are reviewed, not silently merged.
- Bill-by-bill migration imports invoice-level open items when needed.
- Opening stock and accounting balances must reconcile.
- Cutover has a defined rollback procedure.

## Data model / contracts

Staging pattern:

```text
import_batches
import_rows
mapping tables
validation_errors

upload → parse → normalize → validate → preview → commit → reconcile
```

Opening reconciliation:

```text
AR control = customer open items
AP control = supplier open items
Inventory asset = opening stock valuation
Trial balance debit = credit
```

## Implementation sequence

1. Choose opening-only or detailed-history migration scope.
2. Build source-to-target mapping tables.
3. Import masters and resolve duplicates.
4. Import stock identities/opening movements.
5. Import receivable/payable open items.
6. Post balanced opening accounting.
7. Import open job-work/projects/batches/serials as required.
8. Run controlled parallel comparison.
9. Freeze old entry, import delta, reconcile and sign off.

## Acceptance and verification

- Migration can be repeated from a clean target with same result.
- No unbalanced opening journal.
- Open AR/AP reports match agreed source balances.
- Stock quantity/value matches agreed source snapshot.
- Rollback runbook is rehearsed before go-live.

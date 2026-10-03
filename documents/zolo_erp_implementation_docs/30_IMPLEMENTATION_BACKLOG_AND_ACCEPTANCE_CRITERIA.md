# 30 - Implementation Backlog and Acceptance Criteria

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP on SalePro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Translate the architecture into epics that can become GitHub issues or Codex work packages.

## Target rules

- Every issue names its source-of-truth service/table.
- Every behavior-changing issue has acceptance tests.
- Every migration issue documents backfill and rollback/reversal.
- Every capability issue names dependencies and company/profile scope.

## Data model / contracts

Suggested epics:

```text
A  Company / branch / FY foundation
B  Capability and business-profile engine
C  Atomic numbering + transaction infrastructure
D  Stock movement source of truth
E  Accounting idempotency + open items
F  Sales / purchase application services
G  GST + document rendering/dispatch
H  Returns and reversal engine
I  Manufacturing + Job Work
J  FMCG / Textile / Timber / Solar packs
K  UI / API / security
L  Migration / performance / deployment
```

## Implementation sequence

1. Create issues per epic in the listed dependency order.
2. Split migrations, services and UI into reviewable PRs while keeping each PR deployable.
3. Attach UAT case to the industry issue that depends on it.
4. Use traceability matrix to ensure original Optech requirements are not silently lost.

## Acceptance and verification

- Every backlog item explicitly records Company scope, Capability, Permission, Stock effect, Accounting effect, Tax effect, Document effect, API effect, Audit effect, Tests and Migration impact; use N/A where not applicable.
- Epic completion requires all dependency tests and reconciliation checks, not only UI completion.

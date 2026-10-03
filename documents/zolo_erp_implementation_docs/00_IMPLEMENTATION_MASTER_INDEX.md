# 00 - Implementation Master Index

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP on SalePro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

This is the control document for the whole modernization. The implementation order is intentionally different from the original textile-first roadmap: company/capability foundations and shared transaction sources of truth come first.

## Target rules

- Layering: optional SaaS tenant → legal company → branch/FY → shared ERP core → optional capabilities → industry presets.
- Core modules never depend on an industry module.
- Company and capability context must be available before a request reaches business services.
- Industry labels and defaults may change, but stable semantic keys and core services do not.

## Data model / contracts

```text
Optional SaaS Tenant
└── Company / Legal Entity
    ├── Financial Years
    ├── Branches / Warehouses
    ├── Users + Roles + Company Memberships
    ├── Business Profile
    │   └── Enabled Capabilities
    ├── Shared ERP Core
    │   ├── Masters / Parties
    │   ├── Sales / Purchases
    │   ├── Inventory
    │   ├── Accounting
    │   ├── GST / Tax
    │   ├── Documents / Printing / Dispatch
    │   └── API / Audit / Reports
    └── Optional Capabilities
        ├── Manufacturing
        ├── Job Work / Subcontracting
        ├── Batch + Expiry
        ├── Serial / Warranty
        ├── Dimensional Stock
        ├── Projects / Sites
        ├── Route Distribution
        └── Repair / Service
```

## Implementation sequence

1. 01 Repository audit and gaps.
2. 02 Architecture boundaries.
3. 03 Multi-company, branch and financial year.
4. 04 Business profile and capability engine.
5. 05 Safe schema scoping/backfill.
6. 06 Generic master data and attributes.
7. 09 Inventory movement ledger.
8. 10 Accounting hardening and open items.
9. 07 Sales/Order-to-Cash and 08 Purchases/Procure-to-Pay.
10. 11 GST and 12 Document/printing/communication.
11. 13 Returns and reversals.
12. 14 Manufacturing and 15 Job Work.
13. 16-19 industry profiles.
14. 20-22 UI, API and security.
15. 23-25 migration, testing, deployment.
16. 26 Codex execution sequence, 27 traceability, 28 roadmap, 29 scaffold disposition, 30 backlog.

## Acceptance and verification

- Every implementation PR cites the relevant document in this pack.
- No new core feature is implemented only inside an industry module.
- Every transaction-changing PR identifies stock, accounting, tax, open-item and audit effects, or explicitly marks them N/A.

## Supporting execution documents

The numbered 00–30 documents are authoritative specifications. Execution state and operations are maintained separately:

- [Company backfill runbook](COMPANY_BACKFILL_RUNBOOK.md): command guards, rehearsal and rollback procedure.
- [Implementation progress](IMPLEMENTATION_PROGRESS.md): delivered packages, recorded proof and remaining activation gates.
- [Stock/transaction writer audit](STOCK_AND_TRANSACTION_WRITER_AUDIT.md): source inventory; recheck before cutover.

Use [the execution plan](../ZOLO_ERP_EXECUTION_PLAN.md) for source-specific tasks, with document 26's canonical phases 0–12. CompanyContext is `App\Services\Platform\CompanyContext`; FY authority is existing `fiscal_years`; DEFAULT/MAIN initialization belongs to `erp:backfill-company-context`.

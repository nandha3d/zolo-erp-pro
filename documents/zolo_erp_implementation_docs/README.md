# zoloERP General ERP Implementation Pack

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP on SalePro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

This folder is the implementation pack for turning the current SalePro-derived repository into a configurable ERP that can serve different small and medium businesses without forking the application for every industry.

The Optech blueprint remains the behavioral reference for fast entry, GST automation, bill-by-bill accounting, printing, job work and simple operator screens. This pack generalizes those strengths so they are optional capabilities rather than textile assumptions.

Start with `00_IMPLEMENTATION_MASTER_INDEX.md`. For coding, give Codex `26_CODEX_EXECUTION_SEQUENCE_AND_CHECKLIST.md` first.

## Target rules

- One authoritative sales engine, purchase engine, stock engine, accounting ledger, tax engine and document engine.
- A legal Company is distinct from an optional SalePro SaaS Tenant.
- Industry templates enable capabilities; they do not clone core transaction tables.
- Existing SalePro behavior stays available during migration through compatibility adapters.
- Posted stock and accounting changes are reversible through new records, not destructive edits.
- All web and API flows call the same application services.

## Implementation sequence

1. Read the repository audit and architecture boundary documents.
2. Build company context and capability engine before industry features.
3. Introduce stock movement and accounting hardening before refactoring operational modules.
4. Move sales/purchases to shared application services.
5. Add compliance/document output.
6. Refactor Manufacturing and implement generic Job Work.
7. Enable FMCG, Textile, Timber and Solar profiles.
8. Run migration, UAT, reconciliation and production-readiness gates.

## Acceptance and verification

- A General Trading company can operate without textile/FMCG/timber/solar terminology.
- Each industry profile completes its end-to-end UAT using shared core records.
- Stock ledger reconciles to warehouse quantity projections.
- Trial balance balances and open items reconcile to control accounts.
- Company isolation and permissions are enforced server-side.

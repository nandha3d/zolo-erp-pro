# 26 - Codex Execution Sequence and Checklist

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP on SalePro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

This is the primary execution document for Codex. It defines the order and stopping rules so implementation stays evidence-driven and does not create duplicate systems.

## Target rules

- Before each phase, inspect all existing readers/writers of affected tables.
- State observed behavior and target behavior before edits.
- Add tests in the same phase as behavior changes.
- Do not start next phase while new tests fail.
- Use small coherent PRs/commits, not a one-shot rewrite.

## Data model / contracts

Execution order:

```text
0  Baseline and regression tests
1  Company / branch / FY
2  Capability engine
3  Atomic document series
4  Inventory movement source of truth
5  Accounting hardening + open items
6  Shared sales and purchase application services
7  GST / tax
8  Returns + documents/printing/dispatch
9  Manufacturing refactor + generic Job Work
10 General/FMCG/Textile/Timber/Solar profiles
11 UI/API/security completion
12 Migration/UAT/deployment
```

## Implementation sequence

1. Run `git status`, record branch, `php artisan about`, `php artisan route:list`, test suite and frontend build baseline.
2. Phase 1: implement docs 03/05, default-company backfill and isolation tests.
3. Phase 2: implement doc 04 with legacy module adapter.
4. Phase 3: implement atomic numbering from doc 12.
5. Phase 4: implement doc 09 and convert generic ERP services before legacy/vertical controllers.
6. Phase 5: implement doc 10; refactor existing AccountingService rather than replacing it blindly.
7. Phase 6: implement docs 07/08 and converge web/API on shared services.
8. Phase 7-8: implement docs 11-13.
9. Phase 9: refactor Manufacturing then generic Job Work from docs 14/15.
10. Phase 10: implement industry profile seeds/extensions from docs 16-19.
11. Phase 11: finish docs 20-22.
12. Phase 12: execute docs 23-25 and 28.

## Acceptance and verification

- After every transaction-engine change compare document, stock movement, projection, journal, open item, tax snapshot and audit effects.
- Run project test suite and focused tests after each phase.
- Provide changed-file list and unresolved risks before moving on.

## Codex guardrails

- Never fill generated Optech controllers simply because they exist.
- Never create a second sale, purchase, product master or accounting ledger.
- Never add new direct qty mutations.
- Never add new hard-coded account codes/IDs to transaction rules.
- Never use timestamp seconds/count+1 as authoritative document numbering.
- Stop and report if schema/data contradict the spec or tax/legal behavior needs current statutory confirmation.

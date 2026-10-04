# 28 - Phased Delivery Roadmap

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP Pro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Keep the original 24-week milestone discipline but generalize the sequence so common foundations are finished before profile specialization.

## Target rules

- A customer-specific priority may move an industry UAT earlier, but shared boundaries must remain intact.
- Every sprint ends with a demonstrable business task and reconciliation proof.

## Data model / contracts

Reference roadmap:

```text
Weeks 1-4    Platform foundation
  company/branch/FY, capabilities, default backfill, atomic series, onboarding

Weeks 5-8    Sources of truth
  stock movement ledger, accounting idempotency/mappings, open items, period controls

Weeks 9-12   Core commercial + compliance
  shared sales/purchase, fast modes, GST, printing/dispatch, returns
  General Trading UAT

Weeks 13-16  Operations
  Manufacturing refactor, Job Work, batch/expiry, serials, dimensions, projects

Weeks 17-20  Industry profiles
  FMCG, Textile, Timber, Solar configuration + UAT

Weeks 21-24  Migration and go-live
  rehearsal, performance, security, reconciliation, parallel run, restore test, deploy
```

## Acceptance and verification

- Phase exit criteria are documented and signed off before dependent phase closes.
- General Trading works before industry packs are considered complete.
- All four requested industry UAT packs pass before general-product release.

# 15 - Subcontracting / Job Work, DC and GRN

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP Pro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Generalize the Optech textile DC/GRN loop into reusable external processing for textile, timber, FMCG repacking, solar fabrication and future industries.

## Target rules

- Sending own material to a job worker is a stock-location movement, not a sale.
- Ownership remains with the company.
- Service bill is a normal purchase linked to the job-work receipt.
- Loss/shrinkage/yield policy is process-configurable.
- Textile terms are labels/defaults, not shared table names.

## Data model / contracts

Target:

```text
job_work_orders
  company_id, branch_id, job_worker_party_id, process_type_id
  order_no/date, expected_return_date, status, notes

job_work_dispatches + lines
  order link, document link, stock movement link, quantities/identities

job_work_receipts + lines
  dispatch link, received/accepted/rejected/loss quantities
  warehouse, output product/identity
```

Use a virtual job-worker location or equivalent inventory location to show material pending outside.

## Implementation sequence

1. Repurpose OptechJobWork boundary as generic Subcontracting if retained.
2. Create process types and job-worker role.
3. Implement Send Material through stock transfer/movement.
4. Implement receipt reconciliation and shrinkage threshold.
5. Link service Purchase to order/receipt without duplicating material inventory value.
6. Add print profile for material DC.

## Acceptance and verification

- Textile 500.000 MTR sent / 480.000 received reconciles 4% shrinkage.
- Timber external processing reconciles volume/dimensions.
- FMCG outsourced repacking can receive finished packs.
- Pending external material report equals movement balances.

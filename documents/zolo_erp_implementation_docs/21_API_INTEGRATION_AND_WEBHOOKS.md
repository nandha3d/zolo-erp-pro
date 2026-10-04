# 21 - API, Integration and Webhooks

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP Pro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Extend the existing `/api/v1` rather than creating a disconnected API. Web and API must call identical application services.

## Target rules

- Company context is validated through middleware.
- API resources/DTOs provide stable contracts instead of exposing accidental Eloquent columns.
- Write requests support idempotency.
- Provider integrations live behind adapters.
- Reliable outbound webhooks use an outbox/delivery log.

## Data model / contracts

Headers:

```text
Authorization: Bearer ...
X-Company-ID: ...
X-Financial-Year-ID: ...      optional
Idempotency-Key: ...          recommended/required for writes
```

Add/extend endpoints for companies, branches, capabilities, product/party search, inventory availability, open items/allocations, vouchers and GSTIN lookup while retaining existing sales/purchase/accounting routes.

Webhook events may include:

```text
sale.posted
sale.cancelled
payment.received
purchase.posted
stock.low
project.status_changed
```

## Implementation sequence

1. Add company context middleware to protected API.
2. Introduce API Resources/DTOs around current controllers.
3. Implement idempotency store.
4. Add capability-aware endpoints.
5. Make vertical endpoints call shared services.
6. Add outbox + signed webhook delivery only when needed.

## Acceptance and verification

- API user cannot spoof unauthorized company header.
- API and web creation of equivalent sale produce equivalent stock/journal/open-item results.
- Same idempotency key cannot create duplicates.
- Webhook retries do not duplicate downstream event identity.
## Current bounded read contract

Product list/search/detail, customer/supplier lists, stock valuation and sales/purchase list/detail require `company.context` after Sanctum authentication. Root and nested reads use the authorized company. Stock/warehouse reads require the selected branch; a valuation warehouse ID outside it returns 404. Product catalog `qty` is the sum of visible branch stock; it does not expose the legacy product aggregate across branches. Stored quantities remain unchanged. These routes require migration/backfill and authorized memberships. Setup routes are available before FY resolution. Sales/purchase roots require owned warehouses in the selected branch and company-owned linked parties; legitimate absent purchase suppliers remain readable. Detail lines require owned products. Nested product quantity uses visible branch stock, payments require owned accounts and visible linked source documents, and journals/items/accounts require the selected company. Foreign or unassigned rows are excluded; foreign document IDs return 404. Existing list filters and response envelopes remain compatible. Reads preserve historical dates and receipt values without assigning a transaction FY or rewriting stock/accounting. Transaction writes, accounting reports and industry API paths remain gated for full ownership, posting and permission integration; this reader package does not establish full API isolation.

# 21 - API, Integration and Webhooks

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP on SalePro / Laravel 10  
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

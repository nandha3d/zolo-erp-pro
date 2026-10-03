# 24 - Testing, Performance and UAT

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP on SalePro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Create quality gates strong enough to safely refactor the repository's stock, accounting and multi-industry behavior.

## Target rules

- Foundational services require unit + integration + feature tests.
- Performance is measured with realistic data, not assumed from page appearance.
- Concurrency tests are mandatory for numbering, stock and payment allocations.
- Each industry has a full end-to-end UAT pack.

## Data model / contracts

Initial measurable budgets to baseline and tune:

```text
Fast POS warm interactive:      <= 1.5 s
Master autocomplete p95:        <= 300 ms
20-line invoice post p95:       <= 1.0 s excluding external messaging
Common list first page p95:     <= 800 ms
Heavy financial summary:        <= 3 s or move to async/export
```

Representative performance data should include large product/party/line volumes rather than demo-size tables.

## Implementation sequence

1. Capture current test baseline.
2. Add unit tests for UOM, dimensions, tax, numbering, pricing, posting, allocation, capabilities and locks.
3. Add integration tests for sale/purchase/return/transfer/production/job-work effects.
4. Add API auth/company/idempotency tests.
5. Add concurrency tests.
6. Create General/FMCG/Textile/Timber/Solar UAT fixtures.
7. Add reconciliation checks to CI/UAT.

## Acceptance and verification

- General: purchase → sale → payment → return.
- FMCG: batch receipt → FEFO sale → expiry/damage.
- Textile: job-work send/receive → service bill → wholesale sale.
- Timber: dimension receipt → selected sale → conversion.
- Solar: project → serialized procurement → install → invoice.
- Existing SalePro critical flows remain smoke-tested.

# 22 - Security, Permissions, Audit and Data Locks

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP on SalePro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Enforce business controls server-side across web, API, imports and background jobs.

## Target rules

- Access = authentication + company membership + branch restriction + capability + permission + period policy.
- UI hiding is never authorization.
- Sensitive overrides require reason/audit and sometimes approval.
- Posted financial data is protected by lock/close policy.
- Secrets are never exposed in resources/logs.

## Data model / contracts

Example permissions:

```text
sales.view / sales.create / sales.override_price / sales.override_credit
purchases.view / purchases.create
inventory.transfer / inventory.adjust / inventory.override_negative
accounting.journal.create / accounting.period.close
gst.export
manufacturing.execute
jobwork.manage
projects.manage
settings.company / settings.capabilities
```

Audit high-risk actions: cancellation/reversal, price/credit override, stock adjustment, manual journal, opening balances, capability change, tax master change, period unlock.

## Implementation sequence

1. Add company/branch authorization layer around Spatie roles.
2. Add capability middleware.
3. Create/extend audit service with meaningful diffs and reasons.
4. Implement company/FY lock-date checks in shared services.
5. Add approval hooks for configurable high-risk operations.
6. Review upload/download authorization and secret storage.

## Acceptance and verification

- IDOR attempts across company records return 403/404 safely.
- Backdated post beyond lock date is blocked in web, API and import.
- Admin override is audited with user/reason.
- Disabled capability cannot be invoked through direct route.

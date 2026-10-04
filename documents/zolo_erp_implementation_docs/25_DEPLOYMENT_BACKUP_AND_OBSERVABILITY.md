# 25 - Deployment, Backup and Observability

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP Pro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Define a practical production runbook for PHP/Laravel/MySQL deployments without adding unnecessary infrastructure.

## Target rules

- Redis/queues are optional until a selected capability needs them.
- Backups are only trusted after restore testing.
- Additive schema evolution improves rollback safety.
- Logs/metrics include company and correlation context but never secrets.

## Data model / contracts

Production checks should cover:

```text
database + migrations
storage write access
company/FY context
capability dependency integrity
semantic account mappings
document series
queue/scheduler where enabled
backup freshness
stock/accounting reconciliation status
```

## Implementation sequence

1. Document all environment variables and secret ownership.
2. Implement `erp:health` command.
3. Automate database and uploaded-document backups with off-host retention.
4. Define deploy sequence: install → migrate → health → cache → worker restart → smoke check.
5. Add structured logs/correlation ID and failure metrics.
6. Rehearse restore and emergency code rollback.

## Acceptance and verification

- Fresh backup restores into test environment.
- `erp:health` fails clearly when mappings/FY/series are missing.
- Queue/messaging failure is observable without corrupting posted transactions.
- Previous compatible release can run against additive migration state during emergency rollback where promised.

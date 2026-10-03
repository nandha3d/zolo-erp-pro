# Company foundation: staged migration runbook

Implements the initial schema/backfill package from documents 03 and 05. This package does not activate multi-company operation. Continue using one legal company until request, service, raw-query, job and cache isolation passes.

## Scope

The nullable-key migration covers the audited shared masters, commercial headers/lines, payments, warehouse stock, returns, transfers, adjustments, stock counts, expenses, legacy accounts and double-entry accounting tables. It skips absent tables and does not create missing business subsystems. Manufacturing and operational/industry tables require subsequent audited migrations.

Core `users`, `warehouses` and `fiscal_years` are required prerequisites; the new migrations reject missing prerequisites before applying their DDL. Other audited tables may be absent. Known legacy zero references on digital/service item UOMs and opening-stock payment accounts are preserved without exemptions for other orphan IDs.

The migration preserves `fiscal_years`, existing dates and `is_closed`. Backfill synchronizes legacy closed years with the new status. No FY is invented when none exists. Overlaps and inverted ranges must be reviewed, not silently corrected.

## Rehearsal

Use a disposable MySQL copy of the application's schema and representative data, including opening balances and closed fiscal years. Configure the test connection locally; do not commit credentials. First record table counts, stock quantities, account balances and date ranges. Run the existing test suite only against disposable data because some baseline tests write records without rollback.

Pause request writers, workers and the scheduler before real backfill. Laravel maintenance mode does not stop CLI writers or background jobs. Take a verified database backup before applying migrations to any retained database.

```powershell
php artisan down
php artisan migrate
php artisan erp:backfill-company-context --dry-run
```

Review every reported orphan, unexpected company/branch assignment and FY overlap. The command refuses automatic assignment once any non-DEFAULT company exists. It does not infer ownership across multiple companies.

```powershell
php artisan erp:backfill-company-context
php artisan erp:backfill-company-context --dry-run
```

Compare pre/post business counts, stock, journals, balances and FY dates. Confirm the dry-run reports zero unassigned rows in its audited scope. Confirm DEFAULT/MAIN and membership assignments match reviewed legacy access. Do not infer user activation from company membership; existing authentication and active-user rules still apply.

Opening-balance documents dated `1970-01-01` remain unchanged apart from company ownership. Review their opening-data migration separately before financial-year enforcement.

Return the single-company application to service only after reviewing results. Resume only the workers/schedules that were paused for rehearsal or deployment.

```powershell
php artisan up
```

Legacy writers can still produce new null company keys in this stage. Repeat dry-run/backfill during the final paused cutover after all writers become context-aware. Non-null constraints, company-aware uniqueness and request scopes belong to that later gate.

## Failure and rollback

The backfill is one database transaction with bounded row batches. An interrupted or failed run rolls back its writes and can be rerun. The transaction may hold locks for its duration; measure this on representative MySQL data before scheduling production downtime.

Before multi-company activation, reverting the company migrations removes ownership/FY metadata and the new company tables while retaining legacy business rows. Review the actual migration ledger and dependent FKs before rollback: 000003 removes the currency FK while preserving BIGINT width; 000002 removes core ownership/FY additions; 000001 removes platform company tables. Do not use an assumed fixed rollback step count. Prefer restoring the verified rehearsal snapshot when rollback order is uncertain. Do not remove company ownership after real multi-company operation begins: that would collapse legal-entity boundaries. Later cutover requires its own reviewed reversal procedure.

## Remaining activation checks

Integration of Company/FY context middleware (authorized FY setup is implemented); all company-owned operational tables; service reference validation; Eloquent and query-builder isolation; company-aware number/semantic-account uniqueness; active-user, branch, permission and capability enforcement; job context; company cache keys; full API/web IDOR checks; and representative MySQL migration/reconciliation proof remain required before phase 1 can be marked complete.

## Disposable MySQL proof and CI

The source chain and failure recovery pass on official MySQL 8.4.0. Run the isolated suite with explicit disposable credentials:

```powershell
$env:ERP_TEST_MYSQL='1'
$env:ERP_TEST_MYSQL_DATABASE='zolo_test_foundation'
$env:ERP_TEST_MYSQL_USER='zolo_test'
$env:ERP_TEST_MYSQL_PASSWORD='<disposable fixture password>'
php vendor/bin/phpunit -c phpunit.company.xml
```

The fixture account must have privileges only on this disposable database. The suite clears it between tests and requires an explicit `zolo_test_*` or `zolo_audit_*` database name. Host/port may be supplied through `ERP_TEST_MYSQL_HOST` and `ERP_TEST_MYSQL_PORT`. Never point fixtures at retained data. GitHub Actions uses an isolated MySQL 8.4 service with fixture-only credentials. Production-scale locking/duration, retained-data reconciliation and original SalePro browser flows require a separate rehearsal.

The core-key DDL preflights every existing artifact before changes and resumes missing columns/indexes after a failed MySQL ALTER. Investigate incompatible-artifact errors instead of deleting retained columns. Currency defaults are validated during dry-run; migration 000003 refuses orphan references before adding the FK. CLI writers and the scheduler must still be paused during backfill.

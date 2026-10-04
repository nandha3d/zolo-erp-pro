# Phase 12: reviewed migration, UAT and deployment

## Observed behavior and scope

The accepted Phase 9–10 checkpoint already provides authoritative stock/accounting owners, period controls, reviewed BOM conversion, industry fixtures, document delivery and reconciliation. Phase 11 adds authorized setup, bounded API reads, request correlation and safe database errors. There was no reviewed opening-import batch, encrypted instance backup, isolated restore command or combined deployment health check.

This package extends those owners. It delivers an **opening-only import into a clean company**, private encrypted database/upload backups, isolated restore rehearsals, company-scoped health inspection, scheduler controls and deployment/UAT records. It does not infer a customer's source schema or silently merge masters. Detailed transaction history, open jobs/projects and customer-specific master conversion require an agreed source mapping and separate reviewed adapters. No customer data or production migrations ran.

The canonical sequence ends at Phase 12. No Phase 13 requirements appear in documents 00, 26, 28 or 30. Document **13** describes returns/damage/exchanges; those are canonical Phase 8, already delivered. A requested Phase 13 needs its own definition.

## Ownership and migration contracts

`OpeningImportService` stages immutable source keys, reviewed mappings and a canonical SHA-256 source hash in `import_batches`, `import_rows` and `import_mappings`. Company Owner/Admin authorization, branch grants and financial-year context apply to every operation. Preview executes the exact shared posting path inside a rolled-back transaction. It preserves validation errors and totals, but leaves no postings or consumed document numbers.

Commit requires a separately validated batch. It locks the company and batch, rechecks the source hash, mapped ownership, period/lock policy and clean target, then commits all rows atomically. A committed retry returns the same batch even after the year closes. The same batch key with changed data fails. A different import cannot append another opening to an already posted company. Stage/preview never rewrite products' quantities or account balances directly.

Stock rows use `InventoryMovementService::importOpening` and the `migration_opening` movement type. This differs from the existing `opening()` snapshot of quantities already present: reviewed imports start from zero and apply their quantities once. Journal rows use `AccountingPostingService`, which recognizes only a company-owned import batch in `committing` status for this source type. AR/AP rows use `OpenItemService` and preserve original invoice and due dates, including overdue invoices and signed advances. No opening creates a new taxable sale or purchase.

Reviewed totals must match posted stock quantity/value and AR/AP at four decimal places. Stock projections, trial balance, account projections and AR/AP controls must reconcile. Inventory accounting must equal imported stock valuation. Mismatches roll back the entire commit. Offsets are active non-control leaf accounts; other opening journal lines may also use cash/bank leaves. AR/AP controls require explicit invoice rows, not anonymous balance journals.

Both new migrations preflight existing partial tables and resume compatible additive artifacts. `down()` refuses to erase audit/import evidence. Prior company backfill and retained-data reconciliation remain prerequisites; adding import tables does not establish full company isolation.

## Preparing a reviewed source

1. Choose an opening date and agree the opening-only scope with the customer/accountant. Freeze source entry while taking the final snapshot.
2. Prepare company-owned products, parties, accounts, branches and warehouses through the existing authorized master owners. Leave stock quantities and accounting balances at zero. Review duplicates manually; do not create stock openings through master entry before using this importer.
3. Map each source product, warehouse, customer, supplier and account key to a reviewed target ID. Warehouses must belong to the selected branch. IDs are never taken from unreviewed stock payload fields. Configure semantic mappings, units, identity flags and document series first.
4. Normalize source quantities to the product's base UOM and agreed valuation. Supply batches/serials/dimensions using the existing `StockLine` contract where required. Every row needs a stable source key. Preserve original invoice/due dates.
5. Review JSON and its expected stock quantity/value, AR/AP totals. Store the reviewed file outside public storage. The CLI accepts UTF-8 JSON up to 25 MiB, 10,000 rows and 20,000 explicit mappings. Unknown root/mapping/row envelope fields fail.

Example (target IDs are illustrative and must be replaced with owned, reviewed IDs):

```json
{
  "batch_key": "customer-cutover-2026-10-03",
  "source_name": "Reviewed opening snapshot",
  "business_date": "2026-10-03",
  "mappings": [
    {"entity": "product", "source_key": "PRODUCT", "target_id": 101},
    {"entity": "warehouse", "source_key": "MAIN", "target_id": 7},
    {"entity": "customer", "source_key": "CUSTOMER", "target_id": 55},
    {"entity": "supplier", "source_key": "SUPPLIER", "target_id": 66},
    {"entity": "account", "source_key": "EQUITY", "target_id": 88}
  ],
  "expected": {"stock_qty": 10, "stock_value": 30, "ar": 100, "ap": 40},
  "rows": [
    {"source_key": "STOCK-1", "kind": "stock", "payload": {
      "product_key": "PRODUCT", "warehouse_key": "MAIN", "qty": 10,
      "unit_cost": 3, "offset_account_key": "EQUITY"
    }},
    {"source_key": "OLD-CUSTOMER-INVOICE", "kind": "receivable", "payload": {
      "party_key": "CUSTOMER", "amount": 100, "offset_account_key": "EQUITY",
      "document_date": "2025-10-01", "due_date": "2025-11-01"
    }},
    {"source_key": "OLD-SUPPLIER-INVOICE", "kind": "payable", "payload": {
      "party_key": "SUPPLIER", "amount": 40, "offset_account_key": "EQUITY",
      "document_date": "2026-09-01", "due_date": "2026-09-30"
    }}
  ]
}
```

For other balanced openings, use a `journal` row with `payload.items` containing `account_key`, `debit` and/or `credit`. Map those account keys explicitly. Commit compares the exact reviewed source hash as well as aggregate reconciliation.

Run these as three separate operator actions in an isolated staging environment, substituting reviewed positive context IDs:

```sh
php artisan erp:import-opening /private/reviewed-opening.json --company=1 --branch=1 --year=1 --actor=1
php artisan erp:import-opening /private/reviewed-opening.json --company=1 --branch=1 --year=1 --actor=1 --validate
php artisan erp:import-opening /private/reviewed-opening.json --company=1 --branch=1 --year=1 --actor=1 --commit --confirm-reviewed-opening
php artisan erp:health --company=1 --branch=1 --year=1 --actor=1
```

Review the preview JSON before committing. A validation failure stays staged with errors. Changing the source needs a new reviewed batch key; tampering with staged rows cannot bypass the hash. A new rehearsal requires a new clean target, not deletion of posted history. For parallel comparison, replay the agreed business cases independently on source and isolated target, record every difference, then build the final consolidated opening snapshot from the frozen source. **This opening importer is not a transaction-delta importer.** Do not append deltas to an already committed opening. Preserve the prior rehearsal and use a fresh final target, or implement and approve a separate source-specific delta adapter.

## Backup, restore and secret ownership

`erp:backup` is operator-only. The old HTTP database dump is retired. Native `mysqldump` creates the MySQL database snapshot; SQLite uses `VACUUM INTO` for fixtures. The archive includes configured upload roots and an encrypted manifest with per-file SHA-256 values. Every ZIP entry uses AES-256. The temporary plain database dump is removed in `finally`; archive, receipt and restore evidence stay in private storage with restricted directory/file permissions. On Windows, review private filesystem ACLs as part of deployment. Public destinations (including Laravel public storage), symlinked uploads and uploaded `.env` files fail. Passwords are passed to native database processes through environment variables, not CLI arguments or printed process output.

Set `ERP_BACKUP_PASSWORD` to a strong secret of at least 32 characters. The deployment operator owns this secret and stores recovery material separately from backup storage. Application/database credentials, encryption keys, off-site disk credentials and messaging-provider credentials belong in the deployment secret store, never the repository. `.env.example` documents the new variables without secrets.

Configure an existing Laravel filesystem disk for **private off-host storage** using `ERP_BACKUP_OFFSITE_DISK`. Check the provider's permissions and recovery access. The command verifies the uploaded bytes by reading them back and comparing SHA-256 before declaring success. `ERP_BACKUP_ENABLED=true` opts the scheduler into a daily 02:00 backup with overlap protection. Run Laravel's scheduler once per minute through the deployment platform. A single scheduler and persistent lock-capable cache are required.

`config/deployment.php` defines private archive/restore roots, uploaded-document roots, a 14-day local retention window and 26-hour backup freshness. Review upload locations for the actual deployment, including documents stored on external disks. Local expiry only retires tool-generated, checksum-valid, previously off-site-verified copies after a new successful off-site backup. **Configure and verify off-host retention in the storage provider's lifecycle policy**; this command does not delete arbitrary remote objects. No real off-host provider was configured during this implementation. Failed uploads/verification produce no success receipt and do not expire older backups. Investigate private incomplete archives before cleaning them up.

```sh
php artisan erp:backup
# Explicit local rehearsal only; does not pass the deployment off-site check:
php artisan erp:backup --local-only
php artisan erp:restore-rehearsal /private/erp-backup-UUID.zip
```

Restore verifies the password, entry names, encryption and every checksum before extracting into a fresh private UUID directory. SQLite restores pass integrity/table-count checks. For MySQL, the DBA first creates a **fresh, empty `zolo_test_restore_*` database** and a separate user with privileges only on that database. Set `ERP_RESTORE_DB_HOST/PORT/DATABASE/USERNAME/PASSWORD`, plus native binary paths through `ERP_MYSQLDUMP_BINARY` and `ERP_MYSQL_BINARY` if needed. The tool rejects the active database, broad/role grants and nonempty targets. It never drops destination tables or creates a database. A failed rehearsal may leave a partial isolated target for inspection; the DBA must provision another fresh target for the next attempt.

Store matching archive/restore receipts privately. Verify restored stock quantities, posted journals, invoice open items and uploaded documents. A backup becomes deployment evidence only when the matching restore succeeds. Live database plus upload snapshot consistency also requires pausing writers/uploads during the cutover backup; the filesystem is not part of the MySQL transaction.

## Health, schedules and observability

`erp:health` requires an authorized administrator and explicit company/branch/FY/actor. It reports foundation schema, writable storage, an open FY, required semantic mappings, default document series, capability dependencies, stock/accounting reconciliation and inventory value. It also reports company/branch delivery outbox counts by status. Failed checks return exit 1 with safe structured details. It performs no projection rebuild or repair.

```sh
php artisan erp:health --company=1 --branch=1 --year=1 --actor=1 --deployment
```

Deployment mode additionally requires production environment, debug off, an application key, accepted foundation isolation, a fresh verified off-site backup with matching restore proof, and delivery-worker confirmation where compliance is enabled. The existing foundation acceptance constant remains false, so production readiness deliberately fails until that prior gate is reviewed and completed.

The scheduler no longer runs global `purchase:auto`, `dsoalert:find` or the test mailer. Global purchase/alert commands refuse company-enabled databases. `quote:daily` is demo-only. Existing pre-company purchase behavior has a compatibility test. Document dispatch is scheduled only when commercial/compliance gates and `ERP_DISPATCH_WORKER_CONFIRMED` are enabled. Confirm real provider UAT before setting that variable. Redis/queues remain optional; choose a supervised worker only if the selected provider/capability needs asynchronous processing.

Phase 11 request logs provide request ID, route, actor, company/branch, status and duration without query/body secrets. Backup completion logs contain only checksum and off-site verification. Use exit statuses, failed health checks and outbox status counts for deployment alerts. Delivery failures remain in the retryable outbox and do not roll back posted business transactions. Provider acceptance and real worker failure alert routing remain deployment responsibilities.

## Deploy, parallel run and rollback

1. Record the accepted release SHA, disabled gates, source freeze time, source snapshot/hash, recovery contacts and previous compatible release. Back up database and all uploaded-document locations; verify off-host bytes and a matching isolated restore before modifying production.
2. Install dependencies in the release's own checkout (`composer install --no-dev --prefer-dist --optimize-autoloader`). Do not share a Composer classmap between checkouts. Keep secrets outside the release directory.
3. Run the additive root migration chain in staging first and review partial-schema failures. Migrate production only after the independent deployment approval and prerequisite isolation/backfill acceptance. Do not use `migrate:fresh`, table truncation or destructive `down()`.
4. Run health for every deployed context. Investigate every reconciliation or configuration failure. Do not bypass foundation/capability gates to obtain a green result.
5. Build route/config/view caches with the intended deployment environment, then restart supervised workers where selected. Keep one scheduler active. Test authentication, setup, authorized context selection, owned lookup/posting/print, retry and delivery failure behavior.
6. Execute representative General Trading, FMCG, Textile, Timber and Solar cases using the existing shared-flow fixtures and customer records. Compare stock, valuations, balanced journals, AR/AP, taxes, document numbers and audit history against the signed source snapshot. Use the [UAT/cutover record](PHASE_12_UAT_AND_CUTOVER_RECORD.md).
7. During the controlled parallel comparison, record source/target differences and resolve them before freeze. Pause old entry, prepare the final reviewed opening on a clean final target, reconcile and obtain accountant/operator approval. Release writers only after explicit cutover approval.
8. Rehearse emergency code rollback in staging: pause writers/workers, switch to the recorded compatible release, clear/rebuild that release's caches and verify reads plus health against the additive schema. Leave schema and posted history intact. A code rollback cannot undo committed stock/accounting effects. Keep writers gated when the older release cannot understand new source types. An incompatible schema/data rollback requires an approved restore and a plan for transactions after the backup; never overwrite production using the rehearsal command.

Production source migration, controlled parallel comparison, final delta scope, printer/provider hardware checks and emergency rollback with customer data are **not signed off** by fixture tests. This package makes them reviewable and repeatable; it does not claim a completed customer go-live.

## Verification

- Final SQLite delivery suite: **29 tests / 148 assertions**, one native-MySQL restore test skipped. Includes setup/security, staged/preview/commit CLI, original invoice dates, signed advances, cash/bank openings, source tampering, foreign/ambiguous mapping refusal, changed-target rollback, migration resume/preflight, public-path refusal, encryption, wrong-password failure and fake-disk byte verification.
- Disposable MySQL 8.4.11 delivery baseline: **21 tests / 107 assertions**, one SQLite-specific restore test skipped. Native encrypted MySQL restore preserves posted journals, open items and stock. The complete root migration chain passes **1 test / 5 assertions**. Latest focused MySQL additions pass **7 tests / 32 assertions** (native restore, CLI commit and safety/recovery checks), plus **3 tests / 15 assertions** (cash/bank, migration resume and retired updater).
- Existing compliance regression: **62 tests / 348 assertions**, one MySQL-only race skipped. Accounting hardening: **26 tests / 117 assertions**, two opt-in races skipped. Scheduler/reset safety: **4 tests / 17 assertions**. Pre-company automatic-purchase compatibility: **1 test / 9 assertions**.
- Browser verifies the setup workspace and successful save: [saved screenshot](../phase11-12-proof/company-setup.png). Existing Phase 6 and Phase 9–10 records contain keyboard/mobile, performance and five-profile flow evidence. Those are historical fixture proofs, not current operator/hardware sign-off.
- CI now includes the delivery suite with a separately provisioned empty restricted MySQL restore database. New workflow edits have local test proof; remote CI has not been claimed as run.

Software package delivery preserves closed defaults for commercial/compliance/operations, optional capabilities and second-company activation. Full phase exit requires the pending customer/isolation/UAT/deployment evidence above.

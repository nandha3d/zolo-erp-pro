# Company foundation audit resolution

Source audit: [2026-10-03 company foundation audit](ZOLO_ERP_COMPANY_FOUNDATION_COMMIT_AUDIT_2026-10-03.md). This log records corrections and proof without changing the original audit snapshot.

## Contract normalization package

F-10 and F-14: execution plan and documents 03/26 now use canonical phases 0–12, existing `fiscal_years`, `App\Services\Platform\CompanyContext`, combined FY resolution, session keys `company_id`/`branch_id`/`financial_year_id`, and DEFAULT/MAIN creation inside `erp:backfill-company-context`. Index/README/manifest distinguish specifications, runbooks and execution evidence.

F-13: the execution plan formally adopts Option B. Inactive additive foundations may precede unresolved stabilization defects. Each D1–D12 defect has an owning phase and cutover gate. This does not permit unsafe transaction activation, second-company activation or capability work before full company isolation acceptance.

Validation: canonical phase headings, obsolete-contract search, manifest/file parity and Markdown link checks. No runtime behavior changes in this package.

## Remaining implementation findings

F-01 through F-09, F-11 and F-12 remain open pending their implementation and proof. F-02/F-03/F-04/F-05 require complete ownership, reader/writer, constraint, route and job/cache/file integration. They cannot be closed merely by enabling middleware. F-11 additionally needs representative legacy data and MySQL evidence.

Second-company activation and dependent capability work remain blocked. New evidence is appended below per delivered package.

## Scheduler safety correction

Source inspection during the ownership audit found `reset:db` scheduled every minute, dropping every database table without a demo guard. Removed that scheduled event. Manual reset now requires the demo environment, an explicit `--confirm-demo-reset` option and a readable nonempty dump before any cache/database work.

Validation: `ConsoleSafetyTest` verifies schedule exclusion, rejection outside demo even with confirmation, and mandatory explicit confirmation in demo. Three tests, 11 assertions pass using the targeted PHPUnit file. This closes the discovered reset activation hazard; other legacy scheduled writers still need company-context integration.

## F-12 purchase-creation correction

The user selected full supplier-bill AP recognition with received inventory and goods-in-transit separation. Received/Partial/Pending quantities now match native status meanings; status 4 Ordered remains an unbilled PO. Required `received_qty` is validated at API and service boundaries. Missing required accounts roll back all effects. Bank payment classification and zero-value goods are covered.

Proof: 14 ERP regression tests, 82 assertions pass on isolated SQLite fixtures. Purchase creation is corrected. Subsequent receipt release, legacy web update parity, company isolation and valuation/tax integration remain phase gates; this is not full commercial cutover.
## F-01/F-09 migration and currency correction

The 32-table migration preflights all existing planned columns and indexes before applying DDL, validates their exact structure, and resumes missing artifacts after a committed MySQL ALTER. Currency defaults must exist during dry-run. Migration 000003 widens the company currency reference to match `currencies.id` and adds a restricting FK after orphan validation; rollback preserves the widened type.

Proof: isolated official MySQL 8.4.0, 74 tests and 249 assertions, including the full source migration chain, repeated migration, injected failure after committed DDL, recovery/rollback without row loss, backfill dry-run/write/repeat, ID widths, orphan rejection, company context and commercial service regressions. GitHub Actions now runs this same suite against MySQL 8.4. Fixture clearing requires an explicit disposable database name and opt-in. No existing XAMPP data or production database was changed. Production-scale migration duration/locking and original seeded browser flows remain outstanding F-11 acceptance checks.

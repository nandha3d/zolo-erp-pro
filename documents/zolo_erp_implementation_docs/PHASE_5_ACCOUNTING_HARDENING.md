# Phase 5 accounting hardening and open items

Implementation package, 2026-10-04. Implements doc 10 and the Phase 5 section of the execution plan over the existing ledger. Production migration, retained-data reconciliation and UAT activation are not signed off.

## Result and ownership

`AccountingService` remains the compatible facade for sale, purchase, payment, expense and financial-report callers. `AccountingPostingService` owns atomic journal posting, number reservations, account projections and open-item creation. It revalidates company/branch/FY and posting actor, validates owned source/account/party references, refuses parent/inactive accounts, compares exact integer amounts at four decimals and serializes writes with the existing company lock.

Company-scoped posting/idempotency keys prevent duplicate effects. A conflicting payload fails before reserving another number. Historical source journals without keys require reviewed metadata migration before replay. Posted headers and lines reject Eloquent edits/deletes; corrections append an opposite journal with reason and original reference. Database administration/raw SQL remains a privileged boundary; reconciliation detects malformed journal totals and ownership.

`SemanticAccountResolver` resolves explicit owned mappings with no account-code fallback. Setup creates missing roles from unambiguous active leaf subtypes and preserves existing mappings. Unmapped or invalid roles block relevant posting. Fresh chart seeds distinguish sales discounts from sales revenue; discounts may use a contra-revenue or expense account. Retained installations need mapping review rather than reseeding their chart.

`OpenItemService` records normal-balance signed AR/AP items and immutable allocation/reversal rows. Positive amounts represent bills; negative amounts represent receipts, payments, advances or credits. Allocation requires the same company, branch, account and party, opposite signs, a valid unlocked date and sufficient remaining amounts. Partial allocation and reversal update commercial paid/status projections inside the transaction. Allocation itself creates no additional cash journal. Supplier documents with no supplier retain an explicitly unassigned payable; they are absent from party ageing until assigned by a reviewed migration.

`FinancialReportService` owns FY/branch-bounded Trial Balance, P&L, Balance Sheet, General Ledger, monthly drilldown, Day Book, Cash Book and historical ageing. Previous owned journal activity carries into opening balances; undated legacy account opening balances belong to MAIN. Existing fiscal date ranges are preserved. Ageing reconstructs allocations and reversals as of the report date, with 0–30, 31–60, 60+ buckets and separate credits. Cash Book amounts sum only cash/bank lines.

`PeriodCloseService` locks dates, closes years and appends audited unlock/reopen events. Administration uses the effective company membership role. Closing requires balanced journal history, matching account caches, valid allocation/open-item projections, reconciled control accounts and a balanced company trial balance. `erp:ledger-reconcile --rebuild` repairs only cached account balances; it never creates missing historical journals or opening items.

## Operator and API surfaces

Voucher Hub: `/accounting/vouchers`. Contra F4, Payment F5, Receipt F6, Journal F7, Hub F9 and Ctrl+Enter use the shared posting service. The form includes narration templates, cheque metadata, exact live balance, dynamic lines and an Against Reference bill modal. Advance and On Account retain negative open items for later allocation. Native dynamic selects avoid stale shared-layout dropdown state. Posted history exposes details and journal reversal; administrators can link legacy settlement accounts and manage periods.

Payments resolve their cash/bank role from `payments.paying_method`, using a validated `accounts.chart_of_account_id` link when present. Contra requires distinct cash/bank accounts; Payment credits settlement accounts and Receipt debits them. Posting needs an effective company administrator or `accounting.voucher.post`; reversal needs administration or `accounting.journal.reverse`. Period controls and account links require administration. Existing permissions infrastructure is reused; broader permission catalog seeding remains Phase 11.

Authenticated company-context API routes under `/api/v1/accounting`:

| Method | Path | Effect |
|---|---|---|
| POST | `vouchers` | Post voucher; body contains idempotency key |
| POST | `journal-entries/{id}/reverse` | Append journal reversal |
| GET | `open-items` | Company/branch scoped paginated items |
| POST | `allocations` | Allocate two matching opposite items |
| POST | `allocations/{id}/reverse` | Append allocation reversal |
| POST | `period` | Audited lock/unlock/close |
| GET | `day-book`, `cash-book`, `ageing` | Fiscal reports |
| GET | `monthly-ledger/{id}` | Fiscal monthly account activity |

Existing manual-journal endpoints also accept idempotency keys; the API accepts `Idempotency-Key`, and the existing journal form supplies a UUID for duplicate-submit protection. Ownership, branch and period values come from authorized context, not payload fields.

## Migration and setup

The additive migration `2026_10_04_000002_harden_accounting_and_create_open_items.php` adds chart controls, journal metadata, settlement links and three accounting history tables. It replaces global chart-code/mapping-role uniqueness with company uniqueness. It preflights duplicate company keys and incompatible existing tables, resumes committed MySQL DDL and preserves existing records. Rollback refuses accounting history or company keys that cannot regain global uniqueness.

On a reviewed MySQL UAT copy with verified ownership/backfill and a restorable backup:

```text
php artisan migrate --force
php artisan erp:account-mappings --company=COMPANY_ID --actor=ADMIN_ID
php artisan erp:ledger-reconcile --company=COMPANY_ID --actor=ADMIN_ID --branch=BRANCH_ID --year=YEAR_ID
```

Review missing mappings in Accounting → Account mappings, and settlement links in Voucher Hub. Use the same authorized IDs for an intentional projection repair:

```text
php artisan erp:ledger-reconcile --company=COMPANY_ID --actor=ADMIN_ID --branch=BRANCH_ID --year=YEAR_ID --rebuild --force
```

Existing monetary history is not synthesized into open items. Nonzero legacy AR/AP controls will report a difference until reviewed opening items/history are imported and reconciled. Preserve the existing 1970 opening semantics and fiscal dates; opening-data migration belongs to the reviewed migration work. A failed reconciliation prevents year close.

## Proof and limits

- Full SQLite company suite and focused accounting/ownership tests; final totals are recorded in `IMPLEMENTATION_PROGRESS.md`.
- Disposable official MySQL 8.4.11: accounting/ownership/report/numbering tests, original migration chain, committed-DDL resume and unsafe rollback rejection. Concurrent duplicate journal requests create one journal/reservation; competing bill allocations cannot overallocate. The shared supplier-settlement race also passes.
- Original seeded-schema MySQL web/API/accounting smoke: 28 tests, 204 assertions, passing.
- Authenticated in-app browser using a separate disposable database: all four voucher types, F4–F7/Ctrl+Enter, exact imbalance disabling, cheque metadata, Against Reference selection and 10.0000 bill minus 3.0000 receipt leaving 7.0000. Mobile 390×844 and desktop verification; no console errors. The mobile check repaired a shared collapsed-sidebar CSS defect; table overflow stays inside scroll containers.
- PHP syntax, JavaScript syntax and diff whitespace checks. No transaction-service hard-coded posting account numbers.

Remaining commercial web/POS adapters, legacy `PaymentService::payForPurchase` (D11), and source-document edit/delete convergence belong to Phase 6. The malformed/unowned optional damage/exchange/repair/water/cafe journal callers (D1/D2) remain behind their existing capability/cutover gates until their owned operational paths are converted; this package does not activate them. Manufacturing/optional-operation integration remains its assigned phase. Periodic inventory-close posting remains blocked pending dated valuation. GST rules/snapshots are Phase 7. Second-company activation and retained-data/UAT gates recorded in the progress document remain in force.

## Changed files

| Responsibility | Files |
|---|---|
| Schema and models | New Phase 5 migration; `AccountOpenItem`, `AccountAllocation`, `SemanticAccountMapping`; existing `ChartOfAccount`, `JournalEntry`, `JournalItem` |
| Posting and allocation | `AccountingService`, `AccountingPostingService`, `SemanticAccountResolver`, `OpenItemService`, `VoucherService`, `AccountingAccess`, `LedgerAmount`, shared ERP `PaymentService` |
| Reports and periods | `FinancialReportService`, `LedgerReconciliationService`, `PeriodCloseService`; `LedgerReconcile`, `SeedAccountingMappings` commands |
| HTTP integration | `VoucherController`, `JournalEntryController`, `ChartOfAccountsController`, `SemanticMappingController`, `FinancialReportController`, `AccountingApiController`; web/API routes |
| UI | Voucher, book, ageing and monthly-ledger views; existing semantic-mapping/general-ledger views and sidebar; voucher JavaScript; shared sidebar mobile CSS |
| Seed and tests | Chart seed classification; `AccountingHardeningTest`, accounting worker, MySQL migration recovery, accounting web/command safety/regression/writer/numbering tests; accounting fixture setup and legacy MySQL bootstrap; `phpunit.company.xml` |
| Records | This runbook, progress entry and labelled browser evidence |

Stock quantities and movement posting remain owned by Phase 4. This package adds no stock writer, tax calculation, external payment gateway action or parallel accounting ledger.

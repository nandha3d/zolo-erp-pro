# 10 - Accounting, Double Entry, Open Items and Vouchers

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP Pro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Harden the existing double-entry accounting implementation and make it the sole financial ledger for all industries, including Optech-style Contra/Payment/Receipt/Journal and bill-by-bill allocation.

## Target rules

- Never create a second Optech accounting ledger.
- Posted journals are immutable; correction is reversal + corrected posting.
- Business services request semantic account roles, never hard-coded account numbers.
- Posting is idempotent.
- Open receivable/payable items are explicit and allocatable.
- Cached account balances are rebuildable from journal lines.

## Data model / contracts

Extend accounting:

```text
chart_of_accounts
  company_id, financial_report_group, control_type, allow_manual_posting

journal_entries
  company_id, branch_id, financial_year_id
  document_series_id, posting_key, idempotency_key
  reversal_of_id, posted_at, void_reason

account_open_items
  company_id, party_type/id, account_id, source_type/id
  document_no/date, due_date, original_amount, open_amount, status

account_allocations
  company_id, allocation_no, payment source, open_item_id
  allocated_amount, allocation_date, reversal fields
```

Company-scoped semantic roles should include AR, AP, cash, bank, inventory, sales, COGS, tax input/output, returns, discounts, rounding, freight, damage and variance accounts.

## Services and ownership

Refactor current `AccountingService` toward:

```text
AccountingPostingService
SemanticAccountResolver
OpenItemService
FinancialReportService
PeriodCloseService
```

Posting key examples:

```text
sale:{id}:v1
purchase:{id}:v1
payment:{id}:v1
```

## Implementation sequence

1. Add company/FY keys and posting/idempotency metadata.
2. Seed semantic mappings per company.
3. Replace hard-coded account lookup in business posting rules.
4. Add open-item/allocation tables and service.
5. Convert payments/receipts to allocations.
6. Add reconciliation commands for control accounts and financial reports.

## UI / operator behavior

Fast accounting mode can preserve familiar shortcuts:

```text
F4 Contra
F5 Payment
F6 Receipt
F7 Journal
F9 Voucher Hub
```

Against Reference, Advance and On Account are allocation behaviors over the same open-item service.

## Acceptance and verification

- Every posted journal balances exactly within currency precision.
- Reposting same source does not duplicate a journal.
- AR/AP control accounts reconcile to open items.
- Partial allocation leaves exact remaining open amount.
- Reversal preserves source and allocation history.
- Trial balance, P&L and Balance Sheet are company/FY scoped.

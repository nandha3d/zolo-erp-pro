# 12 - Document Series, Printing and Communication

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP on SalePro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Create one atomic numbering, rendering and dispatch layer for A4, thermal, dot-matrix, email, WhatsApp and SMS.

## Target rules

- Authoritative document numbers never use `count()+1` or second-level timestamps.
- Each company/FY/document type can have an independent series.
- Rendering consumes a read-only DTO; templates do not calculate business totals.
- Messaging is after-commit and retryable; its failure does not unpost an invoice.
- Reprint and clone-from-prior are distinct operations.

## Data model / contracts

Tables:

```text
document_series
  company_id, branch_id, financial_year_id, document_type
  code, prefix, suffix, next_number, padding, reset_policy, is_default

document_number_reservations
  series_id, reserved_number, source_type/id, status

print_profiles
  company_id, document_type, name
  format: a4|thermal|dot_matrix|custom
  paper width/height-lines, template_key, copies_json, printer_id, settings_json

document_dispatch_logs
  document type/id, channel, recipient, template, status
  provider_message_id, attempted_at, failure_category
```

## Services and ownership

`DocumentNumberService` uses DB row locking to reserve/increment a series atomically.  
`DocumentRenderingService` returns HTML/PDF/thermal/ESC-P payload.  
`CommunicationDispatchService` logs/retries WhatsApp/SMS/email.

## Implementation sequence

1. Implement series tables and atomic reservation.
2. Replace new generic service timestamp references first.
3. Create print-profile abstraction.
4. Port current A4/thermal behavior.
5. Add optional 68-line fixed-width dot-matrix profile and real-printer fixture testing.
6. Add dispatch log/adapter around existing WhatsApp/SMS/email integrations.

## Acceptance and verification

- Concurrent invoice posts never produce duplicate number.
- FY reset happens exactly once and independently per company.
- A4/thermal/dot-matrix display identical financial totals.
- Messaging failure is retryable and does not rollback invoice.

# 03 - Multi-Company, Branch and Financial Year

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP on SalePro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Make legal company context a first-class ERP concept. This is separate from optional SalePro SaaS tenancy, which may isolate an entire customer's database.

## Target rules

- Every financial/operational transaction belongs to exactly one legal company.
- A company may have multiple branches and warehouses.
- Financial years are company-scoped and can be open, soft-closed or closed.
- Request company IDs are never trusted until membership is verified.
- Backfill existing records before enforcing non-null/global scopes.

## Data model / contracts

Create/extend:

```text
companies
  id, code, legal_name, trade_name, business_profile_id
  country_code, base_currency_id, state_code, timezone, status, settings_json

company_branches
  id, company_id, code, name, branch_type
  tax_registration_id, address/contact, is_active

company_user
  company_id, user_id, is_default, role_id_override

company_user_branches
  company_id, user_id, branch_id

fiscal_years (extend the existing table; never create financial_years)
  company_id, name, start_date, end_date
  status, lock_date, closed_at, closed_by
```

Add `company_id` to all company-owned masters/transactions/accounting records after a staged audit. Add `branch_id` where branch reporting or control is meaningful.

## Services and ownership

`App\Services\Platform\CompanyContext` exposes immutable company, branch and financial-year IDs. `CompanyContextResolver` combines membership, branch and FY resolution; no separate FY middleware is required. `ResolveCompanyContext` validates the coherent header/session tuple. Write services explicitly call `assertPostingDate`.

Session keys: `company_id`, `branch_id`, `financial_year_id`. Header values take precedence; a company-header switch must discard dependent session IDs from the previous company. Choose the sole authorized active branch when unambiguous; otherwise require explicit selection.

Preserve existing FY start/end dates, including calendar-year history. Migrate artificial 1970 opening records separately. Missing/ambiguous FY setup must be resolved through an authorized setup path before business-route activation.

API convention:

```text
X-Company-ID
X-Branch-ID          optional only when branch selection is unambiguous
X-Financial-Year-ID   optional when server can derive
```

## Implementation sequence

1. Add nullable ownership keys before backfill.
2. Initialize DEFAULT/MAIN, map warehouses and memberships inside `erp:backfill-company-context`; dry-run creates nothing. Validate currency references and timezone first.
3. Add nullable company keys to scoped tables.
4. Run `erp:backfill-company-context --dry-run`, then real backfill.
5. Validate zero orphan/null rows and company-aware uniqueness.
6. Add FKs/indexes; make mandatory keys non-null.
7. Enable company middleware and selected model scopes only after parity checks.

## Acceptance and verification

- Company A user cannot retrieve Company B sale/journal by guessed ID.
- Same document sequence code can exist in two companies.
- FY close in one company does not affect another.
- Background jobs restore company context before querying.

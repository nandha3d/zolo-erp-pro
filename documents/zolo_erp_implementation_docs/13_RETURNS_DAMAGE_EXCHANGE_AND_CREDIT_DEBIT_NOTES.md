# 13 - Returns, Damage, Exchange and Credit/Debit Notes

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP Pro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Unify reversal/adjustment workflows so commercial, stock, GST, accounting and open-item effects remain consistent.

## Target rules

- A stock return must be represented by a stock movement.
- A financial adjustment without quantity change must not create fake stock.
- Exchange is orchestration of normal return + new sale + settlement.
- Posted source documents are not silently edited.

## Data model / contracts

Disposition examples:

```text
restock
quarantine
damaged
expired
spillage
theft
quality_reject
sample
internal_consumption
```

Credit/debit notes can represent quantity return, rate difference, discount or tax correction. Stock moves only for quantity cases.

## Implementation sequence

1. Route current sales return/purchase return through shared reversal services.
2. Adapt damage/expiry to stock movement + mapped accounting loss.
3. Refactor exchange so normal return/sale records are authoritative; keep existing JSON only for compatibility if required.
4. Create formal credit/debit note document series and tax projection.
5. Add approval rules for high-value/late returns.

## Acceptance and verification

- Sales return restores correct stock identity and reverses AR/revenue/tax.
- Purchase return reverses inventory/AP/tax.
- Expired FMCG return enters quarantine rather than sellable stock.
- Serialized solar return updates serial/warranty state.
- Timber dimensional return restores exact identity or an inspected replacement identity.

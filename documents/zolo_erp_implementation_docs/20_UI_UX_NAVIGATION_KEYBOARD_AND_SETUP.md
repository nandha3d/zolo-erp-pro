# 20 - UI/UX, Navigation, Keyboard and Setup

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP Pro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Make the ERP understandable to business people by showing only relevant modules and keeping screens familiar, plain and fast.

## Target rules

- Do not rewrite the whole frontend before business foundations are stable.
- Use plain business wording, not framework/technical terminology.
- Progressive disclosure: common fields first, advanced fields collapsed.
- One screen should not force unrelated industry fields.
- Keyboard accelerators are optional enhancements, not the only navigation method.

## Implementation sequence

1. Introduce capability-driven navigation helper.
2. Add company/profile onboarding.
3. Standardize common form/list components on current Bootstrap foundation.
4. Implement fast-sales/faster-purchase keyboard focus manager.
5. Add company/business-specific label overrides.
6. Measure usability with non-technical profile users before visual polish.

## UI / operator behavior

Recommended top-level menu:

```text
Home
Sales
Purchases
Inventory
Accounts
People
Operations
Reports
Settings
```

Operations contains only enabled Manufacturing, Job Work, Projects, Routes, Repair, etc.

Company onboarding:

```text
Company → GST/state/currency → FY → Business Profile
→ Branch/Warehouse → Opening data → Users/Roles → Print preference
→ capability summary → activate
```

Form rules:
- no unnecessary nested tabs/scrolling;
- totals/actions stay visible;
- inline create preserves transaction state;
- clear empty-state instructions;
- consistent filters/search/status;
- visible keyboard focus and logical tab order.

## Acceptance and verification

- General user can add party/product/sale/purchase/payment without developer guidance.
- Disabled capability cannot appear in menu or be accessed directly.
- Keyboard and mouse workflows both work.
- Tablet layout remains usable for common operational screens.

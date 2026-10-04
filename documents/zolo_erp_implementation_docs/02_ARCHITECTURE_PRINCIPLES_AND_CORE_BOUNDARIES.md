# 02 - Architecture Principles and Core Boundaries

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP Pro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Define where shared ERP logic lives so Codex cannot accidentally build parallel textile, FMCG, timber or solar transaction stacks.

## Target rules

- Replace strict 'zero core modification' with 'no destructive vendor-core rewrite': additive platform migrations and tested integration hooks are allowed.
- Industry-specific logic stays modular; integrity-related company/stock/accounting changes may live in platform/core services.
- Controllers authorize/validate and delegate; application services own transaction boundaries.
- Critical stock/journal/open-item/numbering changes remain in one DB transaction unless an outbox pattern exists.
- Events are preferred for after-commit PDF, messaging, analytics and cache work.

## Data model / contracts

Recommended bounded contexts:

```text
PlatformCore
  CompanyContext, CapabilityService, permissions, audit, locks, series

CommercialCore
  Sales, purchases, payments, returns, pricing, party credit

InventoryCore
  Movements, availability, valuation, transfer, batches, serials, dimensions

AccountingCore
  COA, journals, posting rules, open items, allocations, close

IndiaCompliance
  GST registrations, GSTIN provider, tax determination, statutory projections

Operations
  Manufacturing, Job Work, Projects, Routes, Repair

Industry Packs
  Profile presets, labels, attribute definitions, reports, focused extensions
```

## Services and ownership

Recommended service contracts:

```text
CompanyContext
CapabilityService
DocumentNumberService
PartyService
ProductService
PricingService
TaxDeterminationService
SaleApplicationService
PurchaseApplicationService
InventoryMovementService
InventoryAvailabilityService
InventoryValuationService
AccountingPostingService
OpenItemService
DocumentRenderingService
CommunicationDispatchService
AuditService
```

## Implementation sequence

1. Introduce contracts and adapters around existing generic services.
2. Move shared business rules out of controllers into services.
3. Make industry modules depend on service contracts, not core controllers.
4. Add outbox only for work that must be reliably asynchronous.
5. Document the source-of-truth table/service for every bounded context.

## Acceptance and verification

- Core service test suite runs with no industry pack enabled.
- An industry pack can be disabled without making historical shared documents unreadable.
- Web and API paths call the same service for equivalent transactions.

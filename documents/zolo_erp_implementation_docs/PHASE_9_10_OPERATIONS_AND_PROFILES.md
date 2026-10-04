# Phases 9–10: gated operations and industry profiles

## Delivery and dependencies

This package implements canonical Phase 9 from documents 14/15 and Phase 10 from documents 16–19. It is isolated on `codex/phase9-10-operations-profiles` in the managed `phase9-10-operations-profiles` worktree. The primary checkout and the Phase 6 and Phase 7–8 branches retain their own work.

The branch contains the gated Phase 5/6 dependency snapshots (`399d526`, `8cfe866`) and the completed Phase 7–8 checkpoint `47f7535`, merged in `82c9017`. That checkpoint incorporates the final Phase 6 integration corrections. Phase 9/10 implementation began in `944b0c2`; the final package includes the integration tests and changes described here. Dependent package integration does not approve retained-data migration or production activation.

`ERP_OPERATIONS_ENABLED`, `ERP_SHARED_COMMERCIAL_ENABLED` and `ERP_COMPLIANCE_ENABLED` remain false by default. `CapabilityCatalog::OPTIONAL_ACTIVATION_READY` remains false. Test fixtures explicitly override those gates in disposable databases. No production migrations, backfill, recipe conversion, company activation or posting ran during this work.

[Changed-file list](PHASE_9_10_CHANGED_FILES.md) is relative to the Phase 7–8 prerequisite checkpoint.

## Shared ownership and transaction effects

| Workflow | Responsible owner | Stock and financial effects |
|---|---|---|
| BOM publication/import | `BomService` | Immutable effective-dated versions and normalized components; no stock or journal |
| Production planning | `ProductionService`, `DocumentNumberService` | Owned plan and atomic number; no consumption |
| Production completion | `ProductionService`, `InventoryMovementService`, `AccountingPostingService` | Typed consumption, output and recovered-scrap movements; exact consumed valuation plus direct/overhead costs; balanced perpetual-inventory journal |
| Job-work order/dispatch | `JobWorkService`, shared inventory | Company-owned virtual external warehouse; transfer of company material, without sales, tax or revenue |
| Job-work receipt | `JobWorkService`, shared inventory/accounting | Accepted material, separate quarantine transfer, explicit loss movement/journal, optional conversion outputs valued from actual accepted consumption |
| Processing service bill | `PurchaseApplicationService` | Normal service purchase, GST, journal and payable open item; no duplicate material inventory value |
| FMCG scheme/FEFO sale | `FmcgInventoryService`, shared commercial posting | Earliest valid batch balances; visible zero-price free lines also issue stock; normal tax and accounting |
| Expired batch disposal | Shared inventory/accounting via `FmcgInventoryService` | Audited stock write-off and loss journal; expired stock remains blocked for sale |
| Timber receipt/invoice | `DimensionCalculationService`, shared inventory/commercial/documents | Persisted geometry, formula, CFT/CBM and selected identity; invoice freezes proportional line volume |
| Project quote/invoice/payment | `ProjectService`, existing sales/payment services | Pending quotation has no stock/journal; invoice issues actual site serials and posts normal receivable/GST; shared payment settles it |
| Installation/warranty/service | `ProjectService` | Allocation, site transfer, installation, commission and append-only service history; no second sale or inventory issue at installation |
| Project margin | Shared posted journals | Revenue, COGS, service purchases, expenses and approved source-document notes; excludes reversed source documents |

`OperationPosting` supplies the trusted company, branch, fiscal year, effective actor permission, capability check, company lock, business-date policy, canonical request hash, retry record and audit event. Financial effects remain inside the shared inventory and accounting owners. Failed stock, cost, tax, mapping or identity validation rolls the complete transaction back, including number reservations and retry records.

Posted BOMs, production and operational records cannot be edited or deleted. Production reversal removes outputs before restoring components. A sold output prevents the whole reversal. Job-work receipts must be reversed before dispatch; a linked service purchase must be reversed first. Reversals preserve historical costs and documents through opposite movements/journals.

## Manufacturing and subcontracting

BOM versions validate company-owned active stock products, UOM factors, effective dates, default-range overlap and direct/indirect component cycles. The legacy importer validates all comma-separated arrays and the complete inter-recipe graph before any write, including dry runs. It preserves legacy strings and prices and uses stable import keys.

Production supports explicit actual component quantities, multiple finished outputs and recovered scrap. Cost weights allocate the exact total value; allocations that exceed ledger precision fail atomically. Numeric yield/waste snapshots use base quantity or CBM. CBM input must reconcile finished output, recovered scrap and documented waste within 0.5%.

Each subcontract order owns an external location. Dispatch snapshots actual shared-ledger chunks, including batch/variant/serial/piece identities and values. Partial receipts use remaining dispatch balances and an immutable process-policy snapshot. Rejects require a separate internal quarantine destination. Conversion consumes accepted material, preserves rejected raw material and receives finished output at the accepted material cost. Volume-based conversion reconciles accepted input and output CBM within 0.5%; loss is recorded separately.

The shared inventory identity contract keeps one physical identity in one location. Serial and piece transfers move the full remaining identity. Split physical pieces into reviewed identities before dispatch; bulk stock supports partial dispatch receipts. This prevents an identity being simultaneously held at a site and a job worker.

Material dispatch documents support A4 and a generic 80-column, 68-line text layout. Textile labels select “Material DC”; other profiles use “Material Dispatch”. Material documents never create a tax invoice or revenue.

Legacy Manufacturing, Recipe, OptechJobWork and ProjectManagement HTTP controllers are retired at the provider boundary, including cached module routes. Authorized HTML reads redirect to the owned operations screens; legacy writes return 409. Historical tables remain for reviewed backfill/import. New operational stock posting uses shared movement services.

## Profile behavior

Profile application uses the existing capability engine, system presets and company administrator boundary. It installs company-owned product attribute definitions, labels, process presets, quantity/print defaults and subtype capabilities. Switching profiles hides disabled attributes without deleting their values. It does not change existing stock flags, UOM factors, prices, valuation or historical invoices.

- **General Trading:** generic labels and no industry attributes or transport extensions.
- **FMCG:** distribution, manufacturing and optional route-distribution subtypes; MRP/manufacturing/expiry capture, FEFO, expiry disposal and normalized PCS/BOX/CASE factors. Buy/free schemes store base quantities and publish explicit free stock lines.
- **Textile:** wholesale and in-house-processing subtypes; three-decimal quantities, fabric/construction/GSM/design/color/roll/rack attributes, process defaults, LR date/bale/bundle metadata and frozen 68-line print precision. Existing previous-rate, outstanding, pending-bill, inline-master and copy-invoice owners remain shared.
- **Timber:** trading and processor subtypes; dimensional receipts, species/grade/dimension filtering over actual owned piece balances, selected-piece invoicing, multi-output sawing, offcuts and external processing.
- **Solar:** EPC site/project records, system BOM expansion into pending quotation lines, shared procurement, serial allocation, site dispatch, installation/commission, invoice/payment links, margin and warranty/AMC history. A replacement continues the original warranty/AMC dates and references the prior contract; inspections and repairs append history with retry protection.

Industry presets enable the existing shared sales and purchase entry capabilities. The common UI provides a UOM selector; configured product factors remain authoritative. The profile migration refreshes system presets without changing existing company choices. Optional route/van and messaging workflows retain their separate existing capability/provider gates.

Dimension formulas explicitly normalize mm/cm/m/in/ft and persist `rectangular-v1`, CBM and CFT. Receipts after the dimensional migration require units; values below supported precision are rejected. Earlier schemas retain their original unnormalized geometry without guessing a metric unit. Historical identities with unknown units need reviewed migration before volume-based operations. Invoice snapshots preserve both formula and line volume after profile changes.

## Web/API and permissions

Web screens are under `/operations/{manufacturing|job-work|profiles|projects|stock}`. Details use `/operations/{production|job-work|project}/{id}`. API equivalents use `/api/v1/operations` with Sanctum and the same company-context middleware and services.

Posting actions include BOM, production plan/complete/reverse, job order/dispatch/receive/service bill/reversal, project/quotation/allocation/dispatch/install/commission/service history/document links and expiry write-off. `configure/{profile|process|scheme}`, `product/{id}/attributes`, `inventory/{fefo|pieces}` and `dispatch/{id}/print` expose the corresponding owned services. Mutations use `Idempotency-Key` or `idempotency_key` where effects require replay protection; a reused key with changed effects fails.

New permissions are `manufacturing.read`, `manufacturing.bom.manage`, `manufacturing.production.post`, `manufacturing.production.reverse`, `job_work.read/manage/dispatch/receive/reverse`, `profiles.manage`, and `projects.read/manage/install/warranty`. Shared product, sale, purchase and payment permissions still apply to their operations. Role setup exposes the new permission names using array values so PHP form normalization cannot change dotted names. Company Owner/Admin policy still controls profile activation.

Company/branch-scoped lookups and parent-linked children prevent foreign record access. Source links reject another project's document and another customer's sale. An allocated serial cannot be issued through an unrelated sale; a linked project invoice issues it from the site's warehouse after dispatch. The active allocation key prevents simultaneous reservations. Contracts and service history remain available after replacement or sale.

## Validation and saved evidence

- `phpunit.operations.xml`: **28 tests / 209 assertions on SQLite**, with the three MySQL-only races skipped. The final malformed retry-key HTTP check also passes separately (**1 test / 14 assertions**). Covers costs, rollback, reversal, BOM graph/import, subcontract receipts, FEFO/free quantity/disposal, dimensional conversion/snapshots, solar lifecycle, service replay, owned HTTP/API and profile field visibility.
- `phpunit.compliance.xml`: **62 tests / 348 assertions**, with one MySQL-only approval race skipped. Shared GST, returns, document rendering and commercial boundaries pass with these extensions.
- Shared inventory/capability owners: **33 tests / 213 assertions** pass. The integrated company foundation also passed **280 tests / 2,535 assertions**, with 18 opt-in skips, before the final focused compatibility checks.
- Disposable MySQL 8.4.11: **28 tests / 226 assertions** pass, including concurrent production retries, competing receipts and competing serial allocations. The original full migration chain passes **1 test / 5 assertions**.
- PHP/JavaScript syntax and diff whitespace checks pass. CI now runs the operations suite against its disposable MySQL service.

Saved browser evidence uses disposable fixtures: posted production with cost 130, textile attribute persistence/profile switching, keyboard posting, add/remove rows and 390×844 mobile layout with no horizontal overflow. [Production proof](../phase9-10-proof/production-posted.png), [textile profile](../phase9-10-proof/textile-profile.png), [mobile profile](../phase9-10-proof/profile-mobile.png). The final resumed browser pass was blocked by the app URL policy on a connection-error page; final profile forms, timber calculation/selection and solar service history have automated HTTP/service proof. Printer hardware and representative operator UAT remain unsigned.

## Migration and acceptance procedure

1. Integrate accepted Phase 5–8 dependencies and this branch in a staging checkout. Use its own Composer autoload files. Keep production flags disabled.
2. Back up the database and uploaded files. Complete the existing company/branch/FY backfill and stock/accounting reconciliation gates first.
3. Review the actual schema, then run the normal root migration chain in staging. The additive migrations are `2026_10_07_000001_create_manufacturing_and_job_work.php` and `2026_10_08_000001_create_industry_profile_extensions.php`. They preflight existing artifacts and resume after committed MySQL DDL. Their `down()` methods reject destructive rollback; use a reviewed forward rollback that preserves operational, dimensional and warranty history.
4. Dry-run recipe conversion using `php artisan erp:import-boms --company=<id> --branch=<id> --year=<id> --actor=<id> --dry-run`. Resolve invalid arrays, units, dates, foreign references and cycles before the same reviewed command without `--dry-run`. Do not mass-assign legacy productions or projects to a company without source evidence.
5. Map company semantic inventory, COGS, production-cost and damage/loss accounts. Configure product UOM factors, stock flags, process loss thresholds, output cost weights and dimensional units explicitly. Presets do not infer these values.
6. Obtain the existing foundation/optional-capability acceptance before enabling capabilities in a staging fixture. Then enable the three ERP flags only in approved UAT. Production flag changes are a separate deployment decision.
7. Run the acceptance cases below with representative products, branches, permissions, tax, printers and retained records. Compare documents, movements, projections, journals, open items, tax snapshots, numbers and audit events after normal posting, retries, failures and reversals. Sign retained-data reconciliation and hardware/operator UAT before production cutover.

Acceptance cases implemented in automated fixtures:

- Production consumes value 100 and adds direct cost 20 plus overhead 10; output value and journal reconcile to 130, and reversal restores stock.
- Textile purchases 500.000 MTR, sends 500.000, receives 480.000 with 4% loss, bills the service, posts a GST credit sale, receives a partial payment and freezes a 68-line document.
- FMCG receives two expiry batches, selects FEFO, issues paid/free quantities, disposes expired stock, and purchases a CASE/sells a BOX using base factors.
- Timber receives dimensional identities, sells a subset with a frozen invoice volume, and reconciles 10 CBM input to 8 CBM output, 1 CBM offcut and 1 CBM waste. External processing separately reconciles accepted CBM and loss.
- Solar quotes a 5kW system, purchases missing serials, allocates/dispatches/installs/commissions them, invoices and receives payment. Margin reconciles to journals, including a later rate credit. Replacement and inspection preserve contract/service history.

Software package completion does not establish production activation, retained-data migration or signed phase exit. Broader UI/API/security completion and deployment/UAT remain canonical Phases 11 and 12.

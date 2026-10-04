# Phase 7–8: gated tax, returns and document package

## Delivery and dependencies

This implements the inactive software package for specifications 11–13. The branch is `codex/phase7-8-tax-returns-documents`. It contains the Phase 5 dependency snapshot `399d526` and the initial Phase 6 snapshot `8cfe866`; final Phase 6 changes from `c00831a` were reconciled into this package. The original checkout and the separate Phase 6 branch were not changed.

The reconciliation preserves Phase 5 settlement idempotency and voucher/report routes, and Phase 6 shared posting, product locking, query pagination and draft behavior. Shared account lookup accepts the explicit actor used by return approval workers. No second sale, purchase, stock ledger or accounting ledger was introduced.

`ERP_SHARED_COMMERCIAL_ENABLED` and `ERP_COMPLIANCE_ENABLED` default to false. Optional capabilities and second-company activation remain subject to the existing gates. This delivery does not establish retained-data reconciliation, statutory approval, printer certification or production phase completion.

## Ownership and behavior

### GST

`TaxSetupService` owns company-scoped registrations, party profiles, tax categories, effective rate periods, HSN/SAC codes and product assignment. Only company administrators can change setup. Overlapping periods are rejected; close an existing open period before adding its successor. GSTIN shape/checksum validation is separate from registration verification.

`TaxDeterminationService` determines ordinary domestic GST before shared pricing. It freezes company and party details, place of supply, classification, taxable value, discount allocation, rate, CGST/SGST/IGST/cess and input-credit eligibility on each posted document and line. Client-supplied snapshots and recoverable tax are discarded. Intra-state CGST/SGST and interstate IGST use the branch registration and supplied place of supply or party state. Services require SAC; physical goods require HSN. Exempt, nil-rated and non-GST supplies retain their classifications. Composition registrations do not claim input credit. Reverse-charge purchases carry separate mapped liability and eligible or blocked input effects.

Eligible purchase input tax is excluded from inventory valuation; blocked supplier tax stays in landed cost. Blocked reverse-charge tax is recorded as operating expense. Existing semantic account mappings select all accounts; transaction rules contain no account IDs or account codes.

`GstProjectionService` records posting and reversal projections in the same transaction. Reports and review exports use saved snapshots, including signed note/reversal effects, outward classifications, inward reverse charge and eligible/blocked input-credit totals. Subsequent master changes cannot rewrite posted tax.

The only export schema is **`zolo-gst-review-v1`**, with `purpose=review_only` and `filing_ready=false`. It is not a GST portal payload. Unknown statutory schemas are rejected. Import/export/SEZ and intra-state UTGST treatment require reviewed adapters and are refused by the domestic determination path. GST freight must be a separately classified service line rather than an unclassified shipping amount. These limits must be reviewed against the company's actual supply cases before activation.

`GstinLookupService` supports a configured HTTPS provider returning normalized verification data, with a bounded timeout, cache and company audit. Lookup is read-only: failure never changes party or invoice data. Without a provider, verification remains manual. `GST_LOOKUP_URL` and `GST_LOOKUP_TOKEN` are deployment settings; real provider credentials and its response contract require UAT.

### Returns, financial notes, loss and exchange

`ReturnService` extends existing sales/purchase return headers and lines. It links each line to the original shared commercial line. Quantity returns restore or issue the attributed stock identity, variant and batch using the authoritative movement service. Financial rate/discount/tax notes use zero quantity and never create stock. Formal credit/debit numbers are assigned only when posting succeeds.

By default every note awaits approval. Company administrators can set `companies.settings_json.return_policy.amount` and `.days`; notes exceeding either threshold await approval. Approval checks the open period, source status, cumulative quantity/value limits and original stock selections again under locks. A selection changed after submission requires a new return. Repeated create/approve requests do not repeat document, stock, journal, open-item, tax or audit effects. Posted headers and lines cannot be edited, deleted or appended. Full invoice reversal is refused once posted notes exist.

Sales returns offset receivables, reverse revenue/tax and restore inventory/COGS at the original attributed stock cost. Purchase returns offset payables and reverse inventory/input tax, with a mapped variance when current inventory valuation differs. Original tax rates are retained; final quantity returns consume the exact saved residual after earlier quantity returns.

Expired or otherwise non-sellable customer returns require an owned quarantine warehouse. Quarantine cannot issue ordinary sales or transfers. Serialized and dimensioned returns require exact source movement selections; returns preserve warranty dates and record return inspection/state. No legacy attribution is guessed. `StockLossService` issues disposal movements at ledger cost and posts mapped damage/operating expense. Damage and exchange remain optional capabilities.

`ExchangeService` combines an approved unused quantity sales return, a normal new sale to the same customer and open-item allocation. Existing exchange JSON is compatibility data; linked return, replacement sale, movements and journals are authoritative. Refund balances remain open credits rather than invented cash refunds.

### Documents and delivery

`DocumentNumberService` reuses the existing company/branch/FY series and row locks. GST sale/note numbers are restricted to the supported alphabet and at most 16 characters. Existing incompatible long series must be reviewed and configured before GST posting. Printing does not reserve a new number.

`DocumentRenderingService` freezes a read-only document DTO at posting. A4 HTML/PDF, thermal and fixed-width output consume its saved totals and tax details. Master edits cannot change reprints. Clone-from-prior remains Phase 6's separate operation and posts a new document through normal validation. Reversed invoices display reversal status without recalculating amounts.

Company-owned print profiles select format, width, height, columns and copies. Dot-matrix text uses a default 68-line page and optional ESC/P reset/form-feed payload, behind `printing.dot_matrix`. A real printer, encoding, paper alignment and device compatibility have **not** been certified.

`CommunicationDispatchService` creates a durable company/branch/FY outbox after permission checks. Jobs send only after commit. Email uses the configured application mail transport and full saved HTML. SMS and WhatsApp send document details; they do not attach PDFs. Twilio SMS uses form parameters; WhatsApp requires an approved Content SID with variables 1=document number, 2=currency/total, 3=company name. Company channel credentials are encrypted and secret tokens are excluded from flashed input.

`sent` means transport/provider acceptance, not confirmed recipient delivery. Confirmed configuration/provider rejection can retry. Connection loss, invalid acknowledgement, server error or interrupted sends become `delivery_unknown` and require provider reconciliation; they are never automatically resent. Missing queue execution leaves a durable pending entry. `php artisan erp:dispatch-documents --limit=100` processes pending entries; `--recover-stale` marks sends interrupted for more than ten minutes as unknown. Dispatch failure never changes commercial posting or stock.

## Review on an isolated UAT copy

1. Complete Phase 5/6 acceptance and ownership/backfill/reconciliation gates. Back up the database and pause writers according to the existing migration plan.
2. Apply the additive migrations `2026_10_06_000001_create_tax_compliance_contracts.php` and `2026_10_06_000002_extend_returns_and_document_delivery.php` after their foundation dependencies. Do not run the test fixtures against a business database.
3. Verify semantic mappings for input/output CGST, SGST, IGST and cess; receivables/payables, sales returns, inventory/COGS, variance, damage and operating expense. Configure valid document series.
4. In the UAT deployment only, enable both environment gates and clear/rebuild the application configuration cache. Grant `gst-index`, appropriate return permissions, `returns-approve` and `documents-dispatch` to the intended roles. Keep optional capabilities disabled until their own acceptance.
5. Use `/compliance/setup` for registrations, party profiles, effective categories/rates, product HSN/SAC assignment, return thresholds, empty quarantine warehouses, print profiles and encrypted channels. A warehouse must be owned and empty before its quarantine flag changes.
6. Compare source document, attributed movement/projection, journal, open item, frozen tax and audit for intra/interstate sale, purchase, partial/full return, financial note, quarantine/disposal and exchange. Verify reversals and repeated requests.
7. Review `/compliance/gst/report` and the JSON export with the company's accountant. Obtain a currently verified statutory schema adapter before any portal filing. Test the real GST provider, approved messaging templates, mail transport and delivery reconciliation.
8. Verify real A4/thermal/dot-matrix printers and representative retained data before production sign-off. Legacy documents without frozen snapshots or source movement attribution require an explicit reviewed conversion; this package does not infer them.

Migrations preflight existing owned tables, resume missing additive columns/indexes/foreign keys after committed MySQL DDL and reject incompatible owned schema before changing it. Rollback refuses posted note/loss/exchange, document snapshot, projection and delivery history. Empty rollback keeps legacy tables, company ownership and widened damage/exchange decimal precision; it does not narrow retained monetary values.

## Proof and changed files

Local checks use disposable fixtures only. The company regression suite passes **280 tests / 2,517 assertions**, with 18 documented opt-in MySQL/performance skips. The compliance suite passes **62 tests / 348 assertions**, with the MySQL-only approval race skipped. PHP syntax passes for 86 changed PHP/Blade files; both changed JavaScript files pass native syntax checks, and the staged diff passes whitespace validation. An actual PHP HTTP stack produces a valid PDF response and checks web/API permissions, retries, approval and legacy-route adaptation.

Disposable official **MySQL 8.4.0**, bound to loopback on port 33078, passes **28 tax/return/migration/concurrency tests / 161 assertions**. The two-process approval barrier proves that competing full returns cannot over-return, and competing approvals of the same note cannot post twice. The original migration-chain check also passes **1 test / 5 assertions**. These are local fixture results, not CI or retained-data results.

Browser verification covers actual submission/approval of a return, immutable A4/thermal totals, delivery failure/retry, mobile return layout and a GST fast-entry sale with place of supply 29 producing IGST. Fixtures have no configured external delivery credentials. Proof files are under `documents/phase78-proof/`: `returns-posted.jpg`, `delivery-failure.jpg`, `a4-note.jpg`, `gst-sale-posted.jpg` and `gst-interstate-a4.jpg`. Browser console checks report no warnings/errors.

Changed-file ownership:

- `app/Services/Tax/`, `config/compliance.php` and the first migration: setup, determination, lookup, projection and review export.
- `app/Services/Commercial/ReturnService.php`, `StockLossService.php`, `ExchangeService.php`, model history guards and inventory command/policy extensions: adjustments over the existing ledgers.
- `app/Services/Documents/`, `app/Jobs/DispatchDocument.php`, `app/Console/Commands/DispatchDocuments.php` and the second migration: frozen rendering, print profiles and durable delivery.
- Shared commercial/ERP/accounting/numbering services: trusted snapshot integration, mapped postings and Phase 6 reconciliation.
- `ComplianceController`, compliance middleware/routes, existing role/sidebar/entry views, `resources/views/backend/compliance/`, `public/css/compliance.css`, `public/js/compliance.js` and commercial entry changes: operator and API flows.
- `phpunit.compliance.xml`, `tests/Feature/*Compliance*`, `TaxComplianceTest`, `ReturnsDocumentsTest` and support fixtures/workers: behavior, migrations, race and browser proof. The temporary browser server is fixture-only and requires its explicit scratch database pointer.

## Statutory/provider references

The implementation uses a conservative domestic review contract. These sources inform the rules; they do not certify every company supply or establish a current filing JSON schema:

- [CBIC invoice rules](https://cbic-gst.gov.in/gst-invoice-rules.html): invoice particulars and numbering. This is a historical rules page; verify applicable amendments before activation.
- [CBIC tax information: IGST Act](https://taxinformation.cbic.gov.in/content-page/explore-act/1000616/1000001): supply classification reference.
- [CBIC tax information: CGST Act](https://taxinformation.cbic.gov.in/content-page/explore-act/1000304/1000001): credit/debit-note reference.
- [GST portal GSTR-1 user guide](https://tutorial.gst.gov.in/userguide/returns/Creation_of_Outward_Supplies_Return_in_GSTR-1.htm): filing workflow reference. A currently accepted machine schema was not verified, so portal filing remains blocked.
- [Twilio Message resource](https://www.twilio.com/docs/messaging/api/message-resource): provider message submission contract and WhatsApp Content SID usage.

Outstanding phase-exit work: current statutory filing adapter and accountant review; real GST-provider and messaging UAT; printer hardware fixtures; retained-document migration/reconciliation; and existing foundation, optional-capability and production activation sign-offs.

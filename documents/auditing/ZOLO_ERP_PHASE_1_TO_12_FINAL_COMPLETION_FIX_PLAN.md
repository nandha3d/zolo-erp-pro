# zoloERP Pro — Phase 1–12 Completion, Cutover and Final Acceptance Plan

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP Pro / Laravel 10 / MySQL 8.4  
> **Purpose:** Close the remaining gaps preventing the canonical Phase 1–12 program from being declared complete.  
> **Canonical execution source:** `documents/zolo_erp_implementation_docs/26_CODEX_EXECUTION_SEQUENCE_AND_CHECKLIST.md`  
> **Use with Codex:** Implement in the exact order in this document. Do not skip exit gates, do not mark external UAT as complete without evidence, and do not create replacement ERP subsystems where shared owners already exist.

---

# 1. Current audited state

The Phase 1–12 **software packages substantially exist**, but the complete program must **not** yet be marked 12/12 complete.

The important current state is:

- `main` is behind the cumulative Phase 5–12 implementation branch.
- The cumulative implementation is currently on `codex/phase9-10-operations-profiles`.
- Phase 1 full legal-company isolation is still open.
- Phase 2 capability implementation exists, but optional activation is intentionally blocked.
- Phase 3 atomic numbering exists, but legacy authoritative number generators still exist.
- Phase 4 stock ledger exists and operational Phase 4c conversion exists, but legacy commercial stock writers still mutate projections directly.
- Phase 5 accounting/open-items implementation is strong.
- Phase 6 shared commercial implementation exists but remains disabled by default.
- Phase 7–8 tax, returns and document package exists but remains gated.
- Phase 9–10 manufacturing/job-work and industry profiles exist but remain gated.
- Phase 11 workspace/API/security package exists, but broad legacy web/company isolation acceptance is still incomplete.
- Phase 12 migration/backup/restore/health tooling exists, but customer UAT, retained-data acceptance, provider/hardware acceptance and production sign-off remain incomplete.
- The cumulative branch has not yet been certified by the complete intended GitHub CI matrix.

This plan closes those gaps without reimplementing delivered packages.

---

# 2. Non-negotiable architecture rules

## 2.1 One ERP core

Do not create parallel copies of:

- Sales
- Purchases
- Product master
- Inventory ledger
- Accounting ledger
- Tax engine
- Numbering engine
- Document rendering
- Customer/supplier master
- Manufacturing
- Job work

Industry behavior must remain capabilities/profiles around the shared ERP core.

## 2.2 Shared owners remain authoritative

Continue using the existing owners:

- `CompanyContextResolver`
- `CompanyWriteGuard`
- `CapabilityService`
- `DocumentNumberService`
- `InventoryMovementService`
- `InventoryReconciliationService`
- `AccountingPostingService`
- `SemanticAccountResolver`
- `OpenItemService`
- `FinancialReportService`
- `CommercialApplicationService`
- `SaleApplicationService`
- `PurchaseApplicationService`
- `ReturnService`
- `TaxDeterminationService`
- `DocumentRenderingService`
- `CommunicationDispatchService`
- `ProductionService`
- `JobWorkService`
- `IndustryProfileService`
- `OpeningImportService`
- `BackupService`
- `ErpHealthService`

Do not bypass these with new direct SQL/business logic unless the existing owner explicitly cannot support the required behavior.

## 2.3 No new direct stock writes

No new code may directly change authoritative quantities in:

- `products.qty`
- `product_warehouse.qty`
- `product_variants.qty`
- `product_batches.qty`
- serial identity state
- dimension identity state

All authoritative stock changes must go through `InventoryMovementService`.

## 2.4 No new hard-coded posting accounts

Do not add transaction logic using hard-coded account IDs/codes.

Use:

- `SemanticAccountResolver`
- company-owned semantic mappings
- reviewed settlement-account links where required

## 2.5 No new unsafe numbering

Do not generate authoritative document numbers from:

- timestamps
- seconds
- `count() + 1`
- random numbers
- record counts
- request-side values

Use `DocumentNumberService`.

## 2.6 No fake acceptance

Do not:

- set acceptance flags true just to make tests green;
- mark GST filing as ready without a reviewed statutory adapter;
- mark printer UAT as complete without actual hardware;
- mark provider UAT complete without real provider acceptance;
- sign customer UAT programmatically;
- fabricate off-site backup verification;
- claim production go-live from fixture tests.

---

# 3. Branch and integration strategy

Before functional fixes, establish one authoritative completion branch.

## 3.1 Create the closure branch

Start from the latest cumulative Phase 1–12 implementation, not from old `main`.

```bash
git fetch --all --prune

git checkout codex/phase9-10-operations-profiles
git pull --ff-only

git checkout -b codex/phase1-12-final-closure
```

Verify the cumulative branch already contains current `main`.

```bash
git merge-base --is-ancestor origin/main HEAD
```

If this fails:

1. stop;
2. compare the branches;
3. merge current `main` into the closure branch;
4. resolve conflicts by preserving:
   - latest company isolation work;
   - latest stock migration recovery work;
   - latest zoloERP UI/rebranding;
   - latest cumulative Phase 5–12 services;
   - canonical documentation under `zolo_erp_implementation_docs`.

Do not force-push or rewrite accepted history merely to make the graph cleaner.

## 3.2 Record the implementation baseline

Update:

`documents/zolo_erp_implementation_docs/IMPLEMENTATION_PROGRESS.md`

Record:

```text
closure branch
starting SHA
main SHA
cumulative branch SHA
PHP version
Laravel version
MySQL version
route count
test suite results
frontend/browser baseline
known activation gates
```

---

# 4. Workstream A — complete Phase 1 company/branch/FY isolation

This is the highest-priority blocker.

Phase 1 is not complete until company isolation is a property of the **whole active application**, not only the new API/shared service paths.

## 4.1 Inventory every company-owned reader and writer

Use the existing ownership matrix as the starting point.

```bash
rg -n "Customer::|Supplier::|Product::|Warehouse::|Biller::|Account::|Sale::|Purchase::|Payment::|Transfer::" app Modules routes
rg -n "DB::table\(" app Modules
rg -n "cache\(\)|Cache::" app Modules
rg -n "storage_path|public_path|Storage::" app Modules
rg -n "Artisan::|schedule->|Command" app
```

Classify each hit:

```text
MASTER READ
MASTER WRITE
TRANSACTION READ
TRANSACTION WRITE
REPORT
CACHE
JOB/SCHEDULER
IMPORT
EXPORT
DOWNLOAD
PUBLIC FILE
SETTINGS
OPTIONAL MODULE
```

Update:

```text
COMPANY_TABLE_OWNERSHIP_MATRIX.md
STOCK_AND_TRANSACTION_WRITER_AUDIT.md
IMPLEMENTATION_PROGRESS.md
```

Do not mark Phase 1 complete while an active company-owned path is unclassified.

## 4.2 Make active business routes resolve trusted company context

The current legacy route area broadly uses:

```text
common + auth + active
```

Complete the migration so business routes that touch company-owned data also resolve authorized company context.

Do **not** add company context to routes that must work before context exists:

- login;
- installer before installation;
- authorized company/FY setup;
- context-selection endpoints;
- setup endpoints explicitly designed to bootstrap context.

Recommended:

```text
auth
active
company.context
permission/capability middleware
controller/service
```

Do not trust request-body `company_id`, `branch_id` or `financial_year_id`.

## 4.3 Fix legacy master reads

Known examples include legacy Sale UI reads such as unscoped:

```php
Customer::where('is_active', true)->get();
Warehouse::where('is_active', true)->get();
```

Convert active business reads to trusted context-aware query paths.

Audit at minimum:

```text
Customer
Supplier
Product
Product_Warehouse
Warehouse
Biller
Account
Tax
Customer groups
Cash registers
Payments
Sales
Purchases
Returns
Transfers
Adjustments
Quotations
Delivery/packing
Expenses/income where company-owned
Operational/profile data
```

Use explicit company scopes/query services plus branch restrictions where required.

For stock and warehouse data:

```text
company match
+
authorized branch
+
warehouse belongs to company/branch
```

Do not introduce a blanket global Eloquent scope across all legacy models unless installer/backfill/setup/admin paths are reviewed.

## 4.4 Fix legacy writes

Every active business mutation must either:

1. call the shared application service; or
2. revalidate trusted company context and company-owned parent references before persisting.

Reject:

- foreign customer/supplier IDs;
- foreign warehouse;
- foreign product;
- foreign account;
- foreign branch;
- foreign fiscal year;
- foreign nested line;
- guessed document ID from another company.

Never derive ownership from hidden form input, query string or posted company IDs.

## 4.5 Complete database ownership/backfill

Using the ownership matrix, finish company ownership on remaining operational/settings tables.

For each table:

1. classify as global reference / company-owned / company+branch / company+FY / child-inherits-parent;
2. add nullable ownership first when retained rows exist;
3. backfill only from explicit source evidence;
4. reject ambiguous rows;
5. add indexes;
6. add same-company FKs where practical;
7. add company-aware uniqueness;
8. make ownership mandatory only after every writer/backfill path is compatible.

Do not automatically assign historical rows to a company when ownership is ambiguous.

Do not rewrite existing fiscal-year date ranges.

## 4.6 Company-aware uniqueness

Audit unique rules and indexes that are still global.

Typical target:

```text
old:
unique(code)

new:
unique(company_id, code)
```

or:

```text
unique(company_id, branch_id, code)
```

Review at minimum:

- customer codes;
- supplier codes;
- product codes/SKUs;
- account codes;
- document series;
- semantic mappings;
- operational reference numbers;
- settings keys where company-specific.

## 4.7 Fix company-aware caches

Search for shared cache keys such as:

```text
general_setting
user_role
permissions
product_list
warehouse_list
biller_list
customer_list
```

If the value changes by company, branch, role or user, the key must include the relevant trusted scope.

Example:

```text
company:{companyId}:branch:{branchId}:warehouse_list
company:{companyId}:role:{roleId}:permissions
```

Prefer bypassing cache for security-sensitive authorization decisions.

Add tests proving one company cannot receive another company's cached data.

## 4.8 Jobs and scheduler

No production scheduled command may operate globally across company-owned data.

For each command:

- require explicit company context; or
- iterate active companies safely; or
- retire the legacy global job.

Never reuse one company's context for another iteration.

Add tests for:

```text
Company A job effect
Company B unchanged
invalid/closed FY rejected
foreign branch rejected
retry is idempotent
```

## 4.9 Imports

Every business import must:

- require explicit company context;
- validate every mapped parent;
- reject foreign IDs;
- use shared posting services for transactional effects;
- never modify stock/accounting directly;
- create a batch/audit record;
- support dry-run/validation where destructive business effect is possible.

## 4.10 Exports/downloads/public files

Company-owned exports and downloads must check:

```text
authentication
company membership
branch access where applicable
permission
record ownership
```

Move sensitive generated files out of publicly enumerable paths.

Use private storage for:

- accounting exports;
- backups;
- migration evidence;
- customer-specific documents when direct public access is not intended.

## 4.11 Phase 1 test matrix

Add/extend real HTTP tests covering at least two companies and restricted branches.

```text
Company A cannot list Company B customer
Company A cannot guess Company B product ID
Company A cannot load Company B sale
Company A cannot post against Company B warehouse
Company A cannot use Company B account
Company A cannot see Company B cached dropdown values
Company A cannot export Company B data
Company A cannot download Company B file
Company A job cannot mutate Company B
branch-restricted user cannot access another branch
closed FY can be read historically where allowed
closed/locked FY cannot post
body company spoof is ignored/rejected
```

Use MySQL for ownership/FK/concurrency proof.

## 4.12 Phase 1 exit gate

```text
[ ] Ownership matrix has no unexplained active table/path.
[ ] Business web routes resolve authorized company context.
[ ] Active master reads are company/branch scoped.
[ ] Active transaction reads are company/branch scoped.
[ ] Active writes validate company-owned parents.
[ ] Company-specific caches are namespaced or removed.
[ ] Production jobs are company-aware or retired.
[ ] Imports/exports/downloads/files enforce ownership.
[ ] Retained-data backfill rehearsal passes.
[ ] Same-company FKs/uniqueness are implemented where reviewed.
[ ] Two-company HTTP isolation suite passes on MySQL.
[ ] No second company is enabled before this checklist passes.
```

---

# 5. Workstream B — finish Phase 3 authoritative numbering cutover

The numbering engine exists. The remaining work is removing legacy authoritative generators.

## 5.1 Repository-wide numbering search

```bash
rg -n "generateInvoiceName|date\(.*his|date\(.*His|time\(\)|rand\(|random_int|count\(\).*\+|reference_no.*count|payment_reference" app Modules
```

Classify every result:

```text
authoritative business document
external reference
display-only value
temporary draft key
legacy unused code
test fixture
```

## 5.2 Convert remaining authoritative generators

Known example:

```text
SaleController POS -> generateInvoiceName('posr-')
```

Replace remaining authoritative number generation with `DocumentNumberService` inside the same posting transaction.

Failure must roll back:

```text
series increment
reservation
document
stock
journal
open item
tax snapshot
```

## 5.3 Extend document types only when required

Audit active authoritative documents.

Potential candidates must be determined from the codebase:

```text
quotation
sales order
purchase order
delivery challan
goods receipt
stock adjustment
stock count
packing/delivery document
```

Do not create a series for external supplier/customer references unless required by business policy.

## 5.4 Preserve external/manual references separately

Examples:

```text
supplier invoice number
customer PO
LR number
courier reference
external order ID
```

Store them separately from the internal authoritative number.

## 5.5 Phase 3 exit gate

```text
[ ] Repository search finds no active authoritative timestamp/count/random numbering.
[ ] POS uses DocumentNumberService.
[ ] Web/API/shared paths produce the same numbering contract.
[ ] Concurrency tests prove unique numbers.
[ ] Failed posting leaves no consumed reservation.
[ ] FY/company/branch reset behavior passes.
[ ] Reprint never reserves a new number.
[ ] Clone/replacement creates a new number through normal posting.
```

---

# 6. Workstream C — finish Phase 4 commercial authoritative stock cutover

This is the second highest-priority blocker.

The ledger itself exists. The remaining goal is:

```text
ALL active stock effects
        ↓
InventoryMovementService
```

## 6.1 Convert the remaining commercial controllers

Audit and convert every stock mutation in:

```text
SaleController
PurchaseController
ReturnController
ReturnPurchaseController
```

Include:

```text
store
update/replace
destroy/delete
bulk delete
status transition
POS posting
import where applicable
return approval/posting
```

## 6.2 Remove direct quantity mutations

Remove active code equivalent to:

```php
$product->qty -= $qty;
$product->qty += $qty;
$productWarehouse->qty -= $qty;
$productWarehouse->qty += $qty;
$productVariant->qty -= $qty;
$productVariant->qty += $qty;
$productBatch->qty -= $qty;
$productBatch->qty += $qty;
```

Use existing movement service methods.

## 6.3 Preserve all identity semantics

Preserve:

- UOM conversion;
- variant;
- batch;
- expiry;
- serial/IMEI;
- dimensioned pieces;
- combo/component consumption;
- warehouse;
- precision;
- valuation;
- original cost for reversal.

Do not infer historical identities from today's master configuration.

## 6.4 Sales

For completed sales:

```text
document lock
→ validate context/party/warehouse/product
→ reserve number
→ persist document/lines
→ issue movement
→ accounting
→ open item/payment
→ tax snapshot
→ commit
```

Draft-like statuses must not issue stock unless the reviewed contract explicitly says so.

Combo items must issue actual components through the ledger.

## 6.5 Purchases

Preserve approved status semantics:

```text
1 Received -> receive full ordered qty
2 Partial  -> receive validated received_qty only
3 Pending  -> receive 0
4 Ordered  -> unbilled PO; receive 0, no bill/payment journal at creation
```

Later receipts must post additional receipt movements without duplicating AP.

## 6.6 Sales returns

Use original sale movement attribution.

Return must:

- restore exact allowed quantity;
- preserve variant/batch/serial/dimension;
- restore original attributable inventory cost;
- refuse cumulative over-return;
- use quarantine where required;
- avoid duplicate posting on retry.

## 6.7 Purchase returns

Use original receipt attribution.

Return must:

- issue only available attributed stock;
- preserve identity;
- use the Phase 8 variance policy;
- refuse over-return;
- avoid duplicate posting.

## 6.8 Remove commercial writers from shadow mode

After a converted responsibility passes reconciliation + HTTP + MySQL + retry + historical compatibility, remove it from:

```text
LegacyStockShadow::WRITERS
```

Do not delete historical shadow movements.

## 6.9 Direct-quantity search gate

```bash
rg -n -- "->qty\s*[+\-]=" app Modules
rg -n "qty\s*=\s*.*qty\s*[+\-]" app Modules
rg -n "increment\(['\"]qty|decrement\(['\"]qty" app Modules
```

Manually classify false positives such as transaction line quantities and explicit zero initialization.

Final authoritative projection mutation must be owned only by `InventoryMovementService`.

## 6.10 Reconciliation gate

On retained-data UAT:

```bash
php artisan erp:stock-reconcile
```

Resolve every difference.

Do not use rebuild to hide unexplained business differences.

## 6.11 Phase 4 exit gate

```text
[ ] SaleController has no direct authoritative qty writes.
[ ] PurchaseController has no direct authoritative qty writes.
[ ] ReturnController has no direct authoritative qty writes.
[ ] ReturnPurchaseController has no direct authoritative qty writes.
[ ] Active commercial writers are removed from LegacyStockShadow.
[ ] Repository-wide direct quantity search passes.
[ ] MySQL last-unit concurrency passes.
[ ] Partial receipt / later receipt passes.
[ ] Batch/serial/dimension return attribution passes.
[ ] Combo stock issue/reversal passes.
[ ] Retained-data reconciliation has zero unexplained differences.
```

---

# 7. Workstream D — validate Phases 5–10 after the Phase 1/3/4 fixes

Do not reimplement these phases unless regression tests reveal a real defect.

## 7.1 Phase 5 accounting regression

Re-run:

```text
journals
semantic mappings
open items
allocations
reversals
period close/reopen
day book
cash book
ageing
trial balance
P&L
balance sheet
general ledger
settlements
concurrency/idempotency
```

## 7.2 Phase 6 commercial regression

Verify:

```text
web sale == API sale == POS sale effects
web purchase == API purchase effects
idempotency
replacement/reversal
credit control
landed cost
partial receipt
later receipt
service items
draft version conflict
inline master retry
keyboard posting
mobile layout
```

## 7.3 Phase 7 GST regression

Keep:

```text
review-only export
filing_ready=false
```

until a current statutory filing adapter is explicitly reviewed.

Regression:

```text
intra-state CGST/SGST
interstate IGST
exempt
nil
non-GST
reverse charge
eligible ITC
blocked ITC
freight/service classification
credit/debit note tax reversal
master edit cannot rewrite posted snapshot
```

## 7.4 Phase 8 return/document regression

Verify:

```text
quantity returns
financial-only notes
approval thresholds
quarantine
disposal
exchange
A4
thermal
dot-matrix text
frozen totals
delivery outbox
retry
delivery_unknown
```

## 7.5 Phase 9 manufacturing/job-work regression

Verify:

```text
BOM cycle validation
BOM version immutability
production consume/output/scrap
cost allocation
production reversal
job-work dispatch
partial receipt
quarantine/loss
conversion
service bill
concurrency/retry
```

## 7.6 Phase 10 profile regression

Run one complete business flow for:

```text
General Trading
FMCG
Textile
Timber
Solar
```

Profile switching must not rewrite historical transactions or stock identities.

---

# 8. Workstream E — complete Phase 11 UI/API/security acceptance

Phase 11 must close both the new workspace and the still-active legacy operator surface.

## 8.1 Core screen modernization scope

Continue Blade + Bootstrap + jQuery and the existing zoloERP component/style layer.

Migrate and visually verify at minimum:

```text
Dashboard
Product list/create/edit
Customer
Supplier
Sales list
Sales create/Fast Sales
POS
Purchase list
Purchase create/Fast Purchase
Inventory
Transfer
Adjustment
Accounting/Voucher Hub
GST/Returns
Manufacturing/Job Work
Profiles/Projects
Reports
Company setup
Roles/permissions
Settings
```

## 8.2 Required UI behavior

```text
no unnecessary nested tabs
no modal that requires horizontal scrolling
no hidden primary action
totals/actions remain visible
clear empty states
consistent list filters
consistent status badges
keyboard focus visible
mouse workflow works
keyboard workflow works
tablet usable
390px mobile does not horizontally overflow core forms
```

Server authorization remains authoritative.

## 8.3 Capability-driven navigation

Menu visibility must be the intersection of:

```text
authenticated
+
company membership
+
branch authorization
+
permission
+
capability
+
global activation gate
```

Direct URL access must enforce the same result.

## 8.4 API resource contract

Use explicit Resources/DTOs for public contracts:

```text
products
parties
sales
purchases
accounting
returns
operations
projects
```

Keep bounded pagination.

Never expose secrets, provider credentials, internal-only columns or foreign nested relations.

## 8.5 API writes

Every write must enforce:

```text
Sanctum/authentication
company context
branch/FY context where applicable
permission
capability/gate
idempotency
service-level ownership validation
```

Equivalent web/API actions must call the same application owner.

## 8.6 Security cleanup

Review state-changing GET requests.

Known route requiring cleanup:

```text
GET /clear
```

Change to:

```text
POST
auth
explicit admin permission
CSRF
audit
```

Review `webview/auth` redirect input.

Allow only internal relative destinations.

Reject:

```text
absolute URL
scheme-relative //host
javascript:
data:
backslash tricks
encoded external redirect
```

## 8.7 Request correlation and logging

Allowed:

```text
request_id
route
method
actor_id
company_id
branch_id
status
duration
```

Do not log:

```text
Authorization headers
cookies
passwords
provider tokens
full request bodies
DB passwords
backup passwords
```

## 8.8 Browser acceptance matrix

Use a real browser against disposable MySQL.

Viewports:

```text
1366x768
1024x768
768x1024
390x844
```

Capture evidence for core screens and check console errors.

## 8.9 Phase 11 exit gate

```text
[ ] Core legacy reads are company/branch scoped.
[ ] Business routes enforce company context.
[ ] Navigation checks match direct routes.
[ ] Explicit Resources/DTOs cover public API contracts.
[ ] API writes are idempotent and company-scoped.
[ ] State-changing GET routes are removed/reviewed.
[ ] Redirect targets are internal-only.
[ ] Public backup/update execution endpoints remain retired.
[ ] Desktop/tablet/mobile core screen acceptance passes.
[ ] Keyboard and mouse flows both pass.
[ ] No critical console errors.
```

---

# 9. Workstream F — complete Phase 12 retained-data/UAT/deployment acceptance

Phase 12 is not complete merely because backup/import/deployment services exist.

## 9.1 Separate migration scenarios

### Scenario A — existing SalePro installation upgraded in place

Use:

```text
additive migrations
company backfill
stock opening/reconciliation
account mapping
open-item migration/reconciliation where reviewed
legacy operational conversion
retained-data UAT
```

Do not use the clean-target opening importer to hide inconsistencies.

### Scenario B — clean zoloERP target from an external/customer source

Use:

```text
reviewed master migration
OpeningImportService
validate
clean target
commit once
reconcile
```

The opening importer is **not** a transaction-delta loader.

## 9.2 Retained-data rehearsal

Create a sanitized/restorable UAT copy representative of production.

Record:

```text
source hash
row counts
company
branch
FY
stock quantities
stock valuation
AR
AP
cash/bank
trial balance
tax summaries
open transactions/jobs/projects
document numbers
```

Run the full migration chain.

## 9.3 Stock reconciliation

```bash
php artisan erp:stock-reconcile
```

No unexplained difference may remain.

## 9.4 Accounting reconciliation

Verify:

```text
journal debits == credits
account projections
AR control == open-item receivables
AP control == open-item payables
inventory GL == stock ledger value
period status
```

## 9.5 Document number reconciliation

Verify:

```text
retained old numbers preserved
new series start correctly
no duplicates
no accidental reuse across company/FY
GST number format valid where enabled
```

## 9.6 Real off-site backup acceptance

Configure an actual private off-site provider.

Require:

```text
encrypted DB backup
uploaded files
checksum
off-site upload verification
provider lifecycle policy
secret stored separately
private receipt
```

## 9.7 Real restore rehearsal

Restore into a fresh restricted rehearsal DB.

Verify:

```text
database integrity
expected tables
stock
journals
open items
documents/uploads
health
authentication
representative reads
```

Never restore over production using the rehearsal command.

## 9.8 GST/accountant review

Review:

```text
GST registration
place of supply
CGST/SGST/IGST
reverse charge cases used by the business
eligible/blocked ITC
HSN/SAC
credit/debit notes
invoice numbering
review export
```

Do not change `filing_ready=false` until a reviewed current filing adapter exists.

## 9.9 Provider UAT

If used:

```text
GSTIN lookup
email
SMS
WhatsApp
queue/scheduler
delivery retry
delivery_unknown reconciliation
```

## 9.10 Printer/hardware UAT

Use actual target devices.

Test:

```text
A4
thermal
dot matrix / 68-line
barcode where used
long textile invoice
GST invoice
return/note
job-work/material dispatch
```

## 9.11 Five-profile operator UAT

### General Trading

```text
purchase
sale
payment
return
stock
journal
open item
tax
print
retry
reversal
```

### FMCG

```text
batch receipt
FEFO
free quantity
multi-UOM
expiry
damage/disposal
```

### Textile

```text
MTR
previous rate
job work send/receive
loss
service bill
wholesale sale
payment
long/dot-matrix print
```

### Timber

```text
dimension receipt
CFT/CBM
piece selection
sale
conversion
offcut
waste
```

### Solar

```text
project
system BOM
serial procurement
allocation
site dispatch
installation
commission
invoice
payment
margin
replacement
warranty/service
```

## 9.12 Performance acceptance

Record real environment/dataset.

Target budgets:

```text
POS readiness                  <= 1.5 s
autocomplete                   <= 300 ms
20-line posting                <= 1.0 s
first list page                <= 800 ms
financial summary              <= 3.0 s or async/export
```

## 9.13 Rollback rehearsal

```text
pause writers
pause workers
record release SHA
record prior compatible release
verify backup
switch code
rebuild caches
verify reads
run health
document incompatible new source types
```

A code rollback must not delete posted ledger effects.

## 9.14 UAT signatures

Complete:

```text
PHASE_12_UAT_AND_CUTOVER_RECORD.md
```

Required real reviewers:

```text
customer/operator
accountant
company isolation/security owner
deployment/recovery owner
```

Do not auto-fill approval.

---

# 10. Workstream G — activation gate design and final activation order

Do not simply change:

```php
OPTIONAL_ACTIVATION_READY = false
```

to `true` globally for every installation.

Final acceptance should remain deployment-specific.

Recommended acceptance concept:

```text
ERP_FOUNDATION_ACCEPTED=false
ERP_SHARED_COMMERCIAL_ENABLED=false
ERP_COMPLIANCE_ENABLED=false
ERP_OPERATIONS_ENABLED=false
```

Reuse existing architecture rather than creating another feature system.

## Activation order

```text
1. Phase 1 company foundation accepted
2. enable second company only if needed
3. capability activation becomes eligible
4. Phase 3/4 authoritative cutovers verified
5. Phase 5 accounting accepted
6. ERP_SHARED_COMMERCIAL_ENABLED=true in UAT
7. commercial UAT/reconciliation
8. ERP_COMPLIANCE_ENABLED=true in UAT
9. GST/returns/document UAT
10. ERP_OPERATIONS_ENABLED=true in UAT
11. manufacturing/profile UAT
12. backup/restore/health
13. signed cutover
14. production enablement
```

---

# 11. Complete GitHub CI matrix

The final PR must run the exact cumulative code.

Required suites:

```bash
php vendor/bin/phpunit -c phpunit.company.xml
php vendor/bin/phpunit -c phpunit.commercial.xml
php vendor/bin/phpunit -c phpunit.compliance.xml
php vendor/bin/phpunit -c phpunit.operations.xml
php vendor/bin/phpunit -c phpunit.delivery.xml
php vendor/bin/phpunit -c phpunit.legacy-mysql.xml
```

Ensure suites intended for MySQL actually run against disposable MySQL.

## 11.1 Static checks

```bash
git diff --check
```

Run PHP syntax validation for changed PHP files.

Run JavaScript syntax checks for changed JS files.

Do not invent a new frontend build system merely to satisfy CI.

## 11.2 Migration recovery

CI must include:

```text
fresh migration chain
committed-DDL interruption recovery
stock migration recovery
accounting migration recovery
commercial/compliance/operations additive migration recovery
delivery/import additive migration recovery
unsafe rollback refusal
```

## 11.3 Concurrency

Run MySQL concurrency for:

```text
document numbering
last-unit stock issue
supplier settlement
open-item allocation
return approval
production retry
job-work receipt
serial allocation
```

## 11.4 CI release gate

No merge to `main` if any required suite fails.

PR description must record:

```text
main base SHA
closure head SHA
suite results
migration recovery results
known external acceptance pending
activation gates remaining false
```

---

# 12. Final repository searches before declaring 12/12

## 12.1 Direct stock writes

```bash
rg -n -- "->qty\s*[+\-]=" app Modules
rg -n "increment\(['\"]qty|decrement\(['\"]qty" app Modules
rg -n "qty\s*=\s*.*qty\s*[+\-]" app Modules
```

Expected active authoritative owner:

```text
InventoryMovementService
```

## 12.2 Unsafe numbers

```bash
rg -n "generateInvoiceName|date\(.*his|date\(.*His|count\(\).*\+|rand\(|random_int" app Modules
```

Every active business-number hit must be converted or documented as non-authoritative.

## 12.3 Hard-coded posting accounts

```bash
rg -n "where\(['\"]code|chart_of_account_id.*[0-9]" app/Services app/Http Modules
```

Transaction posting must use mappings/owned account links.

## 12.4 Unscoped company masters

```bash
rg -n "Customer::|Supplier::|Product::|Warehouse::|Biller::|Account::" app/Http Modules
```

Review every active business read/write.

## 12.5 Unsafe state-changing GET

Inspect:

```bash
php artisan route:list
```

Review GET routes that clear/delete/reset/upgrade/migrate/post/sync/change status/write configuration.

## 12.6 Remote executable downloads/upgrades

```bash
rg -n "curl|file_get_contents\(.*http|Http::|Guzzle|ZipArchive|extractTo|Artisan::call\(.*migrate|version-upgrade|addon" app Modules
```

Retired browser update/add-on paths must remain incapable of downloading and executing remote code.

---

# 13. Final integrated business acceptance tests

## 13.1 General Trading

```text
create party
create product
purchase
partial receipt
later receipt
sale
partial payment
remaining payment
return
credit/debit note
stock transfer
stock adjustment
print
reverse
period close
reports
```

Verify after each step:

```text
document
stock movement
projection
journal
open item
tax snapshot
number
audit
```

## 13.2 FMCG

```text
CASE purchase
BOX sale
batch/expiry
FEFO
buy/free scheme
expired stock
damage/write-off
return
GST
```

## 13.3 Textile

```text
500.000 MTR purchase
job-work dispatch
480.000 MTR receipt
loss
processing bill
wholesale sale
partial payment
long/dot-matrix document
```

## 13.4 Timber

```text
dimension receipt
10 CBM input
selected-piece sale
8 CBM output
1 CBM offcut
1 CBM waste
external processing
```

## 13.5 Solar

```text
project
5kW system BOM
quotation
procurement
serial allocation
site dispatch
installation
commission
invoice
payment
margin
replacement
warranty/service history
```

---

# 14. Documentation updates required

Update after implementation:

```text
IMPLEMENTATION_PROGRESS.md
COMPANY_TABLE_OWNERSHIP_MATRIX.md
STOCK_AND_TRANSACTION_WRITER_AUDIT.md
LEGACY_STOCK_SHADOW_AUDIT.md
PHASE_4C_AUTHORITATIVE_CUTOVER.md
PHASE_5_ACCOUNTING_HARDENING.md
PHASE_6_SHARED_COMMERCIAL.md
PHASE_7_8_TAX_RETURNS_AND_DOCUMENTS.md
PHASE_9_10_OPERATIONS_AND_PROFILES.md
PHASE_11_UI_API_SECURITY.md
PHASE_12_MIGRATION_UAT_DEPLOYMENT.md
PHASE_12_UAT_AND_CUTOVER_RECORD.md
manifest.json
README.md
```

Do not rewrite historical evidence to pretend it came from a later build.

---

# 15. Status wording rules

## Software exists but external acceptance pending

```text
IMPLEMENTED — ACTIVATION / UAT PENDING
```

## Automated code acceptance passed

Use:

```text
ENGINEERING COMPLETE
```

only when code-level phase exit criteria pass.

## Production/customer acceptance passed

Use:

```text
PHASE COMPLETE
```

only when the canonical phase's required retained-data/operator/provider/hardware/deployment evidence exists.

---

# 16. Final 12-phase acceptance matrix

| Phase | Required final evidence | Done |
|---|---|---|
| 1 Company/Branch/FY | Full active-path company isolation, MySQL two-company proof, retained-data backfill | [ ] |
| 2 Capability Engine | Engine + dependencies + route/menu guards + Phase 1 acceptance allows activation | [ ] |
| 3 Atomic Series | No active authoritative legacy number generators; concurrency/rollback proof | [ ] |
| 4 Inventory Source of Truth | All active stock writes through movement service; zero unexplained reconciliation | [ ] |
| 5 Accounting/Open Items | Semantic posting, open items, reports, periods, reconciliation, retained-data acceptance | [ ] |
| 6 Shared Commercial | Web/POS/API convergence enabled in UAT and reconciled | [ ] |
| 7 GST/Tax | Determination/snapshots pass; accountant review; statutory adapter decision | [ ] |
| 8 Returns/Documents | Returns/notes/printing/dispatch pass; printer/provider acceptance | [ ] |
| 9 Manufacturing/Job Work | Actual cost/reversal/job-work flows pass retained-data/operator UAT | [ ] |
| 10 Industry Profiles | General/FMCG/Textile/Timber/Solar representative flows accepted | [ ] |
| 11 UI/API/Security | Company-safe operator UI, API contracts, permissions, browser/device acceptance | [ ] |
| 12 Migration/UAT/Deployment | Backup+restore, health, parallel/reconciliation, rollback rehearsal, signed cutover | [ ] |

---

# 17. Definition of overall done

The implementation may be declared **12/12 complete** only when:

```text
[ ] Final cumulative code is merged to main.
[ ] GitHub CI passes the complete required suite matrix.
[ ] Phase 1 whole-application isolation is closed.
[ ] Optional capability gate is eligible through reviewed acceptance.
[ ] No active authoritative legacy numbering remains.
[ ] No active authoritative direct stock writer remains outside InventoryMovementService.
[ ] Accounting reconciliation passes.
[ ] Stock reconciliation has zero unexplained differences.
[ ] Shared commercial cutover is validated.
[ ] GST/accountant/statutory review is recorded.
[ ] Returns/documents and real printers/providers are accepted where used.
[ ] Manufacturing/job-work retained-data/operator UAT passes.
[ ] All five business profiles pass representative flows.
[ ] UI/API/security operator acceptance passes.
[ ] Real off-site backup and isolated restore pass.
[ ] Emergency rollback is rehearsed.
[ ] Phase 12 UAT/cutover record is completed with real reviewers.
[ ] Production activation is a deliberate deployment decision.
```

---

# 18. Codex execution order

```text
STEP 1  Create closure branch and record baseline.
STEP 2  Complete Phase 1 route/read/write/cache/job/file isolation.
STEP 3  Complete Phase 1 DB ownership/FK/uniqueness/backfill acceptance.
STEP 4  Remove remaining authoritative legacy numbering.
STEP 5  Convert Sale stock writes to InventoryMovementService.
STEP 6  Convert Purchase stock writes to InventoryMovementService.
STEP 7  Convert Sales Return stock writes to InventoryMovementService.
STEP 8  Convert Purchase Return stock writes to InventoryMovementService.
STEP 9  Remove converted commercial writers from legacy shadow.
STEP 10 Run zero-direct-stock-write and zero-unsafe-number searches.
STEP 11 Run Phase 5–10 integrated regression.
STEP 12 Complete Phase 11 legacy UI/API/security isolation.
STEP 13 Add complete GitHub CI matrix.
STEP 14 Run disposable MySQL full suite.
STEP 15 Run retained-data UAT copy migration/reconciliation.
STEP 16 Perform real provider/printer/off-site backup/restore UAT.
STEP 17 Complete Phase 12 customer cutover record.
STEP 18 Only then update activation gates and merge to main.
STEP 19 Tag the accepted release and record rollback SHA.
```

---

# 19. Stop conditions for Codex

Codex must stop and report instead of guessing when:

```text
ownership of a retained row is ambiguous;
historical batch/serial/dimension attribution is missing;
a legacy transaction cannot be safely reversed;
a tax rule requires current statutory interpretation;
an external GST/provider API contract is unknown;
a printer/device behavior cannot be simulated reliably;
a source migration schema has not been supplied;
a migration would destroy retained business history;
a rollback requires rewriting posted ledger effects;
a company isolation test shows cross-company visibility;
stock/accounting reconciliation has an unexplained difference;
a production acceptance item requires a human/customer signature.
```

---

# 20. Expected final architecture

```text
Authorized User
      │
      ▼
Company / Branch / FY Context
      │
      ├──────── Capability / Permission / Period Policy
      │
      ▼
Shared ERP Application Services
      │
      ├── Sales / Purchase / Returns
      ├── Manufacturing / Job Work / Projects
      │
      ├───────────────┬────────────────┬────────────────
      ▼               ▼                ▼
Inventory          Accounting          Tax
Movement Ledger    Double Entry        Frozen Snapshot
      │               │                │
      └───────────────┴────────────────┘
                      │
                      ▼
         Documents / Print / Dispatch
                      │
                      ▼
             API / Reports / Audit
```

Industry profiles configure the same core:

```text
General Trading
FMCG
Textile
Timber
Solar
```

No industry profile gets a separate sales, stock or accounting engine.

---

# 21. Final implementation instruction to Codex

> Implement this closure plan against the latest cumulative Phase 1–12 branch.  
> Preserve existing working shared services and migrations.  
> Investigate every legacy path before editing it.  
> Make small coherent commits.  
> After each workstream, run its focused tests plus the relevant shared regression suites.  
> Never change an activation gate merely to make acceptance appear complete.  
> Do not claim real UAT, statutory approval, provider certification, printer certification, retained-data reconciliation or production sign-off without actual evidence.  
> The final goal is not merely “all feature code exists”; the final goal is that all canonical Phase 1–12 exit criteria are genuinely satisfied and the accepted cumulative build is merged to `main`.

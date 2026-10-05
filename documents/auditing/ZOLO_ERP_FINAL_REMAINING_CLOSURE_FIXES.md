# zoloERP Pro — Final Remaining Closure Plan

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Branch to fix:** `main`  
> **Latest audited main HEAD:** `1a8c980f5bf7bcf6d8f634349af1090540c8729e`  
> **Current engineering status:** Major Phase 1–12 architecture and implementation are in place. The complete six-suite MySQL acceptance workflow is green on the current `main`.  
> **Purpose of this file:** Close the remaining software/security/isolation gaps and clearly separate them from the external UAT/deployment work that cannot be completed by code alone.

---

# 1. Current verified state

The following major items are already implemented and should **not** be rebuilt:

- Phase 1 company/branch/FY foundation.
- Trusted company context on legacy business routes.
- Request-company Eloquent scoping/stamping.
- Company-scoped raw `DB::table()` handling for known company-owned tables.
- Two-company HTTP isolation proof.
- Phase 2 capability/profile engine.
- Phase 3 atomic document numbering.
- Company-aware numbering for sales, purchases, payments, returns/notes, transfers, adjustments, damage, exchange, expenses, quotations, deliveries, income, money transfers, payroll, production, job work and journals.
- Phase 4 authoritative stock ledger.
- Sale/Purchase/Sale Return/Purchase Return stock cutover through `InventoryMovementService`.
- Empty `LegacyStockShadow::WRITERS` for converted commercial stock writers.
- Original sale-cost restoration for legacy sale returns.
- Stock-ledger migration recovery.
- Phase 5 accounting/open items.
- Phase 6 shared commercial services.
- Phase 7 GST/tax engine.
- Phase 8 returns/documents/dispatch.
- Phase 9 manufacturing/job work.
- Phase 10 industry profiles.
- Phase 11 workspace/API/security package.
- Phase 12 migration/backup/restore/health tooling.
- Company-owned business document attachment delivery through secure routes.
- Full six-suite MySQL CI matrix: company, commercial, compliance, operations, delivery and legacy-mysql.

Do not replace these systems with new parallel modules.

---

# 2. Priority 1 — Complete branch-level isolation

Company isolation is strong, but legacy screens still need a final **branch** audit.

Example:

```php
Warehouse::where('is_active', true)->get();
```

The request-company scope protects Company A vs Company B, but does not automatically guarantee Company A / Chennai vs Company A / Madurai isolation for every legacy list/dropdown.

## Required behavior

For every branch-owned record:

```text
current company
+
current selected/authorized branch
```

must be enforced.

## Audit these areas

```text
SaleController
PurchaseController
TransferController
AdjustmentController
ReturnController
ReturnPurchaseController
ProductController
WarehouseController
StockCountController
ReportController
HomeController
CashRegisterController
DeliveryController
PackingSlipController
Accounting/report screens
Operations / Manufacturing
```

## Search commands

```bash
rg -n "Warehouse::|warehouse_id|branch_id" app/Http Modules
rg -n "Product_Warehouse::|product_warehouse" app/Http Modules
rg -n "CashRegister::|cash_register" app/Http Modules
```

## Rules

Do not globally force one branch where business behavior legitimately spans branches.

Examples:

- transfers have source and destination branches;
- company-wide reports may intentionally aggregate authorized branches;
- admin screens may intentionally show all authorized branches.

Use explicit policies:

```text
selected branch only
authorized branches
company-wide by permission
source + destination branch
```

## Required tests

```text
User authorized only for Chennai cannot see Madurai warehouse.
User authorized only for Chennai cannot post sale into Madurai warehouse.
User authorized only for Chennai cannot access Madurai cash register.
User authorized only for Chennai cannot download Madurai branch document.
Company-wide admin can see all company branches where policy allows.
Transfer validates both source and destination branch authorization.
```

---

# 3. Fix `/clear` — state-changing GET request

Current behavior still uses:

```php
Route::get('clear', ...)
```

while the action changes application state.

## Required change

Replace with:

```text
POST /clear
```

with:

```text
auth
explicit admin permission
CSRF
audit log
```

Do not keep a second GET alias.

## Audit logging

Record:

```text
actor_id
company_id if context exists
request_id
timestamp
action = cache_clear
```

Do not log cookies, tokens or secrets.

## Test

```text
GET /clear              -> 404/405
anonymous POST          -> unauthorized
non-admin POST          -> forbidden
admin POST with CSRF    -> success
cache keys removed
audit row/log exists
```

---

# 4. Secure `webview/auth` redirect

Current behavior approximately does:

```php
$redirect = $request->query('redirect', '/');
return redirect($redirect . '?app=true');
```

This must not accept external destinations.

## Required validation

Only allow internal application-relative paths.

Accept:

```text
/
/dashboard
/sales
/products?status=active
```

Reject:

```text
https://example.com
http://example.com
//example.com
\\example.com
javascript:alert(1)
data:text/html,...
%2F%2Fevil.example
mixed encoded slash/backslash tricks
```

## Recommended implementation

Create one reusable helper/service such as:

```text
InternalRedirectValidator
```

Contract:

```text
input resolves to an internal relative application path
no scheme
no host
no protocol-relative prefix
no control characters
normalize encoded input before decision
```

Append `app=true` safely using URL/query utilities instead of string concatenation.

## Tests

Add tests for every allowed/rejected example above.

---

# 5. Finish company database hardening

The request layer is much stronger, but database constraints remain incomplete for several parent-derived tables.

## Focus first on

```text
variants
product_variants
discounts
discount_plans
discount_plan_customers
discount_plan_discounts
HR-owned tables
notification/business attachment relations
operational child tables
```

For each table decide:

```text
global/shared reference
company-owned
company + branch owned
parent-derived ownership
```

Then implement where safe:

```text
company_id
company-aware uniqueness
same-company parent validation
foreign keys
non-null ownership after reviewed backfill
```

## Important rule

Do not add `company_id` merely because a table exists.

Where ownership is parent-derived and unambiguous, either keep derived ownership and enforce parent constraints, or persist explicit ownership only when it materially improves safety/querying.

Do not fabricate company ownership for ambiguous legacy rows.

## Required tests

```text
Company A discount cannot attach Company B product.
Company A variant relation cannot attach Company B product.
Company A employee cannot reference Company B warehouse where policy forbids it.
```

---

# 6. Raw SQL subquery scoping review

The company-aware raw-query builder covers normal company-owned `DB::table()` statements, but subquery scoping still requires review.

## Search

```bash
rg -n "whereIn\(.*function|whereExists|selectSub|fromSub|joinSub|DB::raw|DB::query" app Modules
```

For each company-owned query using subqueries, verify the subquery itself independently enforces the correct company.

Do not assume the outer query protects the subquery.

## Required outcome

```text
outer query company scope
+
subquery company scope
```

must be explicit/proven.

## Tests

Add a cross-company fixture where a subquery contains a matching foreign-company row and prove it does not affect:

```text
counts
balances
reports
filters
eligibility
```

---

# 7. Finish attachment/file isolation

Business document attachments are substantially protected through secure routes.

Remaining areas include:

```text
notification attachments
production attachments
public/images
```

## Classify every file family

```text
PUBLIC BRAND/PRODUCT ASSET
PRIVATE COMPANY DOCUMENT
PRIVATE USER DOCUMENT
PRIVATE OPERATIONS DOCUMENT
TEMPORARY EXPORT
BACKUP / RECOVERY
```

Examples:

```text
company logo                  may be public
product image                 may be public
customer ID/document          private
production attachment         private
notification business file    usually private
accounting export             private
backup                        private
```

## Private file contract

Private company files must require:

```text
authenticated user
company membership
branch authorization if relevant
permission
record ownership
```

Use controller/private storage instead of direct public paths.

## Web server parity

Apache currently has `.htaccess` protection for business document folders.

Document equivalent Nginx rules for protected paths.

## Tests

```text
Company A member can download Company A document.
Company A member cannot download Company B document.
Anonymous user cannot download private document.
Direct public URL is rejected.
Deleted/unreferenced file is not served.
Path traversal is rejected.
```

---

# 8. Update stale Phase 12 documentation

`PHASE_12_UAT_AND_CUTOVER_RECORD.md` still records the old synthetic rehearsal result of 21 stock differences.

Update it to preserve history while reflecting the later fix:

```text
The first synthetic rehearsal produced 21 stock differences.
Those differences were traced to inconsistent repository seed data.
The seed was corrected at the source and the current synthetic rehearsal
now reports zero stock differences and passes erp:health.

This remains engineering fixture evidence only.
It does not represent customer retained-data reconciliation or customer acceptance.
```

Update:

```text
PHASE_12_UAT_AND_CUTOVER_RECORD.md
PHASE_1_12_CLOSURE_STATUS.md
IMPLEMENTATION_PROGRESS.md
README.md / manifest only if status index requires it
```

Do not mark customer UAT as complete.

---

# 9. Phase 11 final browser/device acceptance

The cumulative UI needs one final real-browser pass.

## Required viewports

```text
1366 x 768
1024 x 768
768 x 1024
390 x 844
```

## Required screens

```text
Dashboard
Products
Customer
Supplier
Sale list
Sale create
Fast Sale / POS
Purchase list
Purchase create
Fast Purchase
Inventory
Transfer
Adjustment
Accounting / Voucher Hub
GST / Returns
Manufacturing
Job Work
General Trading profile
FMCG profile
Textile profile
Timber profile
Solar profile
Reports
Company setup
Roles / Permissions
Settings
```

## Validate

```text
no horizontal overflow on core forms
no broken dropdowns
no clipped modal
no nested unusable scroll
primary actions visible
totals visible
keyboard focus visible
Esc works where expected
Ctrl+Enter/F2/F12 etc. behave as documented
mouse workflow complete
tablet usable
dark mode usable where supported
no critical console errors
```

Record screenshots/evidence separately.

Do not modify business logic merely to satisfy visual tests.

---

# 10. CI remains a hard gate

Keep the current six-suite MySQL acceptance matrix:

```text
phpunit.company.xml
phpunit.commercial.xml
phpunit.compliance.xml
phpunit.operations.xml
phpunit.delivery.xml
phpunit.legacy-mysql.xml
```

After every remaining software fix, rerun the complete matrix.

Also run:

```bash
git diff --check
```

and PHP/JS syntax checks for changed files.

No final software-closure commit is accepted if any required CI job fails.

---

# 11. Final software closure checklist

Do not claim **ENGINEERING COMPLETE** until all are checked:

```text
[ ] Branch-level warehouse/cash-register/list isolation audited and tested.
[ ] `/clear` uses POST only.
[ ] `/clear` has auth/admin/CSRF/audit.
[ ] `webview/auth` accepts internal redirect paths only.
[ ] Remaining high-value same-company FKs/uniqueness reviewed and implemented.
[ ] Company-sensitive raw SQL subqueries are explicitly scoped.
[ ] Private notification/production/business files are protected.
[ ] Nginx/private-file deployment instructions exist.
[ ] Phase 12 stale synthetic-rehearsal wording is corrected.
[ ] Full browser/device operator acceptance is completed for the cumulative build.
[ ] Full six-suite MySQL CI remains green.
```

Once this checklist is fully green, the software can be labelled:

```text
ENGINEERING COMPLETE
```

---

# 12. Phase 12 external acceptance — not a coding task

Even after software closure, do **not** mark Phase 12 complete until real evidence exists.

## Customer retained-data rehearsal

Use an actual sanitized customer/UAT data copy.

Reconcile:

```text
stock quantities
stock valuation
AR
AP
cash/bank
trial balance
tax summaries
open items
document numbers
operational work
projects
serials/batches/dimensions
```

No unexplained difference is acceptable.

## Accountant review

Review:

```text
chart mappings
opening balances
inventory valuation
GST
ITC
RCM
credit/debit notes
invoice numbering
tax reports/export
```

## Provider acceptance

Where used:

```text
GSTIN lookup
email
SMS
WhatsApp
queue/retry
delivery_unknown reconciliation
```

## Printer acceptance

Actual devices:

```text
A4
thermal
68-line / dot matrix
barcode where used
```

## Off-site backup

Verify:

```text
real private off-site destination
encrypted archive
checksum
receipt
retention policy
```

## Restore rehearsal

Restore into isolated target and verify:

```text
DB
stock
journals
open items
documents
authentication
health
```

## Rollback rehearsal

Verify previous compatible release with writers/workers paused.

## Signatures

Required real reviewers:

```text
customer/operator
accountant
company isolation/security owner
deployment/recovery owner
```

---

# 13. Activation gates

Do not enable all gates just because CI is green.

Use controlled activation:

```text
Phase 1 foundation accepted
    ↓
Second-company activation if required
    ↓
Optional capabilities eligible
    ↓
Shared commercial enabled in UAT
    ↓
Commercial reconciliation/UAT
    ↓
Compliance enabled in UAT
    ↓
GST/returns/document UAT
    ↓
Operations enabled in UAT
    ↓
Manufacturing/profile UAT
    ↓
Signed Phase 12 cutover
    ↓
Production activation
```

Do not globally flip capability readiness merely to make the project appear complete.

---

# 14. Recommended Codex execution order

```text
STEP 1  Audit branch-owned legacy reads.
STEP 2  Fix branch-sensitive warehouse/cash-register/list queries.
STEP 3  Add branch-isolation HTTP tests.
STEP 4  Convert `/clear` GET to POST + CSRF + audit.
STEP 5  Lock down `webview/auth` redirect.
STEP 6  Add redirect security tests.
STEP 7  Review/implement remaining same-company FK/unique constraints.
STEP 8  Audit raw SQL subqueries and scope them.
STEP 9  Classify remaining public files and protect private ones.
STEP 10 Add Apache/Nginx private-file deployment guidance.
STEP 11 Update stale closure/UAT docs.
STEP 12 Run browser/device Phase 11 acceptance.
STEP 13 Run all six MySQL CI suites.
STEP 14 Update closure status to ENGINEERING COMPLETE only if every software gate passes.
STEP 15 Leave Phase 12 as UAT/ACCEPTANCE PENDING until real external evidence exists.
```

---

# 15. Stop conditions

Codex must stop and report instead of guessing when:

```text
branch ownership policy is ambiguous;
a legacy table has conflicting ownership parents;
a retained row cannot be safely assigned to a company;
a subquery's intended company scope is unclear;
a file is unclear whether it should be public or private;
a statutory GST decision is required;
a real printer/provider is unavailable;
customer retained-data is unavailable;
an unexplained reconciliation difference appears;
a human acceptance signature is required.
```

---

# 16. Final status wording

## After this file's software work is complete

```text
PHASES 1–12 SOFTWARE IMPLEMENTATION: ENGINEERING COMPLETE
EXTERNAL ACCEPTANCE / PRODUCTION CUTOVER: PENDING
```

## Only after real Phase 12 acceptance

```text
PHASES 1–12: COMPLETE
PRODUCTION CUTOVER: ACCEPTED
```

Do not use the second wording before actual UAT/signatures.

---

# 17. Final instruction to Codex

> Work against the latest `main`.  
> Do not rebuild the existing Phase 1–12 architecture.  
> Close only the remaining items identified in this document.  
> Preserve current company context, stock ledger, accounting, numbering, tax, commercial, operations and profile services.  
> Add focused tests for every security/isolation fix.  
> Run the full six-suite MySQL CI matrix after the final software changes.  
> Do not claim customer UAT, retained-data reconciliation, accountant/provider/printer acceptance, off-site recovery acceptance or production cutover unless actual evidence exists.  
> When all software checklist items pass, mark the software as **ENGINEERING COMPLETE**, while keeping Phase 12 external acceptance explicitly pending.

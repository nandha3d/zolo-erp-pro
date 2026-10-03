# zoloERP Company Foundation Commit Audit

**Repository:** `vigneshsinna/zolo-erp-pro`  
**Branch audited:** `main`  
**HEAD audited:** `b2df894df352e515cc437174d70618fd4eed8eb4`  
**Audit date:** 2026-10-03  

**Commits in scope**
- `2535838916afc7b8fe7415e74803e8533623edad` — `feat: add staged company schema and safe legacy backfill`
- `b2df894df352e515cc437174d70618fd4eed8eb4` — `feat: add authorized company context and financial period guards`

**Carry-forward checked**
- `d6b69d2fe3022227a1dcba2166b141b4fa951b70`

## Source of truth used for this audit

This audit uses the current files on `main`, not the earlier copy of the implementation pack.

Primary specification sources reviewed:

- `documents/zolo_erp_implementation_docs/00_IMPLEMENTATION_MASTER_INDEX.md`
- `documents/zolo_erp_implementation_docs/03_MULTI_COMPANY_BRANCH_AND_FINANCIAL_YEAR.md`
- `documents/zolo_erp_implementation_docs/05_DATABASE_SCOPING_AND_MIGRATION_STRATEGY.md`
- `documents/zolo_erp_implementation_docs/22_SECURITY_PERMISSIONS_AUDIT_AND_DATA_LOCKS.md`
- `documents/zolo_erp_implementation_docs/24_TESTING_PERFORMANCE_AND_UAT.md`
- `documents/zolo_erp_implementation_docs/26_CODEX_EXECUTION_SEQUENCE_AND_CHECKLIST.md`
- `documents/zolo_erp_implementation_docs/COMPANY_BACKFILL_RUNBOOK.md`
- `documents/zolo_erp_implementation_docs/IMPLEMENTATION_PROGRESS.md`
- `documents/ZOLO_ERP_EXECUTION_PLAN.md`

The product rule remains correct: zoloERP must be one shared ERP core for General Trading, FMCG, Textile, Timber, Solar and other business profiles. Company isolation is a platform concern and must not create industry-specific transaction systems.

---

# 1. Executive audit result

Both new commits are directionally correct and materially improve the foundation.

`2535838` adds a cautious, additive company/branch schema and a backfill command that avoids silently guessing multi-company ownership. `b2df894` adds a strong authorization-aware company/branch/FY resolver and period-lock primitive.

However, the implementation is **not ready for multi-company activation**, and the capability/profile phase should not be treated as unblocked yet.

The most important remaining blockers are:

1. The 32-table company-key migration is risky to recover if a MySQL DDL operation fails part-way through.
2. Company context middleware is registered but is not attached to the web/API transaction routes.
3. Existing Eloquent/query-builder readers and writers are still not company-isolated.
4. Operational/industry tables remain outside the company-key migration scope.
5. Existing global unique constraints have not yet been changed to company-aware constraints.
6. Background jobs, cache keys, import/download paths and branch/FY switching are not company-aware.
7. There is still no MySQL CI/rehearsal evidence; current targeted proof is SQLite-only.
8. The previous `d6b69d2` Partial/Pending purchase defect remains on `main`.
9. The current implementation documents still conflict with one another on phase numbering and some company/FY implementation contracts.

**Audit gate:** do not enable a second legal company and do not declare the Company/Branch/FY phase complete until the High-severity findings below are closed.

---

# 2. Repository and commit-chain verification

Current `main` is correctly at:

```text
b2df894  feat: add authorized company context and financial period guards
└── 2535838  feat: add staged company schema and safe legacy backfill
    └── d6b69d2  fix: establish ERP regression baseline and repair transaction relations
        └── edecfa1  Add zoloERP implementation spec and execution plan
```

The active implementation repository is `vigneshsinna/zolo-erp-pro`, and these commits are on its `main`.

GitHub currently exposes **no commit status checks and no workflow runs** for `2535838` or `b2df894`. Therefore local test claims in `IMPLEMENTATION_PROGRESS.md` are useful development evidence, but they are not CI-verified evidence.

---

# 3. Commit `2535838` audit

## What is implemented well

### Company/legal-entity foundation

The commit correctly adds:

- `companies`
- `company_branches`
- `company_user`
- `company_user_branches`

The separation between a legal Company and legacy/SaaS concepts is correct.

### Correct legacy ID compatibility

The company membership tables correctly respect the existing `users.id` type:

```text
users.id = unsigned INT
companies.id / branches.id = unsigned BIGINT
```

This avoids a common FK type mismatch.

### Branch/company integrity

The composite foreign keys in `company_user_branches` are a good design:

- membership must exist for `(company_id, user_id)`
- the selected branch must belong to the same company

This prevents a user/company membership from being paired with a branch owned by another company.

### Additive migration approach

The company keys are added as nullable and indexed first. This follows the staged migration rule in doc 05 and avoids immediately making legacy data invalid.

### Existing fiscal-year authority is preserved

The implementation extends the existing `fiscal_years` table instead of introducing a second financial-year authority.

Added metadata:

- `company_id`
- `status`
- `lock_date`
- `closed_at`
- `closed_by`

This matches the agreed policy to preserve existing fiscal-year date ranges.

### Backfill safety

`erp:backfill-company-context` includes several useful safeguards:

- dry-run performs no writes
- automatic assignment is blocked once another company exists
- real backfill requires maintenance mode
- orphan/reference checks happen before assignment
- unexpected company ownership blocks execution
- invalid/overlapping FY ranges block execution
- backfill DML is wrapped in a transaction
- rows are handled in batches
- DEFAULT/MAIN creation is repeatable
- customized company legal name and role override are preserved on repeat
- 1970 opening-balance dates are reported and preserved
- known zero sentinels are preserved
- latest `general_settings` is used for initial defaults
- invalid timezones are rejected

### Regression coverage

`CompanyFoundationTest` gives useful isolated coverage for dry-run, backfill, repeatability, orphan rejection, maintenance mode, FY overlap, branch isolation, rollback, multiple chunks, 1970 records and settings import.

The limitation is that these tests do not prove real MySQL DDL behavior.

---

# 4. Commit `b2df894` audit

## What is implemented well

### Immutable request context

`CompanyContext` is immutable and contains:

- `companyId`
- `branchId`
- `financialYearId`

### Membership authorization

`CompanyContextResolver` correctly checks:

- user exists
- user is active
- user is not deleted
- user belongs to selected company
- company is active
- branch belongs to company
- user is explicitly assigned to branch
- selected FY belongs to company

### Spoofing protection

A client cannot select another company/branch/FY merely by supplying its ID.

### Financial-year selection

Implicit FY selection:

- uses Company timezone
- requires exactly one matching date range
- rejects ambiguous overlaps

Closed FYs can still be selected for historical reads.

### Posting-date guard

`assertPostingDate()` blocks:

- dates outside the FY
- legacy `is_closed`
- status other than `open`
- dates on or before `lock_date`

### Persistent-worker hygiene

`ResolveCompanyContext` removes the request attribute in a `finally` block.

### Body IDs are not trusted

Context is resolved from authorized header/session state rather than a request-body `company_id`.

---

# 5. Detailed findings

## F-01 — HIGH — Company-key migration is not safely restartable after a partial MySQL DDL failure

### Evidence

`2026_10_03_000002_add_nullable_company_keys_to_core_tables.php` performs schema changes across roughly 32 tables in one Laravel migration.

For each table it unconditionally adds:

```php
$table->unsignedBigInteger('company_id')->nullable()->index();
```

It checks `Schema::hasTable()`, but not `Schema::hasColumn()`.

### Why this matters

MySQL DDL is not equivalent to the SQLite test transaction model. If an ALTER fails after several earlier ALTERs have already succeeded, the migration may not be recorded as complete while earlier schema changes remain.

A retry can then fail immediately with a duplicate `company_id` column/index.

### Required correction

Before production/rehearsal migration:

- Prefer splitting company-key DDL into smaller coherent migrations.
- Add explicit preflight checks before DDL.
- If grouped migrations are retained, detect and validate existing columns/indexes before continuing.
- Do not silently accept structurally different pre-existing columns.
- Add a MySQL failure/recovery rehearsal.

This is a production migration blocker.

---

## F-02 — HIGH — Company context is not yet enforced on real routes

### Evidence

`Kernel.php` registers:

```text
company.context
```

but current `routes/web.php` and `routes/api.php` do not attach it.

### Effect

The resolver is currently a library primitive, not an application security boundary.

### Acceptance impact

The spec requirement below is not yet proven:

> Company A user cannot retrieve Company B sale/journal by guessed ID.

### Required correction

After migration/backfill prerequisites are satisfied:

- attach company context after authentication/active-user checks
- apply it to protected web ERP routes
- apply it to `/api/v1`
- create actual HTTP IDOR tests for sales, purchases, journals, exports and downloads

---

## F-03 — HIGH — Existing readers/writers are still not company-isolated

Middleware alone will not make existing code safe.

Existing paths still include Eloquent/query-builder operations such as:

```php
Sale::find($id)
DB::table('sales')->...
```

without company filtering.

### Required correction

Implement the staged isolation plan:

- `BelongsToCompany` creation helper/trait
- set `company_id` from trusted context
- enable scopes per model only after parity testing
- convert query-builder/raw SQL readers
- validate parent IDs belong to current company before writes
- add Eloquent and raw-query cross-company tests

---

## F-04 — HIGH — Company-key table scope is incomplete

The migration covers the audited shared-core subset, but doc 05 also requires review of company ownership for:

- manufacturing/production/BOM
- projects
- repair/service
- water
- cafe
- operational settings
- document/print/communication records
- other applicable business tables

The runbook correctly acknowledges this.

### Required correction

Create a table ownership matrix:

```text
table
scope = global | company | branch | tenant-only
company_id required?
branch_id required?
readers
writers
backfill
unique-index changes
FK changes
tests
```

Complete these migrations before second-company activation.

---

## F-05 — HIGH — Global unique keys and mandatory ownership constraints remain

Current schema still has global business uniqueness, including examples such as:

- `chart_of_accounts.code`
- `journal_entries.entry_number`

The new company keys are still nullable and generally have no company FK yet.

### Effect

Company B may still be unable to use the same valid business/account/document code as Company A.

### Required correction

After writers are company-aware and backfill is proven:

- convert applicable unique keys to company-aware composite keys
- add reviewed company FKs
- make mandatory company keys non-null
- prove same valid codes can exist independently in two companies

---

## F-06 — HIGH — Enabling middleware can lock out installations with no usable FY

The backfill intentionally does not invent fiscal years.

The resolver requires exactly one matching FY when no FY is explicitly selected.

Therefore a legacy installation with no FY, or no FY covering the current Company-local date, will fail company-context resolution.

### Required correction before route activation

Require:

```text
Every active company has:
- a valid FY
- no overlapping FY ranges
- a deterministically selectable operational FY
```

If missing, route an authorized admin to setup instead of causing broad application failure.

Add a feature test for a company with no FY.

---

## F-07 — MEDIUM — MAIN-only implicit branch selection is too restrictive

When no branch is supplied, the resolver only looks for an authorized branch with code `MAIN`.

A user assigned only to one non-MAIN branch will fail context resolution even though the user has exactly one valid branch.

### Recommended policy

Use one explicit policy such as:

1. trusted session-selected branch
2. explicit user default branch
3. the only authorized branch, if exactly one exists
4. otherwise require branch selection

If MAIN access is mandatory, enforce that during membership management and document it.

---

## F-08 — MEDIUM — Company header switching can conflict with stale session branch/FY values

Each context value independently uses:

```text
header first
otherwise session
```

So sending only:

```text
X-Company-ID = Company B
```

can leave Branch/FY values coming from a Company A session.

The resolver safely rejects the mismatch, so this is not a data leak, but it can break switching UX.

### Recommended correction

Treat company/branch/FY as one coherent tuple.

When company changes:

- do not reuse dependent branch/FY values belonging to another company
- re-resolve safe defaults or require them explicitly

Standardize session keys, e.g.:

```text
active_company_id
active_branch_id
active_financial_year_id
```

or update the specification to the chosen names.

---

## F-09 — MEDIUM — `base_currency_id` is not verified against `currencies`

Backfill accepts any numeric `general_settings.currency` and copies it to `companies.base_currency_id`.

There is no existence validation in this package.

### Required correction

During dry-run:

- verify a numeric currency ID actually exists
- block invalid values
- add FK later after legacy validation

---

## F-10 — MEDIUM — The current implementation documents still conflict

### A. FY table name

`03_MULTI_COMPANY_BRANCH_AND_FINANCIAL_YEAR.md` still shows:

```text
financial_years
```

while the execution plan and actual code correctly use:

```text
fiscal_years
```

**Fix:** explicitly state that existing `fiscal_years` must be extended and `financial_years` must never be introduced.

### B. Phase numbering

`26_CODEX_EXECUTION_SEQUENCE_AND_CHECKLIST.md` uses:

```text
Phase 0 Baseline
Phase 1 Company/FY
Phase 2 Capabilities
```

while `ZOLO_ERP_EXECUTION_PLAN.md` uses:

```text
Phase 0 Discovery
Phase 1 Baseline/stabilization
Phase 2 Company/FY
Phase 3 Capabilities
```

`IMPLEMENTATION_PROGRESS.md` follows the first numbering.

**Fix:** choose one canonical numbering scheme.

### C. CompanyContext location

The execution plan Phase 2 mentions:

```text
App\Support\Company\CompanyContext
```

while actual implementation and later architecture guidance use:

```text
App\Services\Platform\CompanyContext
```

The implemented location is reasonable; update the old instruction.

### D. Separate FY middleware

Some docs mention `ResolveFinancialYearContext`; current implementation combines FY resolution into `CompanyContextResolver`.

Either approach can work, but the contract must be consistent.

### E. Bootstrap command

The execution plan mentions:

```text
erp:bootstrap-default-company
```

while DEFAULT/MAIN creation is currently part of:

```text
erp:backfill-company-context
```

Choose and document one model.

### F. SQLite wording

The execution plan says "no sqlite tests", while the implementation deliberately uses isolated SQLite service fixtures.

A better rule is:

> SQLite may be used for isolated service tests that do not claim migration parity. Schema, backfill, concurrency and cutover behavior must also be proven on MySQL.

---

## F-11 — MEDIUM — No MySQL CI or migration rehearsal proof

GitHub currently reports:

```text
no commit statuses
no workflow runs
```

for both audited commits.

The progress document also states MySQL rehearsal is pending.

### Required correction

Before phase completion:

- create a disposable MySQL test/CI environment
- run the full migration chain
- run dry-run / real backfill / dry-run
- run company tests on MySQL
- run representative legacy data
- run original SalePro smoke flows
- measure migration duration/locking
- prove failure/recovery behavior

---

## F-12 — HIGH carry-forward — Partial/Pending purchase handling remains incorrect

This previous issue is still present on current `main`.

`PurchaseService` currently behaves as:

```text
status 1 -> received = full qty, stock += full qty
status != 1 -> received = 0, stock += 0
```

The existing SalePro UI defines:

```text
1 = Received
2 = Partial
3 = Pending
```

So status 2 is Partial, not Pending.

The generic service cannot represent:

```text
ordered = 10
received = 4
```

It records received quantity as zero.

`postPurchaseJournal($purchase)` is also still called for the full purchase, which can create a physical-stock/accounting mismatch.

### Required correction

Implement:

```text
Received:
  received_qty = ordered_qty

Partial:
  received_qty = validated line-level received_qty

Pending:
  received_qty = 0
```

Then explicitly define whether accounting/AP is recognized at PO, GRN, supplier bill or a combined SalePro purchase event.

---

## F-13 — HIGH process carry-forward — Top-level stabilization gate is incomplete

`ZOLO_ERP_EXECUTION_PLAN.md` says the baseline/stabilization phase must close known D1-D11 defects before Company/FY work proceeds.

`d6b69d2` did not close all D1-D11 items, but Company/FY commits were then started.

The company work is additive and currently not activated, so this has not created an immediate destructive conflict. However, it violates the stated stop rule.

### Required decision

Either:

**Option A**
- keep the top-level execution plan authoritative
- finish D1-D11 before capability work

or:

**Option B**
- formally revise `ZOLO_ERP_EXECUTION_PLAN.md`, doc 26 and progress docs
- state which stabilization defects can move later and why

Do not leave sequencing ambiguous.

---

## F-14 — LOW — Manifest/index are stale

`manifest.json` still declares 32 Markdown files and does not list newer execution-state documents including:

- `COMPANY_BACKFILL_RUNBOOK.md`
- `IMPLEMENTATION_PROGRESS.md`
- `STOCK_AND_TRANSACTION_WRITER_AUDIT.md`

### Recommended correction

Update:

- `manifest.json`
- `00_IMPLEMENTATION_MASTER_INDEX.md`
- optionally `README.md`

and clearly separate:

```text
authoritative specification
runbooks
progress/audit evidence
```

---

# 6. Specification alignment matrix

| Requirement | Current status | Audit result |
|---|---|---|
| Company table | Implemented | Good |
| Branch table | Implemented | Good |
| Company membership | Implemented | Good |
| User-branch authorization | Implemented | Good |
| Extend existing `fiscal_years` | Implemented | Good |
| Nullable company-key staging | Audited core subset | Partial |
| Dry-run backfill | Implemented | Good |
| Real backfill | Implemented, MySQL proof pending | Partial |
| Preserve FY ranges | Implemented | Good |
| Preserve 1970 opening dates | Implemented | Good |
| Company resolver | Implemented | Good primitive |
| FY/lock resolver | Implemented | Good primitive |
| Web route enforcement | Not implemented | Blocker |
| API route enforcement | Not implemented | Blocker |
| Eloquent isolation | Not implemented | Blocker |
| Query-builder/raw SQL isolation | Not implemented | Blocker |
| Writer auto-population of company_id | Not implemented | Blocker |
| Operational/industry table ownership | Incomplete | Blocker |
| Company-aware unique keys | Not implemented | Blocker |
| Non-null/FK cutover | Not implemented | Expected later; blocker before activation |
| Background job context | Not implemented | Blocker |
| Company-aware cache keys | Not implemented | Blocker |
| Company/FY switcher | Not implemented | Pending |
| Real business-record IDOR tests | Not implemented | Blocker |
| MySQL migration/backfill proof | Not implemented | Blocker |
| CI status checks | Not present | Gap |
| Capability engine | Not started | Correct for now |
| Second-company activation | Must remain disabled | Correct |

---

# 7. Recommended next implementation sequence

Do not jump directly to the Capability Engine yet.

## Step 1 — Normalize implementation documents

Update the pack so every future Codex session uses one contract:

- one phase numbering scheme
- `fiscal_years` only
- one CompanyContext namespace
- one session-key convention
- combined vs separate FY middleware decision
- integrated vs separate bootstrap command
- updated manifest/index

## Step 2 — Close the purchase regression

Fix Received / Partial / Pending behavior and align accounting with the intended purchase receipt/bill event.

## Step 3 — Resolve the baseline stabilization gate

Either finish D1-D11 or formally revise the execution plan.

## Step 4 — Make company migrations MySQL-rehearsal-safe

- split risky multi-table DDL or implement strict resumability validation
- add currency/FY preflight
- test partial-failure recovery
- document migration time and locking

## Step 5 — Complete the table ownership inventory

Cover shared core plus manufacturing, projects, repair, water, cafe, documents and other operational tables.

Do not add `company_id` blindly to global reference tables; classify each table first.

## Step 6 — Finish company/FY activation infrastructure

Implement:

- coherent active company/branch/FY state
- branch selection policy
- company switcher
- `BelongsToCompany` creation behavior
- staged model scopes
- raw-query conversion
- job context middleware
- company cache keys
- company validation on foreign IDs
- posting-date calls from actual write services

## Step 7 — Attach middleware only after prerequisites pass

Prerequisites:

```text
DEFAULT backfill complete
zero nulls in activation scope
valid current FY exists
no FY overlaps
authorized branch exists
readers/writers scoped
company-aware uniqueness ready
jobs/cache reviewed
```

Then attach middleware to protected web and `/api/v1` routes.

## Step 8 — Add real cross-company feature tests

At minimum:

```text
Company A user:
  cannot GET Company B sale
  cannot GET Company B purchase
  cannot GET Company B journal
  cannot download Company B document
  cannot POST using Company B customer/product/warehouse IDs

Company B:
  can use same valid account/document/business codes where the specification allows
```

## Step 9 — Run MySQL rehearsal

On a disposable representative copy:

```text
backup baseline
php artisan migrate
php artisan erp:backfill-company-context --dry-run
review
php artisan down
pause workers/scheduler
php artisan erp:backfill-company-context
php artisan erp:backfill-company-context --dry-run
run tests
run smoke flows
compare counts / stock / journals / FYs
php artisan up
```

Capture evidence in the repository.

## Step 10 — Only then begin Capability/Profile work

Capability work should assume company context is already a reliable platform boundary.

---

# 8. Tests to add before Company/FY phase closure

## MySQL migration tests

- fresh install
- upgrade from realistic legacy schema
- failure after several table ALTERs, then recovery
- migration rollback on disposable DB
- large-table migration timing

## Backfill tests

- invalid numeric currency ID
- no fiscal year
- no FY covering current date
- multiple current overlapping FYs
- very large dataset

## Context tests

- user assigned only one non-MAIN branch
- company switch with stale old-company branch session
- company switch with stale old-company FY session
- invalid company timezone
- user with membership but no branch
- company with no FY
- explicit historical FY read
- locked-period web/API/import write

## IDOR tests

- sale
- purchase
- journal
- product/customer/supplier
- warehouse
- report/export/download
- vertical/operational records

---

# 9. Audit outcome per commit

## `2535838`

**Result:** Good staged foundation, but **not production-cutover complete**.

Safe to keep on `main` as an additive foundation. Before applying to a retained production MySQL database, address the multi-table DDL recovery risk and complete MySQL rehearsal.

## `b2df894`

**Result:** Good authorization/context primitive, but **not an isolation boundary yet**.

Safe to keep on `main` because it is registered but not activated. Do not attach it globally until scoped readers/writers, FY prerequisites, branch policy and IDOR tests are ready.

---

# 10. Final gate

The current implementation should be treated as:

```text
Company foundation schema        = started / good
Legacy core backfill             = implemented for audited subset
Context authorization primitive  = implemented
Actual multi-company isolation   = NOT complete
Financial period enforcement     = primitive only, not wired to writes
Second company activation        = NOT safe
Capability phase                 = wait until company isolation gate is completed
```

The architecture direction remains correct. The next work should finish and prove the platform boundary, not add industry-specific functionality yet.

# Phase 2 capability foundation and Phase 3 atomic numbering

Implemented against documents [04](04_BUSINESS_PROFILE_AND_CAPABILITY_ENGINE.md) and [12](12_DOCUMENT_SERIES_PRINTING_AND_COMMUNICATION.md). Optional activation remains blocked until the complete Phase 1 company-isolation acceptance is reviewed, as confirmed by the user on 2026-10-03. This package does not activate a second company, migrate retained data, or deploy the application.

## Capability foundation

Migration `2026_10_03_000004` creates the capability registry, business profiles, profile presets and company overrides. It seeds General Trading, FMCG, Textile, Timber and Solar using the idempotent `CapabilitySeeder`. Existing company decisions are never overwritten by reseeding.

`CapabilityService` owns availability, configuration, dependencies, profile application and navigation. Core capabilities cannot be disabled. Optional capabilities require enabled dependencies; an enabled dependent prevents disabling its prerequisite. Configuration accepts only supported fields and validated values. Company administrators may configure capabilities; company membership and role overrides are checked independently from feature availability.

The reviewed activation gate is `CapabilityCatalog::OPTIONAL_ACTIVATION_READY = false`. Effective reads, menus and middleware always honor the gate. New optional enablement is rejected. Tests exercise the finished engine with an isolated partial mock of this readiness check, and separately verify that the production implementation rejects activation. There is no runtime or environment setting that opens this gate.

`LegacyModuleAdapter` parses exact comma-separated tokens and includes their dependencies. Global `general_settings.modules` belongs only to the backfilled `DEFAULT` company. Other companies receive no global module defaults; a company may have its own `settings_json.legacy_modules` compatibility snapshot. Explicit company overrides take precedence. The import endpoint persists compatibility decisions without enabling operations while the gate is closed. Repeated imports preserve explicit disabled states. Disabling features never deletes documents.

Capability snapshots cache configured states by connection/database and company. Effective dependency and activation checks occur on every read. Changes read directly inside their transaction and invalidate cached committed state after commit. Rollback retains the previous cache state.

The sidebar reads effective capabilities while retaining its existing role/permission checks. Optional industry, manufacturing, damage-stock, exchange, installment, catalogue and WhatsApp links are hidden when unavailable. Optional operator routes receive authenticated company context and capability middleware in `CapabilityServiceProvider`, including routes loaded from a cache. Existing controller permissions remain in effect. Public customer storefront routes retain their existing authentication model; their complete ownership integration remains Phase 1 work.

Authenticated API endpoints:

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/v1/company-context/capabilities` | Effective capability state and profiles |
| PUT | `/api/v1/company-context/capabilities/{key}` | Set enabled state and validated configuration |
| POST | `/api/v1/company-context/profiles/{key}` | Apply a preset transactionally |
| POST | `/api/v1/company-context/capabilities/import-legacy` | Persist compatibility decisions |
| GET | `/api/v1/addons/status` | Authorized company feature status |

The addon-status endpoint now requires Sanctum and company context. Its compatibility `active_modules` and `features` fields come from effective capabilities instead of global settings. Clients must supply an authenticated company selection.

## Atomic numbering

Migration `2026_10_03_000005` creates series and reservations. A series is scoped by company, branch, existing `fiscal_years.id`, document type and code. Branch selection is mandatory and validated by the existing context resolver. A generated nullable default slot ensures one default series per scope while permitting multiple nondefault series.

`DocumentNumberService` reserves a number only inside the document posting transaction. It revalidates actor, company, branch, FY and posting date; locks company, FY and series in that order; increments the counter; and persists a reservation. First use creates the default series under the company lock. Separate FY rows start at one exactly once and never reset the prior FY counter.

Shared sales, purchases, transfers, sale/purchase payments and journal entries use this service. Timestamp references and journal `count()+1` have been removed from these services. Explicit posting actors propagate into automatic accounting calls so non-HTTP writers can use authorized context without relying on a web session.

Default numbers have the form `ERP-SAL-<company-id>-<branch-id>-<fiscal-year-id>-000001`; other document types use PUR, TRF, REC, PAY and JE. Administrators may configure prefix, suffix, padding and starting number through the series API. The only supported reset policy is an independent series per FY, preserving the confirmed historical FY dates.

Reservation constraints reject duplicate sequence values, duplicate formatted numbers within a company/document type, and multiple source bindings. Allocation also rejects references already present in retained source tables, including soft-deleted invoices. Number lengths respect the source and linked journal reference limits: 50 characters for journal numbers and at most 100 characters for commercial/payment references. Used series cannot be reformatted or have their counters reset; create a new code to change future numbering. Choosing a new default preserves the previous series and its history.

Assignment verifies the persisted source, company and reference before binding the reservation. Repeating the same assignment is safe; binding it to another source is rejected. Reprints read the existing reference and never allocate another number. A failed posting or outer transaction rollback removes reservations and counter changes along with document, payment, stock and accounting effects.

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/v1/company-context/document-series` | List series for the selected company/branch/FY |
| POST | `/api/v1/company-context/document-series` | Configure an unused series as an authorized administrator |

Example payload:

```json
{
  "document_type": "sale",
  "code": "COUNTER",
  "prefix": "INV/",
  "suffix": "/26",
  "padding": 4,
  "next_number": 1,
  "is_default": true
}
```

Ownership fields in request bodies do not select company, branch or FY. Use the existing authorized context headers/session.

## Verification and remaining scope

Local proof on disposable MySQL 8.4.11: 57 combined capability, numbering, commercial-regression and full-migration tests pass with 265 assertions. A subsequent focused run passes five tests with 47 assertions for committed-DDL recovery, repeated migrations, gated legacy import, sidebar rendering and six-process concurrency. SQLite passes 40 tests with 162 assertions; the two MySQL-specific cases are explicitly skipped there. PHP syntax and whitespace checks pass.

Both new migrations resume missing indexes and foreign keys after interrupted, committed MySQL table creation. Existing constraints are checked for matching columns, parent and restricting delete behavior. Repeated migrations preserve company overrides and do not duplicate constraints. `PlatformMigrationTest` injects failures immediately after CREATE TABLE, then verifies complete recovery without losing company rows.

`CapabilityEngineTest` covers all five profiles, dependencies, core invariants, configuration, legacy mapping/import, role overrides, company separation, cache/rollback, direct URL/API rejection, preserved history and the actual sidebar template. `DocumentNumberServiceTest` covers shared writer integration, independent company/branch/FY/type counters, duplicate/default constraints, historical collision rejection, immutable used series, posting-date rejection, assignment, reprints and rollback.

The MySQL concurrency fixture launches six independent PHP processes that post invoices against the initially absent sale series. It verifies six distinct invoices, six assigned reservations, one default sale series and a next counter of seven. Each child uses the validated disposable database without clearing it. SQLite does not substitute for this row-lock proof.

Browser smoke checks use the actual rendered sidebar with fixture styling. They verify effective optional visibility and retained core links, with no console errors. These checks are not evidence of full production layout, responsive design or live transaction parity.

Document 12 printing/rendering/dispatch work remains outside this atomic-numbering package. Existing raw legacy controllers retain their numbering paths until their shared-writer conversion; retained posted references are not rewritten. Full Phase 1 ownership acceptance and optional-feature activation remain gated.

# zoloERP Pro — Laravel 10 to Laravel 13 and PHP 8.5 Upgrade Runbook

**Document date:** 6 November 2026  
**Repository:** [vigneshsinna/zolo-erp-pro](https://github.com/vigneshsinna/zolo-erp-pro)  
**Branch inspected:** `main`  
**Status:** Historical upgrade plan. See the [release verification record](../docs/upgrades/laravel13-php85/uat-signoff.md) and [current VPS runbook](../deploy/README.md) for implementation and cutover evidence.

**Audience:** Engineering, DevOps, QA, Product Owner and release approvers.

> **Decision:** Target **Laravel 13.x + PHP 8.5.12 or later patched 8.5 version**, provided all dependency and platform tests pass. **PHP 8.4.27 or later patched 8.4 version** is the supported fallback. Keep the ERP's existing business architecture and front end during this migration. The target version is not a claim that all vulnerabilities are eliminated: ongoing patching and hardening remain necessary.

## 1. Objectives and non-negotiable principles

1. Migrate sequentially from Laravel 10 → 11 → 12 → 13, passing regression tests and committing a reviewed `composer.lock` at each boundary.
2. Run the final application on a **supported, patched PHP 8.5** runtime with matching CLI, FPM, Composer, scheduler and workers; permit a patched PHP 8.4 fallback if a verified dependency prevents 8.5.
3. Preserve existing POS, quotations, sales, purchase, inventory, warehouse, accounting, tax, manufacturing, job-work, billing, document and API behavior.
4. Do not change financial balances, postings, stock quantities, document numbering, historical financial years or company/branch security as a side effect of the framework upgrade.
5. Upgrade the existing application. **Do not regenerate the project with a new Laravel skeleton**, rewrite controllers to match the latest layout, introduce AI, or redesign the front end in the same release.
6. Use an isolated feature branch and disposable/staging databases. **Never execute migration, seed, reset or destructive commands against production during preparation.**
7. A green Composer install is **not** an acceptance test. Financial, data isolation and end-to-end workflows must pass.

## 2. Verified baseline from the repository

| Area | Repository evidence | Upgrade implication |
|---|---|---|
| Framework | `composer.json`: `laravel/framework: ^10.15.0` | Requires a staged upgrade to ^11, ^12 and ^13. |
| Minimum PHP | `composer.json`: `php: ^8.2` | A declared minimum, **not** evidence of the actual running server PHP version. |
| Production PHP documentation | `deploy/README.md` describes a dedicated PHP **8.3-FPM** pool; default `php` executable is a different PHP build | Verify live runtime; do **not** switch machine-wide `php` or disturb other hosted apps. |
| Database | MySQL/MariaDB in app, MySQL 8.0 reported in deployment README, MySQL 8.4 in CI | Keep DB stable for framework upgrade; validate both realistic production and CI dialects. |
| Authentication | `laravel/sanctum: ^3.3`, `config/sanctum.php`, `auth:sanctum` | Upgrade Sanctum to ^4; review/publish migrations, token/session middleware. |
| Permissions | `spatie/laravel-permission: ^5.8` plus company/branch middleware | Upgrade package in supported steps; test every tenant/branch access rule. |
| Modules | `nwidart/laravel-modules: ^8.3`, `Modules/Manufacturing/...` | Major version upgrades and module provider/autoloading changes are a major blocker until verified. |
| Legacy forms | `laravelcollective/html: ^6.2` | **Known dependency blocker**: abandoned package only declares Illuminate through Laravel 10. Must replace/refactor or adopt a properly maintained and audited compatibility strategy. |
| Laravel UI | `laravel/ui: ^4.0` | Latest 4.6.x supports Laravel 13; validate authenticators and views. |
| Testing | `phpunit/phpunit: ^10.0`; `phpunit*.xml`; acceptance workflow | Move to PHPUnit ^11 on Laravel 12 and ^12 on Laravel 13; update XML and tests. |
| CI | `.github/workflows/company-foundation.yml` pins PHP 8.3 | Final pipeline needs patched PHP 8.5 testing, ideally PHP 8.4 compatibility too. |
| Deployment | `deploy/deploy.sh`: `PHP=${PHP:-php8.3}`, reloads `php8.3-fpm`, applies migrations | Must update selected PHP CLI/FPM together and stage the deployment/rollback sequence. |
| Web | Nginx uses an application FPM socket; several sites on shared VPS | Change only the ERP pool, cron, authorized sudo rule, deployment script and relevant Nginx virtual host. |
| Configuration | Deployment guide says controllers/middleware use `env()` directly; it deliberately does not cache config | Preserve current behavior first; plan a separate `env()` → configuration refactor before enabling `config:cache`. |
| API | Sanctum / `/api/v1/...`, company and financial-year context | Validate OAuth/session/bearer flows and company isolation. |
| Frontend | Blade, Bootstrap 4, jQuery, Vue 2 and Laravel Mix 5 dependencies | Keep UI behavior unchanged while separately auditing outdated npm dependencies. |

**Primary files:** [`composer.json`](https://github.com/vigneshsinna/zolo-erp-pro/blob/main/composer.json), [`deploy/deploy.sh`](https://github.com/vigneshsinna/zolo-erp-pro/blob/main/deploy/deploy.sh), [`deploy/README.md`](https://github.com/vigneshsinna/zolo-erp-pro/blob/main/deploy/README.md), [CI workflow](https://github.com/vigneshsinna/zolo-erp-pro/blob/main/.github/workflows/company-foundation.yml), [API routes](https://github.com/vigneshsinna/zolo-erp-pro/blob/main/routes/api.php).

**Validation limit:** this is a repository and documentation audit, not a successful Composer solver run, PHP 8.5 test run or live VPS inspection. A current dependency-lock and runtime compatibility report is a mandatory first implementation step.

## 3. Supported target and security rationale

As at **6 November 2026**:

| Component | Target | Support / reason |
|---|---|---|
| Laravel | Current patched 13.x | Laravel 13 supports PHP 8.3–8.5. Security fixes through 17 March 2028. |
| PHP preferred | **8.5.12+**, patched stable 8.5 | Active PHP branch through 31 Dec 2027, security-only support through 31 Dec 2029. |
| PHP fallback | **8.4.27+**, patched stable 8.4 | Active support through 31 Dec 2026, security-only support through 31 Dec 2028. |
| PHP rejected for final target | 8.2 / 8.3 | 8.2 security ends 31 Dec 2026; 8.3 security support ends 31 Dec 2027. |
| PHP 8.6 | **Do not deploy a release candidate to production** | Not a stable production target as of this document. |

PHP issued urgent **21 October 2026** security releases 8.5.12 and 8.4.27 fixing CVE-2026-109618 and CVE-2026-109619. Do not deploy affected earlier builds just because their major branch is supported. Confirm the *patch version* immediately before rollout. Also patch OS, OpenSSL, Nginx, Composer packages and JavaScript dependencies; PHP alone cannot make the application vulnerability-free.

Official release/support evidence: [PHP downloads](https://www.php.net/downloads.php), [PHP supported versions](https://www.php.net/supported-versions.php), [Laravel support matrix](https://laravel.com/docs/13.x/releases).

## 4. Dependency blockers and treatment plan

| Package / area | Current | Required action before marking the upgrade ready |
|---|---|---|
| `laravel/framework` | `^10.15.0` | Advance `^11.0` → `^12.0` → `^13.0` in independent reviewed commits. |
| `laravel/sanctum` | `^3.3` | Set `^4.0` in Laravel 11 phase, review published migrations and `config/sanctum.php`, test existing tokens. |
| `laravel/tinker` | `^2.8` | Upgrade to a Laravel 13-supported Tinker ^3.0 in final phase. |
| `spatie/laravel-permission` | `^5.8` | Move to a compatible v6 at the Laravel 11 boundary, then Laravel 13-compatible v7/v8 after reading Spatie upgrade docs. Test permission names, cache, role assignment and middleware. |
| `nwidart/laravel-modules` | `^8.3` | Audit current code/providers and move to matching Laravel major (`^11`, `^12`, `^13`) at successive phases as supported. Latest 13.x requires a module autoload/plugin migration; test all modules, especially Manufacturing. |
| `laravelcollective/html` | `^6.2` | **Blocking.** Abandoned and declares Illuminate up to Laravel 10. Inventory `Form::`, `Html::`, aliases, providers and generated markup. Refactor to Blade/native HTML or replace with a supported alternative; `spatie/laravel-html` is **not** guaranteed API-compatible. |
| `laravel/ui` | `^4.0` | Resolve to a 4.6.x release compatible with 13; test login, password reset and scaffolding assumptions. |
| `doctrine/dbal` | `^3.0` | Check for direct application use. Laravel 11 no longer needs DBAL to modify columns; remove only if unused. |
| `phpunit/phpunit` | `^10.0` | ^11 for Laravel 12, ^12 for Laravel 13; update PHPUnit XML schema and failing assertions. |
| `nunomaduro/collision` | `^7.0` | Upgrade to framework-compatible version; Laravel 11 guide recommends ^8.1. Validate final version in Composer solver. |
| `barryvdh/laravel-dompdf` | `*` | Replace unbounded `*` with a reviewed compatible supported major version. Test all PDF invoices/printouts. |
| `milon/barcode`, `orangehill/iseed`, `gladcodes/keygen`, `salla/zatca`, payment integrations, `maatwebsite/excel` | Mixed older constraints | Verify each package's Laravel 13 and PHP 8.5 support. Replace unmaintained blockers rather than suppressing Composer platform checks. |
| Vue 2 / Mix 5 / jQuery / Axios | Older npm stack | Security audit and phased frontend modernization as a separate workstream, with any actively exploitable issues remediated before production approval. |

Important: the **installed** locked versions can differ from the version constraints in `composer.json`. Use `composer show --locked --direct` and the actual `composer.lock` to record all versions. Resolve blockers with maintainers' compatibility tables; do not copy this table into Composer as a blind edit.

Useful package sources: [LaravelCollective package](https://packagist.org/packages/laravelcollective/html), [nWidart compatibility](https://packagist.org/packages/nwidart/laravel-modules), [Spatie prerequisites](https://github.com/spatie/laravel-permission/blob/main/docs/prerequisites.md), [Laravel UI package](https://packagist.org/packages/laravel/ui).

## 5. Phase 0 — Baseline, branch and recovery readiness

### 5.1 Create the upgrade branch

Execute on an authorised local development checkout, **not** on production:

```bash
git checkout main
git pull --ff-only origin main
git status --short
git switch -c upgrade/laravel13-php85
```

Do not merge to `main` while GitHub Actions deploys every successful `main` push to production. Use a dedicated staging deployment instead.

### 5.2 Record current state and dependency conflicts

```bash
php -v
php --ini
php -m
composer --version
composer validate --strict
composer show --locked --direct
composer outdated --direct
composer audit
composer prohibits laravel/framework 11.0.0
composer prohibits php 8.5.12
node --version
npm --version
npm audit --omit=dev
```

Re-run the `composer prohibits` tests with Laravel 12 and 13 target constraints after editing `composer.json` **on the upgrade branch**. `composer prohibits` may report constraints unrelated to the running PHP platform; the package solver is the decisive check.

Look for `Form::`, `Html::`, `LaravelCollective`, module custom providers, service bindings, direct `env()` in app code, PHPUnit extensions, cached objects, legacy authentication classes, and API resource serialization assumptions.

### 5.3 Establish recoverable baselines

- Freeze a reviewed commit SHA and capture current `composer.lock`, application release manifest and configuration differences (do not publish `.env`).
- Take and **restore-test** a consistent MySQL backup; retain a separate recovery copy of private uploads, PDFs, receipts and filesystem storage.
- Capture financial invariants and report outputs on the same test-data snapshot: trial balance, general ledger, P&L, balance sheet, open items, inventory stock ledger, outstanding balances, last document sequence numbers, posted document counts, GST values.
- Snapshot server-specific Nginx config, FPM pool, PHP INI, cron, sudoers, supervisor/worker definitions and secrets references in a secure location.
- Ensure no upgrade code touches unrelated applications hosted on the VPS.
- Record baseline PHP/Composer/Node/npm versions in a machine-readable `docs/upgrade/baseline.md`.

### 5.4 Testing data precautions

Use **disposable test databases** or a masked staging copy. Never use a production DB for test migrations or integration tests. Confirm backup restore and orphan-file recovery before any release window is scheduled.

## 6. Recommended version-by-version migration path

| Checkpoint | Framework | PHP used for compatibility and tests | Dependency gate |
|---|---|---|---|
| Baseline | Laravel 10 | Current documented production 8.3 (verify live) | All current suites pass or failures are catalogued. |
| A | Laravel 11 | Patched 8.3 | Sanctum 4, collision, Spatie v6, nWidart 11 and legacy forms blocker addressed. |
| B | Laravel 12 | Patched 8.4 | Carbon 3, PHPUnit 11, nWidart 12; inventory and finance parity. |
| C | Laravel 13 | Patched **8.5.12+** | PHPUnit 12, Tinker 3, nWidart 13, Spatie compatible version, full security pass. |
| Fallback | Laravel 13 | Patched **8.4.27+** | Only if PHP 8.5 compatibility cannot be verified or supported. Document reason and exit criteria. |

This staging progression is a **testing and compatibility sequence**, not a requirement to temporarily expose unsupported framework versions in production. All intermediate checkpoints stay in non-production environments.

### 6.1 Laravel 10 → 11

1. Complete the abandoned forms package migration or select an audited, maintained alternative.
2. Upgrade direct dependencies only after mapping compatibility: Laravel ^11, Sanctum ^4, Collision ^8.1, nWidart ^11, Spatie Permission compatible v6. Use `composer require ... --no-update` only with reviewed version constraints.
3. Regenerate lock file using a patched PHP 8.3 runtime:

```bash
composer validate --strict
composer update -W --no-scripts
composer check-platform-reqs
composer audit
php artisan about
php artisan route:list
php artisan migrate:status
```

4. Inspect provider/alias autoloading, module discovery, `config/sanctum.php`, token middleware and published Sanctum migrations. Only publish missing migrations after checking for duplicate table creation:

```bash
php artisan vendor:publish --tag=sanctum-migrations
```

5. Review every `Schema::table(...)->change()` migration: Laravel 11 requires preserving column modifiers explicitly. Test migrations against disposable MySQL, including upgrade from a *copy* of the old schema.
6. Review database floating-point changes, Carbon behavior, request validation, authentication and rate limiting. **Do not adopt Laravel 11's new minimal application skeleton**: the existing `app/Http/Kernel.php` and providers may remain in place.
7. Run all tests and browser checks; commit `composer.json`, `composer.lock`, config, code and test repairs as a reviewable checkpoint.

Official: [Laravel 11 upgrade guide](https://laravel.com/docs/11.x/upgrade).

### 6.2 Laravel 11 → 12

1. Move to patched PHP 8.4 for staging tests and set framework ^12; move PHPUnit to ^11; upgrade nWidart to a Laravel 12-compatible version.
2. Check that Carbon 3 is installed and review time differences, tax-date calculations, ageing, credit periods, fiscal-year ranges, document dates and due-date boundary behavior.
3. Review file-storage disk roots, UUID generation, image/SVG validation, nested request merging, container bindings and test fixtures.
4. Run `composer update -W --no-scripts`, `composer check-platform-reqs`, migration dry run on a disposable database, and the complete tests before checkpoint commit.

Official: [Laravel 12 upgrade guide](https://laravel.com/docs/12.x/upgrade).

### 6.3 Laravel 12 → 13

1. Use **patched PHP 8.5.12 or newer**. Update framework to ^13, Tinker to ^3, PHPUnit to ^12, nWidart to ^13 and permission package to a Laravel 13-compatible major after reading each package guide. Update PHPUnit config and module autoloading as necessary.
2. On Laravel 12, optionally use the *Laravel Boost ^2* upgrade assistant and `/upgrade-laravel-v13` prompt, but review every proposed code change; do not grant unrestricted production access.
3. Check Laravel 13 breaking changes especially relevant to zoloERP:
   - CSRF middleware `VerifyCsrfToken` → `PreventRequestForgery`; review `app/Http/Middleware/VerifyCsrfToken.php`, custom exclusions, session-based POS requests, checkout, webhooks and tests.
   - MySQL/MariaDB `upsert()` now rejects an empty `uniqueBy`. Audit imported stock, seeders, idempotency and staging data loads.
   - MySQL `DELETE JOIN ORDER BY LIMIT` compilation may now produce an unsupported query instead of ignoring the clauses; review cleanup and retention routines.
   - Cache `serializable_classes` is secure by default; confirm cache payload shapes and explicit allow-list where genuinely necessary.
   - Session `serialization=json` is recommended where safe, but migrating existing PHP-serialized sessions can log users out and break stored object sessions; perform a dedicated session migration plan.
   - Cache/Redis/session prefixes may change. Pin environment names to avoid unexpected cache collisions or session invalidation.
   - Check jobs, eager-loaded relation serialization, queue events and custom container contracts if used.
4. Repeat the complete acceptance suite on PHP 8.5 and staging MySQL. Recheck runtime and extensions after every FPM build change.

Official: [Laravel 13 upgrade guide](https://laravel.com/docs/13.x/upgrade).

### Example of version edits (illustrative; **not** a drop-in composer.json)

At each checkpoint, edit the compatible **full dependency set** in `composer.json`, resolve package blockers, and then solve/test the lock file.

```json
{
  "require": {
    "php": "^8.4",
    "laravel/framework": "^13.0",
    "laravel/sanctum": "^4.0",
    "laravel/tinker": "^3.0",
    "nwidart/laravel-modules": "^13.0"
  },
  "require-dev": {
    "phpunit/phpunit": "^12.0"
  }
}
```

This is **only a fragment** of a possible final configuration. Retain and upgrade all other production dependencies with confirmed compatible constraints. `^8.4` permits PHP 8.4 and 8.5 while requiring at least 8.4. Do **not** drop dependencies simply to make Composer succeed. Never use `--ignore-platform-reqs` to force the upgrade.

## 7. PHP migration and VPS cutover

### 7.1 Verify live operating system and all PHP runtimes first

The repository documents PHP 8.3-FPM for the ERP and a separate default CLI PHP used by other software. Verify facts before applying commands:

```bash
cat /etc/os-release
command -v php php8.3 php8.4 php8.5 || true
php -v
php8.3 -v
systemctl list-units 'php*-fpm.service' --all
systemctl cat php8.3-fpm
nginx -T | grep -n -E 'fastcgi_pass|server_name'  # redacted output, authorised admin only
```

Check that the OS/distribution provides security-maintained PHP 8.5 packages through a trusted vendor or an approved, maintained repository. On Debian/Ubuntu verify package availability with `apt-cache policy php8.5-cli php8.5-fpm` before planning installation. **Do not assume every OS repository has PHP 8.5 or use an unvetted third-party repository.**

### 7.2 Install side by side, not machine-wide

- Install patched PHP 8.5 CLI, FPM and all required extensions, including `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `intl` if used, `mbstring`, `openssl`, `pdo_mysql`, `simplexml`, `xml`, `zip`, and any other required application-specific extensions. Confirm actual extension names for the OS.
- Keep the old PHP 8.3 FPM service and site configuration available for rollback during the release window.
- Create a dedicated **zoloERP PHP 8.5 FPM pool** with its own socket and correct application user/group and permissions. Do not share or overwrite unrelated sites' pools.
- Review `php.ini`: `display_errors=Off`, `log_errors=On`, `expose_php=Off`, secure session cookie behavior, upload limits, timeouts, OPcache, memory limit and `date.timezone`.
- Confirm `php8.5 -m`, `php8.5 --ini` and application `composer check-platform-reqs` before use.
- Do not globally repoint `php`, replace system symlinks or modify all Nginx virtual hosts.

### 7.3 Change every ERP-specific caller in one coordinated release

| Caller | Before (documented) | After (example) |
|---|---|---|
| ERP CLI | `php8.3` | `php8.5` |
| ERP PHP-FPM pool | PHP 8.3 | Dedicated PHP 8.5 pool |
| Nginx ERP vhost | ERP PHP 8.3 socket | ERP PHP 8.5 socket (validated) |
| `deploy/deploy.sh` | `PHP=${PHP:-php8.3}` | Explicitly versioned PHP 8.5 selected for release |
| Deployment service reload | `php8.3-fpm` | Reload *ERP* PHP 8.5 FPM service/pool |
| Cron scheduler | PHP 8.3 | Explicit PHP 8.5 binary |
| Queue/supervisor jobs | Verify actual command | PHP 8.5 binary, restart after deployment |
| Sudoers deployment permission | Allows PHP 8.3-FPM reload | Add constrained permission for specific PHP 8.5 service (no blanket sudo) |
| GitHub Actions test matrix | PHP 8.3 | PHP 8.5, optional 8.4 compatibility job |

**Production safety:** Rollout PHP-FPM and code change together. Nginx's ERP socket target must exist and be writable before traffic is switched. Nginx config must pass `nginx -t`; validate only the ERP site, then reload Nginx. Confirm live HTTP requests and CLI really run on the intended PHP version. Restart PHP workers and clear OPcache appropriately.

### 7.4 Configuration and security refinement

The current deployment intentionally skips `config:cache` because some application code calls `env()` outside config files. During the upgrade, **do not blindly enable config caching**. Catalogue these calls and later move them to `config/*.php`, accessed via `config()`, then add a separate regression-tested change to enable optimized production config caching. Preserve `APP_KEY` during upgrade; changing it invalidates encrypted application data and sessions. Keep `.env` outside Git and restricted to application owners.

## 8. CI/CD changes required before merging

`company-foundation.yml` already runs MySQL acceptance suites. Add PHP 8.5 testing and retained coverage of current company, commercial, concurrency, compliance, operations, delivery and legacy MySQL suites. Upgrade PHPUnit and XML files; add a job for `composer validate`, `composer check-platform-reqs`, `composer audit`, syntax analysis, package-lock audit and security tests.

Example *conceptual* CI change:

```yaml
strategy:
  fail-fast: false
  matrix:
    php: ['8.5']
    suite: [company, commercial, concurrency, compliance, operations, delivery, legacy-mysql]
steps:
  - uses: actions/checkout@v4
  - uses: shivammathur/setup-php@v2
    with:
      php-version: ${{ matrix.php }}
      extensions: pdo_mysql, pdo_sqlite, zip, gd, bcmath, mbstring, intl
      coverage: none
  - run: composer install --no-interaction --prefer-dist --no-progress --no-scripts
  - run: composer check-platform-reqs
  # Then run the existing suite using the existing MySQL fixture/service setup.
```

Do **not** replace the full existing CI file with this excerpt. Existing MySQL fixture setup and environment variables are required. Update deploy gating and require PR review; keep `main` protected. Expand deploy smoke tests beyond checking `/login` returns 200: include authorised read-only API and representative document/report pages. Do not perform destructive financial transactions against production during smoke testing.

## 9. ERP regression testing and acceptance gates

| Area | Required test evidence |
|---|---|
| Authentication | Login/logout, password reset, role permissions, sessions, Sanctum bearer tokens, CSRF and secure cookies |
| Multi-tenant isolation | User A cannot access company B; restricted branches and financial years remain isolated for API, jobs, reports, nested relations |
| POS, sales and returns | Tax calculation, discount rules, rounding, sequential document numbers, stock reservations, posting, refunds and reversal |
| Purchasing / GRN | PO lifecycle, goods receipt, bill posting, supplier credits, purchase returns, landed costs |
| Inventory | Multi-warehouse stock, weighted cost/valuation, batches, serials, transfers, reserved quantities, stock adjustment, reconciliation |
| Accounting | Balanced journals, ledger/open items, trial balance, P&L, balance sheet, credit allocations, fiscal-year locks, idempotency |
| Compliance | GST, reverse charges where applicable, invoice taxes, statutory exports, immutable posted documents |
| Manufacturing/operations | Manufacturing module discovery, BOM/recipe, stock consumption, finished goods, cost postings, job work and other enabled packs |
| Documents | Blade rendering, HTML and PDF generation, barcode/thermal printing, private upload/download protections, email/SMS integrations |
| API | `/api/v1` authenticated reads/writes, validation, error schemas, idempotency, company context and restricted access |
| Frontend | Existing Blade forms, jQuery, old Vue components, tables, modal dialogs, keyboard shortcuts and browser console errors |
| Scheduled jobs | Backups, report jobs, dispatch, prune, notifications; same PHP binary and queue serialization behavior |
| Security | `composer audit`, `npm audit`, CSRF, direct object reference/tenant tests, permission bypass, secret handling and no `.env` disclosure |
| Performance | POS billing latency, stock queries, reports, high-volume posting, scheduler overlap, DB locks, FPM memory/CPU |

### Command-level test gate

On a **verified disposable database**:

```bash
composer validate --strict
composer check-platform-reqs
composer audit
php artisan --version
php artisan about
php artisan route:list
php artisan migrate:status
php vendor/bin/phpunit -c phpunit.xml
# Run each existing phpunit.<suite>.xml suite from the GitHub Action.
# Run Playwright browser tests with an isolated test server and fixtures.
```

Run test suites separately on PHP 8.5 with the production-compatible DB and extension stack. Do not run `migrate:fresh`, `db:seed`, `ResetDB`, `--force` or factory-based tests against production.

### Approval gate: all must be TRUE

- [ ] No **unresolved critical/high exploitable** application, runtime or dependency vulnerabilities; exceptions approved with compensating controls and owner.
- [ ] PHP 8.5 runtime and required extensions are supported and patched in CLI, FPM, cron and workers.
- [ ] All direct Composer dependencies are compatible and lockfile is reviewed, reproducible and committed.
- [ ] Legacy LaravelCollective forms have a tested supported solution.
- [ ] Manufacturing and optional module discovery/booting work.
- [ ] Tenant and branch permission tests pass with no cross-company data leakage.
- [ ] All accounting and stock invariants match the pre-upgrade snapshot (apart from explicitly approved changes).
- [ ] Existing API contracts and browser smoke tests pass.
- [ ] Restore-tested backup, rollback strategy and release owner sign-offs are recorded.
- [ ] CI and staging deployment pass on same PHP/runtime and MySQL family as production.

## 10. Production deployment runbook

1. Announce planned maintenance window and freeze application writes and scheduled mutating jobs.
2. Confirm tested restore points for production database, object/filesystem storage and server config. Capture the old code commit and dependency lock.
3. Deploy reviewed Laravel 13 code **with matching `composer.lock`** into a safe release location; use `composer install --no-dev` under **PHP 8.5**. Never resolve dependency versions ad hoc on production.
4. Enable maintenance mode using the correct selected PHP binary; pause/stop queue workers and scheduled mutating jobs safely.
5. Review and apply **approved** migrations only (old migrations must not be rewritten; new ones preferably additive). Back up and verify before any irreversible change.
6. Switch only zoloERP's PHP-FPM socket/vhost to PHP 8.5. Preserve other applications' runtimes and vhosts; validate `nginx -t` and reload relevant services.
7. Clear outdated compiled classes, views, and appropriate caches; rebuild views. Keep config caching disabled until all direct `env()` consumers are refactored and verified.
8. Start ERP queue/scheduler processes using the patched PHP 8.5 binary, restart workers gracefully and check queued job compatibility.
9. Remove maintenance mode, run **read-only** functional smoke tests, check Laravel/Nginx/FPM logs, HTTP status, billing page, authorisation and accounting report access.
10. Monitor error rate, 5xx, response times, DB connections, queue failures, stock reconciliation and financial postings. Keep named release owner on duty for rollback.

**Note:** Existing `deploy/deploy.sh` automatically migrates and reloads PHP 8.3. It must be revised and validated before this release. Its automatic migrate step is unsafe to assume reversible; attach a reviewed migration manifest and explicit deployment gate. `deploy.yml` currently checks only the login page, which is insufficient for an ERP major-version upgrade.

## 11. Rollback and restoration

A Git SHA rollback **alone is not enough** for a PHP/FPM and schema upgrade.

- **Code-only failure, schema remains backward-compatible:** restore old code/lock, old PHP 8.3 ERP pool/socket and old ERP cron/workers; verify app, ledger and inventory, then reopen access. Check that the original PHP runtime still receives required patches.
- **Runtime failure:** re-point ERP Nginx vhost to preserved old PHP pool, restore CLI/worker scripts, reload and verify; do not touch unrelated apps.
- **Schema/data failure:** do not guess a migration rollback. Stop writes, assess whether a forward fix is safe; if restoring database backup is necessary, obtain approval because transactions after snapshot may be lost. Coordinate file/object storage restore to the same consistency point.
- **Security exposure:** isolate relevant app service, rotate affected secrets/tokens as appropriate, investigate logs and perform incident response rather than only reverting code.

Maintain staged rollback rehearsal evidence, owner and recovery time criteria before production sign-off.

## 12. Post-upgrade phase: AI readiness

Once the Laravel 13/PHP 8.5 release is stable, create a **separate PR/phase** for Laravel AI SDK:

```bash
composer require laravel/ai
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
# Review generated migrations on a disposable/staging database before applying.
```

First build a read-only ERP Copilot over existing authorisation-aware services, then inventory/reorder and finance-analysis agents. Require human approval for payments, journals, transfers and purchase creation; never pass unscoped DB access to an AI model. See [Laravel AI SDK](https://laravel.com/docs/13.x/ai-sdk).

## 13. Deliverables to be committed in the upgrade PR

1. `docs/upgrades/laravel13-php85/baseline.md` — actual environment, package lock and test baseline.
2. `docs/upgrades/laravel13-php85/dependency-matrix.md` — each direct package, installed version, supported version, migration/replace outcome.
3. Reviewed `composer.json` and `composer.lock` checkpoint commits; PHP-FPM and CI changes.
4. Refactored LaravelCollective calls, modules bootstrap and other framework incompatibilities with tests.
5. Updated PHPUnit XML, CI pipelines, browser fixtures and test evidence.
6. Deploy, database, recovery and server hardening changes approved by owner.
7. `docs/upgrades/laravel13-php85/uat-signoff.md` with finance, inventory, POS, API, tenant isolation and DevOps sign-offs.
8. Post-deploy monitoring report and rollback rehearsal results.

## 14. Main risks and decisions

| Risk | Severity | Mitigation |
|---|---|---|
| Abandoned LaravelCollective blocks Laravel 11+ resolution | **Critical blocker** | Inventory usage, refactor/replace while preserving rendered forms and validation |
| Old nWidart modules bootstrap breaks in 11–13 | High | Per-major provider/autoload verification and enabled module tests |
| PHP 8.5 extension/vendor incompatibility | High | PHP 8.5 staging matrix; use patched PHP 8.4 fallback if necessary |
| Shared VPS PHP switch impacts other apps | **Critical** | Dedicated FPM pool and socket; no global PHP alternatives or broad Nginx changes |
| Financial posting or stock ledger regression | **Critical** | Snapshot comparison, idempotency, concurrency tests and independent functional sign-off |
| CI passes but production PHP differs | High | Match PHP CLI/FPM/cron/workers; version/assert in deployment preflight |
| Existing deployment auto-runs migrations | High | Gate migrations, ensure forward/back compatibility, verify recovery |
| CSRF/session/middleware changes break login/POS | High | Test CSRF, sessions, headers, authentication and API tokens on v13 |
| `env()` outside config conflicts with config caching | Medium | Keep config cache off now; refactor under separate tested task |
| Old npm frontend vulnerabilities | High (depends on findings) | Security audit, mitigate severe issues, modernise in focused PRs |

## 15. Authoritative references

- [Laravel 10→11 upgrade](https://laravel.com/docs/11.x/upgrade)
- [Laravel 11→12 upgrade](https://laravel.com/docs/12.x/upgrade)
- [Laravel 12→13 upgrade](https://laravel.com/docs/13.x/upgrade)
- [Laravel 13 release support](https://laravel.com/docs/13.x/releases)
- [Laravel 13 deployment requirements](https://laravel.com/docs/13.x/deployment)
- [PHP supported versions](https://www.php.net/supported-versions.php)
- [PHP official patched releases](https://www.php.net/downloads.php)
- [PHP 8.4 backward compatibility](https://www.php.net/manual/en/migration84.incompatible.php)
- [PHP 8.5 backward compatibility](https://www.php.net/manual/en/migration85.incompatible.php)
- [nWidart modules version compatibility](https://packagist.org/packages/nwidart/laravel-modules)
- [Spatie permission compatibility](https://github.com/spatie/laravel-permission/blob/main/docs/prerequisites.md)
- [LaravelCollective abandoned package](https://packagist.org/packages/laravelcollective/html)
- [Laravel AI SDK](https://laravel.com/docs/13.x/ai-sdk)

---

**Final implementation rule:** The upgrade can be considered complete only after an actual patch-supported PHP 8.5 runtime and Laravel 13 have passed **Composer resolution, all application/industry tests, security audit, isolated tenant regression, finance/stock reconciliation, staging deployment and tested rollback**. No production implementation should start solely from this plan without validating live server state and dependency compatibility.

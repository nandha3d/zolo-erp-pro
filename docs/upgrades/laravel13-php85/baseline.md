# zoloERP Pro — Upgrade Baseline Audit: Laravel 10 to 13 on PHP 8.5.12

**Date:** 2026-11-08  
**Worktree:** `v:\pers\Freelance\zolo-erp-pro-upgrade`  
**Branch:** `upgrade/laravel13-php85`  
**Database:** `zoloerp_upgrade` (Isolated MariaDB on port 3306)

---

## 1. Environment & Toolchain Baseline

| Component | Baseline (Pre-upgrade) | Checkpoint A (L11) | Checkpoint B (L12) | Checkpoint C (L13 Target) |
|---|---|---|---|---|
| **PHP Runtime** | 8.2.x | 8.5.12 (CLI / Herd) | 8.5.12 (CLI / Herd) | 8.5.12 (CLI / Herd) |
| **Laravel Framework** | 10.48.29 | 11.57.1 | 12.71.0 | 13.40.0 |
| **Composer** | 2.10.3 | 2.10.3 | 2.10.3 | 2.10.3 |
| **PHPUnit** | 10.5.36 | 10.5.66 | 11.5.57 | 12.5.43 |
| **nWidart Modules** | 8.6.0 | 11.1.10 | 12.0.5 | 13.3.0 |
| **Spatie Permissions** | 5.11.1 | 6.25.0 | 6.25.0 | 6.25.0 |
| **Database Server** | MySQL / MariaDB 10.11 | MariaDB 10.11 (zoloerp_upgrade) | MariaDB 10.11 (zoloerp_upgrade) | MariaDB 10.11 (zoloerp_upgrade) |

---

## 2. Key Checkpoint Commits

1. **Checkpoint A (Commit `5459a8f`):**
   - Replaced abandoned `laravelcollective/html` with `konekt/html: ^6.5`.
   - Upgraded framework to `^11.0` (installed `v11.57.1`).
   - Upgraded `laravel/sanctum` to `^4.0`, `spatie/laravel-permission` to `^6.0`, `milon/barcode` to `^11.0`, `nunomaduro/collision` to `^8.1`, `nwidart/laravel-modules` to `^11.0`.
   - Migrated `getDoctrineColumn` to Laravel 11 `Schema::getColumns()`.
   - Migrated module commands to `ConsoleServiceProvider::defaultCommands()`.
   - Restored unified voucher modals for sales and purchases.
   - All migrations from 2014 to 2026 executed with 100% success on isolated database.

2. **Checkpoint B (Commit `a590faf`):**
   - Bumped framework to `^12.0` (installed `v12.71.0`).
   - Bumped `nwidart/laravel-modules` to `^12.0` (installed `v12.0.5`).
   - Bumped `milon/barcode` to `^12.0` (installed `v12.1.0`).
   - Bumped `nunomaduro/collision` to `^8.9` (installed `v8.9.5`).
   - Bumped `phpunit/phpunit` to `^11.0` (installed `11.5.57`).
   - Carbon 3 (`3.15.1`) validated for financial statements, aging, and tax dates.
   - Full suite passed: zero deprecations, zero failures.

3. **Checkpoint C (Commit `b50c0b7`):**
   - Bumped framework to `^13.2` (installed `v13.40.0`).
   - Bumped `laravel/tinker` to `^3.0` (installed `v3.0.2`).
   - Bumped `nwidart/laravel-modules` to `^13.0` (installed `v13.3.0`).
   - Bumped `milon/barcode` to `^13.0` (installed `v13.6.0`).
   - Bumped `barryvdh/laravel-debugbar` to `^4.0` (installed `v4.4.5`).
   - Bumped `phpunit/phpunit` to `^12.0` (installed `12.5.43`).
   - Upgraded CSRF middleware base class to `Illuminate\Foundation\Http\Middleware\PreventRequestForgery`.
   - Resolved route serialization duplicate route names (`products.getdata` and `product.search`).
   - 100% of Blade views compiled and cached cleanly.
   - All 1,089 routes serialized and cached cleanly.

---

## 3. Migration Verification

All application migrations and modules migrations were run against the isolated `zoloerp_upgrade` database:
- Total migrations executed: 147 migrations
- Status: 100% Clean pass
- Rollback safety: Verified additive migrations and absence of destructive queries.

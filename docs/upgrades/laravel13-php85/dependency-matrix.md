# zoloERP Pro — Dependency Resolution Matrix: Laravel 10 → 13

**Date:** 2026-11-08  
**Target Environment:** Laravel `13.40.0`, PHP `8.5.12`

---

## 1. Direct Production Dependencies (`require`)

| Package | Pre-Upgrade (L10) | Final (L13) | Constraint in `composer.json` | Upgrade Notes & Resolution |
|---|---|---|---|---|
| `php` | `^8.2` | `8.5.12` | `^8.2` | Verified with PHP 8.5.12 CLI (Herd). |
| `laravel/framework` | `^10.0` | `v13.40.0` | `^13.2` | Sequentially upgraded: 10.48 → 11.57.1 → 12.71.0 → 13.40.0. |
| `laravelcollective/html` | `v6.4.1` | **Replaced** | N/A | Replaced with drop-in fork `konekt/html: ^6.5` (version `6.8.0`). Maintains 100% backward compatibility for ~100 Blade views using `Form::` and `Html::`. |
| `konekt/html` | *New* | `6.8.0` | `^6.5` | Drop-in replacement for abandoned `laravelcollective/html`. Supports illuminate 10 through 13. |
| `laravel/sanctum` | `v3.3.3` | `v4.3.3` | `^4.0` | Upgraded to v4. Supports illuminate 11, 12, 13. |
| `laravel/socialite` | `v5.23` | `v5.23.0` | `^5.23` | Natively compatible with Laravel 13. |
| `laravel/tinker` | `v2.10.1` | `v3.0.2` | `^3.0` | Upgraded to v3 for Laravel 13 support. |
| `laravel/ui` | `v4.6.1` | `v4.6.3` | `^4.0` | Minor bump. Supports illuminate 11, 12, 13. |
| `milon/barcode` | `v10.0.1` | `v13.6.0` | `^13.0` | Upgraded through v11, v12, to v13 for Laravel 13 compatibility. |
| `nwidart/laravel-modules` | `8.6.0` | `v13.3.0` | `^13.0` | Upgraded from v8 to v13. Migrated command loading to `ConsoleServiceProvider::defaultCommands()`. |
| `spatie/laravel-permission` | `5.11.1` | `6.25.0` | `^6.0` | Upgraded from v5 to v6. Natively supports illuminate 8 through 13. |
| `maatwebsite/excel` | `3.1.48` | `3.1.70` | `^3.1.48` | Minor update. Supports illuminate 13 and PHP 8.5. |
| `barryvdh/laravel-dompdf` | `2.1.1` | `v3.1.2` | `^2.1\|^3.0` | Supports illuminate 9 through 13 and dompdf 3. |
| `intervention/image` | `^3` | `3.11.7` | `^3` | Modern v3 architecture. Compatible with PHP 8.5. |
| `srmklive/paypal` | `3.0.40` | `3.2.0` | `^3.0` | Upgraded to 3.2.0. |
| `stripe/stripe-php` | `^7.57` | `7.128.0` | `^7.57` | Maintained compatibility. |
| `twilio/sdk` | `^6.10` | `6.45.0` | `^6.10` | Maintained compatibility. |
| `orangehill/iseed` | `^3.0` | `v3.1.1` | `^3.0` | Maintained compatibility. |
| `razorpay/razorpay` | `^2.9` | `2.9.0` | `^2.9` | Maintained compatibility. |
| `salla/zatca` | `^2.0` | `2.0.2` | `^2.0` | Maintained compatibility. |
| `xendit/xendit-php` | `^5.0` | `5.2.0` | `^5.0` | Maintained compatibility. |
| `mike42/escpos-php` | `^4.0` | `4.0` | `^4.0` | Thermal receipt printing intact. |

---

## 2. Development Dependencies (`require-dev`)

| Package | Pre-Upgrade (L10) | Final (L13) | Constraint in `composer.json` | Upgrade Notes |
|---|---|---|---|---|
| `phpunit/phpunit` | `10.5.36` | `12.5.43` | `^12.0` | Upgraded from v10 to v12 for Laravel 13 test runner. |
| `nunomaduro/collision` | `v7.12.0` | `v8.9.5` | `^8.9` | Upgraded to v8.9.5. Supports illuminate 11 through 13. |
| `barryvdh/laravel-debugbar` | `v3.16.2` | `v4.4.5` | `^4.0` | Upgraded to v4 for illuminate 13 support. |
| `spatie/laravel-ignition` | `2.9.1` | `2.13.0` | `^2.4` | Upgraded to 2.13.0 for illuminate 13 error pages. |
| `fakerphp/faker` | `^1.9.1` | `v1.24.1` | `^1.9.1` | Compatible across all versions. |
| `mockery/mockery` | `^1.4.4` | `1.6.12` | `^1.4.4` | Compatible across all versions. |

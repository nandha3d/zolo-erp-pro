# Production deployment (VPS)

Every push to `main` runs the acceptance suites
([`company-foundation.yml`](../.github/workflows/company-foundation.yml)). When they pass,
[`deploy.yml`](../.github/workflows/deploy.yml) deploys that commit to **https://erp.samuraibuiness.in**.
A failed suite blocks the deploy.

## How a deploy runs

1. GitHub Actions connects over SSH as `zoloerp` to the Hostinger VPS (`187.127.177.121`).
2. The server checkout (`/var/www/zolo-erp`) fetches from GitHub with its read-only deploy key and moves to the pushed commit.
3. [`deploy.sh`](deploy.sh) runs `composer install --no-dev`, puts the site in maintenance mode, runs `migrate --force`, rebuilds the view cache, reloads PHP-FPM and brings the site back up.
4. The workflow checks that `/login` answers 200.

Roll back: **Actions → Deploy to VPS → Run workflow**, and enter an older commit SHA as `ref`.
Migrations are additive, so an older release keeps running against the newer schema.

## GitHub secrets

| Secret | Value |
|---|---|
| `VPS_HOST` | `187.127.177.121` |
| `VPS_PORT` | `22` |
| `VPS_USER` | `zoloerp` |
| `VPS_SSH_KEY` | Private key whose public half is in `/home/zoloerp/.ssh/authorized_keys` |
| `VPS_KNOWN_HOSTS` | Output of `ssh-keyscan 187.127.177.121` |

## Server layout

| Item | Location |
|---|---|
| App | `/var/www/zolo-erp` (owned by `zoloerp`), `.env` is server-only (mode 600) |
| PHP | PHP 8.3-FPM pool `zolo-erp` running as `zoloerp`, socket `/run/php/zolo-erp.sock` |
| Web | Nginx `/etc/nginx/sites-available/zolo-erp.conf` (copy of [`nginx/erp.samuraibuiness.in.conf`](nginx/erp.samuraibuiness.in.conf)), certbot certificate |
| Database | System MySQL 8.0, database and user `zolo_erp`, connected through the local socket |
| Scheduler | `/etc/cron.d/zolo-erp` runs `artisan schedule:run` every minute |
| Sudo | `zoloerp` may only run `systemctl reload php8.3-fpm` (`/etc/sudoers.d/zolo-erp`) |
| Logs | `storage/logs/laravel-*.log`, `/var/log/nginx/zolo-erp.*.log` |

The same VPS also runs the loan app (`loan.samuraibuiness.in`, `zolofunds.com`, Node on port 3000)
and a trading platform. Do not change their Nginx sites, the `loanapp` database or their services.

## Manual commands

```bash
ssh root@187.127.177.121
sudo -u zoloerp -i
cd /var/www/zolo-erp
php8.3 artisan migrate:status
php8.3 artisan erp:health --company=1 --branch=1 --year=1 --actor=1
```

Always call `php8.3`: the server's default `php` is a separate LiteSpeed PHP 8.4 build.
Do not run `config:cache`; controllers and middleware read `env()` at runtime.

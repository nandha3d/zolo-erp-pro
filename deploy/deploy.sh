#!/usr/bin/env bash
# Run as the app user on the ERP VPS. Keep the live checkout up until staging succeeds.
set -euo pipefail

operation="${1:-}"
ref="${2:-}"
php_version="${3:-}"
current_php_version="${4:-}"
case "$operation" in preflight|deploy|rollback) ;; *) exit 2 ;; esac
[[ "$ref" =~ ^[0-9a-f]{40}$ ]] || exit 2
[[ "$php_version" =~ ^8\.[345]$ && "$current_php_version" =~ ^8\.[345]$ ]] || exit 2
[[ "$operation" != deploy || "$php_version" == 8.5 ]] || exit 2

cd /var/www/zolo-erp
php="php$php_version"
current_php="php$current_php_version"
fpm="php$php_version-fpm"
socket=/run/php/zolo-erp.sock
if [[ "$php_version" == 8.5 ]]; then
    socket=/run/php/zolo-erp-php85.sock
fi
composer=/usr/local/bin/composer
previous=$(git rev-parse HEAD)
echo "Live checkout is $previous. Requested $operation of $ref on PHP $php_version."

git fetch --prune origin
git cat-file -e "$ref^{commit}"
if [[ "$operation" != preflight ]]; then
    git merge-base --is-ancestor "$ref" origin/main
fi

# Keep the stage directory private and on the same filesystem as the live vendor tree.
stage=$(mktemp -d "$PWD/.deploy.XXXXXX")
trap 'rm -rf -- "$stage"' EXIT
git archive "$ref" | tar -x -C "$stage"

echo "Current VPS runtime"
mysql --version
systemctl is-active "php$current_php_version-fpm" || true
"$current_php" -v | head -n 1
if [[ "$operation" == preflight ]]; then
    "$current_php" artisan tinker --execute='echo "Database server: ".DB::selectOne("select version() as version")->version.PHP_EOL;'
    if grep -Eq "php$php_version[[:space:]]+.*artisan[[:space:]]+schedule:run" /etc/cron.d/zolo-erp; then
        echo "Scheduler references PHP $php_version."
    else
        echo "Verify the scheduler cutover to PHP $php_version before enabling it."
    fi
fi

echo "Target VPS runtime"
"$php" -v | head -n 1
"$php" -r 'exit(extension_loaded("pdo_mysql") ? 0 : 1);' || { echo "Missing PHP pdo_mysql."; exit 1; }
"$php" "$composer" --working-dir="$stage" check-platform-reqs --lock --no-dev
"$php" "$composer" --working-dir="$stage" install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress --no-scripts
systemctl is-active "$fpm"
sudo -n -l /usr/bin/systemctl reload "$fpm" >/dev/null
test -S "$socket"
if [[ "$php_version" == 8.5 ]]; then
    cat > "$stage/fpm-check.php" <<'PHP'
<?php
foreach (['pdo_mysql', 'zip', 'gd', 'intl', 'mbstring', 'curl', 'bcmath', 'dom'] as $extension) {
    if (!extension_loaded($extension)) {
        exit;
    }
}
echo 'ERP-FPM ' . PHP_VERSION . ' ' . PHP_SAPI;
PHP
    cli_version=$("$php" -r 'echo PHP_VERSION;')
    fpm_result=$(SCRIPT_FILENAME="$stage/fpm-check.php" REQUEST_METHOD=GET cgi-fcgi -bind -connect "$socket")
    grep -Fx "ERP-FPM $cli_version fpm-fcgi" <<< "$fpm_result"
fi
if [[ "$operation" == preflight ]]; then
    echo "Target install, FPM service, socket and reload permission checks passed."
    exit 0
fi

# The operator switches the ERP vhost and pauses its scheduler in the release window.
grep -Fq "fastcgi_pass unix:$socket;" /etc/nginx/sites-enabled/zolo-erp.conf

# Preserve maintenance mode across failures, including after a partial migration.
# A rollback uses the same sequence and deliberately skips migrations.
if [[ ! -f storage/framework/down ]]; then
    "$current_php" artisan down --retry=15
fi
test -f storage/framework/down
cleanup_release() {
    local status=$?
    if (( status )); then
        if [[ -n "${smoke_secret:-}" ]]; then
            "$php" artisan down --retry=15 >/dev/null || true
        fi
        echo "Release failed. Keep maintenance active and recover from $previous."
    fi
    rm -rf -- "$stage"
}
trap cleanup_release EXIT
git checkout --force --detach "$ref"
if [[ -d vendor ]]; then
    mv vendor "$stage/previous-vendor"
fi
mv "$stage/vendor" vendor
"$php" "$composer" dump-autoload --no-dev --optimize --no-interaction
# Runtime env() reads require uncached configuration.
"$php" artisan optimize:clear
if [[ "$operation" == deploy ]]; then
    "$php" artisan migrate --force
    "$php" artisan migrate:status --pending=1
    "$php" artisan migrate:status --pending=1 --path=Modules/Manufacturing/Database/Migrations
fi
"$php" artisan view:cache
sudo -n /usr/bin/systemctl reload "$fpm"

# Prove Nginx and the selected FPM pool can serve the candidate before reopening traffic.
host=erp.samuraibuiness.in
local_curl=(curl --fail --silent --show-error --max-time 15 --noproxy '*' --resolve "$host:443:127.0.0.1")
smoke_secret=$("$php" -r 'echo bin2hex(random_bytes(32));')
"$php" artisan down --retry=15 --secret="$smoke_secret" >/dev/null
"${local_curl[@]}" --cookie-jar "$stage/cookies" --output /dev/null "https://$host/$smoke_secret"
"${local_curl[@]}" --cookie "$stage/cookies" --output "$stage/login.html" "https://$host/login"
grep -Fq 'id="login-form"' "$stage/login.html"
"$php" artisan up
echo "Released $(git rev-parse --short HEAD)"

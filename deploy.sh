#!/usr/bin/env bash
# Updates the live site to the latest code on GitHub.
# Run on the server, from the project folder:   bash deploy.sh
set -euo pipefail
cd "$(dirname "$0")"

# Hostinger ships Composer 2 as "composer2" on some servers.
COMPOSER="$(command -v composer2 || command -v composer)"

php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' || {
    echo "PHP 8.2+ required on the command line (found $(php -r 'echo PHP_VERSION;'))."
    echo "Set it in hPanel > Advanced > PHP Configuration, then reconnect SSH."
    exit 1
}

php artisan down --retry=15 || true
# Always bring the site back up, even if a step below fails.
trap 'php artisan up' EXIT

git pull --ff-only origin main
# --no-scripts: Composer's post-install hooks need proc_open(), which shared
# hosting disables. package:discover is the one hook that matters; run it directly.
"$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --no-scripts
php artisan package:discover
# PHP's symlink() is disabled too, so `artisan storage:link` can't be used.
[ -e public/storage ] || ln -s ../storage/app/public public/storage
php artisan migrate --force
php artisan optimize

echo
php artisan app:doctor || true

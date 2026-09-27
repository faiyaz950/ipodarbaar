#!/usr/bin/env bash
#
# Deploy/update IPO Darbaar on BigRock shared hosting (run over SSH from the app folder):
#
#   bash deploy/bigrock/deploy.sh
#
# Optional environment variables:
#   PHP_BIN      PHP 8.3+ CLI binary (default: php). Example: /opt/cpanel/ea-php83/root/usr/bin/php
#   PUBLIC_HTML  Only if public_html is NOT a symlink to this app's public/ folder: the folder to copy
#                public assets into (see DEPLOY-BIGROCK.md, "Option B").
#
set -euo pipefail

PHP="${PHP_BIN:-php}"
APP_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$APP_DIR"

if ! "$PHP" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'; then
    echo "✗ $PHP is $("$PHP" -r 'echo PHP_VERSION;'); PHP 8.3+ is required. Set PHP_BIN to the 8.3 CLI binary." >&2
    exit 1
fi

if command -v composer >/dev/null 2>&1; then
    COMPOSER=(composer)
elif [ -f composer.phar ]; then
    COMPOSER=("$PHP" composer.phar)
else
    echo "✗ Composer not found. Install it once: https://getcomposer.org/download/ (save composer.phar in $APP_DIR)." >&2
    exit 1
fi

echo "→ Pulling latest code"
git pull --ff-only

echo "→ Maintenance mode on"
"$PHP" artisan down --retry=15 || true
trap '"$PHP" artisan up >/dev/null 2>&1 || true' EXIT

echo "→ Installing PHP dependencies"
"${COMPOSER[@]}" install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "→ Running migrations"
"$PHP" artisan migrate --force

if [ -n "${PUBLIC_HTML:-}" ]; then
    echo "→ Copying public assets to $PUBLIC_HTML"
    mkdir -p "$PUBLIC_HTML"
    rsync -a --delete-after \
        --exclude 'index.php' --exclude 'logos/' --exclude 'og/' --exclude 'uploads/' --exclude '.well-known/' --exclude 'cgi-bin/' \
        public/ "$PUBLIC_HTML/"
fi

echo "→ Rebuilding caches"
"$PHP" artisan config:clear
"$PHP" artisan optimize

echo "✓ Deployed $(git rev-parse --short HEAD)"

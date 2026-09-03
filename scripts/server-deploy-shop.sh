#!/bin/bash
# Deploy PHP shop from GitHub main → /var/www/mcp (talkaipilot.com)
set -euo pipefail

APP_DIR=/var/www/mcp
BRANCH=main
REMOTE=origin

cd "$APP_DIR"

git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true

# Keep live secrets / uploads across reset
preserve=(
  application/config/database.php
)

tmpdir=$(mktemp -d)
for f in "${preserve[@]}"; do
  if [[ -f "$APP_DIR/$f" ]]; then
    mkdir -p "$tmpdir/$(dirname "$f")"
    cp -a "$APP_DIR/$f" "$tmpdir/$f"
  fi
done

git fetch --prune "$REMOTE" "$BRANCH"
git checkout -B "$BRANCH" "$REMOTE/$BRANCH"
git reset --hard "$REMOTE/$BRANCH"
git clean -fd -e application/config/database.php -e uploads -e assets/uploads -e application/cache -e application/logs

for f in "${preserve[@]}"; do
  if [[ -f "$tmpdir/$f" ]]; then
    mkdir -p "$(dirname "$APP_DIR/$f")"
    cp -a "$tmpdir/$f" "$APP_DIR/$f"
  fi
done
rm -rf "$tmpdir"

mkdir -p application/cache application/logs uploads assets/uploads
chown -R www-data:www-data "$APP_DIR"
chown -R root:root "$APP_DIR/.git"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;
chmod -R 775 application/cache application/logs uploads assets/uploads 2>/dev/null || true
chmod 640 application/config/database.php 2>/dev/null || true
chown www-data:www-data application/config/database.php 2>/dev/null || true
chmod -R u+rwX "$APP_DIR/.git"

# Clear CodeIgniter file cache (keep index.html)
find application/cache -type f ! -name 'index.html' ! -name '.htaccess' -delete 2>/dev/null || true

systemctl reload php8.5-fpm 2>/dev/null || systemctl reload php-fpm 2>/dev/null || true
nginx -t && systemctl reload nginx

echo "OK shop deploy $(git rev-parse --short HEAD) → https://talkaipilot.com/"

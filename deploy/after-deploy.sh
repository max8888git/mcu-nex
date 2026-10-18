#!/usr/bin/env bash
# Runs on the server after every upload (from .github/workflows/deploy.yml).
set -euo pipefail
cd /var/www/mcu-nex

if [ ! -f .env ]; then
  echo "No .env on the server yet. Create it from deploy/env.production.example (docs/deploy-azure.md, step 9)." >&2
  exit 1
fi

# the folders Laravel writes to (kept between deploys; never uploaded)
mkdir -p storage/{app/public,app/private,framework/cache/data,framework/sessions,framework/views,logs} bootstrap/cache
chmod -R g+w storage bootstrap/cache 2> /dev/null || true

php artisan config:cache
php artisan view:cache
sudo systemctl reload php8.4-fpm
echo "Deployed $(date '+%Y-%m-%d %H:%M')."

#!/usr/bin/env bash
set -euo pipefail

DB_NAME="${DB_NAME:-artisan_den_laravel}"
DB_OWNER="${DB_OWNER:-artisan_user}"

echo "Provisioning PostgreSQL database '${DB_NAME}' owned by '${DB_OWNER}'..."

sudo -u postgres psql -v ON_ERROR_STOP=1 -tAc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" | grep -q 1 || \
  sudo -u postgres createdb -O "${DB_OWNER}" "${DB_NAME}"

echo "Done. Database '${DB_NAME}' is ready."
echo "Next:"
echo "  1) Update laravel-app/.env DB_DATABASE=${DB_NAME}"
echo "  2) Run: cd laravel-app && php artisan migrate"


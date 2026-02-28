#!/usr/bin/env bash
set -euo pipefail

echo "=== Artisan Den Ubuntu Service Setup ==="

if [[ "${EUID}" -eq 0 ]]; then
  echo "Please run this script as your normal user (not root)."
  exit 1
fi

if ! command -v apt-get >/dev/null 2>&1; then
  echo "This script currently supports Ubuntu/Debian systems with apt-get."
  exit 1
fi

DB_NAME="${DB_NAME:-artisan_den}"
DB_USER="${DB_USER:-artisan_user}"
DB_PASS="${DB_PASS:-artisan_pass_123}"
IMPORT_LEGACY_SCHEMA="${IMPORT_LEGACY_SCHEMA:-0}"

echo "[1/5] Installing system packages..."
sudo apt-get update
sudo apt-get install -y \
  ca-certificates \
  curl \
  unzip \
  git \
  postgresql \
  postgresql-client \
  postgresql-contrib \
  php8.3-cli \
  php8.3-common \
  php8.3-mbstring \
  php8.3-xml \
  php8.3-curl \
  php8.3-zip \
  php8.3-pgsql \
  php8.3-bcmath \
  composer \
  nodejs \
  npm \
  redis-server

echo "[2/5] Starting/enabling services..."
sudo systemctl enable --now postgresql
sudo systemctl enable --now redis-server

echo "[3/5] Creating/updating PostgreSQL role and database..."
sudo -u postgres psql -v ON_ERROR_STOP=1 <<SQL
DO \$\$
BEGIN
   IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '${DB_USER}') THEN
      CREATE ROLE ${DB_USER} LOGIN PASSWORD '${DB_PASS}';
   ELSE
      ALTER ROLE ${DB_USER} WITH LOGIN PASSWORD '${DB_PASS}';
   END IF;
END
\$\$;
SQL

db_exists="$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" | tr -d '[:space:]')"
if [[ "${db_exists}" != "1" ]]; then
  sudo -u postgres createdb -O "${DB_USER}" "${DB_NAME}"
fi

sudo -u postgres psql -d "${DB_NAME}" -v ON_ERROR_STOP=1 <<SQL
GRANT ALL ON SCHEMA public TO ${DB_USER};
ALTER SCHEMA public OWNER TO ${DB_USER};
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO ${DB_USER};
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO ${DB_USER};
SQL

if [[ "${IMPORT_LEGACY_SCHEMA}" == "1" && -f "database/schema-postgresql.sql" ]]; then
  echo "[4/5] Importing legacy schema (database/schema-postgresql.sql)..."
  PGPASSWORD="${DB_PASS}" psql -h localhost -U "${DB_USER}" -d "${DB_NAME}" -f "database/schema-postgresql.sql"
else
  echo "[4/5] Skipping legacy schema import (set IMPORT_LEGACY_SCHEMA=1 to enable)."
fi

echo "[5/5] Verifying versions..."
php -v | sed -n '1,1p'
composer --version
psql --version
node --version
npm --version
redis-server --version | sed -n '1,1p'

cat <<EOF

=== Setup complete ===

Database:
  DB_NAME=${DB_NAME}
  DB_USER=${DB_USER}
  DB_PASS=${DB_PASS}

Next steps:
1) Run: php test-environment.php
2) Run: php test-connection.php
3) For Laravel later: composer create-project laravel/laravel laravel-app

Tip:
- To import the current legacy schema during setup:
  IMPORT_LEGACY_SCHEMA=1 ./scripts/setup-services-ubuntu.sh
EOF


#!/bin/bash
set -e
echo '=== Artisan Den Database Setup (PostgreSQL) ==='
sudo service postgresql start 2>/dev/null || true
sleep 2
DB_NAME='artisan_den'
DB_USER='artisan_user'
DB_PASS='artisan_pass_123'
echo 'Creating database and user...'
sudo -u postgres psql <<PSQL
CREATE DATABASE ;
CREATE USER  WITH PASSWORD '';
GRANT ALL PRIVILEGES ON DATABASE  TO ;
\q
PSQL
echo 'Granting schema privileges...'
sudo -u postgres psql -d  <<PSQL
GRANT ALL ON SCHEMA public TO ;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO ;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO ;
\q
PSQL
if [ -f database/schema-postgresql.sql ]; then
    echo 'Importing schema...'
    PGPASSWORD= psql -h localhost -U  -d  -f database/schema-postgresql.sql
    echo 'Schema imported!'
fi
echo ''
echo '=== Setup Complete! ==='
echo 

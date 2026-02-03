#!/bin/bash
# Quick local setup script
set -e
echo '=== Artisan Den Local Setup ==='
if ! command -v mysql &> /dev/null; then
    echo 'Installing MySQL...'
    sudo apt-get update && sudo apt-get install -y mysql-server
fi
if ! command -v php &> /dev/null; then
    echo 'Installing PHP...'
    sudo apt-get install -y php php-mysql php-mbstring
fi
sudo service mysql start 2>/dev/null || true
sleep 2
DB_NAME='artisan_den'
DB_USER='artisan_user'
DB_PASS='artisan_pass_123'
sudo mysql <<EOF
CREATE DATABASE IF NOT EXISTS ;
CREATE USER IF NOT EXISTS ''@'localhost' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON .* TO ''@'localhost';
FLUSH PRIVILEGES;
EOF
echo 'Setup complete! Run: php -S localhost:8000'

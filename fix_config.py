content = '''<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
define('DB_HOST', 'localhost');
define('DB_NAME', 'artisan_den');
define('DB_USER', 'artisan_user');
define('DB_PASS', 'artisan_pass_123');
define('DB_CHARSET', 'utf8mb4');
define('APP_NAME', 'Artisan Den');
define('TIMEZONE', 'America/Chicago');
date_default_timezone_set(TIMEZONE);
error_reporting(E_ALL);
ini_set('display_errors', 1);
function getDB() {
    static  = null;
    if ( === null) {
        try {
             = " pgsql:host=\

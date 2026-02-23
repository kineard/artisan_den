#!/usr/bin/env php
<?php
/**
 * Environment check for Artisan Den (WSL / Ubuntu / PostgreSQL).
 * Run in both dev and server, then diff the output to ensure parity.
 *
 * Usage:
 *   php check-environment.php           # Human-readable report
 *   php check-environment.php --summary # One-line fingerprint (for diff)
 *   php check-environment.php --json    # JSON (for diff or CI)
 */

$summaryOnly = in_array('--summary', $argv ?? [], true);
$jsonOnly   = in_array('--json', $argv ?? [], true);

$report = [
    'env'        => [],
    'php'        => [],
    'extensions' => [],
    'database'   => [],
    'files'      => [],
    'errors'     => [],
];

// --- 1. OS / WSL / Ubuntu ---
$report['env']['os'] = PHP_OS_FAMILY; // Linux, Windows, etc.
if (is_readable('/proc/version')) {
    $report['env']['proc_version'] = trim(file_get_contents('/proc/version'));
    $report['env']['is_wsl'] = (stripos($report['env']['proc_version'], 'microsoft') !== false || stripos($report['env']['proc_version'], 'WSL') !== false);
} else {
    $report['env']['proc_version'] = null;
    $report['env']['is_wsl'] = null;
}
if (function_exists('php_uname')) {
    $report['env']['uname'] = php_uname('a');
}

// --- 2. PHP ---
$report['php']['version'] = phpversion();
$report['php']['sapi']   = php_sapi_name();
$report['php']['min_required'] = '8.1.0';
$report['php']['ok'] = version_compare($report['php']['version'], $report['php']['min_required'], '>=');
if (!$report['php']['ok']) {
    $report['errors'][] = "PHP {$report['php']['version']} is below required {$report['php']['min_required']}";
}

// --- 3. Extensions (required for this stack) ---
$required = ['pdo', 'pdo_pgsql', 'json', 'mbstring', 'session'];
$optional = ['curl', 'openssl'];
foreach ($required as $ext) {
    $loaded = extension_loaded($ext);
    $report['extensions'][$ext] = $loaded ? 'loaded' : 'MISSING';
    if (!$loaded) {
        $report['errors'][] = "Missing required extension: $ext";
    }
}
foreach ($optional as $ext) {
    $report['extensions'][$ext] = extension_loaded($ext) ? 'loaded' : 'not loaded';
}

// --- 4. Paths (for comparing dev vs server) ---
$report['paths']['script'] = __FILE__;
$report['paths']['cwd']    = getcwd();
if (isset($_SERVER['DOCUMENT_ROOT'])) {
    $report['paths']['document_root'] = $_SERVER['DOCUMENT_ROOT'];
} else {
    $report['paths']['document_root'] = '(cli)';
}

// --- 5. Config and DB ---
$report['database']['config_exists'] = file_exists(__DIR__ . '/config.php');
if (!$report['database']['config_exists']) {
    $report['errors'][] = 'config.php not found';
}

if ($report['database']['config_exists']) {
    try {
        require_once __DIR__ . '/config.php';
        $report['database']['host'] = defined('DB_HOST') ? DB_HOST : '(not set)';
        $report['database']['name'] = defined('DB_NAME') ? DB_NAME : '(not set)';

        $pdo = getDB();
        $report['database']['connected'] = true;

        // PostgreSQL server version
        $stmt = $pdo->query('SELECT version()');
        $report['database']['pg_version_full'] = $stmt ? $stmt->fetchColumn() : null;
        if ($report['database']['pg_version_full']) {
            if (preg_match('/PostgreSQL (\d+\.\d+)/', $report['database']['pg_version_full'], $m)) {
                $report['database']['pg_version'] = $m[1];
            }
        }

        // Expected tables (core + inventory)
        $expectedTables = ['stores', 'daily_kpis', 'users', 'products', 'vendors', 'inventory', 'orders'];
        $report['database']['tables'] = [];
        foreach ($expectedTables as $table) {
            $stmt = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = " . $pdo->quote($table));
            $report['database']['tables'][$table] = $stmt && $stmt->fetch();
        }
    } catch (Throwable $e) {
        $report['database']['connected'] = false;
        $report['database']['error'] = $e->getMessage();
        $report['errors'][] = 'Database: ' . $e->getMessage();
    }
}

// --- 6. Key files ---
$requiredFiles = ['config.php', 'index.php', 'includes/helpers.php', 'includes/functions.php', 'includes/inventory-functions.php', 'includes/post-handlers.php'];
$report['files'] = [];
foreach ($requiredFiles as $f) {
    $path = __DIR__ . '/' . $f;
    $report['files'][$f] = file_exists($path);
    if (!file_exists($path)) {
        $report['errors'][] = "Missing file: $f";
    }
}

// --- Output ---
if ($jsonOnly) {
    header('Content-Type: application/json');
    echo json_encode($report, JSON_PRETTY_PRINT) . "\n";
    exit(count($report['errors']) > 0 ? 1 : 0);
}

if ($summaryOnly) {
    $parts = [
        'PHP=' . $report['php']['version'],
        'OS=' . $report['env']['os'],
        'WSL=' . ($report['env']['is_wsl'] ? 'yes' : 'no'),
    ];
    if (!empty($report['database']['pg_version'])) {
        $parts[] = 'PG=' . $report['database']['pg_version'];
    }
    if (!empty($report['database']['connected'])) {
        $parts[] = 'DB=ok';
    } else {
        $parts[] = 'DB=fail';
    }
    $parts[] = 'ext=' . implode(',', array_keys(array_filter($report['extensions'], function ($v) { return $v === 'loaded'; })));
    echo implode(' | ', $parts) . "\n";
    exit(count($report['errors']) > 0 ? 1 : 0);
}

// Human-readable report
echo "==========================================\n";
echo "  Artisan Den – Environment Check\n";
echo "  (WSL / Ubuntu / PostgreSQL)\n";
echo "==========================================\n\n";

echo "--- Environment ---\n";
echo "  OS:        " . $report['env']['os'] . "\n";
echo "  WSL:       " . ($report['env']['is_wsl'] === true ? 'yes' : ($report['env']['is_wsl'] === false ? 'no' : 'unknown')) . "\n";
if (!empty($report['env']['uname'])) {
    echo "  uname:     " . $report['env']['uname'] . "\n";
}
echo "\n";

echo "--- PHP ---\n";
echo "  Version:  " . $report['php']['version'] . " (min " . $report['php']['min_required'] . ")\n";
echo "  SAPI:     " . $report['php']['sapi'] . "\n";
echo "  Status:   " . ($report['php']['ok'] ? "OK" : "FAIL") . "\n\n";

echo "--- Extensions ---\n";
foreach ($report['extensions'] as $ext => $status) {
    $mark = ($status === 'loaded') ? '✓' : (($status === 'MISSING') ? '✗' : '○');
    echo "  $mark $ext: $status\n";
}
echo "\n";

echo "--- Paths ---\n";
echo "  Script:   " . $report['paths']['script'] . "\n";
echo "  CWD:      " . $report['paths']['cwd'] . "\n";
echo "  Doc root: " . $report['paths']['document_root'] . "\n\n";

echo "--- Database ---\n";
echo "  Config:   " . ($report['database']['config_exists'] ? 'found' : 'MISSING') . "\n";
if (!empty($report['database']['connected'])) {
    echo "  Connect:  OK\n";
    echo "  Host:    " . ($report['database']['host'] ?? '') . "\n";
    echo "  DB name: " . ($report['database']['name'] ?? '') . "\n";
    if (!empty($report['database']['pg_version'])) {
        echo "  PostgreSQL: " . $report['database']['pg_version'] . "\n";
    }
    echo "  Tables:\n";
    foreach ($report['database']['tables'] ?? [] as $t => $exists) {
        echo "    " . ($exists ? '✓' : '✗') . " $t\n";
    }
} else {
    echo "  Connect:  FAIL\n";
    if (!empty($report['database']['error'])) {
        echo "  Error:    " . $report['database']['error'] . "\n";
    }
}
echo "\n";

echo "--- Key files ---\n";
foreach ($report['files'] as $f => $exists) {
    echo "  " . ($exists ? '✓' : '✗') . " $f\n";
}
echo "\n";

if (count($report['errors']) > 0) {
    echo "--- Errors ---\n";
    foreach ($report['errors'] as $e) {
        echo "  ✗ $e\n";
    }
    echo "\n❌ Check FAILED (" . count($report['errors']) . " error(s))\n";
    exit(1);
}

echo "✅ Environment check PASSED\n";
echo "\nTo compare dev vs server, run this script in both and diff the output,\n";
echo "or use: php check-environment.php --summary  (or --json)\n";
exit(0);

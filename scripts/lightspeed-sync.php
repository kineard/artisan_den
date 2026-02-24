<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/inventory-functions.php';
require_once __DIR__ . '/../includes/integrations/lightspeedx-sync.php';

function cliArgValue($name, $default = null) {
    global $argv;
    $prefix = '--' . $name . '=';
    foreach ((array)$argv as $arg) {
        $arg = (string)$arg;
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

function cliHasFlag($name) {
    global $argv;
    return in_array('--' . $name, (array)$argv, true);
}

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must run in CLI.\n");
    exit(1);
}

$entity = strtolower(trim((string)cliArgValue('entity', 'all')));
$mode = strtolower(trim((string)cliArgValue('mode', 'manual')));
$limit = (int)cliArgValue('limit', 200);
$maxPages = (int)cliArgValue('max-pages', 50);
$startedBy = (string)cliArgValue('started-by', 'cli');

if (cliHasFlag('help') || cliHasFlag('h')) {
    echo "Usage:\n";
    echo "  php scripts/lightspeed-sync.php --entity=all|categories|products|suppliers|outlets|inventory [--mode=manual|incremental|full] [--limit=200] [--max-pages=50] [--started-by=cli] [--outlet-id=<id>]\n";
    exit(0);
}

if (!in_array($entity, ['all', 'categories', 'products', 'suppliers', 'outlets', 'inventory'], true)) {
    fwrite(STDERR, "Invalid --entity. Use all|categories|products|suppliers|outlets|inventory.\n");
    exit(2);
}
if (!in_array($mode, ['manual', 'incremental', 'full'], true)) {
    fwrite(STDERR, "Invalid --mode. Use manual|incremental|full.\n");
    exit(2);
}

$cfgCheck = lightspeedxValidateConfig();
if (empty($cfgCheck['ok'])) {
    fwrite(STDERR, "Config error: " . (string)$cfgCheck['message'] . PHP_EOL);
    exit(2);
}

$options = [
    'mode' => $mode,
    'limit' => $limit > 0 ? $limit : 200,
    'max_pages' => $maxPages > 0 ? $maxPages : 50,
    'started_by' => $startedBy !== '' ? $startedBy : 'cli',
    'outlet_id' => (string)cliArgValue('outlet-id', ''),
];

if ($entity === 'categories') {
    $result = lightspeedxSyncCategories($options);
} elseif ($entity === 'products') {
    $result = lightspeedxSyncProducts($options);
} elseif ($entity === 'suppliers') {
    $result = lightspeedxSyncSuppliers($options);
} elseif ($entity === 'outlets') {
    $result = lightspeedxSyncOutlets($options);
} elseif ($entity === 'inventory') {
    $result = lightspeedxSyncInventory($options);
} else {
    $result = lightspeedxSyncAll($options);
}

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
exit(!empty($result['success']) ? 0 : 1);

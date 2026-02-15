<?php
/**
 * Serve main.js and (if present) chart.umd.min.js. No config/session/includes.
 * When chart is requested but file missing, redirect to CDN so browser never parses 404 as JS.
 */
ob_start();
$f = isset($_GET['f']) ? $_GET['f'] : '';
$allowed = ['js/main.js', 'js/chart.umd.min.js'];
if (!in_array($f, $allowed, true)) {
    ob_end_clean();
    header('HTTP/1.0 404 Not Found');
    exit;
}
$path = __DIR__ . '/' . $f;
if (!is_file($path)) {
    ob_end_clean();
    if ($f === 'js/chart.umd.min.js') {
        header('Location: https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', true, 302);
        exit;
    }
    header('HTTP/1.0 404 Not Found');
    exit;
}
ob_end_clean();
header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
echo "/* serve-asset " . basename($f) . " */\n";
readfile($path);
exit;

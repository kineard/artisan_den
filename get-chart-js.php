<?php
/**
 * One-time script: download Chart.js 4.4.0 to js/chart.umd.min.js
 * Run from CLI: php get-chart-js.php
 * Or visit in browser once: get-chart-js.php
 */
$url = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
$out = __DIR__ . '/js/chart.umd.min.js';
$js = @file_get_contents($url);
if ($js === false || strlen($js) < 1000) {
    echo "Download failed. Create js/chart.umd.min.js manually (e.g. curl -o js/chart.umd.min.js " . $url . ")\n";
    exit(1);
}
if (!is_dir(__DIR__ . '/js')) mkdir(__DIR__ . '/js', 0755, true);
file_put_contents($out, $js);
echo "Saved " . strlen($js) . " bytes to js/chart.umd.min.js\n";
exit(0);

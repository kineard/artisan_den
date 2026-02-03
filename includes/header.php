<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - KPI Dashboard</title>
    <link rel="stylesheet" href="css/style.css?<?php echo file_exists(__DIR__ . '/../css/style.css') ? filemtime(__DIR__ . '/../css/style.css') : '1'; ?>">
    <style>
        /* Critical: ensure modal and chart text + all inputs are always visible (black on white) */
        .modal label, .modal input, .modal select, .modal textarea, .modal small { color: #000 !important; }
        #inventory-chart .chart-header, #inventory-chart .chart-header *, #inventory-chart .chart-legend-controls, #inventory-chart .chart-legend-controls * { color: #000 !important; }
        #inventory-chart .btn-small:not(.btn-primary) { color: #000 !important; }
        #inventory-chart .chart-legend-controls > a.btn-small.btn-primary { color: #000 !important; }
        input, select, textarea { color: #000 !important; background-color: #fff !important; }
        .inventory-tip-box, .inventory-tip-box * { color: #000 !important; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="container">

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1d4ed8">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title><?php echo APP_NAME; ?> - KPI Dashboard</title>
    <script>
        (function () {
            try {
                if (localStorage.getItem('ui_high_contrast_enabled') === '1') {
                    document.documentElement.classList.add('high-contrast');
                }
                if (localStorage.getItem('ui_density_compact_enabled') === '1') {
                    document.documentElement.classList.add('density-compact');
                }
            } catch (e) {}
        })();
    </script>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' fill='%232298d8'/><text x='16' y='22' font-size='18' text-anchor='middle' fill='white' font-family='sans-serif'>K</text></svg>" type="image/svg+xml">
    <link rel="manifest" href="manifest.webmanifest">
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
    <?php $chartLocal = __DIR__ . '/../js/chart.umd.min.js'; ?>
    <script src="<?php echo is_file($chartLocal) ? 'serve-asset.php?f=js/chart.umd.min.js&amp;v=' . (int)($chartVersion ?? 0) : 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'; ?>"></script>
    <script src="serve-asset.php?f=js/main.js&amp;v=<?php echo (int)($scriptVersion ?? 0); ?>"></script>
    <script>
        (function () {
            if (!('serviceWorker' in navigator)) return;
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('service-worker.js').catch(function () {});
            });
        })();
    </script>
</head>
<body class="<?php echo !empty($isKioskMode) ? 'timeclock-kiosk' : ''; ?>">
    <?php
    $currentAction = $action ?? 'dashboard';
    $currentStoreId = isset($storeId) ? (int)$storeId : 0;
    $currentDate = isset($date) ? (string)$date : date('Y-m-d');
    $sidebarTab = isset($dashboardTab) ? (string)$dashboardTab : 'kpi';
    if (!in_array($sidebarTab, ['kpi', 'inventory'], true)) {
        $sidebarTab = 'kpi';
    }
    $sidebarView = isset($view) ? (string)$view : 'week';
    $sidebarDaysParam = ($sidebarView === 'custom' && !empty($customDays)) ? '&days=' . (int)$customDays : '';
    $sidebarKpiMode = isset($kpiMode) ? (string)$kpiMode : 'view';
    if (!in_array($sidebarKpiMode, ['view', 'edit'], true)) {
        $sidebarKpiMode = 'view';
    }
    $roleForNav = isset($currentUserRole) ? (string)$currentUserRole : getCurrentUserRole();
    $canManageNav = isset($canManageTimeclock) ? !empty($canManageTimeclock) : currentUserCan('timeclock_manager');
    $canAdminNav = isset($canAdminTimeclock) ? !empty($canAdminTimeclock) : currentUserCan('timeclock_admin');
    $isTimeclockNavAction = in_array((string)$currentAction, ['timeclock', 'employee_dashboard', 'manager_dashboard', 'admin_dashboard'], true);
    if ($currentAction === 'dashboard') {
        $topbarTitle = ($sidebarTab === 'inventory') ? 'Inventory' : 'KPIs';
    } elseif ($currentAction === 'employee_dashboard') {
        $topbarTitle = 'Employee Dashboard';
    } elseif ($currentAction === 'manager_dashboard') {
        $topbarTitle = 'Manager Operations';
    } elseif ($currentAction === 'admin_dashboard') {
        $topbarTitle = 'Admin Time Clock';
    } else {
        $topbarTitle = ucfirst($currentAction);
    }
    ?>
    <div class="app-shell">
        <button type="button" class="app-sidebar-backdrop" id="app-sidebar-backdrop" aria-label="Close menu"></button>
        <aside class="app-sidebar" aria-label="Primary navigation">
            <div class="app-brand">
                <div class="app-brand-mark">AD</div>
                <div class="app-brand-copy">
                    <strong><?php echo htmlspecialchars(APP_NAME); ?></strong>
                    <span>Admin dashboard</span>
                </div>
            </div>
            <nav class="app-sidebar-nav" id="app-sidebar-nav">
                <a href="?action=dashboard&store=<?php echo $currentStoreId; ?>&date=<?php echo urlencode($currentDate); ?>&view=<?php echo urlencode($sidebarView); ?><?php echo $sidebarDaysParam; ?>&tab=kpi&mode=<?php echo urlencode($sidebarKpiMode); ?>" class="<?php echo ($currentAction === 'dashboard' && $sidebarTab === 'kpi') ? 'active' : ''; ?>"><span class="nav-ico" aria-hidden="true">▣</span>KPIs</a>
                <a href="?action=dashboard&store=<?php echo $currentStoreId; ?>&date=<?php echo urlencode($currentDate); ?>&view=<?php echo urlencode($sidebarView); ?><?php echo $sidebarDaysParam; ?>&tab=inventory&mode=view" class="<?php echo ($currentAction === 'dashboard' && $sidebarTab === 'inventory') ? 'active' : ''; ?>"><span class="nav-ico" aria-hidden="true">◫</span>Inventory</a>
                <a href="?action=employee_dashboard&store=<?php echo $currentStoreId; ?>&date=<?php echo urlencode($currentDate); ?>" class="<?php echo $currentAction === 'employee_dashboard' ? 'active' : ''; ?>"><span class="nav-ico" aria-hidden="true">◔</span>Employee</a>
                <?php if ($canManageNav): ?>
                <a href="?action=manager_dashboard&store=<?php echo $currentStoreId; ?>&date=<?php echo urlencode($currentDate); ?>" class="<?php echo in_array($currentAction, ['manager_dashboard', 'timeclock'], true) ? 'active' : ''; ?>"><span class="nav-ico" aria-hidden="true">◷</span>Manager Ops<?php if (!empty($timeClockNeedsAttention)): ?> <span class="nav-alert-dot" title="Needs attention"></span><?php endif; ?></a>
                <?php endif; ?>
                <?php if ($canAdminNav): ?>
                <a href="?action=admin_dashboard&store=<?php echo $currentStoreId; ?>&date=<?php echo urlencode($currentDate); ?>" class="<?php echo $currentAction === 'admin_dashboard' ? 'active' : ''; ?>"><span class="nav-ico" aria-hidden="true">◉</span>Admin Time</a>
                <?php endif; ?>
                <a href="?action=history&store=<?php echo $currentStoreId; ?>" class="<?php echo $currentAction === 'history' ? 'active' : ''; ?>"><span class="nav-ico" aria-hidden="true">☰</span>History</a>
                <a href="import.php"><span class="nav-ico" aria-hidden="true">⇪</span>Import</a>
            </nav>
            <div class="app-sidebar-footer">
                <span><?php echo htmlspecialchars($currentDate); ?></span>
                <span>Store #<?php echo $currentStoreId > 0 ? $currentStoreId : 0; ?></span>
                <button type="button" class="density-toggle" data-density-toggle="1" aria-pressed="false">Density: Comfortable</button>
                <button type="button" class="contrast-toggle" data-contrast-toggle="1" aria-pressed="false">High Contrast: Off</button>
            </div>
        </aside>
        <main class="app-main">
            <div class="app-topbar">
                <button type="button" id="app-sidebar-toggle" class="app-sidebar-toggle" aria-controls="app-sidebar-nav" aria-expanded="false">Menu</button>
                <div class="app-topbar-title"><?php echo htmlspecialchars($topbarTitle); ?><?php if ($isTimeclockNavAction && !empty($timeClockNeedsAttention)): ?> <span class="app-topbar-badge-alert">Needs Attention</span><?php endif; ?></div>
                <button type="button" class="density-toggle" data-density-toggle="1" aria-pressed="false">Density: Comfortable</button>
                <button type="button" class="contrast-toggle" data-contrast-toggle="1" aria-pressed="false">High Contrast: Off</button>
            </div>
            <div class="app-submenu">
                <?php if ($isTimeclockNavAction): ?>
                    <a href="?action=employee_dashboard&store=<?php echo $currentStoreId; ?>&date=<?php echo urlencode($currentDate); ?>" class="app-submenu-item <?php echo $currentAction === 'employee_dashboard' ? 'active' : ''; ?>">Employee Dashboard</a>
                    <?php if ($canManageNav): ?>
                    <a href="?action=manager_dashboard&store=<?php echo $currentStoreId; ?>&date=<?php echo urlencode($currentDate); ?>" class="app-submenu-item <?php echo in_array($currentAction, ['manager_dashboard', 'timeclock'], true) ? 'active' : ''; ?>">Manager Ops</a>
                    <?php endif; ?>
                    <?php if ($canAdminNav): ?>
                    <a href="?action=admin_dashboard&store=<?php echo $currentStoreId; ?>&date=<?php echo urlencode($currentDate); ?>" class="app-submenu-item <?php echo $currentAction === 'admin_dashboard' ? 'active' : ''; ?>">Admin Time</a>
                    <?php endif; ?>
                    <a href="?action=timeclock&store=<?php echo $currentStoreId; ?>&date=<?php echo urlencode($currentDate); ?>" class="app-submenu-item <?php echo $currentAction === 'timeclock' ? 'active' : ''; ?>">Core Time Clock</a>
                <?php else: ?>
                    <a href="?action=dashboard&store=<?php echo $currentStoreId; ?>&date=<?php echo urlencode($currentDate); ?>&view=<?php echo urlencode($sidebarView); ?>&tab=kpi&mode=view" class="app-submenu-item <?php echo ($currentAction === 'dashboard' && $sidebarTab === 'kpi' && $sidebarKpiMode === 'view') ? 'active' : ''; ?>">KPIs View</a>
                    <a href="?action=dashboard&store=<?php echo $currentStoreId; ?>&date=<?php echo urlencode($currentDate); ?>&view=<?php echo urlencode($sidebarView); ?>&tab=kpi&mode=edit" class="app-submenu-item <?php echo ($currentAction === 'dashboard' && $sidebarTab === 'kpi' && $sidebarKpiMode === 'edit') ? 'active' : ''; ?>">KPIs Edit</a>
                    <a href="?action=dashboard&store=<?php echo $currentStoreId; ?>&date=<?php echo urlencode($currentDate); ?>&view=<?php echo urlencode($sidebarView); ?>&tab=inventory&mode=view" class="app-submenu-item <?php echo ($currentAction === 'dashboard' && $sidebarTab === 'inventory') ? 'active' : ''; ?>">Inventory</a>
                    <a href="?action=history&store=<?php echo $currentStoreId; ?>" class="app-submenu-item <?php echo $currentAction === 'history' ? 'active' : ''; ?>">History</a>
                    <a href="import.php" class="app-submenu-item">Import</a>
                <?php endif; ?>
            </div>
            <div class="container">

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
            var host = String(window.location.hostname || '').toLowerCase();
            var isLocalDev = host === 'localhost' || host === '127.0.0.1';
            window.addEventListener('load', function () {
                if (isLocalDev) {
                    // Avoid stale JS/CSS caches while iterating quickly in local dev.
                    navigator.serviceWorker.getRegistrations().then(function (regs) {
                        regs.forEach(function (reg) { reg.unregister(); });
                    }).catch(function () {});
                    return;
                }
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
    $isTimeclockNavAction = in_array((string)$currentAction, ['timeclock', 'employee_dashboard', 'manager_dashboard', 'schedule_center', 'admin_dashboard'], true);
    $navPanel = (string)($_GET['panel'] ?? '');
    $currentUserDisplay = isset($currentUserName) ? (string)$currentUserName : getCurrentUserDisplayName();
    $currentRoleDisplay = ucfirst((string)$roleForNav);
    $currentSessionEmployeeId = (int)($_SESSION['employee_id'] ?? ($_SESSION['user_employee_id'] ?? 0));
    $userSwitchOptions = isset($headerUserSwitchEmployees) && is_array($headerUserSwitchEmployees)
        ? $headerUserSwitchEmployees
        : (($currentStoreId > 0 && function_exists('getTimeClockEmployeesForStore')) ? getTimeClockEmployeesForStore((int)$currentStoreId) : []);
    $userSwitchQuery = $_GET;
    unset($userSwitchQuery['switch_user'], $userSwitchQuery['switch_user_employee_id'], $userSwitchQuery['switch_user_role']);
    if ($currentAction === 'dashboard') {
        $topbarTitle = ($sidebarTab === 'inventory') ? 'Inventory' : 'KPIs';
    } elseif ($currentAction === 'employee_dashboard') {
        $topbarTitle = 'Employee Dashboard';
    } elseif ($currentAction === 'manager_dashboard') {
        $topbarTitle = 'Manager Operations';
    } elseif ($currentAction === 'schedule_center') {
        $topbarTitle = 'Schedule Builder';
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
                <a href="?action=dashboard&store=<?php echo $currentStoreId; ?>&date=<?php echo urlencode($currentDate); ?>&view=<?php echo urlencode($sidebarView); ?><?php echo $sidebarDaysParam; ?>&tab=kpi&mode=<?php echo urlencode($sidebarKpiMode); ?>" class="nav-kpi <?php echo ($currentAction === 'dashboard' && $sidebarTab === 'kpi') ? 'active' : ''; ?>" data-nav-help="KPI performance and labor tracking."><span class="nav-ico" aria-hidden="true">▣</span>KPIs</a>
                <a href="?action=dashboard&store=<?php echo $currentStoreId; ?>&date=<?php echo urlencode($currentDate); ?>&view=<?php echo urlencode($sidebarView); ?><?php echo $sidebarDaysParam; ?>&tab=inventory&mode=view" class="nav-contrast <?php echo ($currentAction === 'dashboard' && $sidebarTab === 'inventory') ? 'active' : ''; ?>" data-nav-help="Inventory counts, reorder, and vendors."><span class="nav-ico" aria-hidden="true">◫</span>Inventory</a>
                <a href="?action=employee_dashboard&store=<?php echo $currentStoreId; ?>&date=<?php echo urlencode($currentDate); ?>" class="nav-contrast <?php echo $currentAction === 'employee_dashboard' ? 'active' : ''; ?>" data-nav-help="Employee self-service tools and requests."><span class="nav-ico" aria-hidden="true">◔</span>Employee</a>
                <?php if ($canManageNav): ?>
                <a href="?action=manager_dashboard&store=<?php echo $currentStoreId; ?>&date=<?php echo urlencode($currentDate); ?>" class="nav-contrast <?php echo in_array($currentAction, ['manager_dashboard', 'timeclock', 'schedule_center'], true) ? 'active' : ''; ?>" data-nav-help="Manager operations, approvals, and floor visibility."><span class="nav-ico" aria-hidden="true">◷</span>Manager Ops<?php if (!empty($timeClockNeedsAttention)): ?> <span class="nav-alert-dot" title="Needs attention"></span><?php endif; ?></a>
                <?php endif; ?>
                <?php if ($canAdminNav): ?>
                <a href="?action=admin_dashboard&store=<?php echo $currentStoreId; ?>&date=<?php echo urlencode($currentDate); ?>" class="nav-contrast <?php echo $currentAction === 'admin_dashboard' ? 'active' : ''; ?>" data-nav-help="Policy, payroll controls, and governance."><span class="nav-ico" aria-hidden="true">◉</span>Admin Time</a>
                <?php endif; ?>
                <a href="?action=history&store=<?php echo $currentStoreId; ?>" class="nav-contrast <?php echo $currentAction === 'history' ? 'active' : ''; ?>" data-nav-help="Historical activity and reports."><span class="nav-ico" aria-hidden="true">☰</span>History</a>
                <a href="import.php" class="nav-contrast" data-nav-help="Data import and bulk updates."><span class="nav-ico" aria-hidden="true">⇪</span>Import</a>
            </nav>
            <div class="app-sidebar-footer">
                <span class="app-go-live-note">Go-live reminder: enable 60s idle timeout.</span>
            </div>
        </aside>
        <main class="app-main">
            <div class="app-topbar">
                <button type="button" id="app-sidebar-toggle" class="app-sidebar-toggle" aria-controls="app-sidebar-nav" aria-expanded="false">Menu</button>
                <div class="app-topbar-title"><?php echo htmlspecialchars($topbarTitle); ?><?php if ($isTimeclockNavAction && !empty($timeClockNeedsAttention)): ?> <span class="app-topbar-badge-alert">Needs Attention</span><?php endif; ?></div>
                <?php if ($currentAction !== 'dashboard'): ?>
                <button type="button" class="density-toggle" data-density-toggle="1" aria-pressed="false" aria-label="Density: Comfortable" title="Density: Comfortable"></button>
                <button type="button" class="contrast-toggle" data-contrast-toggle="1" aria-pressed="false" aria-label="Contrast: Off" title="Contrast: Off"></button>
                <?php endif; ?>
            </div>
            <?php if ($isTimeclockNavAction): ?>
            <?php
                $timeclockPrimaryTabs = [];
                if ($currentAction === 'employee_dashboard') {
                    $timeclockPrimaryTabs[] = [
                        'label' => 'Employee Dashboard',
                        'href' => '?action=employee_dashboard&store=' . $currentStoreId . '&date=' . urlencode($currentDate),
                        'active' => ($currentAction === 'employee_dashboard'),
                    ];
                }
                if ($canManageNav && in_array($currentAction, ['manager_dashboard', 'schedule_center', 'timeclock'], true)) {
                    $timeclockPrimaryTabs[] = [
                        'label' => 'Manager Ops',
                        'href' => '?action=manager_dashboard&store=' . $currentStoreId . '&date=' . urlencode($currentDate),
                        'active' => ($currentAction === 'manager_dashboard'),
                    ];
                    $timeclockPrimaryTabs[] = [
                        'label' => 'Schedule Builder',
                        'href' => '?action=schedule_center&store=' . $currentStoreId . '&date=' . urlencode($currentDate),
                        'active' => ($currentAction === 'schedule_center'),
                    ];
                }
                if ($canAdminNav && $currentAction === 'admin_dashboard') {
                    $timeclockPrimaryTabs[] = [
                        'label' => 'Admin Time',
                        'href' => '?action=admin_dashboard&store=' . $currentStoreId . '&date=' . urlencode($currentDate),
                        'active' => ($currentAction === 'admin_dashboard'),
                    ];
                }
                $timeclockContextTabs = [];
                if ($currentAction === 'employee_dashboard') {
                    $timeclockContextTabs = [
                        [
                            'label' => 'Overview',
                            'href' => '?action=employee_dashboard&store=' . $currentStoreId . '&date=' . urlencode($currentDate),
                            'active' => ($navPanel === ''),
                        ],
                        [
                            'label' => 'Schedule',
                            'href' => '?action=employee_dashboard&store=' . $currentStoreId . '&date=' . urlencode($currentDate) . '&panel=tc_panel_staff_schedule',
                            'active' => ($navPanel === 'tc_panel_staff_schedule'),
                        ],
                        [
                            'label' => 'Tasks',
                            'href' => '?action=employee_dashboard&store=' . $currentStoreId . '&date=' . urlencode($currentDate) . '&panel=tc_panel_tasks',
                            'active' => ($navPanel === 'tc_panel_tasks'),
                        ],
                        [
                            'label' => 'PTO',
                            'href' => '?action=employee_dashboard&store=' . $currentStoreId . '&date=' . urlencode($currentDate) . '&panel=tc_panel_pto',
                            'active' => ($navPanel === 'tc_panel_pto'),
                        ],
                    ];
                } elseif ($canManageNav && in_array($currentAction, ['manager_dashboard', 'schedule_center'], true)) {
                    $managerDashBase = '?action=manager_dashboard&store=' . $currentStoreId . '&date=' . urlencode($currentDate);
                    $timeclockContextTabs = [
                        [
                            'label' => 'Overview',
                            'href' => $managerDashBase,
                            'active' => ($currentAction === 'manager_dashboard' && $navPanel === ''),
                        ],
                        [
                            'label' => 'Schedule',
                            'href' => '?action=schedule_center&store=' . $currentStoreId . '&date=' . urlencode($currentDate),
                            'active' => ($currentAction === 'schedule_center'),
                        ],
                        [
                            'label' => 'Tasks',
                            'href' => $managerDashBase . '&panel=tc_panel_tasks',
                            'active' => ($currentAction === 'manager_dashboard' && $navPanel === 'tc_panel_tasks'),
                        ],
                        [
                            'label' => 'PTO',
                            'href' => $managerDashBase . '&panel=tc_panel_pto',
                            'active' => ($currentAction === 'manager_dashboard' && $navPanel === 'tc_panel_pto'),
                        ],
                        [
                            'label' => 'Users',
                            'href' => $managerDashBase . '&panel=tc_panel_users',
                            'active' => ($currentAction === 'manager_dashboard' && $navPanel === 'tc_panel_users'),
                        ],
                        [
                            'label' => 'Reviews',
                            'href' => $managerDashBase . '&panel=tc_panel_mgr_reviews',
                            'active' => ($currentAction === 'manager_dashboard' && $navPanel === 'tc_panel_mgr_reviews'),
                        ],
                        [
                            'label' => 'Employee Notes',
                            'href' => $managerDashBase . '&panel=tc_panel_mgr_notes',
                            'active' => ($currentAction === 'manager_dashboard' && $navPanel === 'tc_panel_mgr_notes'),
                        ],
                    ];
                } elseif ($currentAction === 'admin_dashboard') {
                    $adminBase = '?action=admin_dashboard&store=' . $currentStoreId . '&date=' . urlencode($currentDate);
                    $timeclockContextTabs = [
                        ['label' => 'Overview', 'href' => $adminBase, 'active' => ($navPanel === '')],
                        ['label' => 'Payroll', 'href' => $adminBase . '&panel=tc_panel_payroll', 'active' => ($navPanel === 'tc_panel_payroll')],
                        ['label' => 'Settings', 'href' => $adminBase . '&panel=tc_panel_settings', 'active' => ($navPanel === 'tc_panel_settings')],
                        ['label' => 'PTO Policy', 'href' => $adminBase . '&panel=tc_panel_pto', 'active' => ($navPanel === 'tc_panel_pto')],
                        ['label' => 'Audit', 'href' => $adminBase . '&panel=tc_panel_admin', 'active' => ($navPanel === 'tc_panel_admin')],
                        ['label' => 'Live', 'href' => $adminBase . '&panel=tc_panel_live', 'active' => ($navPanel === 'tc_panel_live')],
                    ];
                }
            ?>
            <div class="app-timeclock-session-strip">
                <div class="app-user-banner app-user-banner-timeclock">
                    <div class="app-timeclock-nav-stack">
                        <div class="app-submenu app-local-tabs-kpi">
                            <?php foreach ($timeclockPrimaryTabs as $tab): ?>
                            <a href="<?php echo $tab['href']; ?>" class="app-submenu-item app-local-tab kpi-local-tab <?php echo !empty($tab['active']) ? 'active' : ''; ?>"><?php echo htmlspecialchars((string)$tab['label']); ?></a>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($timeclockContextTabs)): ?>
                        <div class="app-submenu app-submenu-context app-local-tabs-kpi">
                            <?php foreach ($timeclockContextTabs as $tab): ?>
                            <a href="<?php echo $tab['href']; ?>" class="app-submenu-item app-local-tab kpi-local-tab <?php echo !empty($tab['active']) ? 'active' : ''; ?>"><?php echo htmlspecialchars((string)$tab['label']); ?></a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="app-timeclock-meta-bar">
                        <span class="app-user-banner-sep">|</span>
                        <strong>Logged in as:</strong>
                        <span><?php echo htmlspecialchars($currentUserDisplay); ?></span>
                        <span class="app-user-role"><?php echo htmlspecialchars($currentRoleDisplay); ?></span>
                        <span class="app-user-banner-sep">|</span>
                        <span>Store #<?php echo $currentStoreId > 0 ? $currentStoreId : 0; ?></span>
                        <span class="app-user-banner-sep">|</span>
                        <form method="GET" action="index.php" class="app-user-switch-form-inline">
                            <?php foreach ($userSwitchQuery as $k => $v): ?>
                                <?php if (is_array($v)) continue; ?>
                                <input type="hidden" name="<?php echo htmlspecialchars((string)$k); ?>" value="<?php echo htmlspecialchars((string)$v); ?>">
                            <?php endforeach; ?>
                            <input type="hidden" name="switch_user" value="1">
                            <select name="switch_user_employee_id" aria-label="Switch user">
                                <option value="0" <?php echo $currentSessionEmployeeId <= 0 ? 'selected' : ''; ?>>No link</option>
                                <?php foreach ($userSwitchOptions as $empOpt): ?>
                                    <?php $empId = (int)($empOpt['id'] ?? 0); ?>
                                    <option value="<?php echo $empId; ?>" <?php echo $currentSessionEmployeeId === $empId ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars((string)($empOpt['full_name'] ?? ('Employee #' . $empId))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="switch_user_role" aria-label="Switch role">
                                <option value="employee" <?php echo $roleForNav === 'employee' ? 'selected' : ''; ?>>Employee</option>
                                <option value="manager" <?php echo $roleForNav === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                <option value="admin" <?php echo $roleForNav === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                            <button type="submit" class="app-user-switch-btn app-user-switch-btn-inline">Switch</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php elseif ($currentAction === 'dashboard'): ?>
            <div class="app-kpi-session-strip">
                <div class="app-kpi-strip-left">
                    <?php if ($sidebarTab === 'kpi'): ?>
                    <div class="app-local-tabs app-local-tabs-kpi" aria-label="KPI section navigation">
                        <a href="?action=dashboard&store=<?php echo $currentStoreId; ?>&date=<?php echo urlencode($currentDate); ?>&view=<?php echo urlencode($sidebarView); ?>&tab=kpi&mode=view" class="app-local-tab kpi-local-tab <?php echo ($sidebarTab === 'kpi' && $sidebarKpiMode === 'view') ? 'active' : ''; ?>" data-nav-help="Read-only KPI review mode.">KPIs View</a>
                        <?php if (!empty($canWriteKpi)): ?>
                        <a href="?action=dashboard&store=<?php echo $currentStoreId; ?>&date=<?php echo urlencode($currentDate); ?>&view=<?php echo urlencode($sidebarView); ?>&tab=kpi&mode=edit" class="app-local-tab kpi-local-tab <?php echo ($sidebarTab === 'kpi' && $sidebarKpiMode === 'edit') ? 'active' : ''; ?>" data-nav-help="Edit KPI numbers and assumptions.">KPIs Edit</a>
                        <?php endif; ?>
                    </div>
                    <?php elseif ($sidebarTab === 'inventory'): ?>
                    <div class="app-local-tabs app-local-tabs-kpi" aria-label="Inventory section navigation">
                        <a href="?action=dashboard&store=<?php echo $currentStoreId; ?>&date=<?php echo urlencode($currentDate); ?>&view=<?php echo urlencode($sidebarView); ?>&tab=inventory&mode=view" class="app-local-tab kpi-local-tab active" data-nav-help="Inventory management workspace.">Inventory</a>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="app-kpi-strip-right app-kpi-meta-bar">
                    <button type="button" class="app-nav-help-toggle app-toggle-compact" data-context-help-toggle="1" aria-pressed="false" aria-label="Context Help: Off" title="Context Help: Off"></button>
                    <button type="button" class="density-toggle app-toggle-compact" data-density-toggle="1" aria-pressed="false" aria-label="Density: Comfortable" title="Density: Comfortable"></button>
                    <button type="button" class="contrast-toggle app-toggle-compact" data-contrast-toggle="1" aria-pressed="false" aria-label="Contrast: Off" title="Contrast: Off"></button>
                    <span class="app-user-banner-sep">|</span>
                    <strong>Logged in as:</strong>
                    <span><?php echo htmlspecialchars($currentUserDisplay); ?></span>
                    <span class="app-user-role"><?php echo htmlspecialchars($currentRoleDisplay); ?></span>
                    <span class="app-user-banner-sep">|</span>
                    <span>Store #<?php echo $currentStoreId > 0 ? $currentStoreId : 0; ?></span>
                    <span class="app-user-banner-sep">|</span>
                    <form method="GET" action="index.php" class="app-user-switch-form-inline">
                        <?php foreach ($userSwitchQuery as $k => $v): ?>
                            <?php if (is_array($v)) continue; ?>
                            <input type="hidden" name="<?php echo htmlspecialchars((string)$k); ?>" value="<?php echo htmlspecialchars((string)$v); ?>">
                        <?php endforeach; ?>
                        <input type="hidden" name="switch_user" value="1">
                        <select name="switch_user_employee_id" aria-label="Switch user">
                            <option value="0" <?php echo $currentSessionEmployeeId <= 0 ? 'selected' : ''; ?>>No link</option>
                            <?php foreach ($userSwitchOptions as $empOpt): ?>
                                <?php $empId = (int)($empOpt['id'] ?? 0); ?>
                                <option value="<?php echo $empId; ?>" <?php echo $currentSessionEmployeeId === $empId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string)($empOpt['full_name'] ?? ('Employee #' . $empId))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="switch_user_role" aria-label="Switch role">
                            <option value="employee" <?php echo $roleForNav === 'employee' ? 'selected' : ''; ?>>Employee</option>
                            <option value="manager" <?php echo $roleForNav === 'manager' ? 'selected' : ''; ?>>Manager</option>
                            <option value="admin" <?php echo $roleForNav === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                        <button type="submit" class="app-user-switch-btn app-user-switch-btn-inline">Switch</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            <div class="container">
                <?php if ($currentAction !== 'dashboard' && !$isTimeclockNavAction): ?>
                <div class="app-user-banner">
                    <strong>Logged in as:</strong>
                    <span><?php echo htmlspecialchars($currentUserDisplay); ?></span>
                    <span class="app-user-role"><?php echo htmlspecialchars($currentRoleDisplay); ?></span>
                    <span class="app-user-banner-sep">|</span>
                    <span>Store #<?php echo $currentStoreId > 0 ? $currentStoreId : 0; ?></span>
                    <span class="app-user-banner-sep">|</span>
                    <form method="GET" action="index.php" class="app-user-switch-form-inline app-user-switch-form-inline-light">
                        <?php foreach ($userSwitchQuery as $k => $v): ?>
                            <?php if (is_array($v)) continue; ?>
                            <input type="hidden" name="<?php echo htmlspecialchars((string)$k); ?>" value="<?php echo htmlspecialchars((string)$v); ?>">
                        <?php endforeach; ?>
                        <input type="hidden" name="switch_user" value="1">
                        <select name="switch_user_employee_id" aria-label="Switch user">
                            <option value="0" <?php echo $currentSessionEmployeeId <= 0 ? 'selected' : ''; ?>>No link</option>
                            <?php foreach ($userSwitchOptions as $empOpt): ?>
                                <?php $empId = (int)($empOpt['id'] ?? 0); ?>
                                <option value="<?php echo $empId; ?>" <?php echo $currentSessionEmployeeId === $empId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string)($empOpt['full_name'] ?? ('Employee #' . $empId))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="switch_user_role" aria-label="Switch role">
                            <option value="employee" <?php echo $roleForNav === 'employee' ? 'selected' : ''; ?>>Employee</option>
                            <option value="manager" <?php echo $roleForNav === 'manager' ? 'selected' : ''; ?>>Manager</option>
                            <option value="admin" <?php echo $roleForNav === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                        <button type="submit" class="app-user-switch-btn app-user-switch-btn-inline">Switch</button>
                    </form>
                </div>
                <?php endif; ?>

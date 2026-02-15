<?php
// If request is for main script (e.g. server routes serve-asset.php to index.php), serve JS before any output/includes.
if (isset($_GET['f']) && in_array($_GET['f'], ['js/main.js', 'js/chart.umd.min.js'], true)) {
    $p = __DIR__ . '/' . $_GET['f'];
    if (is_file($p)) {
        header('Content-Type: application/javascript; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo "/* index.php asset " . basename($_GET['f']) . " */\n";
        readfile($p);
        exit;
    }
}
ob_start();
require_once 'config.php';
require_once 'includes/helpers.php';
require_once 'includes/functions.php';
require_once 'includes/inventory-functions.php';
require_once 'includes/timeclock-functions.php';

// Serve static assets via ?asset= so the browser always gets correct Content-Type (avoids JSON/HTML parsed as script).
$asset = isset($_GET['asset']) ? $_GET['asset'] : null;
if ($asset !== null && preg_match('#^(js/[a-zA-Z0-9_.-]+\.js|css/[a-zA-Z0-9_.-]+\.css|service-worker\.js|manifest\.webmanifest|assets/[a-zA-Z0-9_.-]+\.(svg|png))$#', $asset)) {
    $staticPath = __DIR__ . '/' . $asset;
    if (is_file($staticPath) && strpos(realpath($staticPath), realpath(__DIR__)) === 0) {
        if (ob_get_level()) ob_end_clean();
        $ext = strtolower((string)pathinfo($asset, PATHINFO_EXTENSION));
        $contentType = 'text/plain';
        if ($asset === 'manifest.webmanifest') $contentType = 'application/manifest+json';
        elseif ($ext === 'js') $contentType = 'application/javascript';
        elseif ($ext === 'css') $contentType = 'text/css';
        elseif ($ext === 'svg') $contentType = 'image/svg+xml';
        elseif ($ext === 'png') $contentType = 'image/png';
        header('Content-Type: ' . $contentType . '; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($staticPath);
        exit;
    }
}

// If the request path looks like /js/... or /css/... (front controller), serve file and exit.
$reqUri = $_SERVER['REQUEST_URI'] ?? '';
$reqPath = preg_replace('#\?.*$#', '', $reqUri);
if (preg_match('#/(js/[a-zA-Z0-9_.-]+\.js)$#', $reqPath, $m)) {
    $staticPath = __DIR__ . '/' . $m[1];
    if (is_file($staticPath) && strpos(realpath($staticPath), realpath(__DIR__)) === 0) {
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/javascript; charset=UTF-8');
        readfile($staticPath);
        exit;
    }
}
if (preg_match('#/(css/[a-zA-Z0-9_.-]+\.css)$#', $reqPath, $m)) {
    $staticPath = __DIR__ . '/' . $m[1];
    if (is_file($staticPath) && strpos(realpath($staticPath), realpath(__DIR__)) === 0) {
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: text/css; charset=UTF-8');
        readfile($staticPath);
        exit;
    }
}
if (preg_match('#/(service-worker\.js|manifest\.webmanifest|assets/[a-zA-Z0-9_.-]+\.(svg|png))$#', $reqPath, $m)) {
    $staticPath = __DIR__ . '/' . $m[1];
    if (is_file($staticPath) && strpos(realpath($staticPath), realpath(__DIR__)) === 0) {
        if (ob_get_level()) ob_end_clean();
        $assetName = $m[1];
        $ext = strtolower((string)pathinfo($assetName, PATHINFO_EXTENSION));
        $contentType = 'text/plain';
        if ($assetName === 'manifest.webmanifest') $contentType = 'application/manifest+json';
        elseif ($ext === 'js') $contentType = 'application/javascript';
        elseif ($ext === 'svg') $contentType = 'image/svg+xml';
        elseif ($ext === 'png') $contentType = 'image/png';
        header('Content-Type: ' . $contentType . '; charset=UTF-8');
        readfile($staticPath);
        exit;
    }
}

header('Content-Type: text/html; charset=UTF-8');
$scriptVersion = (int)@filemtime(__DIR__ . '/js/main.js');
$chartVersion = (int)@filemtime(__DIR__ . '/js/chart.umd.min.js');
$action = $_GET['action'] ?? 'dashboard';
$isKioskMode = ($action === 'timeclock' && (string)($_GET['kiosk'] ?? $_POST['kiosk'] ?? '') === '1');
if ($action === 'entry') {
    $qs = $_GET;
    $qs['action'] = 'dashboard';
    if (empty($qs['mode'])) {
        $qs['mode'] = 'edit';
    }
    header('Location: index.php?' . http_build_query($qs));
    exit;
}
if (isset($_GET['success'])) {
    $successMessage = $_GET['success'];
}
if (isset($_GET['error'])) {
    $errorMessage = $_GET['error'];
}
$storeId = isset($_GET['store']) ? intval($_GET['store']) : null;
$date = $_GET['date'] ?? date('Y-m-d');
$view = $_GET['view'] ?? 'week'; // week, month, or custom
$customDays = isset($_GET['days']) ? intval($_GET['days']) : 7;
$customDays = max(1, min(30, $customDays)); // clamp 1-30 for inventory days dropdown
$chartProductId = isset($_GET['chart_product_id']) ? intval($_GET['chart_product_id']) : null;
$chartDays = isset($_GET['chart_days']) ? intval($_GET['chart_days']) : 30;
if (!in_array($chartDays, [7, 30, 90], true)) $chartDays = 30;
$stores = getAllStores();
if (!$storeId && !empty($stores)) {
    $storeId = $stores[0]['id'];
}
$invLimitParam = $_GET['inventory_limit'] ?? $_POST['inventory_limit'] ?? '10';
$invDaysParam = isset($_GET['inventory_days']) ? max(1, min(30, (int)$_GET['inventory_days'])) : (isset($_POST['inventory_days']) ? max(1, min(30, (int)$_POST['inventory_days'])) : null);
$invSortParam = $_GET['inventory_sort'] ?? $_POST['inventory_sort'] ?? 'status';
$dashboardTab = $_GET['tab'] ?? $_POST['tab'] ?? 'kpi';
if (!in_array($dashboardTab, ['kpi', 'inventory'], true)) {
    $dashboardTab = 'kpi';
}
$kpiMode = $_GET['mode'] ?? $_POST['kpi_mode'] ?? 'view';
if (!in_array($kpiMode, ['view', 'edit'], true)) {
    $kpiMode = 'view';
}
$currentUserRole = getCurrentUserRole();
$canWriteKpi = currentUserCan('kpi_write');
$canWriteInventory = currentUserCan('inventory_write');
$canManageTimeclock = currentUserCan('timeclock_manager');
$canAdminTimeclock = currentUserCan('timeclock_admin');
$currentUserName = getCurrentUserDisplayName();
if ($kpiMode === 'edit' && !$canWriteKpi) {
    $kpiMode = 'view';
    if (!isset($errorMessage)) {
        $errorMessage = 'You have view-only access for KPIs.';
    }
}
$isKpiEditMode = ($kpiMode === 'edit');
$isKpiAction = ($action === 'dashboard');
require_once 'includes/post-handlers.php';

if ($action === 'timeclock' && $storeId && !empty($_GET['payroll_export_period_id'])) {
    if (!currentUserCan('timeclock_manager')) {
        header('Content-Type: text/plain; charset=UTF-8', true, 403);
        echo 'Manager role required for payroll export.';
        exit;
    }
    $periodIdExport = (int)$_GET['payroll_export_period_id'];
    $csv = getPayrollCsvContent($periodIdExport, (int)$storeId);
    if ($csv === null) {
        header('Content-Type: text/plain; charset=UTF-8', true, 404);
        echo 'Payroll period not found for export.';
        exit;
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="timeclock-payroll-period-' . $periodIdExport . '.csv"');
    echo $csv;
    exit;
}

// API: return vendor or inventory as JSON (for modal population)
if (isset($_GET['api']) && $_GET['api'] === 'vendor' && !empty($_GET['id'])) {
    header('Content-Type: application/json');
    $v = getVendor(intval($_GET['id']));
    echo json_encode($v ?: ['error' => 'Vendor not found']);
    exit;
}
if (isset($_GET['api']) && $_GET['api'] === 'inventory' && !empty($_GET['id'])) {
    header('Content-Type: application/json');
    $inv = getInventoryById(intval($_GET['id']));
    echo json_encode($inv ?: ['error' => 'Inventory not found']);
    exit;
}

$currentKpi = null;
if ($storeId) {
    $currentKpi = getDailyKpi($storeId, $date);
}
$currentStore = null;
if ($storeId) {
    $currentStore = getStore($storeId);
}
$timeClockEmployees = [];
$timeClockOpenShifts = [];
$timeClockRecentEvents = [];
$timeClockRecentShifts = [];
$timeClockPendingEditRequests = [];
$timeClockRecentEditRequests = [];
$timeClockAuditRows = [];
$timeClockKioskSyncRows = [];
$timeClockKioskDeviceSummary = [];
$timeClockKioskOpenFailures = [];
$timeClockGeoSettings = [];
$timeClockNeedsAttention = false;
$timeClockSlaAlertRows = [];
$timeClockSlaOpenFailureThreshold = 3;
$timeClockSlaStaleMinutes = 60;
$timeClockNoShowGraceMinutes = 15;
$timeClockNoShowAlerts = [];
$timeClockOperatingHoursMap = [];
$timeClockOperatingHoursByDate = [];
$timeClockSelectedDateHours = ['enabled' => true, 'open' => '09:00', 'close' => '21:00'];
$timeClockScheduleWeekRange = null;
$timeClockPrevWeekDate = null;
$timeClockNextWeekDate = null;
$timeClockThisWeekDate = null;
$timeClockScheduleShifts = [];
$timeClockStaffScheduleRows = [];
$timeClockCalendarPtoRows = [];
$timeClockCalendarWorkedRows = [];
$timeClockCalendarRangeStart = null;
$timeClockCalendarRangeEnd = null;
$timeClockScheduleWeekStatus = null;
$timeClockScheduleCalendar = ['days' => [], 'shifts_by_day' => [], 'coverage_by_day' => [], 'employee_hours' => []];
$timeClockRoleOptions = [];
$timeClockPayrollPeriods = [];
$timeClockSelectedPayrollPeriodId = null;
$timeClockSelectedPayrollRuns = [];
$timeClockPtoSettings = [];
$timeClockPtoBalances = [];
$timeClockPendingPtoRequests = [];
$timeClockRecentPtoRequests = [];
$timeClockSelectedDateLocked = false;
$timeClockPendingEditRequestLockMap = [];
$timeClockPendingPtoRequestLockMap = [];
if ($storeId && $action === 'timeclock') {
    $timeClockEmployees = getTimeClockEmployeesForStore($storeId);
    $timeClockOpenShifts = getOpenShiftsByStore($storeId);
    $timeClockRecentEvents = getRecentShiftEventsByStore($storeId, 25);
    $timeClockRecentShifts = getRecentShiftsByStore($storeId, 50);
    $timeClockPendingEditRequests = getPendingPunchEditRequestsByStore($storeId);
    $timeClockRecentEditRequests = getRecentPunchEditRequestsByStore($storeId, 25);
    $timeClockAuditRows = getRecentTimeclockAuditByStore($storeId, 25);
    $timeClockKioskSyncRows = getRecentKioskSyncLogsByStore($storeId, 60);
    $timeClockKioskDeviceSummary = getKioskSyncDeviceSummaryByStore($storeId, 20);
    $timeClockKioskOpenFailures = getOpenKioskSyncFailuresByStore($storeId, 60);
    $timeClockScheduleWeekRange = getWeekRangeForDate($date);
    $timeClockOperatingHoursMap = getStoreOperatingHoursMap((int)$storeId);
    $timeClockOperatingHoursByDate = buildOperatingHoursByDate(
        (string)($timeClockScheduleWeekRange['start'] ?? $date),
        (string)($timeClockScheduleWeekRange['end'] ?? $date),
        $timeClockOperatingHoursMap
    );
    $timeClockSelectedDateHours = getOperatingHoursForDate((int)$storeId, (string)$date);
    try {
        $weekAnchor = new DateTime($timeClockScheduleWeekRange['start'] . ' 00:00:00', new DateTimeZone(TIMEZONE));
        $timeClockPrevWeekDate = (clone $weekAnchor)->modify('-7 days')->format('Y-m-d');
        $timeClockNextWeekDate = (clone $weekAnchor)->modify('+7 days')->format('Y-m-d');
        $timeClockThisWeekDate = (new DateTime('monday this week', new DateTimeZone(TIMEZONE)))->format('Y-m-d');
    } catch (Throwable $e) {
        $timeClockPrevWeekDate = null;
        $timeClockNextWeekDate = null;
        $timeClockThisWeekDate = null;
    }
    $timeClockScheduleShifts = getScheduleShiftsForStoreWeek($storeId, $timeClockScheduleWeekRange['start'], $timeClockScheduleWeekRange['end']);
    $monthDateObj = new DateTime($date, new DateTimeZone(TIMEZONE));
    $monthStartYmd = $monthDateObj->format('Y-m-01');
    $monthEndYmd = $monthDateObj->format('Y-m-t');
    $calendarFetchStartYmd = (clone $monthDateObj)->modify('first day of -1 month')->format('Y-m-d');
    $calendarFetchEndYmd = (clone $monthDateObj)->modify('last day of +1 month')->format('Y-m-d');
    $timeClockCalendarRangeStart = min($timeClockScheduleWeekRange['start'], $monthStartYmd, $calendarFetchStartYmd);
    $timeClockCalendarRangeEnd = max($timeClockScheduleWeekRange['end'], $monthEndYmd, $calendarFetchEndYmd);
    $timeClockCalendarScheduleShifts = getScheduleShiftsForStoreRange($storeId, $timeClockCalendarRangeStart, $timeClockCalendarRangeEnd);
    foreach ($timeClockCalendarScheduleShifts as $shift) {
        $startLocalObj = null;
        $endLocalObj = null;
        $startDateYmd = '';
        $endDateYmd = '';
        $startTimeLabel = '';
        $endTimeLabel = '';
        try {
            $startLocalObj = new DateTime((string)($shift['start_utc'] ?? ''), new DateTimeZone('UTC'));
            $endLocalObj = new DateTime((string)($shift['end_utc'] ?? ''), new DateTimeZone('UTC'));
            $startLocalObj->setTimezone(new DateTimeZone(TIMEZONE));
            $endLocalObj->setTimezone(new DateTimeZone(TIMEZONE));
            $startDateYmd = $startLocalObj->format('Y-m-d');
            $endDateYmd = $endLocalObj->format('Y-m-d');
            $startTimeLabel = $startLocalObj->format('g:i A');
            $endTimeLabel = $endLocalObj->format('g:i A');
        } catch (Throwable $e) {
            // keep defaults
        }
        $timeClockStaffScheduleRows[] = [
            'shift_id' => (int)($shift['id'] ?? 0),
            'employee_id' => (int)($shift['employee_id'] ?? 0),
            'employee_name' => (string)($shift['full_name'] ?? ''),
            'role_name' => (string)($shift['role_name'] ?? 'Employee'),
            'break_minutes' => (int)($shift['break_minutes'] ?? 0),
            'start_utc' => (string)($shift['start_utc'] ?? ''),
            'end_utc' => (string)($shift['end_utc'] ?? ''),
            'start_local' => formatUtcTimestampForDisplay($shift['start_utc'] ?? null),
            'end_local' => formatUtcTimestampForDisplay($shift['end_utc'] ?? null),
            'start_date_ymd' => $startDateYmd,
            'end_date_ymd' => $endDateYmd,
            'start_time_label' => $startTimeLabel,
            'end_time_label' => $endTimeLabel
        ];
    }
    usort($timeClockStaffScheduleRows, function ($a, $b) {
        return strcmp((string)$a['start_utc'], (string)$b['start_utc']);
    });
    $approvedPtoRows = getApprovedPtoRequestsByStoreRange($storeId, $timeClockCalendarRangeStart, $timeClockCalendarRangeEnd, 600);
    foreach ($approvedPtoRows as $pto) {
        $timeClockCalendarPtoRows[] = [
            'employee_id' => (int)($pto['employee_id'] ?? 0),
            'employee_name' => (string)($pto['full_name'] ?? ''),
            'start_date_ymd' => (string)($pto['request_start_date'] ?? ''),
            'end_date_ymd' => (string)($pto['request_end_date'] ?? ''),
            'requested_minutes' => (int)($pto['requested_minutes'] ?? 0),
        ];
    }
    $workedShiftRows = getWorkedShiftsByStoreRange($storeId, $timeClockCalendarRangeStart, $timeClockCalendarRangeEnd, 1400);
    foreach ($workedShiftRows as $ws) {
        $clockInDate = '';
        $clockOutDate = '';
        $clockInTime = '';
        $clockOutTime = '';
        try {
            $inLocal = new DateTime((string)($ws['clock_in_utc'] ?? ''), new DateTimeZone('UTC'));
            $inLocal->setTimezone(new DateTimeZone(TIMEZONE));
            $clockInDate = $inLocal->format('Y-m-d');
            $clockInTime = $inLocal->format('g:i A');
            if (!empty($ws['clock_out_utc'])) {
                $outLocal = new DateTime((string)$ws['clock_out_utc'], new DateTimeZone('UTC'));
                $outLocal->setTimezone(new DateTimeZone(TIMEZONE));
                $clockOutDate = $outLocal->format('Y-m-d');
                $clockOutTime = $outLocal->format('g:i A');
            }
        } catch (Throwable $e) {
            // keep defaults
        }
        $timeClockCalendarWorkedRows[] = [
            'employee_id' => (int)($ws['employee_id'] ?? 0),
            'employee_name' => (string)($ws['full_name'] ?? ''),
            'clock_in_utc' => (string)($ws['clock_in_utc'] ?? ''),
            'clock_out_utc' => (string)($ws['clock_out_utc'] ?? ''),
            'clock_in_date_ymd' => $clockInDate,
            'clock_out_date_ymd' => $clockOutDate,
            'clock_in_time_label' => $clockInTime,
            'clock_out_time_label' => $clockOutTime
        ];
    }
    $timeClockScheduleWeekStatus = getScheduleWeekStatus($storeId, $timeClockScheduleWeekRange['start'], $timeClockScheduleWeekRange['end']);
    $timeClockScheduleCalendar = buildScheduleCalendarData(
        $timeClockScheduleShifts,
        $timeClockScheduleWeekRange['start'],
        $timeClockScheduleWeekRange['end'],
        9,
        21,
        $timeClockOperatingHoursByDate
    );
    $timeClockRoleOptions = getTimeClockRoleOptions($storeId);
    $timeClockPayrollPeriods = getPayrollPeriodsByStore($storeId, 30);
    $timeClockSelectedPayrollPeriodId = isset($_GET['payroll_period_id']) ? (int)$_GET['payroll_period_id'] : (!empty($timeClockPayrollPeriods) ? (int)$timeClockPayrollPeriods[0]['id'] : null);
    if ($timeClockSelectedPayrollPeriodId) {
        $timeClockSelectedPayrollRuns = getPayrollRunsByPeriod($timeClockSelectedPayrollPeriodId);
    }
    $timeClockPtoSettings = getTimeclockSettingsMap([
        'pto_accrual_method',
        'pto_minutes_per_hour',
        'pto_exclude_overtime',
        'pto_annual_cap_minutes',
        'pto_waiting_period_days',
        'sick_policy_mode',
        'sick_minutes_per_hour',
        'holiday_policy_mode',
        'holiday_pay_multiplier'
    ], null);
    $timeClockGeoSettings = getGeofenceSettingsForStore((int)$storeId);
    $timeClockSlaOpenFailureThreshold = (int)($timeClockGeoSettings['alert_open_failure_threshold'] ?? 3);
    $timeClockSlaStaleMinutes = (int)($timeClockGeoSettings['alert_stale_minutes'] ?? 60);
    $timeClockNoShowGraceMinutes = (int)($timeClockGeoSettings['no_show_grace_minutes'] ?? 15);
    $timeClockNoShowAlerts = getMissedClockInAlertsForStoreDate((int)$storeId, (string)$date, $timeClockNoShowGraceMinutes, 120);
    $nowTs = time();
    foreach ($timeClockKioskDeviceSummary as $row) {
        $openFailed = (int)($row['unresolved_failed_attempts'] ?? 0);
        $lastSeenTs = !empty($row['last_seen_at']) ? strtotime((string)$row['last_seen_at']) : null;
        $isStale = $lastSeenTs ? (($nowTs - $lastSeenTs) > ($timeClockSlaStaleMinutes * 60)) : false;
        if ($openFailed >= $timeClockSlaOpenFailureThreshold || $isStale) {
            $timeClockSlaAlertRows[] = [
                'device_id' => (string)($row['device_id'] ?? 'unknown'),
                'open_failed' => $openFailed,
                'is_stale' => $isStale,
                'last_seen_at' => $row['last_seen_at'] ?? null
            ];
        }
    }
    $timeClockNeedsAttention = !empty($timeClockSlaAlertRows) || !empty($timeClockNoShowAlerts);
    $timeClockPtoBalances = getPtoBalancesByStore($storeId);
    $timeClockPendingPtoRequests = getPendingPtoRequestsByStore($storeId);
    $timeClockRecentPtoRequests = getRecentPtoRequestsByStore($storeId, 30);
    foreach ($timeClockPendingPtoRequests as $req) {
        $reqId = (int)($req['id'] ?? 0);
        if ($reqId <= 0) {
            continue;
        }
        $lockStart = (string)($req['request_start_date'] ?? '');
        $lockEnd = (string)($req['request_end_date'] ?? '');
        $timeClockPendingPtoRequestLockMap[$reqId] = ($lockStart !== '' && $lockEnd !== '')
            ? hasLockedPayrollPeriodOverlap($storeId, $lockStart, $lockEnd)
            : false;
    }
    $timeClockSelectedDateLocked = hasLockedPayrollPeriodOverlap($storeId, $date, $date);
    foreach ($timeClockPendingEditRequests as $req) {
        $reqId = (int)($req['id'] ?? 0);
        if ($reqId <= 0) {
            continue;
        }
        $lockStart = null;
        $lockEnd = null;
        $reqType = (string)($req['request_type'] ?? '');
        if ($reqType === 'MISS_CLOCK_IN') {
            [$lockStart, $lockEnd] = getLocalDateRangeFromUtc($req['requested_clock_in_utc'] ?? null, $req['requested_clock_out_utc'] ?? null);
        } elseif ($reqType === 'MISS_CLOCK_OUT') {
            $shift = !empty($req['shift_id']) ? getTimeclockShiftById((int)$req['shift_id'], (int)$storeId) : getOpenShiftForEmployeeStore((int)$req['employee_id'], (int)$storeId);
            [$lockStart, $lockEnd] = getLocalDateRangeFromUtc($shift['clock_in_utc'] ?? null, $req['requested_clock_out_utc'] ?? null);
        } else {
            $shift = !empty($req['shift_id']) ? getTimeclockShiftById((int)$req['shift_id'], (int)$storeId) : null;
            $effectiveIn = $req['requested_clock_in_utc'] ?? ($shift['clock_in_utc'] ?? null);
            $effectiveOut = $req['requested_clock_out_utc'] ?? ($shift['clock_out_utc'] ?? null);
            [$lockStart, $lockEnd] = getLocalDateRangeFromUtc($effectiveIn, $effectiveOut);
        }
        $timeClockPendingEditRequestLockMap[$reqId] = $lockStart ? hasLockedPayrollPeriodOverlap($storeId, $lockStart, $lockEnd) : false;
    }
}
// Get date range and KPIs for spreadsheet view
$dateRange = null;
$kpiMap = [];
$dateArray = [];
if ($storeId && $isKpiAction) {
    try {
        if ($view === 'custom') {
            $startDate = (new DateTime($date))->modify("-" . ($customDays - 1) . " days")->format('Y-m-d');
            $endDate = $date;
        } else {
            $dateRange = getDateRangeForView($view, $date);
            $startDate = $dateRange['start'];
            $endDate = $dateRange['end'];
        }
        $kpiMap = getKpisForDateRange($storeId, $startDate, $endDate);
        $dateArray = generateDateArray($startDate, $endDate);
        $datesWithData = getDatesWithDataForRange($startDate, $endDate, $date, $view);
    } catch (Exception $e) {
        error_log("Error generating date range: " . $e->getMessage());
        try {
            $fallbackEnd = new DateTime($date);
            $fallbackStart = clone $fallbackEnd;
            $fallbackStart->modify('-6 days');
            $dateArray = generateDateArray($fallbackStart->format('Y-m-d'), $fallbackEnd->format('Y-m-d'));
            $kpiMap = $dateArray ? getKpisForDateRange($storeId, $dateArray[0], $dateArray[count($dateArray) - 1]) : [];
            $datesWithData = $dateArray;
        } catch (Exception $e2) {
            $dateArray = [];
            $kpiMap = [];
            $datesWithData = [];
        }
    }
} else {
    $datesWithData = [];
}
if (!isset($datesWithData)) {
    $datesWithData = $dateArray ?? [];
}

// Inventory table can show its own number of days (1-30) without changing the main view
$inventoryDays = isset($_GET['inventory_days']) ? max(1, min(30, (int)$_GET['inventory_days'])) : null;
if ($inventoryDays !== null && $storeId && $isKpiAction) {
    $invEndDt = new DateTime($date);
    $invStartDt = clone $invEndDt;
    $invStartDt->modify('-' . ($inventoryDays - 1) . ' days');
    $inventoryDateArray = generateDateArray($invStartDt->format('Y-m-d'), $invEndDt->format('Y-m-d'));
} else {
    $inventoryDateArray = $dateArray;
}
$inventoryTableDates = $inventoryDateArray ?? $dateArray;

// Ensure we always have a date range for charts when KPIs view is open
if ($isKpiAction && $storeId && (empty($dateArray) || !is_array($dateArray))) {
    try {
        $fallbackEnd = new DateTime($date);
        $fallbackStart = clone $fallbackEnd;
        $fallbackStart->modify('-6 days');
        $dateArray = generateDateArray($fallbackStart->format('Y-m-d'), $fallbackEnd->format('Y-m-d'));
        $kpiMap = $dateArray ? getKpisForDateRange($storeId, $dateArray[0], $dateArray[count($dateArray) - 1]) : [];
    } catch (Exception $e) {
        $dateArray = [];
    }
}
$historicalKpis = [];
if ($action === 'history') {
    $historicalKpis = getHistoricalKpis($storeId, 100);
}

// Get inventory data
$inventoryItems = [];
$products = [];
$vendors = [];
$orders = [];
$inventorySort = $_GET['inventory_sort'] ?? 'status';
$inventoryLimit = $_GET['inventory_limit'] ?? '10';
if (!in_array($inventoryLimit, ['10', '15', '20', 'all'], true)) {
    $inventoryLimit = '10';
}
// Effective inventory table "days" for preserving state in forms/redirects (1–30)
$effectiveInventoryDays = ($inventoryDays !== null) ? max(1, min(30, $inventoryDays)) : (($view === 'custom') ? max(1, min(30, (int)($customDays ?? 7))) : (($view === 'month') ? 30 : 7));
$snapshotsMap = [];
$receivedMap = [];
$salesMap = [];
$purchasesMap = [];
$extrapolatedSales = [];
if ($storeId && $isKpiAction) {
    try {
        // Get inventory items (sorted by user preference for inventory list)
        $inventoryItems = getInventoryForStore($storeId, $inventorySort);
        if ($inventoryLimit !== 'all') {
            $limitNum = (int) $inventoryLimit;
            $inventoryItems = array_slice($inventoryItems, 0, $limitNum);
        }
        $products = getAllProducts();
        $vendors = getAllVendors();
        $orders = getOrdersForStore($storeId);
        if (!empty($dateArray) && isset($startDate) && isset($endDate)) {
            $dataStart = $startDate;
            $dataEnd = $endDate;
            if (!empty($inventoryTableDates)) {
                $dataStart = min($dataStart, $inventoryTableDates[0]);
                $dataEnd = max($dataEnd, $inventoryTableDates[count($inventoryTableDates) - 1]);
            }
            $snapshotsMap = getInventorySnapshotsMap($storeId, $dataStart, $dataEnd);
            $receivedMap = getReceivedByProductDate($storeId, $dataStart, $dataEnd);
            $salesMap = getSalesByProductDate($storeId, $dataStart, $dataEnd);
            $purchasesMap = getPurchasesByProductDate($storeId, $dataStart, $dataEnd);
            $manualPurchasesMap = getManualPurchasesByProductDate($storeId, $dataStart, $dataEnd);
            // V2: no extrapolation on load; chart uses user-entered sales ($salesMap)
            $extrapolatedSales = $salesMap;
        }
        
        // For daily on-hand grid, sort by 7-day average (top sellers first)
        $dailyOnHandItems = getInventoryForStore($storeId, 'avg_7day_sales');
    } catch (Exception $e) {
        error_log("Error loading inventory data: " . $e->getMessage());
    }
}

// Single-product chart data (when a SKU is selected)
$chartDateArray = [];
$chartOnHandByDate = [];
$chartSalesByDate = [];
$chartPurchasesByDate = [];
$chartProductSku = '';
$chartProductUnitCost = 0;
if ($chartProductId && $storeId && $isKpiAction) {
    $chartEnd = $date;
    $chartStartDt = (new DateTime($date))->modify('-' . ($chartDays - 1) . ' days');
    $chartStart = $chartStartDt->format('Y-m-d');
    $chartDateArray = generateDateArray($chartStart, $chartEnd);
    $chartSnapshotsMap = getInventorySnapshotsMap($storeId, $chartStart, $chartEnd);
    $chartSalesMap = getSalesByProductDate($storeId, $chartStart, $chartEnd);
    $chartPurchasesMap = getPurchasesByProductDate($storeId, $chartStart, $chartEnd);
    foreach ($chartDateArray as $d) {
        $chartOnHandByDate[$d] = isset($chartSnapshotsMap[$chartProductId][$d]) ? (float)$chartSnapshotsMap[$chartProductId][$d] : null;
        $chartSalesByDate[$d] = isset($chartSalesMap[$chartProductId][$d]) ? (float)$chartSalesMap[$chartProductId][$d] : 0;
        $chartPurchasesByDate[$d] = isset($chartPurchasesMap[$chartProductId][$d]) ? (float)$chartPurchasesMap[$chartProductId][$d] : 0;
    }
    foreach ($inventoryItems as $it) {
        if ((int)$it['product_id'] === $chartProductId) {
            $chartProductSku = $it['sku'] ?? '';
            $chartProductUnitCost = (float)($it['unit_cost'] ?? 0);
            break;
        }
    }
}
// Build inventory chart data in PHP so no PHP is echoed inside <script>
$inventoryChartJson = '';
if ($chartProductId && !empty($chartDateArray)) {
    $invLabels = array_map(function($d) {
        try { return (new DateTime($d))->format('M j'); } catch (Exception $e) { return $d; }
    }, $chartDateArray);
    $invOnHand = array_map(function($d) use ($chartOnHandByDate) { return isset($chartOnHandByDate[$d]) ? $chartOnHandByDate[$d] : null; }, $chartDateArray);
    $invSales = array_map(function($d) use ($chartSalesByDate) { return $chartSalesByDate[$d] ?? 0; }, $chartDateArray);
    $invPurchases = array_map(function($d) use ($chartPurchasesByDate) { return $chartPurchasesByDate[$d] ?? 0; }, $chartDateArray);
    $inventoryChartJson = json_encode([
        'labels' => $invLabels,
        'onHand' => $invOnHand,
        'sales' => $invSales,
        'purchases' => $invPurchases,
        'unitCost' => $chartProductUnitCost
    ]);
}
include 'includes/header.php';
?>
<div class="message-stack" id="toast-region" aria-live="polite" aria-atomic="true">
<?php if (isset($successMessage)): ?>
    <div class="message success js-toast" role="status">
        <div class="message-text"><?php echo htmlspecialchars($successMessage); ?></div>
        <button type="button" class="message-close" aria-label="Dismiss notification">&times;</button>
    </div>
<?php endif; ?>
<?php if (isset($errorMessage)): ?>
    <div class="message error js-toast" role="alert">
        <div class="message-text"><?php echo htmlspecialchars($errorMessage); ?></div>
        <button type="button" class="message-close" aria-label="Dismiss notification">&times;</button>
    </div>
<?php endif; ?>
</div>
<?php if ($isKpiAction): ?>
    <?php if (!$storeId || empty($stores)): ?>
        <div class="message error">
            <p>No stores found. Please set up the database first.</p>
            <p>Run: <code>make seed</code> or set up the database using <code>setup-db.sh</code></p>
        </div>
    <?php else: ?>
    <div class="header app-hero">
        <div>
            <h1><?php echo APP_NAME; ?></h1>
            <p class="app-hero-subtitle">Performance, inventory, and labor operations in one place.</p>
            <div class="store-info">Store: <strong><?php echo htmlspecialchars($currentStore['name'] ?? 'N/A'); ?></strong></div>
            <div class="store-selector">
                <?php foreach ($stores as $store): ?>
                    <a href="?action=<?php echo $action; ?>&store=<?php echo $store['id']; ?>&date=<?php echo $date; ?>&view=<?php echo $view; ?>&tab=<?php echo htmlspecialchars($dashboardTab); ?>" class="store-pill <?php echo ($store['id'] == $storeId) ? 'active' : ''; ?>" data-store-id="<?php echo $store['id']; ?>"><?php echo htmlspecialchars($store['name']); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="header-controls">
            <div class="view-selector">
                <label>View:</label>
                <select id="view-select" onchange="changeView(this.value)">
                    <option value="week" <?php echo $view === 'week' ? 'selected' : ''; ?>>Week (Mon-Sun)</option>
                    <option value="month" <?php echo $view === 'month' ? 'selected' : ''; ?>>Month</option>
                    <option value="custom" <?php echo $view === 'custom' ? 'selected' : ''; ?>>Custom</option>
                </select>
                <?php if ($view === 'custom'): ?>
                    <input type="number" id="custom-days" value="<?php echo $customDays; ?>" min="1" max="90" style="width: 60px; margin-left: 10px; padding: 4px; color: #000;" onchange="changeCustomDays(this.value)">
                    <span style="margin-left: 5px; color: #000;">days</span>
                <?php endif; ?>
            </div>
            <div class="date-selector">
                <label for="entry-date">Reference Date:</label>
                <input type="date" id="entry-date" value="<?php echo htmlspecialchars($date); ?>">
            </div>
        </div>
    </div>
    <?php if ($dashboardTab === 'kpi'): ?>
    <div class="dashboard-subnav" style="margin-top: -6px;">
        <a href="?action=dashboard&store=<?php echo (int)$storeId; ?>&date=<?php echo urlencode($date); ?>&view=<?php echo urlencode($view); ?><?php echo $view === 'custom' ? '&days=' . (int)$customDays : ''; ?>&inventory_limit=<?php echo urlencode($inventoryLimit ?? '10'); ?>&inventory_sort=<?php echo urlencode($inventorySort ?? 'status'); ?><?php echo $inventoryDays !== null ? '&inventory_days=' . (int)$inventoryDays : ''; ?>&tab=kpi&mode=view" class="dashboard-subnav-tab <?php echo !$isKpiEditMode ? 'active' : ''; ?>">
            View Mode
        </a>
        <?php if ($canWriteKpi): ?>
        <a href="?action=dashboard&store=<?php echo (int)$storeId; ?>&date=<?php echo urlencode($date); ?>&view=<?php echo urlencode($view); ?><?php echo $view === 'custom' ? '&days=' . (int)$customDays : ''; ?>&inventory_limit=<?php echo urlencode($inventoryLimit ?? '10'); ?>&inventory_sort=<?php echo urlencode($inventorySort ?? 'status'); ?><?php echo $inventoryDays !== null ? '&inventory_days=' . (int)$inventoryDays : ''; ?>&tab=kpi&mode=edit" class="dashboard-subnav-tab <?php echo $isKpiEditMode ? 'active' : ''; ?>">
            Edit Mode
        </a>
        <?php else: ?>
        <span class="dashboard-subnav-tab" aria-disabled="true" title="Manager role required">Edit Mode (Manager)</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($dashboardTab === 'kpi'): ?>
    <form method="POST" action="" id="spreadsheet-form">
        <input type="hidden" name="store_id" value="<?php echo $storeId; ?>">
        <input type="hidden" name="view" value="<?php echo $view; ?>">
        <input type="hidden" name="kpi_mode" value="<?php echo htmlspecialchars($kpiMode); ?>">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($dashboardTab); ?>">
        <input type="hidden" name="inventory_limit" value="<?php echo htmlspecialchars($inventoryLimit ?? '10'); ?>">
        <input type="hidden" name="inventory_days" value="<?php echo (int)$effectiveInventoryDays; ?>">
        <input type="hidden" name="inventory_sort" value="<?php echo htmlspecialchars($inventorySort ?? 'status'); ?>">
        <input type="hidden" name="bulk_save" value="1">
        <div class="spreadsheet-container">
            <div class="spreadsheet-wrapper">
                <table class="spreadsheet-table">
                    <thead class="spreadsheet-header">
                        <tr>
                            <th class="frozen-label-col"></th>
                            <?php foreach ($dateArray as $d): 
                                $dateObj = new DateTime($d);
                                $dayName = $dateObj->format('D');
                                $dayNum = $dateObj->format('j');
                                $month = $dateObj->format('M');
                            ?>
                                <th class="date-col">
                                    <div class="date-header">
                                        <div class="date-day"><?php echo $dayName; ?></div>
                                        <div class="date-number"><?php echo $dayNum; ?></div>
                                        <div class="date-month"><?php echo $month; ?></div>
                                    </div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $metrics = [
                            'bank_balance' => 'Bank Balance',
                            'safe_balance' => 'Safe Balance',
                            'sales_today' => 'Sales',
                            'cogs_today' => 'COGS',
                            'labor_today' => 'Labor',
                            'avg_daily_overhead' => 'Overhead'
                        ];
                        $computedMetrics = [
                            'cash_available' => 'Cash Available',
                            'profit' => 'Profit',
                            'labor_percentage' => 'Labor %'
                        ];
                        ?>
                        <?php foreach ($metrics as $key => $label): ?>
                            <tr>
                                <td class="frozen-label-col metric-label">
                                    <div class="label-text"><?php echo htmlspecialchars($label); ?></div>
                                </td>
                                <?php foreach ($dateArray as $d): 
                                    $kpi = $kpiMap[$d] ?? null;
                                    $value = $kpi ? ($kpi[$key] ?? 0) : 0;
                                ?>
                                    <td class="data-cell">
                                        <input type="number" 
                                               name="<?php echo $key; ?>[<?php echo $d; ?>]" 
                                               value="<?php echo htmlspecialchars($value); ?>" 
                                               step="0.01" 
                                               class="spreadsheet-input"
                                               data-date="<?php echo $d; ?>"
                                               data-metric="<?php echo $key; ?>"
                                               <?php echo !$isKpiEditMode ? 'readonly' : ''; ?>>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach ($computedMetrics as $key => $label): ?>
                            <tr class="computed-row">
                                <td class="frozen-label-col metric-label computed-label">
                                    <div class="label-text"><?php echo htmlspecialchars($label); ?></div>
                                </td>
                                <?php foreach ($dateArray as $d): 
                                    $kpi = $kpiMap[$d] ?? null;
                                    if ($key === 'cash_available') {
                                        $bank = $kpi ? ($kpi['bank_balance'] ?? 0) : 0;
                                        $safe = $kpi ? ($kpi['safe_balance'] ?? 0) : 0;
                                        $value = calculateCashAvailable($bank, $safe);
                                        $displayValue = formatCurrency($value);
                                    } elseif ($key === 'labor_percentage') {
                                        $sales = $kpi ? ($kpi['sales_today'] ?? 0) : 0;
                                        $labor = $kpi ? ($kpi['labor_today'] ?? 0) : 0;
                                        $value = $sales > 0 ? calculateLaborPercentage($labor, $sales) : 0;
                                        $displayValue = formatPercentage($value);
                                    } else {
                                        $sales = $kpi ? ($kpi['sales_today'] ?? 0) : 0;
                                        $cogs = $kpi ? ($kpi['cogs_today'] ?? 0) : 0;
                                        $labor = $kpi ? ($kpi['labor_today'] ?? 0) : 0;
                                        $overhead = $kpi ? ($kpi['avg_daily_overhead'] ?? 0) : 0;
                                        $value = calculateProfit($sales, $cogs, $labor, $overhead);
                                        $displayValue = formatCurrency($value);
                                    }
                                ?>
                                    <td class="data-cell computed-cell" data-date="<?php echo $d; ?>" data-metric="<?php echo $key; ?>">
                                        <div class="computed-value <?php echo ($key === 'profit' && $value < 0) ? 'negative' : ''; ?>">
                                            <?php echo $displayValue; ?>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php foreach ($dateArray as $d): ?>
            <input type="hidden" name="dates[]" value="<?php echo $d; ?>">
        <?php endforeach; ?>
        <?php if ($isKpiEditMode): ?>
            <div class="spreadsheet-footer">
                <div>
                    <label for="updated-by">Updated by:</label>
                    <input type="text" id="updated-by" name="updated_by" placeholder="Your name" value="" style="padding: 8px; margin-left: 10px; border: 1px solid #ddd; border-radius: 4px; width: 200px;">
                </div>
                <button type="submit" name="save_kpi" class="btn btn-primary">Save All Changes</button>
            </div>
        <?php endif; ?>
    </form>
    
    <?php 
    $showChartSection = $isKpiAction && $storeId;
    if ($showChartSection && (empty($dateArray) || !is_array($dateArray))) {
        $dateArray = [];
        try {
            $d0 = new DateTime($date);
            $d0->modify('-6 days');
            $dateArray = generateDateArray($d0->format('Y-m-d'), $date);
            $kpiMap = getKpisForDateRange($storeId, $dateArray[0], $dateArray[count($dateArray) - 1]);
        } catch (Exception $e) {}
    }
    $hasChartDates = !empty($dateArray) && is_array($dateArray) && count($dateArray) > 0;
    // Build KPI chart data in PHP so we never inject stray characters (e.g. "}") into inline script
    $kpiChartData = null;
    if ($showChartSection && is_array($dateArray ?? null)) {
        $arr = $dateArray;
        $labels = array_map(function($d) {
            try { return (new DateTime($d))->format('M j'); } catch (Exception $e) { return $d; }
        }, $arr);
        $kpiChartData = [
            'labels' => $labels,
            'datasets' => [
                [ 'label' => 'Sales', 'data' => array_map(function($d) use ($kpiMap) { $k = $kpiMap[$d] ?? null; return (float)($k ? ($k['sales_today'] ?? 0) : 0); }, $arr), 'borderColor' => 'rgb(52, 152, 219)', 'backgroundColor' => 'rgba(52, 152, 219, 0.1)', 'fill' => true, 'tension' => 0.4, 'hidden' => false ],
                [ 'label' => 'COGS', 'data' => array_map(function($d) use ($kpiMap) { $k = $kpiMap[$d] ?? null; return (float)($k ? ($k['cogs_today'] ?? 0) : 0); }, $arr), 'borderColor' => 'rgb(231, 76, 60)', 'backgroundColor' => 'rgba(231, 76, 60, 0.1)', 'fill' => true, 'tension' => 0.4, 'hidden' => true ],
                [ 'label' => 'Labor', 'data' => array_map(function($d) use ($kpiMap) { $k = $kpiMap[$d] ?? null; return (float)($k ? ($k['labor_today'] ?? 0) : 0); }, $arr), 'borderColor' => 'rgb(241, 196, 15)', 'backgroundColor' => 'rgba(241, 196, 15, 0.1)', 'fill' => true, 'tension' => 0.4, 'hidden' => true ],
                [ 'label' => 'Overhead', 'data' => array_map(function($d) use ($kpiMap) { $k = $kpiMap[$d] ?? null; return (float)($k ? ($k['avg_daily_overhead'] ?? 0) : 0); }, $arr), 'borderColor' => 'rgb(155, 89, 182)', 'backgroundColor' => 'rgba(155, 89, 182, 0.1)', 'fill' => true, 'tension' => 0.4, 'hidden' => true ],
                [ 'label' => 'Profit', 'data' => array_map(function($d) use ($kpiMap) { $k = $kpiMap[$d] ?? null; $s = (float)($k ? ($k['sales_today'] ?? 0) : 0); $c = (float)($k ? ($k['cogs_today'] ?? 0) : 0); $l = (float)($k ? ($k['labor_today'] ?? 0) : 0); $o = (float)($k ? ($k['avg_daily_overhead'] ?? 0) : 0); return $s - $c - $l - $o; }, $arr), 'borderColor' => 'rgb(39, 174, 96)', 'backgroundColor' => 'rgba(39, 174, 96, 0.1)', 'fill' => true, 'tension' => 0.4, 'hidden' => true ],
                [ 'label' => 'Labor %', 'data' => array_map(function($d) use ($kpiMap) { $k = $kpiMap[$d] ?? null; $s = (float)($k ? ($k['sales_today'] ?? 0) : 0); $l = (float)($k ? ($k['labor_today'] ?? 0) : 0); return $s > 0 ? ($l / $s) * 100 : 0; }, $arr), 'borderColor' => 'rgb(230, 126, 34)', 'backgroundColor' => 'rgba(230, 126, 34, 0.1)', 'fill' => true, 'tension' => 0.4, 'hidden' => true, 'yAxisID' => 'y1' ],
            ]
        ];
    }
    if ($showChartSection): ?>
    <!-- Chart Section -->
    <div class="chart-container">
        <div class="chart-header">
            <h2>
                <button type="button" class="collapse-toggle" onclick="toggleChartCollapse()">
                    <span id="chart-toggle-icon">▼</span> Data Visualization
                </button>
            </h2>
            <div class="chart-controls" style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
                <button type="button" class="chart-control-btn kpi-series-btn" data-index="0" title="Toggle Sales">Sales</button>
                <button type="button" class="chart-control-btn kpi-series-btn" data-index="1" title="Toggle COGS">COGS</button>
                <button type="button" class="chart-control-btn kpi-series-btn" data-index="2" title="Toggle Labor">Labor</button>
                <button type="button" class="chart-control-btn kpi-series-btn" data-index="3" title="Toggle Overhead">Overhead</button>
                <button type="button" class="chart-control-btn kpi-series-btn" data-index="4" title="Toggle Profit">Profit</button>
                <button type="button" class="chart-control-btn kpi-series-btn" data-index="5" title="Toggle Labor %">Labor %</button>
                <button type="button" id="show-all-chart" class="chart-control-btn">Show All</button>
                <button type="button" id="hide-all-chart" class="chart-control-btn">Hide All</button>
            </div>
        </div>
        <div id="chart-content" class="chart-content">
            <canvas id="kpiChart" style="max-height: 500px; height: 500px; width: 100%;"></canvas>
            <div id="chart-legend" class="chart-legend" style="margin-top: 8px;"></div>
        </div>
    </div>
    <?php $kpiChartJsonOut = ($kpiChartData !== null) ? json_encode($kpiChartData) : ''; ?>
    <textarea id="kpi-chart-data" style="display:none" readonly><?php echo htmlspecialchars($kpiChartJsonOut, ENT_QUOTES, 'UTF-8'); ?></textarea>
    <?php else: ?>
    <textarea id="kpi-chart-data" style="display:none" readonly></textarea>
    <?php endif; // end chart section ?>
    <?php endif; // end kpi tab ?>
    
    <!-- Inventory Management Section -->
    <?php if ($dashboardTab === 'inventory' && $storeId && $isKpiAction): ?>
    <div class="inventory-container">
        <div class="inventory-header">
            <h2>
                <button type="button" class="collapse-toggle" onclick="toggleInventoryCollapse()">
                    <span id="inventory-toggle-icon">▼</span> Inventory Management
                </button>
            </h2>
            <div class="inventory-actions">
                <form method="get" action="index.php" id="inventory-days-form" style="display: inline;">
                    <input type="hidden" name="action" value="<?php echo htmlspecialchars($action); ?>">
                    <input type="hidden" name="store" value="<?php echo (int)$storeId; ?>">
                    <input type="hidden" name="date" value="<?php echo htmlspecialchars($date ?? ''); ?>">
                    <input type="hidden" name="view" value="<?php echo htmlspecialchars($view ?? 'week'); ?>">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($dashboardTab); ?>">
                    <?php if ($view === 'custom'): ?>
                    <input type="hidden" name="days" value="<?php echo (int)$customDays; ?>">
                    <?php endif; ?>
                    <input type="hidden" name="inventory_limit" value="<?php echo htmlspecialchars($inventoryLimit ?? '10'); ?>">
                    <input type="hidden" name="inventory_sort" value="<?php echo htmlspecialchars($inventorySort ?? 'status'); ?>">
                    <label for="inventory_days_select" style="font-size: 13px; color: #000; margin-right: 6px;">Days:</label>
                    <select name="inventory_days" id="inventory_days_select" class="inventory-days-select" onchange="this.form.submit()" title="Number of days shown in this table only (does not change the KPI view above)">
                        <?php
                        $effectiveDays = ($inventoryDays !== null) ? $inventoryDays : (($view === 'custom') ? (int)$customDays : (($view === 'month') ? 30 : 7));
                        $effectiveDays = max(1, min(30, $effectiveDays));
                        for ($d = 1; $d <= 30; $d++):
                        ?>
                        <option value="<?php echo $d; ?>" <?php echo $effectiveDays === $d ? ' selected' : ''; ?>><?php echo $d; ?></option>
                        <?php endfor; ?>
                    </select>
                </form>
                <span style="margin-left: 8px;"></span>
                <form method="get" action="index.php" id="inventory-limit-form" style="display: inline;">
                    <input type="hidden" name="action" value="<?php echo htmlspecialchars($action); ?>">
                    <input type="hidden" name="store" value="<?php echo (int)$storeId; ?>">
                    <input type="hidden" name="date" value="<?php echo htmlspecialchars($date ?? ''); ?>">
                    <input type="hidden" name="view" value="<?php echo htmlspecialchars($view ?? 'week'); ?>">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($dashboardTab); ?>">
                    <input type="hidden" name="inventory_sort" value="<?php echo htmlspecialchars($inventorySort ?? 'status'); ?>">
                    <?php if ($view === 'custom'): ?>
                    <input type="hidden" name="days" value="<?php echo (int)$customDays; ?>">
                    <?php endif; ?>
                    <?php if ($inventoryDays !== null): ?>
                    <input type="hidden" name="inventory_days" value="<?php echo (int)$inventoryDays; ?>">
                    <?php endif; ?>
                    <label for="inventory_limit_select" style="font-size: 13px; color: #000; margin-right: 6px;">Show:</label>
                    <select name="inventory_limit" id="inventory_limit_select" class="inventory-limit-select" onchange="this.form.submit()" style="color: #000; background: #fff; padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">
                        <option value="10" <?php echo $inventoryLimit === '10' ? ' selected' : ''; ?>>Top 10</option>
                        <option value="15" <?php echo $inventoryLimit === '15' ? ' selected' : ''; ?>>Top 15</option>
                        <option value="20" <?php echo $inventoryLimit === '20' ? ' selected' : ''; ?>>Top 20</option>
                        <option value="all" <?php echo $inventoryLimit === 'all' ? ' selected' : ''; ?>>All</option>
                    </select>
                </form>
                <span style="margin-left: 12px;"></span>
                <button type="button" class="btn btn-primary" onclick="openOrderModal()" title="Create a new order (choose product and vendor)">+ Add Order</button>
                <button type="button" class="btn btn-primary" onclick="showProductModal()">+ Add Product</button>
                <button type="button" class="btn btn-primary" onclick="showVendorModal()">+ Add Vendor</button>
                <?php if ($canWriteInventory && !empty($vendors)): ?>
                <?php
                $vendorsSorted = $vendors;
                usort($vendorsSorted, function ($a, $b) { return strcasecmp($a['name'] ?? '', $b['name'] ?? ''); });
                ?>
                <div class="vendor-edit-dropdown">
                    <div class="vendor-edit-label">Edit existing vendor</div>
                    <button type="button" class="btn btn-vendor-edit" onclick="toggleVendorEditDropdown()" title="Choose a vendor to edit">Choose vendor to edit <span class="dropdown-arrow">▼</span></button>
                    <div id="vendor-edit-dropdown-menu" class="vendor-edit-dropdown-menu" role="listbox">
                        <?php foreach ($vendorsSorted as $v): ?>
                        <button type="button" class="vendor-edit-dropdown-item" role="option" onclick="selectVendorToEdit(<?php echo (int)$v['id']; ?>)" title="Edit <?php echo htmlspecialchars($v['name']); ?>"><?php echo htmlspecialchars($v['name']); ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div id="inventory-content" class="inventory-content">
            <?php include 'includes/inventory-section.php'; ?>
        </div>
    </div>
    
    <?php
    $chartBaseUrl = '?action=' . urlencode($action) . '&store=' . (int)$storeId . '&date=' . urlencode($date) . '&view=' . urlencode($view ?? 'week') . '&tab=inventory';
    $chartProductIdParam = $chartProductId ? '&chart_product_id=' . (int)$chartProductId : '';
    ?>
    <div id="inventory-chart" class="chart-container inventory-chart-container">
        <div class="chart-header">
            <h2>
                <button type="button" class="collapse-toggle" onclick="toggleInventoryChartCollapse()">
                    <span id="inventory-chart-toggle-icon">▼</span> Product daily: On Hand, Sales, Purchases, Price
                </button>
            </h2>
            <p style="font-size: 13px; color: #000; margin: 4px 0 0;">
                <?php if ($chartProductId && $chartProductSku !== ''): ?>
                    Showing <strong><?php echo htmlspecialchars($chartProductSku); ?></strong>. Choose days:
                <?php else: ?>
                    Click a <strong>SKU/Barcode</strong> in the table above to view that product&apos;s daily data here.
                <?php endif; ?>
            </p>
            <div class="chart-legend-controls" style="margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                <span style="font-size: 13px; color: #000; font-weight: 500;">Days:</span>
                <a href="<?php echo $chartBaseUrl . $chartProductIdParam . '&chart_days=7'; ?>#inventory-chart" class="btn-small <?php echo ($chartDays ?? 30) === 7 ? 'btn-primary' : ''; ?>">7</a>
                <a href="<?php echo $chartBaseUrl . $chartProductIdParam . '&chart_days=30'; ?>#inventory-chart" class="btn-small <?php echo ($chartDays ?? 30) === 30 ? 'btn-primary' : ''; ?>">30</a>
                <a href="<?php echo $chartBaseUrl . $chartProductIdParam . '&chart_days=90'; ?>#inventory-chart" class="btn-small <?php echo ($chartDays ?? 30) === 90 ? 'btn-primary' : ''; ?>">90</a>
                <?php if ($chartProductId && !empty($chartDateArray)): ?>
                <button type="button" class="btn-small" onclick="toggleInventoryChartSeries('onhand')" id="toggle-onhand">Hide On-Hand</button>
                <button type="button" class="btn-small" onclick="toggleInventoryChartSeries('sales')" id="toggle-sales">Hide Sales</button>
                <button type="button" class="btn-small" onclick="toggleInventoryChartSeries('purchases')" id="toggle-purchases">Hide Purchases</button>
                <button type="button" class="btn-small" onclick="toggleInventoryChartSeries('price')" id="toggle-price">Hide Price</button>
                <button type="button" class="btn-small" onclick="showAllInventorySeries()">Show All</button>
                <?php endif; ?>
            </div>
        </div>
        <div id="inventory-chart-content" class="chart-content">
            <?php if ($chartProductId && !empty($chartDateArray)): ?>
            <textarea id="inventory-chart-data" style="display:none" readonly><?php echo htmlspecialchars($inventoryChartJson, ENT_QUOTES, 'UTF-8'); ?></textarea>
            <canvas id="inventoryChart" style="max-height: 400px; height: 400px;"></canvas>
            <?php else: ?>
            <p id="inventory-chart-placeholder" style="padding: 40px; text-align: center; color: #000;">Select a product by clicking its SKU/Barcode in the table above.</p>
            <canvas id="inventoryChart" style="max-height: 400px; height: 400px; display: none;"></canvas>
            <?php endif; ?>
        </div>
    </div>

    <?php endif; ?>
    
    <?php endif; // end storeId/empty stores check (closes line 115) ?>
<?php elseif ($action === 'timeclock'): ?>
    <textarea id="tc_staff_schedule_data" hidden><?php echo htmlspecialchars(json_encode($timeClockStaffScheduleRows)); ?></textarea>
    <textarea id="tc_staff_pto_data" hidden><?php echo htmlspecialchars(json_encode($timeClockCalendarPtoRows)); ?></textarea>
    <textarea id="tc_staff_worked_data" hidden><?php echo htmlspecialchars(json_encode($timeClockCalendarWorkedRows)); ?></textarea>
    <input type="hidden" id="tc_staff_calendar_today" value="<?php echo htmlspecialchars((new DateTime('now', new DateTimeZone(TIMEZONE)))->format('Y-m-d')); ?>">
    <input type="hidden" id="tc_staff_calendar_anchor" value="<?php echo htmlspecialchars($date); ?>">
    <?php if ($isKioskMode): ?>
    <div class="kiosk-shell">
        <div class="kiosk-header-card">
            <div>
                <h1>Time Clock Kiosk</h1>
                <p class="app-hero-subtitle">Quick punch terminal for employees.</p>
                <div class="store-info">Store: <strong><?php echo htmlspecialchars($currentStore['name'] ?? 'N/A'); ?></strong></div>
            </div>
            <div class="kiosk-header-actions">
                <a class="btn" href="?action=timeclock&store=<?php echo (int)$storeId; ?>&date=<?php echo urlencode($date); ?>">Exit Kiosk</a>
            </div>
        </div>

        <div class="store-selector">
            <?php foreach ($stores as $store): ?>
                <a href="?action=timeclock&kiosk=1&store=<?php echo (int)$store['id']; ?>&date=<?php echo urlencode($date); ?>" class="store-pill <?php echo ($store['id'] == $storeId) ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($store['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="timeclock-mobile-card kiosk-punch-card">
            <h2>Punch In/Out</h2>
            <?php if ($timeClockSelectedDateLocked): ?>
                <div class="timeclock-lock-notice">
                    Selected date <strong><?php echo htmlspecialchars($date); ?></strong> is inside a locked payroll period. Punches are blocked until unlocked.
                </div>
            <?php endif; ?>
            <form method="POST" action="" id="timeclock-punch-form" class="kiosk-punch-form" data-kiosk-idle-seconds="<?php echo (int)($timeClockGeoSettings['kiosk_idle_seconds'] ?? 75); ?>">
                <input type="hidden" name="timeclock_punch" value="1">
                <input type="hidden" name="store_id" value="<?php echo (int)$storeId; ?>">
                <input type="hidden" name="action" value="timeclock">
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                <input type="hidden" name="kiosk" value="1">
                <input type="hidden" name="gps_lat" id="tc_gps_lat" value="">
                <input type="hidden" name="gps_lng" id="tc_gps_lng" value="">
                <input type="hidden" name="gps_accuracy_m" id="tc_gps_accuracy_m" value="">
                <input type="hidden" name="gps_status" id="tc_gps_status" value="unavailable">

                <div class="form-group">
                    <label for="tc_employee_id">Employee</label>
                    <select id="tc_employee_id" name="employee_id" required>
                        <option value="">Select employee...</option>
                        <?php foreach ($timeClockEmployees as $emp): ?>
                            <option value="<?php echo (int)$emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="kiosk-employee-grid">
                    <?php foreach ($timeClockEmployees as $emp): ?>
                        <button type="button" class="kiosk-emp-btn" data-employee-id="<?php echo (int)$emp['id']; ?>" onclick="var s=document.getElementById('tc_employee_id'); var p=document.getElementById('tc_pin'); if(s){s.value=this.getAttribute('data-employee-id')||'';} if(p){p.focus();}">
                            <?php echo htmlspecialchars($emp['full_name']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="form-group">
                    <label for="tc_pin">PIN</label>
                    <input type="password" id="tc_pin" name="pin" inputmode="numeric" pattern="[0-9]*" maxlength="10" required placeholder="Enter PIN">
                </div>
                <div class="form-group">
                    <label for="tc_note">Note (optional)</label>
                    <input type="text" id="tc_note" name="punch_note" maxlength="255" placeholder="Optional note">
                </div>
                <div class="timeclock-status-line" id="tc_geo_status">GPS: Not captured yet</div>
                <div class="timeclock-buttons kiosk-timeclock-buttons">
                    <button type="submit" name="punch_type" value="in" class="btn btn-primary timeclock-btn-in" <?php echo $timeClockSelectedDateLocked ? 'disabled title="Selected date is in a locked payroll period"' : ''; ?>>Clock In</button>
                    <button type="submit" name="punch_type" value="out" class="btn timeclock-btn-out" <?php echo $timeClockSelectedDateLocked ? 'disabled title="Selected date is in a locked payroll period"' : ''; ?>>Clock Out</button>
                </div>
                <div class="kiosk-queue-status" id="kiosk_queue_status">Queued punches: 0</div>
                <div class="staff-schedule-widget" data-staff-schedule-widget="kiosk">
                    <h3>Your Schedule (This Week)</h3>
                    <div class="form-group">
                        <label for="tc_staff_view_employee_kiosk">Employee</label>
                        <select id="tc_staff_view_employee_kiosk" class="staff-schedule-employee-select">
                            <option value="">Select employee...</option>
                            <?php foreach ($timeClockEmployees as $emp): ?>
                                <option value="<?php echo (int)$emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="staff-schedule-list"></div>
                </div>
                <p class="timeclock-muted kiosk-idle-note">For privacy, kiosk clears PIN/selection after <?php echo (int)($timeClockGeoSettings['kiosk_idle_seconds'] ?? 75); ?> seconds of inactivity.</p>
                <div class="kiosk-reconcile-card">
                    <div class="kiosk-reconcile-header">
                        <strong>Offline Sync Reconciliation</strong>
                        <div class="kiosk-reconcile-actions">
                            <button type="button" class="btn" id="kiosk_retry_failed_all">Retry All Failed</button>
                            <button type="button" class="btn" id="kiosk_clear_failed_all">Clear Failed</button>
                        </div>
                    </div>
                    <table class="history-table kiosk-failed-table">
                        <thead>
                            <tr><th>When</th><th>Employee</th><th>Punch</th><th>Reason</th><th>Action</th></tr>
                        </thead>
                        <tbody id="kiosk_failed_tbody">
                            <tr><td colspan="5" class="timeclock-muted">No failed offline punches.</td></tr>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="header app-hero">
        <div>
            <h1>Time Clock</h1>
            <p class="app-hero-subtitle">Scheduling, payroll integrity, and manager approvals.</p>
            <div class="store-info">Store: <strong><?php echo htmlspecialchars($currentStore['name'] ?? 'N/A'); ?></strong></div>
            <div class="store-selector">
                <?php foreach ($stores as $store): ?>
                    <a href="?action=timeclock&store=<?php echo (int)$store['id']; ?>&date=<?php echo urlencode($date); ?>" class="store-pill <?php echo ($store['id'] == $storeId) ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($store['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="header-controls">
            <div class="date-selector">
                <label for="entry-date">Date:</label>
                <input type="date" id="entry-date" value="<?php echo htmlspecialchars($date); ?>">
            </div>
            <a class="btn" href="?action=timeclock&kiosk=1&store=<?php echo (int)$storeId; ?>&date=<?php echo urlencode($date); ?>">Open Kiosk Mode</a>
        </div>
    </div>

    <div class="timeclock-lock-legend">
        <div class="timeclock-lock-legend-row">
            <span class="<?php echo $timeClockSelectedDateLocked ? 'timeclock-badge-danger' : 'timeclock-badge-ok'; ?>">
                Selected date <?php echo htmlspecialchars($date); ?>: <?php echo $timeClockSelectedDateLocked ? 'LOCKED' : 'OPEN'; ?>
            </span>
            <span class="timeclock-badge-warning">Disabled buttons = action blocked by locked payroll period</span>
        </div>
        <div class="timeclock-lock-legend-help">
            Approve actions are disabled only for overlapping requests. Deny remains available.
        </div>
    </div>

    <div class="timeclock-launcher-card">
        <h2>Time Clock Functions</h2>
        <p class="timeclock-mobile-help">Open a function in a focused popup window.</p>
        <div class="timeclock-hub-stats">
            <span class="timeclock-badge-ok">Open shifts: <?php echo (int)count($timeClockOpenShifts); ?></span>
            <span class="timeclock-badge-warning">Pending punch edits: <?php echo (int)count($timeClockPendingEditRequests); ?></span>
            <span class="timeclock-badge-warning">Pending PTO: <?php echo (int)count($timeClockPendingPtoRequests); ?></span>
            <span class="<?php echo (int)($timeClockTaskSummary['missed'] ?? 0) > 0 ? 'timeclock-badge-danger' : 'timeclock-badge-ok'; ?>">
                Tasks missed: <?php echo (int)($timeClockTaskSummary['missed'] ?? 0); ?>
            </span>
            <span class="timeclock-badge-danger">Edit requests locked: <?php echo (int)count(array_filter($timeClockPendingEditRequestLockMap)); ?></span>
            <span class="<?php echo !empty($timeClockKioskOpenFailures) ? 'timeclock-badge-danger' : 'timeclock-badge-ok'; ?>">
                Kiosk sync failures: <?php echo (int)count($timeClockKioskOpenFailures); ?>
            </span>
            <span class="<?php echo !empty($timeClockNoShowAlerts) ? 'timeclock-badge-danger' : 'timeclock-badge-ok'; ?>">
                Missed clock-ins: <?php echo (int)count($timeClockNoShowAlerts); ?>
            </span>
        </div>
        <div class="timeclock-quick-actions">
            <button type="button" class="timeclock-quick-btn" data-target="tc_panel_payroll">Run Payroll</button>
            <button type="button" class="timeclock-quick-btn" data-target="tc_panel_schedule">Add Schedule</button>
            <button type="button" class="timeclock-quick-btn" data-target="tc_panel_admin">Review Approvals</button>
        </div>
        <div class="timeclock-launcher-grid">
            <button type="button" class="timeclock-launcher-btn" data-target="tc_panel_punch" data-icon="⏱">
                <span class="timeclock-launcher-title">Punch In/Out</span>
                <span class="timeclock-launcher-subtitle">Employee clock-in and clock-out terminal</span>
            </button>
            <button type="button" class="timeclock-launcher-btn" data-target="tc_panel_schedule" data-icon="🗓">
                <span class="timeclock-launcher-title">Weekly Schedule</span>
                <span class="timeclock-launcher-subtitle">Coverage, gaps, and overtime planning</span>
            </button>
            <button type="button" class="timeclock-launcher-btn" data-target="tc_panel_staff_schedule" data-icon="🗂">
                <span class="timeclock-launcher-title">Schedule Calendar Center</span>
                <span class="timeclock-launcher-subtitle">Weekly/monthly staff calendar and manager day board</span>
            </button>
            <button type="button" class="timeclock-launcher-btn" data-target="tc_panel_tasks" data-icon="✅">
                <span class="timeclock-launcher-title">Task List</span>
                <span class="timeclock-launcher-subtitle">Assign by day/shift, checkoff, and missed visibility</span>
            </button>
            <?php if ($canManageTimeclock): ?>
            <button type="button" class="timeclock-launcher-btn" data-target="tc_panel_settings" data-icon="📡">
                <span class="timeclock-launcher-title">Location & Kiosk Settings</span>
                <span class="timeclock-launcher-subtitle">Geofence policy, radius, GPS behavior, kiosk timeout</span>
            </button>
            <?php endif; ?>
            <button type="button" class="timeclock-launcher-btn" data-target="tc_panel_pto" data-icon="🏖">
                <span class="timeclock-launcher-title">PTO / Leave Setup</span>
                <span class="timeclock-launcher-subtitle">Policies, balances, and employee requests</span>
            </button>
            <button type="button" class="timeclock-launcher-btn" data-target="tc_panel_payroll" data-icon="💵">
                <span class="timeclock-launcher-title">Payroll</span>
                <span class="timeclock-launcher-subtitle">Periods, lock/unlock, run summary, export</span>
            </button>
            <button type="button" class="timeclock-launcher-btn" data-target="tc_panel_requests" data-icon="📝">
                <span class="timeclock-launcher-title">Punch Edit Requests</span>
                <span class="timeclock-launcher-subtitle">Submit missed punch and shift corrections</span>
            </button>
            <button type="button" class="timeclock-launcher-btn" data-target="tc_panel_live" data-icon="📍">
                <span class="timeclock-launcher-title">Live Floor View</span>
                <span class="timeclock-launcher-subtitle">Who is clocked in and recent punches</span>
            </button>
            <button type="button" class="timeclock-launcher-btn" data-target="tc_panel_admin" data-icon="🛡">
                <span class="timeclock-launcher-title">Approvals & Audit</span>
                <span class="timeclock-launcher-subtitle">Manager review queue and audit trail</span>
            </button>
        </div>
    </div>
    <div id="timeclock-popup-backdrop" class="timeclock-popup-backdrop" aria-hidden="true"></div>

    <div class="timeclock-mobile-card timeclock-popup-section" id="tc_panel_punch">
        <h2>Punch In/Out</h2>
        <p class="timeclock-mobile-help">Mobile-friendly punch screen. Network connection is required.</p>
        <?php if ($timeClockSelectedDateLocked): ?>
            <div class="timeclock-lock-notice">
                Selected date <strong><?php echo htmlspecialchars($date); ?></strong> is inside a locked payroll period. Punches are blocked until unlocked.
            </div>
        <?php endif; ?>
        <form method="POST" action="" id="timeclock-punch-form">
            <input type="hidden" name="timeclock_punch" value="1">
            <input type="hidden" name="store_id" value="<?php echo (int)$storeId; ?>">
            <input type="hidden" name="action" value="timeclock">
            <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
            <input type="hidden" name="gps_lat" id="tc_gps_lat" value="">
            <input type="hidden" name="gps_lng" id="tc_gps_lng" value="">
            <input type="hidden" name="gps_accuracy_m" id="tc_gps_accuracy_m" value="">
            <input type="hidden" name="gps_status" id="tc_gps_status" value="unavailable">
            <div class="form-group">
                <label for="tc_employee_id">Employee</label>
                <select id="tc_employee_id" name="employee_id" required>
                    <option value="">Select employee...</option>
                    <?php foreach ($timeClockEmployees as $emp): ?>
                        <option value="<?php echo (int)$emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="tc_pin">PIN</label>
                <input type="password" id="tc_pin" name="pin" inputmode="numeric" pattern="[0-9]*" maxlength="10" required placeholder="Enter PIN">
            </div>
            <div class="form-group">
                <label for="tc_note">Note (optional)</label>
                <input type="text" id="tc_note" name="punch_note" maxlength="255" placeholder="Optional note">
            </div>
            <div class="timeclock-status-line" id="tc_geo_status">GPS: Not captured yet</div>
            <div class="timeclock-buttons">
                <button type="submit" name="punch_type" value="in" class="btn btn-primary timeclock-btn-in" <?php echo $timeClockSelectedDateLocked ? 'disabled title="Selected date is in a locked payroll period"' : ''; ?>>Clock In</button>
                <button type="submit" name="punch_type" value="out" class="btn timeclock-btn-out" <?php echo $timeClockSelectedDateLocked ? 'disabled title="Selected date is in a locked payroll period"' : ''; ?>>Clock Out</button>
            </div>
        </form>
    </div>

    <div class="timeclock-mobile-card timeclock-popup-section" id="tc_panel_schedule">
        <h2>Weekly Schedule Calendar</h2>
        <?php if ($timeClockScheduleWeekRange): ?>
            <p class="timeclock-mobile-help">
                Week: <strong><?php echo htmlspecialchars($timeClockScheduleWeekRange['start']); ?></strong> to <strong><?php echo htmlspecialchars($timeClockScheduleWeekRange['end']); ?></strong>
                | Store hours (selected date): <strong><?php echo !empty($timeClockSelectedDateHours['enabled']) ? htmlspecialchars(((string)($timeClockSelectedDateHours['open'] ?? '09:00')) . ' - ' . ((string)($timeClockSelectedDateHours['close'] ?? '21:00'))) : 'CLOSED'; ?></strong>
                | Status: <strong><?php echo htmlspecialchars($timeClockScheduleWeekStatus['status'] ?? 'DRAFT'); ?></strong>
            </p>
        <?php endif; ?>
        <div class="timeclock-week-nav">
            <span class="timeclock-badge-ok">Store: <?php echo htmlspecialchars((string)($currentStore['name'] ?? ('#' . (int)$storeId))); ?></span>
            <span class="timeclock-badge-warning">Week: <?php echo htmlspecialchars((string)($timeClockScheduleWeekRange['start'] ?? '')); ?> to <?php echo htmlspecialchars((string)($timeClockScheduleWeekRange['end'] ?? '')); ?></span>
            <div class="timeclock-week-nav-actions">
                <a class="btn" href="?action=timeclock&store=<?php echo (int)$storeId; ?>&date=<?php echo urlencode((string)($timeClockPrevWeekDate ?? $date)); ?>&panel=tc_panel_schedule">Prev Week</a>
                <a class="btn" href="?action=timeclock&store=<?php echo (int)$storeId; ?>&date=<?php echo urlencode((string)($timeClockThisWeekDate ?? $date)); ?>&panel=tc_panel_schedule">This Week</a>
                <a class="btn" href="?action=timeclock&store=<?php echo (int)$storeId; ?>&date=<?php echo urlencode((string)($timeClockNextWeekDate ?? $date)); ?>&panel=tc_panel_schedule">Next Week</a>
            </div>
        </div>
        <?php if ($canManageTimeclock): ?>
            <div class="timeclock-dnd-card">
                <div class="timeclock-dnd-header">
                    <h3>Manager Staffing Board (15-min snap)</h3>
                    <p class="timeclock-mobile-help">Add employees to a day, auto-create a 3:00 PM block, then drag the block or its top/bottom handles.</p>
                </div>
                <div class="timeclock-dnd-toolbar">
                    <span id="tc_lane_week_status" class="<?php echo (($timeClockScheduleWeekStatus['status'] ?? 'DRAFT') === 'PUBLISHED') ? 'timeclock-badge-ok' : 'timeclock-badge-warning'; ?>">
                        Week status: <?php echo htmlspecialchars((string)($timeClockScheduleWeekStatus['status'] ?? 'DRAFT')); ?>
                    </span>
                    <button type="button" class="btn" id="tc_lane_copy_prev_btn">Copy Previous Week</button>
                    <button type="button" class="btn btn-primary" id="tc_lane_publish_btn">Publish Week</button>
                    <button type="button" class="btn" id="tc_lane_unpublish_btn">Unpublish Week</button>
                </div>
                <div class="timeclock-dnd-apply">
                    <div class="form-group">
                        <label for="tc_lane_apply_employee">Apply Employee</label>
                        <select id="tc_lane_apply_employee">
                            <option value="">Select employee...</option>
                            <?php foreach ($timeClockEmployees as $emp): ?>
                                <option value="<?php echo (int)$emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tc_lane_apply_role">Role</label>
                        <select id="tc_lane_apply_role">
                            <?php foreach ($timeClockRoleOptions as $roleOpt): ?>
                                <option value="<?php echo htmlspecialchars($roleOpt); ?>" <?php echo strcasecmp($roleOpt, 'Employee') === 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars($roleOpt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="timeclock-dnd-daypick">
                        <?php foreach (($timeClockScheduleCalendar['days'] ?? []) as $dayPick): ?>
                            <label><input type="checkbox" class="tc_lane_apply_day" value="<?php echo htmlspecialchars((string)($dayPick['date'] ?? '')); ?>"> <?php echo htmlspecialchars((string)($dayPick['label'] ?? 'Day')); ?></label>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn" id="tc_lane_apply_days_btn">Apply to Selected Days (3:00 PM default)</button>
                </div>
                <div class="timeclock-dnd-warnings">
                    <span id="tc_lane_ot_warning" class="timeclock-badge-warning">Overtime warning: none</span>
                    <span id="tc_lane_gap_warning" class="timeclock-badge-ok">Coverage warning: fully covered</span>
                </div>
                <div id="tc_lane_role_legend" class="timeclock-dnd-role-legend"></div>
                <div id="tc_lane_board"></div>
            </div>
        <?php endif; ?>
        <div class="timeclock-defaults-row">
            <div class="form-group">
                <label for="tc_sched_default_employee">Default Employee</label>
                <select id="tc_sched_default_employee">
                    <option value="">Choose default...</option>
                    <?php foreach ($timeClockEmployees as $emp): ?>
                        <option value="<?php echo (int)$emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="tc_sched_default_role">Default Role</label>
                <select id="tc_sched_default_role">
                    <?php foreach ($timeClockRoleOptions as $roleOpt): ?>
                        <option value="<?php echo htmlspecialchars($roleOpt); ?>" <?php echo strcasecmp($roleOpt, 'Employee') === 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars($roleOpt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="tc_sched_default_manager">Default Manager Name</label>
                <input type="text" id="tc_sched_default_manager" placeholder="Manager name for quick add/publish">
            </div>
        </div>

        <div class="timeclock-warning-row">
            <?php foreach (($timeClockScheduleCalendar['coverage_by_day'] ?? []) as $dayKey => $cov): ?>
                <?php if (($cov['gap_minutes'] ?? 0) > 0): ?>
                    <span class="timeclock-badge-warning">
                        <?php echo htmlspecialchars($dayKey); ?> uncovered: <?php echo (int)floor(((int)$cov['gap_minutes']) / 60); ?>h <?php echo ((int)$cov['gap_minutes']) % 60; ?>m
                    </span>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php
            $otWarnings = 0;
            foreach (($timeClockScheduleCalendar['employee_hours'] ?? []) as $eh) {
                if (($eh['overtime_hours'] ?? 0) > 0) $otWarnings++;
            }
            ?>
            <?php if ($otWarnings > 0): ?>
                <span class="timeclock-badge-danger"><?php echo (int)$otWarnings; ?> employee(s) over 40h this week</span>
            <?php endif; ?>
            <?php if (empty($timeClockScheduleCalendar['coverage_by_day']) && empty($timeClockScheduleCalendar['employee_hours'])): ?>
                <span class="timeclock-muted">No schedule data yet.</span>
            <?php endif; ?>
        </div>

        <div class="timeclock-calendar-grid">
            <?php foreach (($timeClockScheduleCalendar['days'] ?? []) as $day): ?>
                <?php $dayKey = $day['date']; $cov = $timeClockScheduleCalendar['coverage_by_day'][$dayKey] ?? null; ?>
                <div class="timeclock-calendar-day">
                    <div class="timeclock-calendar-day-header">
                        <strong><?php echo htmlspecialchars($day['label']); ?></strong>
                        <?php if ($cov && (int)$cov['gap_minutes'] > 0): ?>
                            <span class="timeclock-badge-danger">Gap <?php echo (int)floor(((int)$cov['gap_minutes']) / 60); ?>h <?php echo ((int)$cov['gap_minutes']) % 60; ?>m</span>
                        <?php else: ?>
                            <span class="timeclock-badge-ok">Covered</span>
                        <?php endif; ?>
                    </div>
                    <div class="timeclock-calendar-shifts">
                        <?php $dayShifts = $timeClockScheduleCalendar['shifts_by_day'][$dayKey] ?? []; ?>
                        <?php if (empty($dayShifts)): ?>
                            <div class="timeclock-muted">No shifts.</div>
                        <?php else: ?>
                            <?php foreach ($dayShifts as $s): ?>
                                <div class="timeclock-shift-chip">
                                    <div><strong><?php echo htmlspecialchars($s['employee_name']); ?></strong> (<?php echo htmlspecialchars($s['role_name']); ?>)</div>
                                    <div><?php echo htmlspecialchars($s['display_start']); ?> - <?php echo htmlspecialchars($s['display_end']); ?> | Break <?php echo (int)$s['break_minutes']; ?>m</div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($cov && !empty($cov['gap_windows'])): ?>
                        <div class="timeclock-gap-list">
                            <?php foreach ($cov['gap_windows'] as $gap): ?>
                                <div class="timeclock-gap-item">Uncovered: <?php echo htmlspecialchars($gap['start']); ?> - <?php echo htmlspecialchars($gap['end']); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($canManageTimeclock): ?>
                    <form method="POST" action="" class="timeclock-day-add-form">
                        <input type="hidden" name="timeclock_schedule_add_shift_day" value="1">
                        <input type="hidden" name="store_id" value="<?php echo (int)$storeId; ?>">
                        <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                        <input type="hidden" name="schedule_date" value="<?php echo htmlspecialchars($dayKey); ?>">
                        <select name="employee_id" class="schedule-employee-input" required>
                            <option value="">Employee...</option>
                            <?php foreach ($timeClockEmployees as $emp): ?>
                                <option value="<?php echo (int)$emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="timeclock-time-row">
                            <input type="time" name="schedule_start_time" required value="09:00">
                            <input type="time" name="schedule_end_time" required value="17:00">
                        </div>
                        <div class="timeclock-time-row">
                            <select name="role_name" class="schedule-role-input">
                                <?php foreach ($timeClockRoleOptions as $roleOpt): ?>
                                    <option value="<?php echo htmlspecialchars($roleOpt); ?>" <?php echo strcasecmp($roleOpt, 'Employee') === 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars($roleOpt); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="break_minutes" min="0" value="0" placeholder="Break">
                        </div>
                        <input type="text" name="manager_name" class="schedule-manager-input" required value="<?php echo htmlspecialchars($currentUserName); ?>" readonly>
                        <button type="submit" class="btn btn-primary">Add Shift</button>
                    </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top: 12px;">
            <h3 style="margin-bottom: 8px;">Weekly Hours / Overtime</h3>
            <?php if (empty($timeClockScheduleCalendar['employee_hours'])): ?>
                <p class="timeclock-muted">No hours yet.</p>
            <?php else: ?>
                <table class="history-table">
                    <thead>
                        <tr><th>Employee</th><th>Scheduled Hours</th><th>Overtime (&gt;40)</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($timeClockScheduleCalendar['employee_hours'] as $eh): ?>
                            <?php $ot = (float)($eh['overtime_hours'] ?? 0); ?>
                            <tr class="<?php echo $ot > 0 ? 'timeclock-row-danger' : ''; ?>">
                                <td><?php echo htmlspecialchars($eh['employee_name']); ?></td>
                                <td><?php echo number_format((float)($eh['hours'] ?? 0), 2); ?> h</td>
                                <td><?php echo $ot > 0 ? number_format($ot, 2) . ' h (WARNING)' : '0.00 h'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div style="margin-top: 12px;">
            <?php if ($canManageTimeclock): ?>
            <form method="POST" action="" class="timeclock-approve-controls">
                <input type="hidden" name="timeclock_schedule_publish_week" value="1">
                <input type="hidden" name="store_id" value="<?php echo (int)$storeId; ?>">
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                <input type="hidden" name="week_start_date" value="<?php echo htmlspecialchars($timeClockScheduleWeekRange['start'] ?? ''); ?>">
                <input type="hidden" name="week_end_date" value="<?php echo htmlspecialchars($timeClockScheduleWeekRange['end'] ?? ''); ?>">
                <input type="text" name="manager_name" class="schedule-manager-input" required value="<?php echo htmlspecialchars($currentUserName); ?>" readonly>
                <button type="submit" class="btn btn-primary">Publish Week</button>
            </form>
            <?php else: ?>
            <p class="timeclock-muted">View-only role: manager permission required to add/publish schedule.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="timeclock-mobile-card timeclock-popup-section" id="tc_panel_staff_schedule">
        <h2>Schedule Calendar Center</h2>
        <p class="timeclock-mobile-help">High-visibility calendar for staff plus a manager-ready daily operations board.</p>

        <div class="staff-calendar-shell">
            <div class="staff-calendar-controls">
                <div class="form-group">
                    <label for="tc_staff_calendar_employee">Employee</label>
                    <select id="tc_staff_calendar_employee">
                        <option value="">Select employee...</option>
                        <?php foreach ($timeClockEmployees as $emp): ?>
                            <option value="<?php echo (int)$emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tc_staff_calendar_mode">View</label>
                    <select id="tc_staff_calendar_mode">
                        <option value="week">Weekly</option>
                        <option value="month">Monthly</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tc_staff_calendar_date">Anchor Date</label>
                    <input type="date" id="tc_staff_calendar_date" value="<?php echo htmlspecialchars($date); ?>">
                </div>
                <div class="staff-calendar-actions">
                    <button type="button" class="btn" id="tc_staff_calendar_prev">Prev</button>
                    <button type="button" class="btn" id="tc_staff_calendar_today_btn">Today</button>
                    <button type="button" class="btn" id="tc_staff_calendar_next">Next</button>
                </div>
            </div>
            <div class="staff-calendar-legend">
                <span class="timeclock-badge-danger">Today (working)</span>
                <span class="timeclock-badge-ok">Scheduled</span>
                <span class="timeclock-badge-warning">Vacation / PTO</span>
                <span class="staff-calendar-badge-off">Day Off</span>
            </div>
            <div id="tc_staff_calendar_title" class="staff-calendar-title"></div>
            <div id="tc_staff_calendar_grid" class="staff-calendar-grid"></div>
        </div>

        <div class="manager-day-board">
            <div class="manager-day-board-header">
                <h3>Manager Day Board</h3>
                <div class="form-group">
                    <label for="tc_manager_day_date">Day</label>
                    <input type="date" id="tc_manager_day_date" value="<?php echo htmlspecialchars($date); ?>">
                </div>
            </div>
            <div class="manager-day-board-subtitle">Quick view of who is scheduled and who actually clocked in/out.</div>
            <div class="history-table">
                <table>
                    <thead>
                    <tr><th>Employee</th><th>Scheduled</th><th>Worked</th><th>Status</th></tr>
                    </thead>
                    <tbody id="tc_manager_day_tbody">
                    <tr><td colspan="4" class="timeclock-muted">No data.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="timeclock-mobile-card timeclock-popup-section" id="tc_panel_tasks">
        <h2>Task List</h2>
        <p class="timeclock-mobile-help">Assign daily/shift tasks and track open, done, and missed items.</p>

        <div class="timeclock-hub-stats">
            <span class="timeclock-badge-warning">Open: <?php echo (int)($timeClockTaskSummary['open'] ?? 0); ?></span>
            <span class="timeclock-badge-ok">Done: <?php echo (int)($timeClockTaskSummary['done'] ?? 0); ?></span>
            <span class="<?php echo (int)($timeClockTaskSummary['missed'] ?? 0) > 0 ? 'timeclock-badge-danger' : 'timeclock-badge-ok'; ?>">
                Missed: <?php echo (int)($timeClockTaskSummary['missed'] ?? 0); ?>
            </span>
            <span class="timeclock-badge-warning">Date: <?php echo htmlspecialchars($date); ?></span>
        </div>

        <?php if ($canManageTimeclock): ?>
            <form method="POST" action="" class="timeclock-edit-grid">
                <input type="hidden" name="timeclock_task_create" value="1">
                <input type="hidden" name="store_id" value="<?php echo (int)$storeId; ?>">
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                <div class="form-group">
                    <label for="tc_task_date">Task Date</label>
                    <input type="date" id="tc_task_date" name="task_date" required value="<?php echo htmlspecialchars($date); ?>">
                </div>
                <div class="form-group">
                    <label for="tc_task_title">Task Title</label>
                    <input type="text" id="tc_task_title" name="task_title" required maxlength="200" placeholder="e.g., Clean espresso machine">
                </div>
                <div class="form-group">
                    <label for="tc_task_assignee">Assign Employee (optional)</label>
                    <select id="tc_task_assignee" name="assigned_employee_id">
                        <option value="">Unassigned</option>
                        <?php foreach ($timeClockEmployees as $emp): ?>
                            <option value="<?php echo (int)$emp['id']; ?>"><?php echo htmlspecialchars((string)$emp['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tc_task_shift_id">Link to Shift (optional)</label>
                    <select id="tc_task_shift_id" name="schedule_shift_id">
                        <option value="">No linked shift</option>
                        <?php foreach ($timeClockTaskShiftOptions as $shiftOpt): ?>
                            <option
                                value="<?php echo (int)($shiftOpt['shift_id'] ?? 0); ?>"
                                data-employee-id="<?php echo (int)($shiftOpt['employee_id'] ?? 0); ?>"
                            >
                                <?php echo htmlspecialchars((string)($shiftOpt['employee_name'] ?? 'Employee') . ' (' . (string)($shiftOpt['role_name'] ?? 'Employee') . ') ' . (string)($shiftOpt['start_time_label'] ?? '') . '-' . (string)($shiftOpt['end_time_label'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tc_task_details">Details (optional)</label>
                    <input type="text" id="tc_task_details" name="task_details" maxlength="500" placeholder="Notes or checklist details">
                </div>
                <div class="form-group">
                    <label for="tc_task_manager_name">Manager</label>
                    <input type="text" id="tc_task_manager_name" name="manager_name" required value="<?php echo htmlspecialchars($currentUserName); ?>" readonly>
                </div>
                <div class="form-group" style="display:flex; align-items:flex-end;">
                    <button type="submit" class="btn btn-primary">Create Task</button>
                </div>
            </form>
        <?php else: ?>
            <p class="timeclock-muted">View-only role: manager permission required to create/delete tasks.</p>
        <?php endif; ?>

        <div class="history-table" style="margin-top:12px;">
            <table>
                <thead>
                <tr>
                    <th>Task</th>
                    <th>Assigned</th>
                    <th>Shift</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($timeClockTasksForDate)): ?>
                    <tr><td colspan="5" class="timeclock-muted">No tasks for this date yet.</td></tr>
                <?php else: ?>
                    <?php $timeClockTodayYmd = (new DateTime('now', new DateTimeZone(TIMEZONE)))->format('Y-m-d'); ?>
                    <?php foreach ($timeClockTasksForDate as $taskRow): ?>
                        <?php
                            $taskStatus = strtoupper((string)($taskRow['status'] ?? 'OPEN'));
                            $taskIsDone = $taskStatus === 'DONE';
                            $taskIsMissed = !$taskIsDone && ((string)($taskRow['task_date'] ?? '') < $timeClockTodayYmd);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars((string)($taskRow['title'] ?? '')); ?></strong>
                                <?php if (!empty($taskRow['details'])): ?>
                                    <div class="timeclock-muted"><?php echo htmlspecialchars((string)$taskRow['details']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars((string)($taskRow['assigned_employee_name'] ?? 'Unassigned')); ?></td>
                            <td>
                                <?php if (!empty($taskRow['assigned_shift_start_utc']) && !empty($taskRow['assigned_shift_end_utc'])): ?>
                                    <?php echo htmlspecialchars((string)($taskRow['assigned_shift_role'] ?? 'Employee')); ?>
                                    <div class="timeclock-muted">
                                        <?php echo htmlspecialchars(formatUtcTimestampForDisplay($taskRow['assigned_shift_start_utc'] ?? null) . ' - ' . formatUtcTimestampForDisplay($taskRow['assigned_shift_end_utc'] ?? null)); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="timeclock-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($taskIsDone): ?>
                                    <span class="timeclock-badge-ok">DONE</span>
                                    <?php if (!empty($taskRow['completed_by'])): ?>
                                        <div class="timeclock-muted">By <?php echo htmlspecialchars((string)$taskRow['completed_by']); ?></div>
                                    <?php endif; ?>
                                <?php elseif ($taskIsMissed): ?>
                                    <span class="timeclock-badge-danger">MISSED</span>
                                <?php else: ?>
                                    <span class="timeclock-badge-warning">OPEN</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$taskIsDone): ?>
                                    <form method="POST" action="" class="timeclock-approve-controls">
                                        <input type="hidden" name="timeclock_task_toggle" value="1">
                                        <input type="hidden" name="store_id" value="<?php echo (int)$storeId; ?>">
                                        <input type="hidden" name="task_id" value="<?php echo (int)($taskRow['id'] ?? 0); ?>">
                                        <input type="hidden" name="task_date" value="<?php echo htmlspecialchars($date); ?>">
                                        <input type="hidden" name="task_status" value="DONE">
                                        <select name="employee_id" required>
                                            <option value="">Employee...</option>
                                            <?php foreach ($timeClockEmployees as $emp): ?>
                                                <option value="<?php echo (int)$emp['id']; ?>"><?php echo htmlspecialchars((string)$emp['full_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="password" name="pin" inputmode="numeric" pattern="[0-9]*" maxlength="10" required placeholder="PIN">
                                        <button type="submit" class="btn btn-primary">Mark Done</button>
                                    </form>
                                    <?php if ($canManageTimeclock): ?>
                                        <form method="POST" action="" style="margin-top:6px;">
                                            <input type="hidden" name="timeclock_task_toggle" value="1">
                                            <input type="hidden" name="store_id" value="<?php echo (int)$storeId; ?>">
                                            <input type="hidden" name="task_id" value="<?php echo (int)($taskRow['id'] ?? 0); ?>">
                                            <input type="hidden" name="task_date" value="<?php echo htmlspecialchars($date); ?>">
                                            <input type="hidden" name="task_status" value="DONE">
                                            <input type="hidden" name="manager_name" value="<?php echo htmlspecialchars($currentUserName); ?>">
                                            <button type="submit" class="btn">Manager Complete</button>
                                        </form>
                                    <?php endif; ?>
                                <?php elseif ($canManageTimeclock): ?>
                                    <form method="POST" action="" style="display:inline-block;">
                                        <input type="hidden" name="timeclock_task_toggle" value="1">
                                        <input type="hidden" name="store_id" value="<?php echo (int)$storeId; ?>">
                                        <input type="hidden" name="task_id" value="<?php echo (int)($taskRow['id'] ?? 0); ?>">
                                        <input type="hidden" name="task_date" value="<?php echo htmlspecialchars($date); ?>">
                                        <input type="hidden" name="task_status" value="OPEN">
                                        <input type="hidden" name="manager_name" value="<?php echo htmlspecialchars($currentUserName); ?>">
                                        <button type="submit" class="btn">Reopen</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($canManageTimeclock): ?>
                                    <form method="POST" action="" style="display:inline-block; margin-left:6px;" onsubmit="return confirm('Delete this task?');">
                                        <input type="hidden" name="timeclock_task_delete" value="1">
                                        <input type="hidden" name="store_id" value="<?php echo (int)$storeId; ?>">
                                        <input type="hidden" name="task_id" value="<?php echo (int)($taskRow['id'] ?? 0); ?>">
                                        <input type="hidden" name="task_date" value="<?php echo htmlspecialchars($date); ?>">
                                        <input type="hidden" name="manager_name" value="<?php echo htmlspecialchars($currentUserName); ?>">
                                        <button type="submit" class="btn">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="timeclock-mobile-card timeclock-popup-section" id="tc_panel_settings">
        <h2>Location & Kiosk Settings</h2>
        <p class="timeclock-mobile-help">Configure store geofence policy and kiosk auto-reset timeout.</p>
        <?php if ($canManageTimeclock): ?>
        <form method="POST" action="" class="timeclock-edit-grid">
            <input type="hidden" name="timeclock_geofence_settings_save" value="1">
            <input type="hidden" name="store_id" value="<?php echo (int)$storeId; ?>">
            <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
            <div class="form-group">
                <label><input type="checkbox" name="geofence_enabled" value="1" <?php echo !empty($timeClockGeoSettings['enabled']) ? 'checked' : ''; ?>> Enable geofence for punches</label>
            </div>
            <div class="form-group">
                <label for="tc_geofence_lat">Store Latitude</label>
                <input type="number" id="tc_geofence_lat" name="geofence_lat" step="0.0000001" value="<?php echo htmlspecialchars(number_format((float)($timeClockGeoSettings['lat'] ?? 0), 7, '.', '')); ?>">
            </div>
            <div class="form-group">
                <label for="tc_geofence_lng">Store Longitude</label>
                <input type="number" id="tc_geofence_lng" name="geofence_lng" step="0.0000001" value="<?php echo htmlspecialchars(number_format((float)($timeClockGeoSettings['lng'] ?? 0), 7, '.', '')); ?>">
            </div>
            <div class="form-group">
                <label for="tc_geofence_radius">Radius (meters)</label>
                <input type="number" id="tc_geofence_radius" name="geofence_radius_m" min="5" max="10000" value="<?php echo (int)($timeClockGeoSettings['radius_m'] ?? 120); ?>">
            </div>
            <div class="form-group">
                <label for="tc_geofence_policy">Outside geofence policy</label>
                <select id="tc_geofence_policy" name="geofence_policy">
                    <option value="warn" <?php echo (($timeClockGeoSettings['policy'] ?? 'warn') === 'warn') ? 'selected' : ''; ?>>Warn only (allow punch)</option>
                    <option value="block" <?php echo (($timeClockGeoSettings['policy'] ?? '') === 'block') ? 'selected' : ''; ?>>Block punch</option>
                </select>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="geofence_allow_no_gps" value="1" <?php echo !empty($timeClockGeoSettings['allow_no_gps']) ? 'checked' : ''; ?>> Allow punch when GPS unavailable/denied</label>
            </div>
            <div class="form-group">
                <label for="tc_kiosk_idle_seconds">Kiosk idle reset (seconds)</label>
                <input type="number" id="tc_kiosk_idle_seconds" name="kiosk_idle_seconds" min="30" max="600" value="<?php echo (int)($timeClockGeoSettings['kiosk_idle_seconds'] ?? 75); ?>">
            </div>
            <div class="form-group">
                <label for="tc_alert_open_threshold">Alert if open failed syncs per device >=</label>
                <input type="number" id="tc_alert_open_threshold" name="kiosk_alert_open_failure_threshold" min="1" max="100" value="<?php echo (int)($timeClockGeoSettings['alert_open_failure_threshold'] ?? 3); ?>">
            </div>
            <div class="form-group">
                <label for="tc_alert_stale_minutes">Alert if device stale for minutes >=</label>
                <input type="number" id="tc_alert_stale_minutes" name="kiosk_alert_stale_minutes" min="5" max="1440" value="<?php echo (int)($timeClockGeoSettings['alert_stale_minutes'] ?? 60); ?>">
            </div>
            <div class="form-group">
                <label for="tc_no_show_grace_minutes">Missed clock-in grace minutes</label>
                <input type="number" id="tc_no_show_grace_minutes" name="no_show_grace_minutes" min="0" max="180" value="<?php echo (int)($timeClockGeoSettings['no_show_grace_minutes'] ?? 15); ?>">
            </div>
            <div class="form-group">
                <label for="tc_geofence_mgr">Manager</label>
                <input type="text" id="tc_geofence_mgr" name="manager_name" required value="<?php echo htmlspecialchars($currentUserName); ?>" readonly>
            </div>
            <div class="form-group" style="display:flex; align-items:flex-end;">
                <button type="submit" class="btn btn-primary">Save Location/Kiosk Settings</button>
            </div>
        </form>
        <?php else: ?>
        <p class="timeclock-muted">Manager permission required.</p>
        <?php endif; ?>
    </div>

    <div class="timeclock-mobile-card timeclock-popup-section" id="tc_panel_pto">
        <h2>PTO / Leave Setup (Company-Wide)</h2>
        <p class="timeclock-mobile-help">
            Common US setup defaults are included. This is a policy setup aid, not legal advice.
        </p>
        <form method="POST" action="">
            <input type="hidden" name="timeclock_pto_settings_save" value="1">
            <input type="hidden" name="store_id" value="<?php echo (int)$storeId; ?>">
            <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
            <div class="timeclock-defaults-row">
                <div class="form-group">
                    <label for="tc_pto_preset">US-common preset</label>
                    <select id="tc_pto_preset">
                        <option value="">Choose preset...</option>
                        <option value="pto-40h-year">PTO 40h/year, per-hour accrual (exclude OT)</option>
                        <option value="pto-80h-year">PTO 80h/year, per-hour accrual (exclude OT)</option>
                        <option value="sick-1-per-30">Sick leave 1 hour per 30 hours worked</option>
                        <option value="holiday-fixed">Fixed paid holidays (no premium)</option>
                        <option value="holiday-premium">Holiday premium pay (1.5x if worked)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tc_pto_mgr_name">Manager Name</label>
                    <input type="text" id="tc_pto_mgr_name" name="manager_name" required value="<?php echo htmlspecialchars($currentUserName); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary" style="width:100%;">Save PTO/Leave Policy</button>
                </div>
            </div>

            <div class="timeclock-edit-grid">
                <div class="form-group">
                    <label for="tc_pto_method">PTO accrual method</label>
                    <select id="tc_pto_method" name="pto_accrual_method">
                        <option value="per_hour_worked" <?php echo (($timeClockPtoSettings['pto_accrual_method'] ?? 'per_hour_worked') === 'per_hour_worked') ? 'selected' : ''; ?>>Per hour worked</option>
                        <option value="per_pay_period" <?php echo (($timeClockPtoSettings['pto_accrual_method'] ?? '') === 'per_pay_period') ? 'selected' : ''; ?>>Per pay period</option>
                        <option value="annual_lump_sum" <?php echo (($timeClockPtoSettings['pto_accrual_method'] ?? '') === 'annual_lump_sum') ? 'selected' : ''; ?>>Annual lump sum</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tc_pto_min_per_hour">PTO minutes per hour worked</label>
                    <input type="number" step="0.01" min="0" id="tc_pto_min_per_hour" name="pto_minutes_per_hour" value="<?php echo htmlspecialchars($timeClockPtoSettings['pto_minutes_per_hour'] ?? '1.15'); ?>">
                </div>
                <div class="form-group">
                    <label for="tc_pto_cap">Annual PTO cap (minutes)</label>
                    <input type="number" min="0" id="tc_pto_cap" name="pto_annual_cap_minutes" value="<?php echo htmlspecialchars($timeClockPtoSettings['pto_annual_cap_minutes'] ?? '2400'); ?>">
                </div>
                <div class="form-group">
                    <label for="tc_pto_wait_days">PTO waiting period (days)</label>
                    <input type="number" min="0" id="tc_pto_wait_days" name="pto_waiting_period_days" value="<?php echo htmlspecialchars($timeClockPtoSettings['pto_waiting_period_days'] ?? '0'); ?>">
                </div>
                <div class="form-group">
                    <label><input type="checkbox" id="tc_pto_exclude_ot" name="pto_exclude_overtime" value="1" <?php echo (($timeClockPtoSettings['pto_exclude_overtime'] ?? '1') === '1') ? 'checked' : ''; ?>> Exclude overtime hours from PTO accrual</label>
                </div>
            </div>

            <div class="timeclock-edit-grid">
                <div class="form-group">
                    <label for="tc_sick_mode">Sick leave policy</label>
                    <select id="tc_sick_mode" name="sick_policy_mode">
                        <option value="none" <?php echo (($timeClockPtoSettings['sick_policy_mode'] ?? '') === 'none') ? 'selected' : ''; ?>>None</option>
                        <option value="per_30_hours" <?php echo (($timeClockPtoSettings['sick_policy_mode'] ?? 'per_30_hours') === 'per_30_hours') ? 'selected' : ''; ?>>1 hour per 30 hours worked</option>
                        <option value="custom_per_hour" <?php echo (($timeClockPtoSettings['sick_policy_mode'] ?? '') === 'custom_per_hour') ? 'selected' : ''; ?>>Custom per-hour accrual</option>
                        <option value="fixed_annual" <?php echo (($timeClockPtoSettings['sick_policy_mode'] ?? '') === 'fixed_annual') ? 'selected' : ''; ?>>Fixed annual bank</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tc_sick_min_per_hour">Sick minutes per hour (if accrual-based)</label>
                    <input type="number" step="0.01" min="0" id="tc_sick_min_per_hour" name="sick_minutes_per_hour" value="<?php echo htmlspecialchars($timeClockPtoSettings['sick_minutes_per_hour'] ?? '2.00'); ?>">
                </div>
                <div class="form-group">
                    <label for="tc_holiday_mode">Holiday policy</label>
                    <select id="tc_holiday_mode" name="holiday_policy_mode">
                        <option value="none" <?php echo (($timeClockPtoSettings['holiday_policy_mode'] ?? '') === 'none') ? 'selected' : ''; ?>>No holiday pay</option>
                        <option value="fixed_paid_holidays" <?php echo (($timeClockPtoSettings['holiday_policy_mode'] ?? 'fixed_paid_holidays') === 'fixed_paid_holidays') ? 'selected' : ''; ?>>Fixed paid holidays</option>
                        <option value="premium_if_worked" <?php echo (($timeClockPtoSettings['holiday_policy_mode'] ?? '') === 'premium_if_worked') ? 'selected' : ''; ?>>Premium pay when worked</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tc_holiday_multiplier">Holiday premium multiplier</label>
                    <input type="number" step="0.01" min="1" id="tc_holiday_multiplier" name="holiday_pay_multiplier" value="<?php echo htmlspecialchars($timeClockPtoSettings['holiday_pay_multiplier'] ?? '1.50'); ?>">
                </div>
            </div>
        </form>

        <hr style="margin: 14px 0; border: none; border-top: 1px solid #e5e7eb;">
        <h3 style="margin-bottom: 8px;">Employee PTO Balances</h3>
        <?php if (empty($timeClockPtoBalances)): ?>
            <p class="timeclock-muted">No PTO balances yet.</p>
        <?php else: ?>
            <table class="history-table">
                <thead>
                    <tr><th>Employee</th><th>Accrued</th><th>Used</th><th>Pending</th><th>Available</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($timeClockPtoBalances as $bal): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($bal['full_name']); ?></td>
                            <td><?php echo number_format(((int)$bal['accrued_minutes']) / 60, 2); ?> h</td>
                            <td><?php echo number_format(((int)$bal['used_minutes']) / 60, 2); ?> h</td>
                            <td><?php echo number_format(((int)$bal['pending_minutes']) / 60, 2); ?> h</td>
                            <td><strong><?php echo number_format(((int)$bal['available_minutes']) / 60, 2); ?> h</strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div style="margin-top: 14px;">
            <h3 style="margin-bottom: 8px;">Submit PTO Request (Employee)</h3>
            <form method="POST" action="" class="timeclock-edit-grid">
                <input type="hidden" name="timeclock_pto_request_submit" value="1">
                <input type="hidden" name="store_id" value="<?php echo (int)$storeId; ?>">
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                <div class="form-group">
                    <label for="tc_pto_emp">Employee</label>
                    <select id="tc_pto_emp" name="employee_id" required>
                        <option value="">Select employee...</option>
                        <?php foreach ($timeClockEmployees as $emp): ?>
                            <option value="<?php echo (int)$emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tc_pto_pin">PIN</label>
                    <input type="password" id="tc_pto_pin" name="pin" inputmode="numeric" pattern="[0-9]*" maxlength="10" required placeholder="Enter PIN">
                </div>
                <div class="form-group">
                    <label for="tc_pto_start">Start date</label>
                    <input type="date" id="tc_pto_start" name="pto_start_date" required>
                </div>
                <div class="form-group">
                    <label for="tc_pto_end">End date</label>
                    <input type="date" id="tc_pto_end" name="pto_end_date" required>
                </div>
                <div class="form-group">
                    <label for="tc_pto_hours">Requested hours</label>
                    <input type="number" step="0.25" min="0.25" id="tc_pto_hours" name="pto_hours" required placeholder="e.g. 8">
                </div>
                <div class="form-group">
                    <label for="tc_pto_reason">Reason</label>
                    <input type="text" id="tc_pto_reason" name="pto_reason" required maxlength="255" placeholder="Vacation / personal / medical">
                </div>
                <div class="form-group" style="display:flex; align-items:flex-end;">
                    <button type="submit" class="btn btn-primary">Submit PTO Request</button>
                </div>
            </form>
        </div>

        <div class="timeclock-grid" style="margin-top: 14px;">
            <div class="timeclock-panel">
                <h3>Pending PTO Requests (Manager)</h3>
                <?php if (empty($timeClockPendingPtoRequests)): ?>
                    <p class="timeclock-muted">No pending PTO requests.</p>
                <?php else: ?>
                    <?php foreach ($timeClockPendingPtoRequests as $req): ?>
                        <?php $isPtoReqLocked = !empty($timeClockPendingPtoRequestLockMap[(int)$req['id']]); ?>
                        <form method="POST" action="" class="timeclock-approve-form">
                            <input type="hidden" name="timeclock_pto_request_review" value="1">
                            <input type="hidden" name="store_id" value="<?php echo (int)$storeId; ?>">
                            <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                            <input type="hidden" name="request_id" value="<?php echo (int)$req['id']; ?>">
                            <div class="timeclock-request-row">
                                <div><strong><?php echo htmlspecialchars($req['full_name']); ?></strong> requested <?php echo number_format(((int)$req['requested_minutes']) / 60, 2); ?> h</div>
                                <div class="timeclock-muted"><?php echo htmlspecialchars($req['request_start_date']); ?> to <?php echo htmlspecialchars($req['request_end_date']); ?></div>
                                <div>Reason: <?php echo htmlspecialchars($req['reason']); ?></div>
                                <?php if ($isPtoReqLocked): ?>
                                    <div class="timeclock-lock-notice-inline">This PTO request overlaps a locked payroll period. Approve is disabled until unlocked.</div>
                                <?php endif; ?>
                                <div class="timeclock-approve-controls">
                                    <input type="text" name="manager_name" required value="<?php echo htmlspecialchars($currentUserName); ?>" readonly>
                                    <input type="text" name="manager_note" placeholder="Optional note">
                                    <button type="submit" name="review_decision" value="APPROVED" class="btn btn-primary" <?php echo $isPtoReqLocked ? 'disabled title="Request overlaps locked payroll period"' : ''; ?>>Approve</button>
                                    <button type="submit" name="review_decision" value="DENIED" class="btn timeclock-btn-out">Deny</button>
                                </div>
                            </div>
                        </form>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="timeclock-panel">
                <h3>Recent PTO Requests</h3>
                <?php if (empty($timeClockRecentPtoRequests)): ?>
                    <p class="timeclock-muted">No PTO requests yet.</p>
                <?php else: ?>
                    <table class="history-table">
                        <thead>
                            <tr><th>Employee</th><th>Dates</th><th>Hours</th><th>Status</th><th>Reviewed By</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timeClockRecentPtoRequests as $req): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($req['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($req['request_start_date']); ?> to <?php echo htmlspecialchars($req['request_end_date']); ?></td>
                                    <td><?php echo number_format(((int)$req['requested_minutes']) / 60, 2); ?> h</td>
                                    <td><?php echo htmlspecialchars($req['status']); ?></td>
                                    <td><?php echo htmlspecialchars($req['reviewed_by'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="timeclock-mobile-card timeclock-popup-section" id="tc_panel_payroll">
        <h2>Payroll Periods & Summary</h2>
        <p class="timeclock-mobile-help">Run payroll summary (regular/OT + loaded labor cost) for a selected period.</p>
        <form method="POST" action="" class="timeclock-edit-grid">
            <input type="hidden" name="timeclock_payroll_period_save" value="1">
            <input type="hidden" name="store_id" value="<?php echo (int)$storeId; ?>">
            <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
            <div class="form-group">
                <label for="tc_payroll_start">Period Start</label>
                <input type="date" id="tc_payroll_start" name="payroll_start_date" required value="<?php echo htmlspecialchars($timeClockScheduleWeekRange['start'] ?? $date); ?>">
            </div>
            <div class="form-group">
                <label for="tc_payroll_end">Period End</label>
                <input type="date" id="tc_payroll_end" name="payroll_end_date" required value="<?php echo htmlspecialchars($timeClockScheduleWeekRange['end'] ?? $date); ?>">
            </div>
            <div class="form-group">
                <label for="tc_payroll_manager">Manager Name</label>
                <input type="text" id="tc_payroll_manager" name="manager_name" required value="<?php echo htmlspecialchars($currentUserName); ?>" readonly>
            </div>
            <div class="form-group" style="display:flex; align-items:flex-end;">
                <button type="submit" class="btn btn-primary">Save Payroll Period</button>
            </div>
        </form>

        <div class="timeclock-warning-row" style="margin-top: 8px;">
            <form method="GET" action="index.php" class="timeclock-approve-controls">
                <input type="hidden" name="action" value="timeclock">
                <input type="hidden" name="store" value="<?php echo (int)$storeId; ?>">
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                <label for="tc_payroll_period_pick">Select Period</label>
                <select id="tc_payroll_period_pick" name="payroll_period_id" onchange="this.form.submit()">
                    <option value="">Choose...</option>
                    <?php foreach ($timeClockPayrollPeriods as $pp): ?>
                        <?php $pid = (int)$pp['id']; ?>
                        <option value="<?php echo $pid; ?>" <?php echo $timeClockSelectedPayrollPeriodId === $pid ? 'selected' : ''; ?>>
                            #<?php echo $pid; ?> | <?php echo htmlspecialchars($pp['period_start_date']); ?> to <?php echo htmlspecialchars($pp['period_end_date']); ?> (<?php echo htmlspecialchars($pp['status']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($timeClockSelectedPayrollPeriodId): ?>
            <?php $selectedPeriodRow = getPayrollPeriodById((int)$timeClockSelectedPayrollPeriodId, (int)$storeId); ?>
            <div class="timeclock-warning-row" style="margin-top:8px;">
                <span class="timeclock-badge-warning">
                    Period status: <strong><?php echo htmlspecialchars($selectedPeriodRow['status'] ?? 'OPEN'); ?></strong>
                    <?php if (!empty($selectedPeriodRow['locked_by'])): ?>
                        (locked by <?php echo htmlspecialchars($selectedPeriodRow['locked_by']); ?>)
                    <?php endif; ?>
                </span>
            </div>
            <form method="POST" action="" class="timeclock-approve-controls" style="margin-top: 8px;">
                <input type="hidden" name="timeclock_payroll_run" value="1">
                <input type="hidden" name="store_id" value="<?php echo (int)$storeId; ?>">
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                <input type="hidden" name="payroll_period_id" value="<?php echo (int)$timeClockSelectedPayrollPeriodId; ?>">
                <input type="text" name="manager_name" required value="<?php echo htmlspecialchars($currentUserName); ?>" readonly>
                <button type="submit" class="btn btn-primary" <?php echo (($selectedPeriodRow['status'] ?? '') === 'LOCKED') ? 'disabled title="Unlock period first"' : ''; ?>>Run Payroll Summary</button>
                <a class="btn" href="?action=timeclock&store=<?php echo (int)$storeId; ?>&date=<?php echo urlencode($date); ?>&payroll_period_id=<?php echo (int)$timeClockSelectedPayrollPeriodId; ?>&payroll_export_period_id=<?php echo (int)$timeClockSelectedPayrollPeriodId; ?>">Export CSV</a>
            </form>

            <form method="POST" action="" class="timeclock-approve-controls" style="margin-top: 8px;">
                <input type="hidden" name="store_id" value="<?php echo (int)$storeId; ?>">
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                <input type="hidden" name="payroll_period_id" value="<?php echo (int)$timeClockSelectedPayrollPeriodId; ?>">
                <input type="text" name="manager_name" required value="<?php echo htmlspecialchars($currentUserName); ?>" readonly>
                <?php if (($selectedPeriodRow['status'] ?? '') === 'LOCKED'): ?>
                    <button type="submit" name="timeclock_payroll_unlock" value="1" class="btn">Unlock Period</button>
                <?php else: ?>
                    <button type="submit" name="timeclock_payroll_lock" value="1" class="btn">Lock Period</button>
                <?php endif; ?>
            </form>

            <div style="margin-top: 10px;">
                <?php if (empty($timeClockSelectedPayrollRuns)): ?>
                    <p class="timeclock-muted">No payroll run rows yet for selected period. Run summary to generate.</p>
                <?php else: ?>
                    <table class="history-table">
                        <thead>
                            <tr><th>Employee</th><th>Regular</th><th>OT</th><th>PTO Paid</th><th>Gross</th><th>Loaded Cost</th><th>Effective Loaded Hourly</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timeClockSelectedPayrollRuns as $pr): ?>
                                <tr class="<?php echo ((int)$pr['overtime_minutes'] > 0) ? 'timeclock-row-danger' : ''; ?>">
                                    <td><?php echo htmlspecialchars($pr['full_name']); ?></td>
                                    <td><?php echo number_format(((int)$pr['regular_minutes']) / 60, 2); ?> h</td>
                                    <td><?php echo number_format(((int)$pr['overtime_minutes']) / 60, 2); ?> h</td>
                                    <td><?php echo number_format(((int)$pr['pto_minutes']) / 60, 2); ?> h</td>
                                    <td>$<?php echo number_format(((int)$pr['gross_cents']) / 100, 2); ?></td>
                                    <td>$<?php echo number_format(((int)$pr['loaded_cost_cents']) / 100, 2); ?></td>
                                    <td>$<?php echo number_format(((int)$pr['effective_hourly_cents']) / 100, 2); ?>/h</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="timeclock-mobile-card timeclock-popup-section" id="tc_panel_requests">
        <h2>Request Missed Punch / Shift Edit</h2>
        <p class="timeclock-mobile-help">Employee submits request with PIN. Manager reviews below.</p>
        <?php if ($timeClockSelectedDateLocked): ?>
            <div class="timeclock-lock-notice">
                Selected date <strong><?php echo htmlspecialchars($date); ?></strong> is locked. New requests for this date range are likely to be blocked.
            </div>
        <?php endif; ?>
        <form method="POST" action="">
            <input type="hidden" name="timeclock_edit_request_submit" value="1">
            <input type="hidden" name="store_id" value="<?php echo (int)$storeId; ?>">
            <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
            <div class="timeclock-edit-grid">
                <div class="form-group">
                    <label for="tc_req_employee_id">Employee</label>
                    <select id="tc_req_employee_id" name="employee_id" required>
                        <option value="">Select employee...</option>
                        <?php foreach ($timeClockEmployees as $emp): ?>
                            <option value="<?php echo (int)$emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tc_req_pin">PIN</label>
                    <input type="password" id="tc_req_pin" name="pin" inputmode="numeric" pattern="[0-9]*" maxlength="10" required placeholder="Enter PIN">
                </div>
                <div class="form-group">
                    <label for="tc_request_type">Request Type</label>
                    <select id="tc_request_type" name="request_type" required>
                        <option value="MISS_CLOCK_IN">Missed Clock In</option>
                        <option value="MISS_CLOCK_OUT">Missed Clock Out</option>
                        <option value="ADJUST_SHIFT">Adjust Existing Shift</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tc_request_shift_id">Shift (for adjust)</label>
                    <select id="tc_request_shift_id" name="request_shift_id">
                        <option value="">Select shift...</option>
                        <?php foreach ($timeClockRecentShifts as $shift): ?>
                            <option value="<?php echo (int)$shift['id']; ?>">
                                #<?php echo (int)$shift['id']; ?> - <?php echo htmlspecialchars($shift['full_name']); ?> (<?php echo htmlspecialchars(formatUtcTimestampForDisplay($shift['clock_in_utc'])); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tc_req_in_local">Requested Clock-In (local)</label>
                    <input type="datetime-local" id="tc_req_in_local" name="requested_clock_in_local">
                </div>
                <div class="form-group">
                    <label for="tc_req_out_local">Requested Clock-Out (local)</label>
                    <input type="datetime-local" id="tc_req_out_local" name="requested_clock_out_local">
                </div>
            </div>
            <div class="form-group">
                <label for="tc_request_reason">Reason</label>
                <textarea id="tc_request_reason" name="request_reason" rows="2" required placeholder="Explain what needs to be corrected"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" <?php echo $timeClockSelectedDateLocked ? 'disabled title="Selected date is in a locked payroll period"' : ''; ?>>Submit Edit Request</button>
        </form>
    </div>

    <div class="timeclock-mobile-card timeclock-popup-section" id="tc_panel_live">
        <h2>Live Floor View</h2>
        <div class="timeclock-grid">
        <div class="timeclock-panel">
            <h3>Open Shifts</h3>
            <?php if (empty($timeClockOpenShifts)): ?>
                <p class="timeclock-muted">No one is currently clocked in.</p>
            <?php else: ?>
                <table class="history-table">
                    <thead>
                        <tr><th>Employee</th><th>Clocked In (Local)</th><th>Note</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($timeClockOpenShifts as $openShift): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($openShift['full_name']); ?></td>
                                <td><?php echo htmlspecialchars(formatUtcTimestampForDisplay($openShift['clock_in_utc'])); ?></td>
                                <td><?php echo htmlspecialchars($openShift['clock_in_note'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <div class="timeclock-panel">
            <h3>Recent Punch Events</h3>
            <?php if (empty($timeClockRecentEvents)): ?>
                <p class="timeclock-muted">No punches yet.</p>
            <?php else: ?>
                <table class="history-table">
                    <thead>
                        <tr><th>When (Local)</th><th>Employee</th><th>Type</th><th>GPS</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($timeClockRecentEvents as $event): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(formatUtcTimestampForDisplay($event['event_utc'])); ?></td>
                                <td><?php echo htmlspecialchars($event['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($event['event_type']); ?></td>
                                <td><?php echo htmlspecialchars($event['gps_status'] ?? 'unavailable'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    </div>

    <div class="timeclock-mobile-card timeclock-popup-section" id="tc_panel_admin">
        <h2>Approvals & Audit</h2>
        <div class="timeclock-panel" style="margin-bottom: 12px;">
            <h3>Missed Clock-In Alerts</h3>
            <p class="timeclock-muted">Date: <?php echo htmlspecialchars($date); ?> | Grace: <?php echo (int)$timeClockNoShowGraceMinutes; ?> min</p>
            <?php if (empty($timeClockNoShowAlerts)): ?>
                <p class="timeclock-badge-ok">No missed clock-ins detected for selected date.</p>
            <?php else: ?>
                <table class="history-table">
                    <thead>
                        <tr><th>Employee</th><th>Role</th><th>Scheduled</th><th>Late By</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($timeClockNoShowAlerts as $alert): ?>
                            <tr class="timeclock-row-danger">
                                <td><?php echo htmlspecialchars($alert['full_name'] ?? 'Employee'); ?></td>
                                <td><?php echo htmlspecialchars($alert['role_name'] ?? 'Employee'); ?></td>
                                <td><?php echo htmlspecialchars(($alert['scheduled_start_local'] ?? '-') . ' - ' . ($alert['scheduled_end_local'] ?? '-')); ?></td>
                                <td><?php echo (int)($alert['minutes_late'] ?? 0); ?> min</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <div class="timeclock-grid">
        <div class="timeclock-panel">
            <h3>Pending Edit Requests (Manager)</h3>
            <?php if (empty($timeClockPendingEditRequests)): ?>
                <p class="timeclock-muted">No pending edit requests.</p>
            <?php else: ?>
                <?php foreach ($timeClockPendingEditRequests as $req): ?>
                    <?php $isReqLocked = !empty($timeClockPendingEditRequestLockMap[(int)$req['id']]); ?>
                    <form method="POST" action="" class="timeclock-approve-form">
                        <input type="hidden" name="timeclock_edit_request_review" value="1">
                        <input type="hidden" name="store_id" value="<?php echo (int)$storeId; ?>">
                        <input type="hidden" name="request_id" value="<?php echo (int)$req['id']; ?>">
                        <div class="timeclock-request-row">
                            <div><strong><?php echo htmlspecialchars($req['full_name']); ?></strong> - <?php echo htmlspecialchars($req['request_type']); ?></div>
                            <div class="timeclock-muted">Submitted: <?php echo htmlspecialchars(formatUtcTimestampForDisplay($req['submitted_at'])); ?></div>
                            <div>Reason: <?php echo htmlspecialchars($req['reason']); ?></div>
                            <div class="timeclock-muted">
                                In: <?php echo htmlspecialchars(formatUtcTimestampForDisplay($req['requested_clock_in_utc'] ?? null)); ?> |
                                Out: <?php echo htmlspecialchars(formatUtcTimestampForDisplay($req['requested_clock_out_utc'] ?? null)); ?>
                            </div>
                            <?php if ($isReqLocked): ?>
                                <div class="timeclock-lock-notice-inline">This request overlaps a locked payroll period. Approve is disabled until unlocked.</div>
                            <?php endif; ?>
                            <div class="timeclock-approve-controls">
                                <input type="text" name="manager_name" required value="<?php echo htmlspecialchars($currentUserName); ?>" readonly>
                                <input type="text" name="manager_note" placeholder="Optional note">
                                <button type="submit" name="review_decision" value="APPROVED" class="btn btn-primary" <?php echo $isReqLocked ? 'disabled title="Request overlaps locked payroll period"' : ''; ?>>Approve</button>
                                <button type="submit" name="review_decision" value="DENIED" class="btn timeclock-btn-out">Deny</button>
                            </div>
                        </div>
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="timeclock-panel">
            <h3>Recent Edit Requests</h3>
            <?php if (empty($timeClockRecentEditRequests)): ?>
                <p class="timeclock-muted">No edit requests yet.</p>
            <?php else: ?>
                <table class="history-table">
                    <thead>
                        <tr><th>Employee</th><th>Type</th><th>Status</th><th>Reviewed By</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($timeClockRecentEditRequests as $req): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($req['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($req['request_type']); ?></td>
                                <td><?php echo htmlspecialchars($req['status']); ?></td>
                                <td><?php echo htmlspecialchars($req['reviewed_by'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="timeclock-panel" style="margin-top: 16px;">
        <h3>Audit Trail (Recent)</h3>
        <?php if (empty($timeClockAuditRows)): ?>
            <p class="timeclock-muted">No audit entries yet.</p>
        <?php else: ?>
            <table class="history-table">
                <thead>
                    <tr><th>When (Local)</th><th>Actor</th><th>Action</th><th>Details</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($timeClockAuditRows as $audit): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(formatUtcTimestampForDisplay($audit['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($audit['actor_name']); ?></td>
                            <td><?php echo htmlspecialchars($audit['action_type']); ?></td>
                            <td><?php echo htmlspecialchars($audit['details_json'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php if ($canManageTimeclock): ?>
    <div class="timeclock-panel" style="margin-top: 16px;">
        <h3>Kiosk SLA Alerts</h3>
        <p class="timeclock-muted">Thresholds: open failures per device >= <?php echo (int)$timeClockSlaOpenFailureThreshold; ?>, stale minutes >= <?php echo (int)$timeClockSlaStaleMinutes; ?>.</p>
        <?php if (empty($timeClockSlaAlertRows)): ?>
            <p class="timeclock-badge-ok">No SLA alerts. Kiosk fleet is healthy.</p>
        <?php else: ?>
            <table class="history-table">
                <thead>
                    <tr><th>Device</th><th>Open Failed</th><th>Stale</th><th>Last Seen (Local)</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($timeClockSlaAlertRows as $row): ?>
                        <tr class="timeclock-row-danger">
                            <td><?php echo htmlspecialchars($row['device_id'] ?? 'unknown'); ?></td>
                            <td><?php echo (int)($row['open_failed'] ?? 0); ?></td>
                            <td><?php echo !empty($row['is_stale']) ? 'YES' : 'No'; ?></td>
                            <td><?php echo htmlspecialchars(formatUtcTimestampForDisplay($row['last_seen_at'] ?? null)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <div class="timeclock-panel" style="margin-top: 16px;">
        <h3>Kiosk Sync Failures (Open)</h3>
        <?php if (empty($timeClockKioskOpenFailures)): ?>
            <p class="timeclock-muted">No open kiosk sync failures.</p>
        <?php else: ?>
            <form method="POST" action="" class="timeclock-approve-controls" style="margin-bottom:8px;">
                <input type="hidden" name="timeclock_kiosk_failure_resolve_all" value="1">
                <input type="hidden" name="store_id" value="<?php echo (int)$storeId; ?>">
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                <input type="hidden" name="manager_name" value="<?php echo htmlspecialchars($currentUserName); ?>">
                <input type="hidden" name="resolution_status" value="RESOLVED">
                <input type="text" name="resolution_note" placeholder="Optional bulk resolution note">
                <button type="submit" class="btn">Resolve All Open</button>
            </form>
            <table class="history-table">
                <thead>
                    <tr><th>When (Local)</th><th>Device</th><th>Employee</th><th>Punch</th><th>Reason</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($timeClockKioskOpenFailures as $row): ?>
                        <tr class="timeclock-row-danger">
                            <td><?php echo htmlspecialchars(formatUtcTimestampForDisplay($row['created_at'] ?? null)); ?></td>
                            <td><?php echo htmlspecialchars($row['device_id'] ?? 'unknown'); ?></td>
                            <td><?php echo htmlspecialchars($row['full_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars(($row['punch_type'] ?? 'in') === 'out' ? 'Clock Out' : 'Clock In'); ?></td>
                            <td><?php echo htmlspecialchars($row['result_message'] ?? '-'); ?></td>
                            <td>
                                <form method="POST" action="" class="timeclock-approve-controls">
                                    <input type="hidden" name="timeclock_kiosk_failure_resolve" value="1">
                                    <input type="hidden" name="store_id" value="<?php echo (int)$storeId; ?>">
                                    <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                                    <input type="hidden" name="sync_log_id" value="<?php echo (int)($row['id'] ?? 0); ?>">
                                    <input type="hidden" name="manager_name" value="<?php echo htmlspecialchars($currentUserName); ?>">
                                    <select name="resolution_status">
                                        <option value="RESOLVED">Resolve</option>
                                        <option value="IGNORED">Ignore</option>
                                        <option value="OPEN">Keep Open</option>
                                    </select>
                                    <input type="text" name="resolution_note" placeholder="Note">
                                    <button type="submit" class="btn">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <div class="timeclock-panel" style="margin-top: 16px;">
        <h3>Kiosk Sync Monitor</h3>
        <?php if (empty($timeClockKioskDeviceSummary)): ?>
            <p class="timeclock-muted">No kiosk sync activity logged yet.</p>
        <?php else: ?>
            <table class="history-table">
                <thead>
                    <tr><th>Device</th><th>Total Attempts</th><th>Failed</th><th>Open Failed</th><th>Last Success</th><th>Last Failure</th><th>Last Seen</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($timeClockKioskDeviceSummary as $row): ?>
                        <tr class="<?php echo ((int)($row['unresolved_failed_attempts'] ?? 0) > 0) ? 'timeclock-row-danger' : ''; ?>">
                            <td><?php echo htmlspecialchars($row['device_id'] ?? 'unknown'); ?></td>
                            <td><?php echo (int)($row['total_attempts'] ?? 0); ?></td>
                            <td><?php echo (int)($row['failed_attempts'] ?? 0); ?></td>
                            <td><?php echo (int)($row['unresolved_failed_attempts'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars(formatUtcTimestampForDisplay($row['last_success_at'] ?? null)); ?></td>
                            <td><?php echo htmlspecialchars(formatUtcTimestampForDisplay($row['last_failure_at'] ?? null)); ?></td>
                            <td><?php echo htmlspecialchars(formatUtcTimestampForDisplay($row['last_seen_at'] ?? null)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <div class="timeclock-panel" style="margin-top: 16px;">
        <h3>Kiosk Sync Attempts (Recent)</h3>
        <?php if (empty($timeClockKioskSyncRows)): ?>
            <p class="timeclock-muted">No kiosk sync attempts logged yet.</p>
        <?php else: ?>
            <table class="history-table">
                <thead>
                    <tr><th>When (Local)</th><th>Device</th><th>Employee</th><th>Punch</th><th>Status</th><th>Resolution</th><th>Result</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($timeClockKioskSyncRows as $row): ?>
                        <?php $isFailed = (($row['sync_status'] ?? '') === 'failed'); ?>
                        <tr class="<?php echo $isFailed ? 'timeclock-row-danger' : ''; ?>">
                            <td><?php echo htmlspecialchars(formatUtcTimestampForDisplay($row['created_at'] ?? null)); ?></td>
                            <td><?php echo htmlspecialchars($row['device_id'] ?? 'unknown'); ?></td>
                            <td><?php echo htmlspecialchars($row['full_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars(($row['punch_type'] ?? 'in') === 'out' ? 'Clock Out' : 'Clock In'); ?></td>
                            <td><?php echo htmlspecialchars(strtoupper((string)($row['sync_status'] ?? ''))); ?></td>
                            <td><?php echo htmlspecialchars((string)($row['resolution_status'] ?? 'OPEN')); ?></td>
                            <td><?php echo htmlspecialchars($row['result_message'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    </div>
    <script>
    (function () {
        var backdrop = document.getElementById('timeclock-popup-backdrop');
        var launcherButtons = document.querySelectorAll('.timeclock-launcher-btn, .timeclock-quick-btn');
        var panels = document.querySelectorAll('.timeclock-popup-section');
        if (!backdrop || !launcherButtons.length || !panels.length) return;

        panels.forEach(function (panel) {
            var heading = panel.querySelector('h2');
            if (!heading) return;
            if (heading.querySelector('.timeclock-popup-close')) return;
            panel.setAttribute('aria-hidden', 'true');
            var closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'timeclock-popup-close';
            closeBtn.textContent = 'Close';
            closeBtn.addEventListener('click', function () {
                panel.classList.remove('open');
                panel.setAttribute('aria-hidden', 'true');
                backdrop.classList.remove('open');
                document.body.classList.remove('timeclock-popup-open');
            });
            heading.appendChild(closeBtn);
        });

        var openPanel = function (targetId) {
            var panel = document.getElementById(targetId);
            if (!panel) return;
            panels.forEach(function (p) { p.classList.remove('open'); p.setAttribute('aria-hidden', 'true'); });
            panel.classList.add('open');
            panel.setAttribute('aria-hidden', 'false');
            backdrop.classList.add('open');
            document.body.classList.add('timeclock-popup-open');
            panel.focus();
        };

        var closeAll = function () {
            panels.forEach(function (p) { p.classList.remove('open'); p.setAttribute('aria-hidden', 'true'); });
            backdrop.classList.remove('open');
            document.body.classList.remove('timeclock-popup-open');
        };

        launcherButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = btn.getAttribute('data-target');
                if (target) openPanel(target);
            });
        });
        backdrop.addEventListener('click', closeAll);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeAll();
        });
        var initialPanel = <?php echo json_encode((string)($_GET['panel'] ?? '')); ?>;
        if (initialPanel && /^tc_panel_/.test(initialPanel)) {
            openPanel(initialPanel);
        }
    })();
    (function () {
        var shiftEl = document.getElementById('tc_task_shift_id');
        var assigneeEl = document.getElementById('tc_task_assignee');
        if (!shiftEl || !assigneeEl) return;
        shiftEl.addEventListener('change', function () {
            var selected = shiftEl.options[shiftEl.selectedIndex];
            if (!selected) return;
            var employeeId = selected.getAttribute('data-employee-id') || '';
            if (employeeId && !assigneeEl.value) {
                assigneeEl.value = employeeId;
            }
        });
    })();
    (function () {
        var form = document.getElementById('timeclock-punch-form');
        if (!form) return;
        var statusEl = document.getElementById('tc_geo_status');
        var setStatus = function (msg) {
            if (statusEl) statusEl.textContent = msg;
        };
        var setGpsStatus = function (status) {
            var el = document.getElementById('tc_gps_status');
            if (el) el.value = status;
        };
        if (!navigator.onLine) {
            setStatus('No connection - reconnect or use kiosk.');
        }
        if (!navigator.geolocation) {
            setGpsStatus('unavailable');
            setStatus('GPS unavailable on this device/browser.');
            return;
        }
        navigator.geolocation.getCurrentPosition(function (pos) {
            var lat = document.getElementById('tc_gps_lat');
            var lng = document.getElementById('tc_gps_lng');
            var acc = document.getElementById('tc_gps_accuracy_m');
            if (lat) lat.value = String(pos.coords.latitude);
            if (lng) lng.value = String(pos.coords.longitude);
            if (acc) acc.value = String(pos.coords.accuracy || '');
            setGpsStatus('ok');
            setStatus('Location verified on device.');
        }, function (err) {
            if (err && err.code === 1) {
                setGpsStatus('denied');
                setStatus('GPS denied. Punch is still allowed and flagged.');
            } else {
                setGpsStatus('unavailable');
                setStatus('GPS unavailable. Punch is still allowed and flagged.');
            }
        }, {
            enableHighAccuracy: true,
            timeout: 5000,
            maximumAge: 15000
        });
        form.addEventListener('submit', function (e) {
            if (!navigator.onLine && !document.body.classList.contains('timeclock-kiosk')) {
                e.preventDefault();
                alert('No connection - reconnect or use kiosk.');
                return false;
            }
            return true;
        });
    })();
    (function () {
        var boardEl = document.getElementById('tc_lane_board');
        if (!boardEl) return;
        var dataEl = document.getElementById('tc_staff_schedule_data');
        if (!dataEl) return;
        var otWarnEl = document.getElementById('tc_lane_ot_warning');
        var gapWarnEl = document.getElementById('tc_lane_gap_warning');
        var weekStatusEl = document.getElementById('tc_lane_week_status');
        var publishBtn = document.getElementById('tc_lane_publish_btn');
        var unpublishBtn = document.getElementById('tc_lane_unpublish_btn');
        var copyPrevBtn = document.getElementById('tc_lane_copy_prev_btn');
        var copySourceDayEl = document.getElementById('tc_lane_copy_source_day');
        var copyDayBtn = document.getElementById('tc_lane_copy_day_btn');
        var applyEmployeeEl = document.getElementById('tc_lane_apply_employee');
        var applyRoleEl = document.getElementById('tc_lane_apply_role');
        var applyDaysBtn = document.getElementById('tc_lane_apply_days_btn');
        var roleLegendEl = document.getElementById('tc_lane_role_legend');
        var defaultRoleEl = document.getElementById('tc_sched_default_role');
        var weekDays = <?php echo json_encode($timeClockScheduleCalendar['days'] ?? []); ?>;
        var operatingHoursByDate = <?php echo json_encode($timeClockOperatingHoursByDate ?? []); ?>;
        var employees = <?php echo json_encode(array_map(function ($emp) {
            return ['id' => (int)($emp['id'] ?? 0), 'full_name' => (string)($emp['full_name'] ?? '')];
        }, $timeClockEmployees)); ?>;
        var weekStartYmd = <?php echo json_encode((string)($timeClockScheduleWeekRange['start'] ?? '')); ?>;
        var weekEndYmd = <?php echo json_encode((string)($timeClockScheduleWeekRange['end'] ?? '')); ?>;
        var managerName = <?php echo json_encode((string)$currentUserName); ?>;
        var storeId = <?php echo (int)$storeId; ?>;
        var dateParam = <?php echo json_encode((string)$date); ?>;
        var weekStatus = <?php echo json_encode((string)($timeClockScheduleWeekStatus['status'] ?? 'DRAFT')); ?>;

        var SLOT_MINUTES = 15;
        var DAY_START_MINUTES = 6 * 60;
        var DAY_END_MINUTES = 23 * 60;
        var OPEN_START_MINUTES = 9 * 60;
        var OPEN_END_MINUTES = 21 * 60;
        var SLOT_PIXELS = 6;

        var parseJsonArray = function (el) {
            try { var parsed = JSON.parse((el && el.value) || '[]'); return Array.isArray(parsed) ? parsed : []; } catch (e) { return []; }
        };
        var scheduleRows = parseJsonArray(dataEl);
        var shifts = scheduleRows
            .filter(function (r) {
                var id = parseInt(r.shift_id || '0', 10) || 0;
                var startDate = String(r.start_date_ymd || '');
                var endDate = String(r.end_date_ymd || startDate);
                return id > 0 && startDate !== '' && startDate <= weekEndYmd && endDate >= weekStartYmd;
            })
            .map(function (r) {
                var start = new Date(r.start_utc);
                var end = new Date(r.end_utc);
                if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return null;
                return {
                    shift_id: parseInt(r.shift_id || '0', 10) || 0,
                    employee_id: parseInt(r.employee_id || '0', 10) || 0,
                    employee_name: String(r.employee_name || ''),
                    role_name: String(r.role_name || 'Employee'),
                    break_minutes: parseInt(r.break_minutes || '0', 10) || 0,
                    start: start,
                    end: end
                };
            }).filter(Boolean);

        var escapeHtml = function (value) { return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); };
        var isoYmd = function (d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
        var startOfDay = function (ymd) { return new Date(ymd + 'T00:00:00'); };
        var formatTime = function (minutes) {
            var mins = Math.max(0, Math.min(23 * 60 + 59, minutes));
            var h = Math.floor(mins / 60), m = mins % 60, am = h < 12, hr12 = (h % 12 === 0 ? 12 : h % 12);
            return hr12 + ':' + String(m).padStart(2, '0') + ' ' + (am ? 'AM' : 'PM');
        };
        var minutesFromDate = function (d) { return (d.getHours() * 60) + d.getMinutes(); };
        var roundToSlot = function (minutes) { return Math.round(minutes / SLOT_MINUTES) * SLOT_MINUTES; };
        var clamp = function (v, min, max) { return Math.max(min, Math.min(max, v)); };
        var setShiftTimesForDay = function (shift, dayYmd, startMinutes, endMinutes) {
            var dayStart = startOfDay(dayYmd);
            shift.start = new Date(dayStart.getTime() + (startMinutes * 60000));
            shift.end = new Date(dayStart.getTime() + (endMinutes * 60000));
        };
        var hhmmToMinutes = function (hhmm) {
            var str = String(hhmm || '');
            var m = /^(\d{2}):(\d{2})$/.exec(str);
            if (!m) return null;
            var h = parseInt(m[1], 10);
            var mm = parseInt(m[2], 10);
            if (!Number.isFinite(h) || !Number.isFinite(mm) || h < 0 || h > 23 || mm < 0 || mm > 59) return null;
            return (h * 60) + mm;
        };
        var asLocalInput = function (d) {
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0')
                + ' ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0') + ':' + String(d.getSeconds()).padStart(2, '0');
        };
        var postOp = function (params) {
            var fd = new FormData();
            fd.append('timeclock_schedule_dragdrop_json', '1');
            fd.append('store_id', String(storeId));
            fd.append('date', dateParam);
            fd.append('manager_name', managerName || 'Manager');
            Object.keys(params || {}).forEach(function (k) { fd.append(k, String(params[k])); });
            return fetch('index.php?action=timeclock&store=' + encodeURIComponent(String(storeId)) + '&date=' + encodeURIComponent(dateParam), { method: 'POST', body: fd }).then(function (r) { return r.json(); });
        };
        var getRoleDefault = function () { return defaultRoleEl && defaultRoleEl.value ? defaultRoleEl.value : 'Employee'; };
        var getEmployeeName = function (employeeId) {
            var id = parseInt(employeeId || '0', 10) || 0;
            var hit = employees.find(function (e) { return (parseInt(e.id || '0', 10) || 0) === id; });
            return hit ? String(hit.full_name || ('Employee #' + id)) : ('Employee #' + id);
        };
        var stringHash = function (value) {
            var str = String(value || ''), h = 0;
            for (var i = 0; i < str.length; i++) { h = ((h << 5) - h) + str.charCodeAt(i); h |= 0; }
            return Math.abs(h);
        };
        var getRoleColor = function (roleName) {
            var role = String(roleName || 'Employee');
            if (/manager/i.test(role)) return { border: '#7c3aed', bg: '#ede9fe', head: '#4c1d95' };
            if (/cashier/i.test(role)) return { border: '#059669', bg: '#d1fae5', head: '#065f46' };
            if (/stock/i.test(role)) return { border: '#d97706', bg: '#fef3c7', head: '#92400e' };
            var hue = stringHash(role) % 360;
            return { border: 'hsl(' + hue + ', 55%, 38%)', bg: 'hsl(' + hue + ', 70%, 90%)', head: 'hsl(' + hue + ', 62%, 26%)' };
        };
        var shiftsForDay = function (dayYmd) { return shifts.filter(function (s) { return isoYmd(s.start) <= dayYmd && isoYmd(s.end) >= dayYmd; }); };
        var employeeLanesForDay = function (dayYmd) {
            var map = {};
            shiftsForDay(dayYmd).forEach(function (s) { if (!map[s.employee_id]) map[s.employee_id] = { employee_id: s.employee_id, employee_name: s.employee_name }; });
            return Object.keys(map).map(function (k) { return map[k]; }).sort(function (a, b) { return String(a.employee_name).localeCompare(String(b.employee_name)); });
        };
        var availableEmployeesForDay = function (dayYmd) {
            var used = {};
            employeeLanesForDay(dayYmd).forEach(function (l) { used[l.employee_id] = true; });
            return employees.filter(function (e) { return !used[(parseInt(e.id || '0', 10) || 0)]; });
        };
        var setWeekStatus = function (statusText) {
            weekStatus = String(statusText || 'DRAFT').toUpperCase();
            if (weekStatusEl) {
                var published = weekStatus === 'PUBLISHED';
                weekStatusEl.className = published ? 'timeclock-badge-ok' : 'timeclock-badge-warning';
                weekStatusEl.textContent = 'Week status: ' + weekStatus;
                if (publishBtn) publishBtn.disabled = published;
                if (unpublishBtn) unpublishBtn.disabled = !published;
            }
        };
        var renderRoleLegend = function () {
            if (!roleLegendEl) return;
            var roleMap = {};
            shifts.forEach(function (s) { roleMap[String(s.role_name || 'Employee')] = true; });
            if (applyRoleEl && applyRoleEl.value) roleMap[String(applyRoleEl.value)] = true;
            var roles = Object.keys(roleMap).sort();
            roleLegendEl.innerHTML = roles.map(function (r) {
                var c = getRoleColor(r);
                return '<span class="lane-role-chip" style="--lane-role-border:' + escapeHtml(c.border) + ';--lane-role-bg:' + escapeHtml(c.bg) + ';--lane-role-head:' + escapeHtml(c.head) + ';">'
                    + '<span class="lane-role-dot"></span>' + escapeHtml(r) + '</span>';
            }).join('');
            if (!roles.length) roleLegendEl.innerHTML = '<span class="timeclock-muted">No role colors yet.</span>';
        };
        var refreshWarnings = function () {
            var weekHours = {};
            shifts.forEach(function (s) {
                var key = String(s.employee_id);
                var durH = Math.max(0, ((s.end.getTime() - s.start.getTime()) / 3600000) - ((parseInt(s.break_minutes || '0', 10) || 0) / 60));
                weekHours[key] = (weekHours[key] || 0) + durH;
            });
            var otCount = Object.keys(weekHours).filter(function (k) { return weekHours[k] > 40; }).length;
            var uncoveredDays = 0;
            weekDays.forEach(function (d) {
                var dayYmd = String(d.date || '');
                if (!dayYmd) return;
                var hoursCfg = (operatingHoursByDate && operatingHoursByDate[dayYmd]) ? operatingHoursByDate[dayYmd] : null;
                var dayOpenStart = OPEN_START_MINUTES;
                var dayOpenEnd = OPEN_END_MINUTES;
                var dayEnabled = true;
                if (hoursCfg && typeof hoursCfg === 'object') {
                    dayEnabled = !!hoursCfg.enabled;
                    var parsedOpen = hhmmToMinutes(hoursCfg.open);
                    var parsedClose = hhmmToMinutes(hoursCfg.close);
                    if (parsedOpen !== null) dayOpenStart = parsedOpen;
                    if (parsedClose !== null) dayOpenEnd = parsedClose;
                }
                if (!dayEnabled) {
                    return;
                }
                var intervals = [];
                shiftsForDay(dayYmd).forEach(function (s) {
                    var startM = minutesFromDate(s.start), endM = minutesFromDate(s.end);
                    var ovStart = Math.max(startM, dayOpenStart), ovEnd = Math.min(endM, dayOpenEnd);
                    if (ovEnd > ovStart) intervals.push([ovStart, ovEnd]);
                });
                intervals.sort(function (a, b) { return a[0] - b[0]; });
                var merged = [];
                intervals.forEach(function (inr) {
                    if (!merged.length || inr[0] > merged[merged.length - 1][1]) merged.push(inr);
                    else merged[merged.length - 1][1] = Math.max(merged[merged.length - 1][1], inr[1]);
                });
                var covered = 0;
                merged.forEach(function (m) { covered += Math.max(0, m[1] - m[0]); });
                if (covered < Math.max(0, (dayOpenEnd - dayOpenStart))) uncoveredDays++;
            });
            if (otWarnEl) {
                if (otCount > 0) { otWarnEl.className = 'timeclock-badge-danger'; otWarnEl.textContent = 'Overtime warning: ' + otCount + ' employee(s) over 40h'; }
                else { otWarnEl.className = 'timeclock-badge-ok'; otWarnEl.textContent = 'Overtime warning: none'; }
            }
            if (gapWarnEl) {
                if (uncoveredDays > 0) { gapWarnEl.className = 'timeclock-badge-warning'; gapWarnEl.textContent = 'Coverage warning: ' + uncoveredDays + ' day(s) unfilled'; }
                else { gapWarnEl.className = 'timeclock-badge-ok'; gapWarnEl.textContent = 'Coverage warning: fully covered'; }
            }
        };

        var dragState = null;
        var render = function () {
            var dayColumnsHtml = weekDays.map(function (day) {
                var dayYmd = String(day.date || ''), dayLabel = String(day.label || dayYmd);
                var lanes = employeeLanesForDay(dayYmd), available = availableEmployeesForDay(dayYmd);
                var addOptions = available.map(function (emp) { return '<option value="' + (parseInt(emp.id || '0', 10) || 0) + '">' + escapeHtml(emp.full_name || '') + '</option>'; }).join('');
                var lanesHtml = lanes.map(function (lane) {
                    var laneShifts = shiftsForDay(dayYmd).filter(function (s) { return s.employee_id === lane.employee_id; });
                    var laneRole = laneShifts.length ? String(laneShifts[0].role_name || 'Employee') : 'Employee';
                    var laneColor = getRoleColor(laneRole);
                    var blocksHtml = laneShifts.map(function (s) {
                        var startM = clamp(roundToSlot(minutesFromDate(s.start)), DAY_START_MINUTES, DAY_END_MINUTES - SLOT_MINUTES);
                        var endM = clamp(roundToSlot(minutesFromDate(s.end)), startM + SLOT_MINUTES, DAY_END_MINUTES);
                        var top = ((startM - DAY_START_MINUTES) / SLOT_MINUTES) * SLOT_PIXELS;
                        var height = ((endM - startM) / SLOT_MINUTES) * SLOT_PIXELS;
                        var c = getRoleColor(s.role_name || laneRole);
                        return '<div class="lane-shift-block" data-shift-id="' + s.shift_id + '" data-day="' + escapeHtml(dayYmd) + '" style="--lane-role-border:' + escapeHtml(c.border) + ';--lane-role-bg:' + escapeHtml(c.bg) + ';--lane-role-head:' + escapeHtml(c.head) + ';top:' + top + 'px;height:' + Math.max(10, height) + 'px;">'
                            + '<div class="lane-shift-handle lane-shift-handle-top" data-handle="start"></div>'
                            + '<div class="lane-shift-content">' + escapeHtml(formatTime(startM) + ' - ' + formatTime(endM)) + '</div>'
                            + '<div class="lane-shift-handle lane-shift-handle-bottom" data-handle="end"></div>'
                            + '</div>';
                    }).join('');
                    var trackHeight = ((DAY_END_MINUTES - DAY_START_MINUTES) / SLOT_MINUTES) * SLOT_PIXELS;
                    return '<div class="lane-employee" style="--lane-role-border:' + escapeHtml(laneColor.border) + ';--lane-role-bg:' + escapeHtml(laneColor.bg) + ';--lane-role-head:' + escapeHtml(laneColor.head) + ';">'
                        + '<div class="lane-employee-header"><strong>' + escapeHtml(lane.employee_name) + '</strong> <span class="lane-employee-role">' + escapeHtml(laneRole) + '</span></div>'
                        + '<div class="lane-track" data-day="' + escapeHtml(dayYmd) + '" data-employee-id="' + lane.employee_id + '" style="height:' + trackHeight + 'px;">' + blocksHtml + '</div>'
                        + '</div>';
                }).join('');
                return '<div class="lane-day-col" data-day="' + escapeHtml(dayYmd) + '">'
                    + '<div class="lane-day-header"><strong>' + escapeHtml(dayLabel) + '</strong><span>' + escapeHtml(dayYmd) + '</span><button type="button" class="btn lane-use-source-btn" data-day="' + escapeHtml(dayYmd) + '">Use as source</button></div>'
                    + '<div class="lane-day-add"><select class="lane-day-add-select" data-day="' + escapeHtml(dayYmd) + '"><option value="">Add employee...</option>' + addOptions + '</select><button type="button" class="btn lane-day-add-btn" data-day="' + escapeHtml(dayYmd) + '">Add</button></div>'
                    + '<div class="lane-day-lanes">' + (lanesHtml || '<div class="timeclock-muted lane-day-empty">No lanes yet.</div>') + '</div>'
                    + '</div>';
            }).join('');
            boardEl.innerHTML = '<div class="lane-board-grid">' + dayColumnsHtml + '</div>';
            renderRoleLegend();
            refreshWarnings();
        };
        var findShiftById = function (shiftId) { var sid = parseInt(shiftId || '0', 10) || 0; return shifts.find(function (s) { return s.shift_id === sid; }) || null; };

        boardEl.addEventListener('click', function (e) {
            var addBtn = e.target.closest('.lane-day-add-btn');
            if (addBtn) {
                var dayYmd = addBtn.getAttribute('data-day') || '';
                var select = boardEl.querySelector('.lane-day-add-select[data-day="' + dayYmd + '"]');
                var empId = select ? (parseInt(select.value || '0', 10) || 0) : 0;
                if (empId <= 0) return;
                var role = getRoleDefault(), dayStart = startOfDay(dayYmd);
                var startDate = new Date(dayStart.getTime() + (15 * 60 * 60000)); // 3:00 PM
                var endDate = new Date(dayStart.getTime() + (19 * 60 * 60000));   // 7:00 PM
                postOp({ operation: 'create', employee_id: empId, role_name: role, break_minutes: 0, start_local: asLocalInput(startDate), end_local: asLocalInput(endDate) })
                    .then(function (res) {
                        if (!res || !res.success) { alert((res && res.message) ? res.message : 'Unable to add employee lane.'); return; }
                        shifts.push({ shift_id: parseInt(res.shift_id || '0', 10) || 0, employee_id: empId, employee_name: getEmployeeName(empId), role_name: role, break_minutes: 0, start: startDate, end: endDate });
                        render();
                    }).catch(function () { alert('Network error while creating shift.'); });
                return;
            }

            var useSourceBtn = e.target.closest('.lane-use-source-btn');
            if (useSourceBtn) {
                var sourceDay = useSourceBtn.getAttribute('data-day') || '';
                if (copySourceDayEl && sourceDay) {
                    copySourceDayEl.value = sourceDay;
                    copySourceDayEl.focus();
                }
                return;
            }
            var block = e.target.closest('.lane-shift-block');
            if (!block) return;
            if (e.detail === 2) {
                var shiftIdDelete = parseInt(block.getAttribute('data-shift-id') || '0', 10) || 0;
                if (shiftIdDelete <= 0) return;
                if (!confirm('Delete this shift?')) return;
                postOp({ operation: 'delete', shift_id: shiftIdDelete }).then(function (res) {
                    if (!res || !res.success) { alert((res && res.message) ? res.message : 'Unable to delete shift.'); return; }
                    shifts = shifts.filter(function (s) { return s.shift_id !== shiftIdDelete; });
                    render();
                }).catch(function () { alert('Network error while deleting shift.'); });
            }
        });

        boardEl.addEventListener('mousedown', function (e) {
            var block = e.target.closest('.lane-shift-block');
            if (!block) return;
            var shiftId = parseInt(block.getAttribute('data-shift-id') || '0', 10) || 0;
            var dayYmd = block.getAttribute('data-day') || '';
            var shift = findShiftById(shiftId);
            if (!shift || !dayYmd) return;
            var handle = e.target.closest('.lane-shift-handle');
            var mode = handle ? (handle.getAttribute('data-handle') === 'start' ? 'resize-start' : 'resize-end') : 'move';
            var startM = roundToSlot(minutesFromDate(shift.start));
            var endM = roundToSlot(minutesFromDate(shift.end));
            dragState = { mode: mode, shiftId: shiftId, dayYmd: dayYmd, startY: e.clientY, initialStartM: startM, initialEndM: endM, latestStartM: startM, latestEndM: endM };
            document.body.classList.add('lane-dragging');
            e.preventDefault();
        });

        document.addEventListener('mousemove', function (e) {
            if (!dragState) return;
            var block = boardEl.querySelector('.lane-shift-block[data-shift-id="' + dragState.shiftId + '"][data-day="' + dragState.dayYmd + '"]');
            if (!block) return;
            var deltaSlots = Math.round((e.clientY - dragState.startY) / SLOT_PIXELS);
            var deltaM = deltaSlots * SLOT_MINUTES;
            var newStartM = dragState.initialStartM, newEndM = dragState.initialEndM;
            if (dragState.mode === 'move') {
                newStartM = dragState.initialStartM + deltaM; newEndM = dragState.initialEndM + deltaM;
                var dur = dragState.initialEndM - dragState.initialStartM;
                if (newStartM < DAY_START_MINUTES) { newStartM = DAY_START_MINUTES; newEndM = newStartM + dur; }
                if (newEndM > DAY_END_MINUTES) { newEndM = DAY_END_MINUTES; newStartM = newEndM - dur; }
            } else if (dragState.mode === 'resize-start') {
                newStartM = clamp(dragState.initialStartM + deltaM, DAY_START_MINUTES, dragState.initialEndM - SLOT_MINUTES);
            } else {
                newEndM = clamp(dragState.initialEndM + deltaM, dragState.initialStartM + SLOT_MINUTES, DAY_END_MINUTES);
            }
            newStartM = roundToSlot(newStartM); newEndM = roundToSlot(newEndM);
            if (newEndM <= newStartM) newEndM = newStartM + SLOT_MINUTES;
            dragState.latestStartM = newStartM; dragState.latestEndM = newEndM;
            var top = ((newStartM - DAY_START_MINUTES) / SLOT_MINUTES) * SLOT_PIXELS;
            var h = ((newEndM - newStartM) / SLOT_MINUTES) * SLOT_PIXELS;
            block.style.top = top + 'px'; block.style.height = Math.max(10, h) + 'px';
            var content = block.querySelector('.lane-shift-content');
            if (content) content.textContent = formatTime(newStartM) + ' - ' + formatTime(newEndM);
        });

        document.addEventListener('mouseup', function () {
            if (!dragState) return;
            var shift = findShiftById(dragState.shiftId);
            if (!shift) { dragState = null; document.body.classList.remove('lane-dragging'); return; }
            var prevStart = new Date(shift.start.getTime()), prevEnd = new Date(shift.end.getTime());
            setShiftTimesForDay(shift, dragState.dayYmd, dragState.latestStartM, dragState.latestEndM);
            postOp({
                operation: 'update',
                shift_id: shift.shift_id,
                employee_id: shift.employee_id,
                role_name: shift.role_name || 'Employee',
                break_minutes: shift.break_minutes || 0,
                start_local: asLocalInput(shift.start),
                end_local: asLocalInput(shift.end)
            }).then(function (res) {
                if (!res || !res.success) { shift.start = prevStart; shift.end = prevEnd; alert((res && res.message) ? res.message : 'Unable to update shift.'); }
                render();
            }).catch(function () {
                shift.start = prevStart; shift.end = prevEnd;
                alert('Network error while updating shift.');
                render();
            });
            dragState = null;
            document.body.classList.remove('lane-dragging');
        });

        if (publishBtn) publishBtn.addEventListener('click', function () {
            postOp({ operation: 'publish_week', week_start_date: weekStartYmd, week_end_date: weekEndYmd }).then(function (res) {
                if (!res || !res.success) { alert((res && res.message) ? res.message : 'Unable to publish week.'); return; }
                setWeekStatus('PUBLISHED');
            }).catch(function () { alert('Network error while publishing week.'); });
        });
        if (unpublishBtn) unpublishBtn.addEventListener('click', function () {
            postOp({ operation: 'unpublish_week', week_start_date: weekStartYmd, week_end_date: weekEndYmd }).then(function (res) {
                if (!res || !res.success) { alert((res && res.message) ? res.message : 'Unable to unpublish week.'); return; }
                setWeekStatus('DRAFT');
            }).catch(function () { alert('Network error while unpublishing week.'); });
        });
        if (copyPrevBtn) copyPrevBtn.addEventListener('click', function () {
            if (!confirm('Copy previous week schedule into this week? Existing overlaps will be skipped.')) return;
            postOp({ operation: 'copy_previous_week', week_start_date: weekStartYmd, week_end_date: weekEndYmd }).then(function (res) {
                if (!res || !res.success) { alert((res && res.message) ? res.message : 'Unable to copy previous week.'); return; }
                window.location.reload();
            }).catch(function () { alert('Network error while copying previous week.'); });
        });
        if (applyDaysBtn) applyDaysBtn.addEventListener('click', function () {
            var empId = applyEmployeeEl ? (parseInt(applyEmployeeEl.value || '0', 10) || 0) : 0;
            var role = applyRoleEl && applyRoleEl.value ? applyRoleEl.value : getRoleDefault();
            if (empId <= 0) { alert('Choose an employee to apply.'); return; }
            var selectedDays = Array.prototype.slice.call(document.querySelectorAll('.tc_lane_apply_day:checked')).map(function (el) { return String(el.value || ''); }).filter(Boolean);
            if (!selectedDays.length) { alert('Select at least one day.'); return; }
            var pending = selectedDays.slice(), added = 0, skipped = 0;
            var runNext = function () {
                if (!pending.length) { render(); alert('Applied days complete. Added ' + added + ', skipped ' + skipped + '.'); return; }
                var dayYmd = pending.shift();
                var hasExisting = shifts.some(function (s) { return s.employee_id === empId && isoYmd(s.start) <= dayYmd && isoYmd(s.end) >= dayYmd; });
                if (hasExisting) { skipped++; runNext(); return; }
                var dayStart = startOfDay(dayYmd);
                var startDate = new Date(dayStart.getTime() + (15 * 60 * 60000));
                var endDate = new Date(dayStart.getTime() + (19 * 60 * 60000));
                postOp({
                    operation: 'create',
                    employee_id: empId,
                    role_name: role,
                    break_minutes: 0,
                    start_local: asLocalInput(startDate),
                    end_local: asLocalInput(endDate)
                }).then(function (res) {
                    if (res && res.success) {
                        shifts.push({ shift_id: parseInt(res.shift_id || '0', 10) || 0, employee_id: empId, employee_name: getEmployeeName(empId), role_name: role, break_minutes: 0, start: startDate, end: endDate });
                        added++;
                    } else skipped++;
                    runNext();
                }).catch(function () { skipped++; runNext(); });
            };
            runNext();
        });

        if (copyDayBtn) copyDayBtn.addEventListener('click', function () {
            var sourceDay = copySourceDayEl ? String(copySourceDayEl.value || '') : '';
            if (!sourceDay) {
                alert('Select a source day first.');
                return;
            }
            var selectedTargets = Array.prototype.slice.call(document.querySelectorAll('.tc_lane_copy_target_day:checked'))
                .map(function (el) { return String(el.value || ''); })
                .filter(function (v) { return !!v && v !== sourceDay; });
            if (!selectedTargets.length) {
                alert('Select at least one target day (different from source).');
                return;
            }
            var sourceShifts = shifts.filter(function (s) { return isoYmd(s.start) === sourceDay; });
            if (!sourceShifts.length) {
                alert('No source-day shifts to copy.');
                return;
            }

            var queue = [];
            selectedTargets.forEach(function (targetDay) {
                sourceShifts.forEach(function (src) {
                    var sourceStartM = minutesFromDate(src.start);
                    var durationM = Math.max(SLOT_MINUTES, roundToSlot((src.end.getTime() - src.start.getTime()) / 60000));
                    var targetStart = new Date(startOfDay(targetDay).getTime() + (sourceStartM * 60000));
                    var targetEnd = new Date(targetStart.getTime() + (durationM * 60000));
                    queue.push({
                        targetDay: targetDay,
                        employee_id: src.employee_id,
                        employee_name: src.employee_name,
                        role_name: src.role_name || 'Employee',
                        break_minutes: src.break_minutes || 0,
                        start: targetStart,
                        end: targetEnd
                    });
                });
            });

            var added = 0;
            var skipped = 0;
            var runNextCopy = function () {
                if (!queue.length) {
                    render();
                    alert('Copy day complete. Added ' + added + ', skipped ' + skipped + '.');
                    return;
                }
                var item = queue.shift();
                var overlapsExisting = shifts.some(function (s) {
                    if (s.employee_id !== item.employee_id) return false;
                    var day = item.targetDay;
                    return isoYmd(s.start) <= day && isoYmd(s.end) >= day;
                });
                if (overlapsExisting) {
                    skipped++;
                    runNextCopy();
                    return;
                }
                postOp({
                    operation: 'create',
                    employee_id: item.employee_id,
                    role_name: item.role_name,
                    break_minutes: item.break_minutes,
                    start_local: asLocalInput(item.start),
                    end_local: asLocalInput(item.end)
                }).then(function (res) {
                    if (res && res.success) {
                        shifts.push({
                            shift_id: parseInt(res.shift_id || '0', 10) || 0,
                            employee_id: item.employee_id,
                            employee_name: item.employee_name || getEmployeeName(item.employee_id),
                            role_name: item.role_name,
                            break_minutes: item.break_minutes,
                            start: item.start,
                            end: item.end
                        });
                        added++;
                    } else {
                        skipped++;
                    }
                    runNextCopy();
                }).catch(function () {
                    skipped++;
                    runNextCopy();
                });
            };
            runNextCopy();
        });

        setWeekStatus(weekStatus);
        render();
    })();
    (function () {
        var kiosk = document.body.classList.contains('timeclock-kiosk');
        if (!kiosk) return;
        var form = document.getElementById('timeclock-punch-form');
        var employeeSelect = document.getElementById('tc_employee_id');
        var pinInput = document.getElementById('tc_pin');
        var noteInput = document.getElementById('tc_note');
        var gpsStatus = document.getElementById('tc_gps_status');
        var geoStatus = document.getElementById('tc_geo_status');
        var feedbackEl = document.querySelector('.kiosk-feedback');
        var queueStatusEl = document.getElementById('kiosk_queue_status');
        var failedTbody = document.getElementById('kiosk_failed_tbody');
        var retryFailedAllBtn = document.getElementById('kiosk_retry_failed_all');
        var clearFailedAllBtn = document.getElementById('kiosk_clear_failed_all');
        if (!form || !employeeSelect || !pinInput) return;
        var QUEUE_KEY = 'tc_kiosk_punch_queue_v1';
        var FAILED_KEY = 'tc_kiosk_punch_failed_v1';
        var DEVICE_KEY = 'tc_kiosk_device_id_v1';
        var lastPunchType = 'in';
        var syncing = false;
        var deviceId = '';

        var resetForm = function () {
            form.reset();
            if (gpsStatus) gpsStatus.value = 'unavailable';
            if (geoStatus) geoStatus.textContent = 'GPS: Not captured yet';
            employeeSelect.focus();
        };
        var readQueue = function () {
            try {
                var parsed = JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]');
                return Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                return [];
            }
        };
        var writeQueue = function (items) {
            try { localStorage.setItem(QUEUE_KEY, JSON.stringify(items || [])); } catch (e) {}
        };
        var readFailed = function () {
            try {
                var parsed = JSON.parse(localStorage.getItem(FAILED_KEY) || '[]');
                return Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                return [];
            }
        };
        var writeFailed = function (items) {
            try { localStorage.setItem(FAILED_KEY, JSON.stringify(items || [])); } catch (e) {}
        };
        var escapeHtml = function (value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };
        var renderFailed = function () {
            if (!failedTbody) return;
            var failed = readFailed();
            if (!failed.length) {
                failedTbody.innerHTML = '<tr><td colspan="5" class="timeclock-muted">No failed offline punches.</td></tr>';
                return;
            }
            failedTbody.innerHTML = failed.map(function (item, idx) {
                var p = item.payload || {};
                var when = item.failed_at || p.queued_at || '';
                var employee = p.employee_name || ('#' + (p.employee_id || '?'));
                var punch = (p.punch_type === 'out' ? 'Clock Out' : 'Clock In');
                var reason = item.error || 'Sync rejected';
                return '<tr>'
                    + '<td>' + escapeHtml(when) + '</td>'
                    + '<td>' + escapeHtml(employee) + '</td>'
                    + '<td>' + escapeHtml(punch) + '</td>'
                    + '<td>' + escapeHtml(reason) + '</td>'
                    + '<td>'
                    + '<button type="button" class="btn kiosk-failed-action" data-failed-action="retry" data-failed-idx="' + idx + '">Retry</button> '
                    + '<button type="button" class="btn kiosk-failed-action" data-failed-action="remove" data-failed-idx="' + idx + '">Remove</button>'
                    + '</td>'
                    + '</tr>';
            }).join('');
        };
        var renderQueueStatus = function (hint) {
            if (!queueStatusEl) return;
            var count = readQueue().length;
            var failedCount = readFailed().length;
            queueStatusEl.textContent = 'Queued punches: ' + count + ' | Failed sync: ' + failedCount + (hint ? (' - ' + hint) : '');
        };
        var buildPayload = function (punchType) {
            var selectedOption = employeeSelect.options[employeeSelect.selectedIndex];
            return {
                timeclock_punch_json: '1',
                kiosk: '1',
                device_id: deviceId || 'unknown',
                store_id: form.querySelector('input[name="store_id"]') ? form.querySelector('input[name="store_id"]').value : '',
                employee_id: employeeSelect.value || '',
                employee_name: selectedOption ? selectedOption.text : '',
                pin: pinInput.value || '',
                punch_type: (punchType === 'out' ? 'out' : 'in'),
                punch_note: noteInput ? (noteInput.value || '') : '',
                gps_lat: (document.getElementById('tc_gps_lat') || {}).value || '',
                gps_lng: (document.getElementById('tc_gps_lng') || {}).value || '',
                gps_accuracy_m: (document.getElementById('tc_gps_accuracy_m') || {}).value || '',
                gps_status: (gpsStatus || {}).value || 'unavailable',
                queued_at: (new Date()).toLocaleString()
            };
        };
        var moveToFailed = function (payload, errorMessage) {
            var failed = readFailed();
            failed.unshift({
                payload: payload,
                error: errorMessage || 'Sync rejected',
                failed_at: (new Date()).toLocaleString()
            });
            writeFailed(failed);
            renderFailed();
        };
        var pushQueue = function (payload) {
            var q = readQueue();
            q.push(payload);
            writeQueue(q);
            renderQueueStatus('saved offline');
        };
        var postPayload = function (payload) {
            var fd = new FormData();
            Object.keys(payload || {}).forEach(function (k) { fd.append(k, payload[k]); });
            return fetch('index.php?action=timeclock&kiosk=1', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); });
        };
        var flushQueue = function () {
            if (!navigator.onLine || syncing) return;
            var q = readQueue();
            if (!q.length) {
                renderQueueStatus('');
                return;
            }
            syncing = true;
            var next = q[0];
            postPayload(next).then(function (res) {
                if (res && res.success) {
                    q.shift();
                    writeQueue(q);
                    renderQueueStatus('synced');
                    syncing = false;
                    if (q.length) flushQueue();
                } else {
                    q.shift();
                    writeQueue(q);
                    moveToFailed(next, (res && res.message) ? res.message : 'Sync rejected');
                    syncing = false;
                    renderQueueStatus('moved to failed');
                    if (q.length) flushQueue();
                }
            }).catch(function () {
                syncing = false;
                renderQueueStatus('offline');
            });
        };

        var idleSeconds = parseInt(form.getAttribute('data-kiosk-idle-seconds') || '75', 10);
        if (!Number.isFinite(idleSeconds) || idleSeconds < 30) idleSeconds = 75;
        var idleMs = idleSeconds * 1000;
        var timer = null;
        var bumpIdle = function () {
            if (timer) window.clearTimeout(timer);
            timer = window.setTimeout(resetForm, idleMs);
        };

        document.querySelectorAll('.kiosk-emp-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-employee-id');
                if (!id) return;
                employeeSelect.value = id;
                pinInput.focus();
                bumpIdle();
            });
        });
        document.querySelectorAll('.kiosk-key').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var key = btn.getAttribute('data-kiosk-key') || '';
                if (!key) return;
                if (key === 'clear') {
                    pinInput.value = '';
                } else if (key === 'back') {
                    pinInput.value = pinInput.value.slice(0, -1);
                } else if (/^\d$/.test(key) && pinInput.value.length < 10) {
                    pinInput.value += key;
                }
                pinInput.focus();
                bumpIdle();
            });
        });
        pinInput.addEventListener('input', function () {
            pinInput.value = (pinInput.value || '').replace(/\D+/g, '').slice(0, 10);
        });
        pinInput.addEventListener('keydown', function (e) {
            var allowed = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter'];
            if (allowed.indexOf(e.key) !== -1) return;
            if (!/^\d$/.test(e.key)) {
                e.preventDefault();
            }
        });
        form.querySelectorAll('button[type="submit"][name="punch_type"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                lastPunchType = btn.value === 'out' ? 'out' : 'in';
            });
        });
        form.addEventListener('submit', function (e) {
            if (navigator.onLine) return true;
            e.preventDefault();
            var payload = buildPayload(lastPunchType);
            if (!payload.employee_id || !payload.pin) {
                renderQueueStatus('employee and PIN required');
                return false;
            }
            pushQueue(payload);
            resetForm();
            return false;
        });
        window.addEventListener('online', flushQueue);
        window.setInterval(flushQueue, 20000);
        if (retryFailedAllBtn) {
            retryFailedAllBtn.addEventListener('click', function () {
                var failed = readFailed();
                if (!failed.length) return;
                var q = readQueue();
                failed.forEach(function (item) {
                    if (item && item.payload) q.push(item.payload);
                });
                writeQueue(q);
                writeFailed([]);
                renderFailed();
                renderQueueStatus('failed moved to queue');
                flushQueue();
            });
        }
        if (clearFailedAllBtn) {
            clearFailedAllBtn.addEventListener('click', function () {
                writeFailed([]);
                renderFailed();
                renderQueueStatus('failed cleared');
            });
        }
        if (failedTbody) {
            failedTbody.addEventListener('click', function (e) {
                var btn = e.target && e.target.closest ? e.target.closest('.kiosk-failed-action') : null;
                if (!btn) return;
                var action = btn.getAttribute('data-failed-action') || '';
                var idx = parseInt(btn.getAttribute('data-failed-idx') || '-1', 10);
                if (!Number.isFinite(idx) || idx < 0) return;
                var failed = readFailed();
                if (!failed[idx]) return;
                if (action === 'remove') {
                    failed.splice(idx, 1);
                    writeFailed(failed);
                    renderFailed();
                    renderQueueStatus('failed item removed');
                    return;
                }
                if (action === 'retry') {
                    var q = readQueue();
                    if (failed[idx].payload) q.push(failed[idx].payload);
                    failed.splice(idx, 1);
                    writeQueue(q);
                    writeFailed(failed);
                    renderFailed();
                    renderQueueStatus('retry queued');
                    flushQueue();
                }
            });
        }

        ['click', 'keydown', 'input', 'touchstart'].forEach(function (evt) {
            document.addEventListener(evt, bumpIdle, { passive: true });
        });
        [employeeSelect, pinInput, noteInput].forEach(function (el) {
            if (!el) return;
            el.addEventListener('input', bumpIdle);
            el.addEventListener('change', bumpIdle);
        });

        employeeSelect.focus();
        try {
            deviceId = localStorage.getItem(DEVICE_KEY) || '';
            if (!deviceId) {
                deviceId = 'kiosk-' + Math.random().toString(36).slice(2, 10);
                localStorage.setItem(DEVICE_KEY, deviceId);
            }
        } catch (e) {
            deviceId = 'kiosk-unknown';
        }
        bumpIdle();
        renderFailed();
        renderQueueStatus('');
        if (navigator.onLine) flushQueue();
        if (feedbackEl) {
            window.setTimeout(function () {
                if (feedbackEl && feedbackEl.parentNode) {
                    feedbackEl.parentNode.removeChild(feedbackEl);
                }
                try {
                    var url = new URL(window.location.href);
                    url.searchParams.delete('success');
                    url.searchParams.delete('error');
                    window.history.replaceState({}, '', url.toString());
                } catch (e) {}
            }, 2400);
        }
    })();
    (function () {
        var defaultEmp = document.getElementById('tc_sched_default_employee');
        var defaultRole = document.getElementById('tc_sched_default_role');
        var defaultMgr = document.getElementById('tc_sched_default_manager');
        if (!defaultEmp || !defaultRole || !defaultMgr) return;

        var KEY_EMP = 'tc_sched_default_employee';
        var KEY_ROLE = 'tc_sched_default_role';
        var KEY_MGR = 'tc_sched_default_manager';

        var applyDefaultsToForms = function () {
            var empVal = defaultEmp.value || '';
            var roleVal = defaultRole.value || '';
            var mgrVal = defaultMgr.value || '';

            document.querySelectorAll('.schedule-employee-input').forEach(function (el) {
                if (!el.value && empVal) el.value = empVal;
            });
            document.querySelectorAll('.schedule-role-input').forEach(function (el) {
                if ((!el.value || el.value === 'Employee') && roleVal) el.value = roleVal;
            });
            document.querySelectorAll('.schedule-manager-input').forEach(function (el) {
                if (!el.value && mgrVal) el.value = mgrVal;
            });
        };

        var loadStored = function () {
            try {
                var vEmp = localStorage.getItem(KEY_EMP) || '';
                var vRole = localStorage.getItem(KEY_ROLE) || '';
                var vMgr = localStorage.getItem(KEY_MGR) || '';
                if (vEmp) defaultEmp.value = vEmp;
                if (vRole) defaultRole.value = vRole;
                if (vMgr) defaultMgr.value = vMgr;
            } catch (e) {}
        };

        var saveStored = function () {
            try {
                localStorage.setItem(KEY_EMP, defaultEmp.value || '');
                localStorage.setItem(KEY_ROLE, defaultRole.value || '');
                localStorage.setItem(KEY_MGR, defaultMgr.value || '');
            } catch (e) {}
        };

        loadStored();
        applyDefaultsToForms();

        [defaultEmp, defaultRole, defaultMgr].forEach(function (el) {
            el.addEventListener('change', function () {
                saveStored();
                applyDefaultsToForms();
            });
            el.addEventListener('input', function () {
                saveStored();
                applyDefaultsToForms();
            });
        });
    })();
    (function () {
        var dataEl = document.getElementById('tc_staff_schedule_data');
        if (!dataEl) return;
        var rows = [];
        try {
            rows = JSON.parse(dataEl.value || '[]');
            if (!Array.isArray(rows)) rows = [];
        } catch (e) {
            rows = [];
        }
        var escapeHtml = function (value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };

        var renderWidget = function (widget) {
            if (!widget) return;
            var select = widget.querySelector('.staff-schedule-employee-select');
            var list = widget.querySelector('.staff-schedule-list');
            if (!select || !list) return;

            var employeeId = parseInt(select.value || '0', 10);
            if (!Number.isFinite(employeeId) || employeeId <= 0) {
                list.innerHTML = '<p class="timeclock-muted">Select an employee to see scheduled shifts.</p>';
                return;
            }

            var employeeRows = rows.filter(function (r) {
                return parseInt(r.employee_id || '0', 10) === employeeId;
            });
            if (!employeeRows.length) {
                list.innerHTML = '<p class="timeclock-muted">No scheduled shifts for this week.</p>';
                return;
            }

            list.innerHTML = employeeRows.map(function (r) {
                var role = escapeHtml(r.role_name || 'Employee');
                var start = escapeHtml(r.start_local || '');
                var end = escapeHtml(r.end_local || '');
                return '<div class="staff-schedule-item">'
                    + '<div><strong>' + role + '</strong></div>'
                    + '<div>' + start + ' - ' + end + '</div>'
                    + '</div>';
            }).join('');
        };

        document.querySelectorAll('[data-staff-schedule-widget]').forEach(function (widget) {
            var select = widget.querySelector('.staff-schedule-employee-select');
            if (!select) return;
            if (!select.value && rows.length) {
                for (var i = 0; i < select.options.length; i++) {
                    var optVal = parseInt(select.options[i].value || '0', 10);
                    if (!Number.isFinite(optVal) || optVal <= 0) continue;
                    var hasShifts = rows.some(function (r) {
                        return parseInt(r.employee_id || '0', 10) === optVal;
                    });
                    if (hasShifts) {
                        select.value = String(optVal);
                        break;
                    }
                }
            }
            select.addEventListener('change', function () {
                renderWidget(widget);
            });
            renderWidget(widget);
        });

        var punchEmployeeSelect = document.getElementById('tc_employee_id');
        var kioskWidgetEmployee = document.getElementById('tc_staff_view_employee_kiosk');
        if (punchEmployeeSelect && kioskWidgetEmployee) {
            punchEmployeeSelect.addEventListener('change', function () {
                kioskWidgetEmployee.value = punchEmployeeSelect.value || '';
                var widget = kioskWidgetEmployee.closest('[data-staff-schedule-widget]');
                if (widget) renderWidget(widget);
            });
        }
    })();
    (function () {
        var scheduleDataEl = document.getElementById('tc_staff_schedule_data');
        var ptoDataEl = document.getElementById('tc_staff_pto_data');
        var workedDataEl = document.getElementById('tc_staff_worked_data');
        var employeeEl = document.getElementById('tc_staff_calendar_employee');
        var modeEl = document.getElementById('tc_staff_calendar_mode');
        var dateEl = document.getElementById('tc_staff_calendar_date');
        var titleEl = document.getElementById('tc_staff_calendar_title');
        var gridEl = document.getElementById('tc_staff_calendar_grid');
        var prevBtn = document.getElementById('tc_staff_calendar_prev');
        var nextBtn = document.getElementById('tc_staff_calendar_next');
        var todayBtn = document.getElementById('tc_staff_calendar_today_btn');
        var managerDayEl = document.getElementById('tc_manager_day_date');
        var managerDayTbody = document.getElementById('tc_manager_day_tbody');
        var todayEl = document.getElementById('tc_staff_calendar_today');
        var anchorEl = document.getElementById('tc_staff_calendar_anchor');
        if (!scheduleDataEl || !employeeEl || !modeEl || !dateEl || !titleEl || !gridEl || !managerDayEl || !managerDayTbody) return;

        var parseJsonArray = function (el) {
            try {
                var parsed = JSON.parse((el && el.value) || '[]');
                return Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                return [];
            }
        };
        var scheduleRows = parseJsonArray(scheduleDataEl);
        var ptoRows = parseJsonArray(ptoDataEl);
        var workedRows = parseJsonArray(workedDataEl);
        var todayYmd = (todayEl && todayEl.value) ? todayEl.value : '';
        var anchorYmd = (anchorEl && anchorEl.value) ? anchorEl.value : todayYmd;

        var escapeHtml = function (value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };
        var parseYmd = function (ymd) {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(String(ymd || ''))) return null;
            var parts = ymd.split('-');
            var dt = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
            if (Number.isNaN(dt.getTime())) return null;
            return dt;
        };
        var toYmd = function (dt) {
            if (!(dt instanceof Date) || Number.isNaN(dt.getTime())) return '';
            var y = dt.getFullYear();
            var m = String(dt.getMonth() + 1).padStart(2, '0');
            var d = String(dt.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + d;
        };
        var addDays = function (dt, count) {
            var d = new Date(dt.getTime());
            d.setDate(d.getDate() + count);
            return d;
        };
        var startOfWeek = function (dt) {
            var d = new Date(dt.getTime());
            var dow = d.getDay(); // 0..6
            var offset = dow === 0 ? -6 : (1 - dow);
            d.setDate(d.getDate() + offset);
            return d;
        };
        var endOfWeek = function (dt) {
            return addDays(startOfWeek(dt), 6);
        };
        var startOfMonth = function (dt) {
            return new Date(dt.getFullYear(), dt.getMonth(), 1);
        };
        var endOfMonth = function (dt) {
            return new Date(dt.getFullYear(), dt.getMonth() + 1, 0);
        };
        var prettyDate = function (dt) {
            return dt.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
        };
        var monthTitle = function (dt) {
            return dt.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
        };
        var overlapsDay = function (startYmd, endYmd, targetYmd) {
            if (!startYmd || !endYmd || !targetYmd) return false;
            return startYmd <= targetYmd && endYmd >= targetYmd;
        };
        var firstEmployeeWithRows = function () {
            for (var i = 0; i < employeeEl.options.length; i++) {
                var opt = employeeEl.options[i];
                var id = parseInt(opt.value || '0', 10);
                if (!Number.isFinite(id) || id <= 0) continue;
                var hasAny = scheduleRows.some(function (r) { return parseInt(r.employee_id || '0', 10) === id; })
                    || ptoRows.some(function (r) { return parseInt(r.employee_id || '0', 10) === id; });
                if (hasAny) return String(id);
            }
            return '';
        };
        if (!employeeEl.value) {
            employeeEl.value = firstEmployeeWithRows();
        }
        if (!dateEl.value && anchorYmd) {
            dateEl.value = anchorYmd;
        }

        var renderCalendar = function () {
            var empId = parseInt(employeeEl.value || '0', 10);
            var mode = (modeEl.value === 'month') ? 'month' : 'week';
            var anchor = parseYmd(dateEl.value || anchorYmd || todayYmd);
            if (!anchor || !Number.isFinite(empId) || empId <= 0) {
                titleEl.textContent = 'Choose an employee to view calendar.';
                gridEl.innerHTML = '<div class="timeclock-muted">Select an employee to load calendar details.</div>';
                return;
            }

            var rangeStart = mode === 'month' ? startOfMonth(anchor) : startOfWeek(anchor);
            var rangeEnd = mode === 'month' ? endOfMonth(anchor) : endOfWeek(anchor);
            var rangeStartYmd = toYmd(rangeStart);
            var rangeEndYmd = toYmd(rangeEnd);
            titleEl.textContent = mode === 'month'
                ? (monthTitle(anchor) + ' - ' + prettyDate(rangeStart) + ' to ' + prettyDate(rangeEnd))
                : ('Week of ' + prettyDate(rangeStart) + ' - ' + prettyDate(rangeEnd));

            var cells = [];
            var cursor = new Date(rangeStart.getTime());
            while (cursor <= rangeEnd) {
                var dayYmd = toYmd(cursor);
                var dayShifts = scheduleRows.filter(function (r) {
                    return parseInt(r.employee_id || '0', 10) === empId
                        && overlapsDay(r.start_date_ymd || '', r.end_date_ymd || (r.start_date_ymd || ''), dayYmd);
                });
                var dayPto = ptoRows.some(function (r) {
                    return parseInt(r.employee_id || '0', 10) === empId
                        && overlapsDay(r.start_date_ymd || '', r.end_date_ymd || '', dayYmd);
                });
                var classes = ['staff-calendar-day'];
                var statusLabel = 'OFF';
                if (dayPto) {
                    classes.push('is-pto');
                    statusLabel = 'VACATION';
                } else if (dayShifts.length) {
                    classes.push('is-scheduled');
                    statusLabel = 'WORKING';
                } else {
                    classes.push('is-off');
                }
                if (dayYmd === todayYmd) {
                    classes.push('is-today');
                    if (dayShifts.length) classes.push('is-working-today');
                }

                var shiftHtml = '';
                if (dayShifts.length) {
                    shiftHtml = dayShifts.map(function (s) {
                        return '<div class="staff-calendar-shift">'
                            + '<strong>' + escapeHtml(s.role_name || 'Shift') + '</strong>'
                            + '<span>' + escapeHtml((s.start_time_label || '') + ' - ' + (s.end_time_label || '')) + '</span>'
                            + '</div>';
                    }).join('');
                } else if (dayPto) {
                    shiftHtml = '<div class="staff-calendar-note">PTO / Vacation day</div>';
                } else {
                    shiftHtml = '<div class="staff-calendar-note">Day off</div>';
                }

                cells.push(
                    '<div class="' + classes.join(' ') + '">'
                    + '<div class="staff-calendar-day-head">'
                    + '<div class="staff-calendar-day-label">' + escapeHtml(prettyDate(cursor)) + '</div>'
                    + '<span class="staff-calendar-day-status">' + escapeHtml(statusLabel) + '</span>'
                    + '</div>'
                    + '<div class="staff-calendar-day-body">' + shiftHtml + '</div>'
                    + '</div>'
                );
                cursor = addDays(cursor, 1);
            }

            gridEl.classList.toggle('is-month', mode === 'month');
            gridEl.classList.toggle('is-week', mode === 'week');
            gridEl.innerHTML = cells.join('');
        };

        var renderManagerDayBoard = function () {
            var targetYmd = managerDayEl.value || todayYmd;
            if (!/^\d{4}-\d{2}-\d{2}$/.test(String(targetYmd || ''))) {
                managerDayTbody.innerHTML = '<tr><td colspan="4" class="timeclock-muted">Pick a valid day.</td></tr>';
                return;
            }

            var scheduleByEmp = {};
            scheduleRows.forEach(function (r) {
                var empId = parseInt(r.employee_id || '0', 10);
                if (!Number.isFinite(empId) || empId <= 0) return;
                if (!overlapsDay(r.start_date_ymd || '', r.end_date_ymd || (r.start_date_ymd || ''), targetYmd)) return;
                if (!scheduleByEmp[empId]) {
                    scheduleByEmp[empId] = { name: r.employee_name || ('Employee #' + empId), shifts: [] };
                }
                scheduleByEmp[empId].shifts.push((r.start_time_label || '') + ' - ' + (r.end_time_label || ''));
            });

            var workedByEmp = {};
            workedRows.forEach(function (r) {
                var empId = parseInt(r.employee_id || '0', 10);
                if (!Number.isFinite(empId) || empId <= 0) return;
                var inYmd = r.clock_in_date_ymd || '';
                var outYmd = r.clock_out_date_ymd || inYmd;
                if (!overlapsDay(inYmd, outYmd || inYmd, targetYmd)) return;
                if (!workedByEmp[empId]) {
                    workedByEmp[empId] = { name: r.employee_name || ('Employee #' + empId), entries: [], hasOpen: false };
                }
                var outLabel = r.clock_out_time_label || 'OPEN';
                if (!r.clock_out_time_label) workedByEmp[empId].hasOpen = true;
                workedByEmp[empId].entries.push((r.clock_in_time_label || '?') + ' - ' + outLabel);
            });

            var ptoByEmp = {};
            ptoRows.forEach(function (r) {
                var empId = parseInt(r.employee_id || '0', 10);
                if (!Number.isFinite(empId) || empId <= 0) return;
                if (!overlapsDay(r.start_date_ymd || '', r.end_date_ymd || '', targetYmd)) return;
                ptoByEmp[empId] = true;
            });

            var allEmpIds = {};
            Object.keys(scheduleByEmp).forEach(function (k) { allEmpIds[k] = true; });
            Object.keys(workedByEmp).forEach(function (k) { allEmpIds[k] = true; });
            Object.keys(ptoByEmp).forEach(function (k) { allEmpIds[k] = true; });
            var empIds = Object.keys(allEmpIds).map(function (k) { return parseInt(k, 10); }).filter(function (n) { return Number.isFinite(n) && n > 0; });
            empIds.sort(function (a, b) {
                var an = ((scheduleByEmp[a] || workedByEmp[a] || {}).name || '').toLowerCase();
                var bn = ((scheduleByEmp[b] || workedByEmp[b] || {}).name || '').toLowerCase();
                return an < bn ? -1 : (an > bn ? 1 : 0);
            });

            if (!empIds.length) {
                managerDayTbody.innerHTML = '<tr><td colspan="4" class="timeclock-muted">No scheduled or worked rows for this date.</td></tr>';
                return;
            }

            var rowsHtml = empIds.map(function (empId) {
                var sch = scheduleByEmp[empId] || null;
                var wrk = workedByEmp[empId] || null;
                var onPto = !!ptoByEmp[empId];
                var name = (sch && sch.name) || (wrk && wrk.name) || ('Employee #' + empId);
                var status = 'Off';
                var statusClass = 'timeclock-badge-ok';
                if (onPto) {
                    status = 'On Vacation/PTO';
                    statusClass = 'timeclock-badge-warning';
                } else if (wrk && wrk.hasOpen) {
                    status = 'Clocked In';
                    statusClass = 'timeclock-badge-danger';
                } else if (wrk && wrk.entries.length) {
                    status = 'Clocked Out';
                    statusClass = 'timeclock-badge-ok';
                } else if (sch && sch.shifts.length) {
                    status = 'Scheduled (no punch yet)';
                    statusClass = 'timeclock-badge-warning';
                }

                return '<tr>'
                    + '<td>' + escapeHtml(name) + '</td>'
                    + '<td>' + escapeHtml(sch && sch.shifts.length ? sch.shifts.join(', ') : '-') + '</td>'
                    + '<td>' + escapeHtml(wrk && wrk.entries.length ? wrk.entries.join(', ') : '-') + '</td>'
                    + '<td><span class="' + statusClass + '">' + escapeHtml(status) + '</span></td>'
                    + '</tr>';
            }).join('');
            managerDayTbody.innerHTML = rowsHtml;
        };

        var shiftRangeByStep = function (step) {
            var anchor = parseYmd(dateEl.value || todayYmd);
            if (!anchor) return;
            var mode = (modeEl.value === 'month') ? 'month' : 'week';
            if (mode === 'month') {
                anchor.setMonth(anchor.getMonth() + step);
            } else {
                anchor.setDate(anchor.getDate() + (7 * step));
            }
            dateEl.value = toYmd(anchor);
            renderCalendar();
        };

        if (!managerDayEl.value) managerDayEl.value = todayYmd;
        [employeeEl, modeEl, dateEl].forEach(function (el) {
            el.addEventListener('change', renderCalendar);
            el.addEventListener('input', renderCalendar);
        });
        managerDayEl.addEventListener('change', renderManagerDayBoard);
        managerDayEl.addEventListener('input', renderManagerDayBoard);
        if (prevBtn) prevBtn.addEventListener('click', function () { shiftRangeByStep(-1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { shiftRangeByStep(1); });
        if (todayBtn) {
            todayBtn.addEventListener('click', function () {
                dateEl.value = todayYmd;
                managerDayEl.value = todayYmd;
                renderCalendar();
                renderManagerDayBoard();
            });
        }
        renderCalendar();
        renderManagerDayBoard();
    })();
    (function () {
        var preset = document.getElementById('tc_pto_preset');
        var method = document.getElementById('tc_pto_method');
        var minPerHour = document.getElementById('tc_pto_min_per_hour');
        var excludeOt = document.getElementById('tc_pto_exclude_ot');
        var cap = document.getElementById('tc_pto_cap');
        var waitDays = document.getElementById('tc_pto_wait_days');
        var sickMode = document.getElementById('tc_sick_mode');
        var sickMinPerHour = document.getElementById('tc_sick_min_per_hour');
        var holidayMode = document.getElementById('tc_holiday_mode');
        var holidayMult = document.getElementById('tc_holiday_multiplier');
        var ptoMgr = document.getElementById('tc_pto_mgr_name');
        var scheduleMgr = document.getElementById('tc_sched_default_manager');
        if (!preset || !method || !minPerHour || !excludeOt || !cap || !waitDays || !sickMode || !sickMinPerHour || !holidayMode || !holidayMult) return;

        if (ptoMgr && scheduleMgr && !ptoMgr.value && scheduleMgr.value) {
            ptoMgr.value = scheduleMgr.value;
        }
        if (ptoMgr && scheduleMgr) {
            scheduleMgr.addEventListener('input', function () {
                if (!ptoMgr.value) ptoMgr.value = scheduleMgr.value;
            });
        }

        preset.addEventListener('change', function () {
            switch (preset.value) {
                case 'pto-40h-year':
                    method.value = 'per_hour_worked';
                    minPerHour.value = '1.15';
                    excludeOt.checked = true;
                    cap.value = '2400';
                    waitDays.value = '0';
                    break;
                case 'pto-80h-year':
                    method.value = 'per_hour_worked';
                    minPerHour.value = '2.31';
                    excludeOt.checked = true;
                    cap.value = '4800';
                    waitDays.value = '0';
                    break;
                case 'sick-1-per-30':
                    sickMode.value = 'per_30_hours';
                    sickMinPerHour.value = '2.00';
                    break;
                case 'holiday-fixed':
                    holidayMode.value = 'fixed_paid_holidays';
                    holidayMult.value = '1.00';
                    break;
                case 'holiday-premium':
                    holidayMode.value = 'premium_if_worked';
                    holidayMult.value = '1.50';
                    break;
            }
        });
    })();
    </script>
    <?php endif; ?>
<?php elseif ($action === 'history'): ?>
    <div class="header app-hero">
        <h1>Historical KPI Data</h1>
        <p class="app-hero-subtitle">Review trend history across stores and date ranges.</p>
        <div class="store-selector">
            <a href="?action=history" class="store-pill <?php echo !$storeId ? 'active' : ''; ?>">All Stores</a>
            <?php foreach ($stores as $store): ?>
                <a href="?action=history&store=<?php echo $store['id']; ?>" class="store-pill <?php echo ($store['id'] == $storeId) ? 'active' : ''; ?>"><?php echo htmlspecialchars($store['name']); ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="history-table">
        <table>
            <thead><tr><th>Date</th><th>Store</th><th>Sales</th><th>COGS</th><th>Labor</th><th>Overhead</th><th>Profit</th><th>Cash Available</th><th>Updated By</th></tr></thead>
            <tbody>
                <?php if (empty($historicalKpis)): ?>
                    <tr><td colspan="9" style="text-align: center; padding: 40px; color: #999;">No historical data found.</td></tr>
                <?php else: ?>
                    <?php foreach ($historicalKpis as $kpi): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($kpi['entry_date']); ?></td>
                            <td><?php echo htmlspecialchars($kpi['store_name']); ?></td>
                            <td><?php echo formatCurrency($kpi['sales_today']); ?></td>
                            <td><?php echo formatCurrency($kpi['cogs_today']); ?></td>
                            <td><?php echo formatCurrency($kpi['labor_today']); ?></td>
                            <td><?php echo formatCurrency($kpi['avg_daily_overhead']); ?></td>
                            <td class="<?php $profit = calculateProfit($kpi['sales_today'], $kpi['cogs_today'], $kpi['labor_today'], $kpi['avg_daily_overhead']); echo $profit < 0 ? 'negative' : ''; ?>"><?php echo formatCurrency($profit); ?></td>
                            <td><?php echo formatCurrency(calculateCashAvailable($kpi['bank_balance'], $kpi['safe_balance'])); ?></td>
                            <td><?php echo htmlspecialchars($kpi['updated_by'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; // end KPI action block ?>
<script>
(function () {
    var toasts = document.querySelectorAll('.js-toast');
    if (!toasts.length) return;
    toasts.forEach(function (toast) {
        var closeBtn = toast.querySelector('.message-close');
        var dismiss = function () {
            toast.classList.add('is-dismissing');
            window.setTimeout(function () {
                if (toast && toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 180);
        };
        if (closeBtn) {
            closeBtn.addEventListener('click', dismiss);
        }
        window.setTimeout(dismiss, 5200);
    });
})();

(function () {
    var focusableSelector = 'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])';
    var activeModal = null;
    var restoreFocusTo = null;
    var getVisibleModal = function () {
        var candidates = document.querySelectorAll('.modal, .timeclock-popup-section.open');
        for (var i = 0; i < candidates.length; i++) {
            var node = candidates[i];
            if (node.classList.contains('timeclock-popup-section') && node.classList.contains('open')) return node;
            if (node.classList.contains('modal') && node.style.display !== 'none') return node;
        }
        return null;
    };
    var setModalA11y = function (modal, open) {
        if (!modal) return;
        if (open) {
            modal.setAttribute('aria-hidden', 'false');
            modal.setAttribute('tabindex', '-1');
            if (!modal.getAttribute('role')) modal.setAttribute('role', 'dialog');
            if (!modal.getAttribute('aria-modal')) modal.setAttribute('aria-modal', 'true');
        } else {
            modal.setAttribute('aria-hidden', 'true');
        }
    };
    var trapKeydown = function (e) {
        if (!activeModal) return;
        if (e.key === 'Escape') {
            var closeButton = activeModal.querySelector('.modal-close, .timeclock-popup-close');
            if (closeButton) closeButton.click();
            return;
        }
        if (e.key !== 'Tab') return;
        var focusables = activeModal.querySelectorAll(focusableSelector);
        if (!focusables.length) return;
        var first = focusables[0];
        var last = focusables[focusables.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    };
    document.addEventListener('keydown', trapKeydown);
    window.setInterval(function () {
        var current = getVisibleModal();
        if (current === activeModal) return;
        if (activeModal) {
            setModalA11y(activeModal, false);
            if (restoreFocusTo && typeof restoreFocusTo.focus === 'function') restoreFocusTo.focus();
        }
        activeModal = current;
        if (activeModal) {
            restoreFocusTo = document.activeElement;
            setModalA11y(activeModal, true);
            var focusables = activeModal.querySelectorAll(focusableSelector);
            if (focusables.length) focusables[0].focus();
            else activeModal.focus();
        }
    }, 200);
})();

(function () {
    var toggleBtn = document.getElementById('app-sidebar-toggle');
    var backdrop = document.getElementById('app-sidebar-backdrop');
    if (!toggleBtn || !backdrop) return;
    var closeSidebar = function () {
        document.body.classList.remove('sidebar-open');
        toggleBtn.setAttribute('aria-expanded', 'false');
    };
    var openSidebar = function () {
        document.body.classList.add('sidebar-open');
        toggleBtn.setAttribute('aria-expanded', 'true');
    };
    toggleBtn.addEventListener('click', function () {
        if (document.body.classList.contains('sidebar-open')) closeSidebar();
        else openSidebar();
    });
    backdrop.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeSidebar();
    });
})();

(function () {
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('button[type="submit"]:not([disabled])');
            if (!btn) return;
            btn.classList.add('is-loading');
            btn.setAttribute('data-original-text', btn.textContent || '');
            btn.textContent = 'Saving';
            window.setTimeout(function () {
                btn.classList.remove('is-loading');
                if (btn.hasAttribute('data-original-text')) {
                    btn.textContent = btn.getAttribute('data-original-text');
                }
            }, 5000);
        });
    });
})();

(function () {
    document.querySelectorAll('.timeclock-muted, .history-table td[colspan]').forEach(function (el) {
        var txt = (el.textContent || '').trim().toLowerCase();
        if (txt.indexOf('no ') === 0 || txt.indexOf('select a product') === 0) {
            el.classList.add('empty-state');
        }
    });
})();

(function () {
    var KEY = 'ui_high_contrast_enabled';
    var buttons = document.querySelectorAll('[data-contrast-toggle="1"]');
    if (!buttons.length) return;
    var applyState = function (enabled) {
        document.documentElement.classList.toggle('high-contrast', enabled);
        document.body.classList.toggle('high-contrast', enabled);
        buttons.forEach(function (btn) {
            btn.setAttribute('aria-pressed', enabled ? 'true' : 'false');
            btn.textContent = enabled ? 'High Contrast: On' : 'High Contrast: Off';
        });
    };
    var enabled = false;
    try {
        enabled = localStorage.getItem(KEY) === '1';
    } catch (e) {}
    applyState(enabled);
    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            enabled = !enabled;
            applyState(enabled);
            try {
                localStorage.setItem(KEY, enabled ? '1' : '0');
            } catch (e) {}
        });
    });
})();

(function () {
    var KEY = 'ui_density_compact_enabled';
    var buttons = document.querySelectorAll('[data-density-toggle="1"]');
    if (!buttons.length) return;
    var applyState = function (compact) {
        document.documentElement.classList.toggle('density-compact', compact);
        buttons.forEach(function (btn) {
            btn.setAttribute('aria-pressed', compact ? 'true' : 'false');
            btn.textContent = compact ? 'Density: Compact' : 'Density: Comfortable';
        });
    };
    var compact = false;
    try {
        compact = localStorage.getItem(KEY) === '1';
    } catch (e) {}
    applyState(compact);
    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            compact = !compact;
            applyState(compact);
            try {
                localStorage.setItem(KEY, compact ? '1' : '0');
            } catch (e) {}
        });
    });
})();
</script>
<?php include 'includes/modals.php'; ?>
<?php include 'includes/footer.php'; ?>
<?php
$obLevel = ob_get_level();
if ($obLevel) {
    $body = ob_get_clean();
    if (substr($body, 0, 3) === "\xEF\xBB\xBF") {
        $body = substr($body, 3);
    }
    echo $body;
}

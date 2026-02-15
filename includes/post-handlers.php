<?php
/**
 * POST request handlers. Included from index.php after $action, $storeId, $date, $view are set.
 * Each handler exits (redirect or JSON); if none match, execution continues in index.php.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}

// Build inventory table state for redirect URLs (preserve days, sort, limit)
$invStateQuery = 'inventory_limit=' . urlencode($invLimitParam) . '&inventory_sort=' . urlencode($invSortParam);
if ($invDaysParam !== null) {
    $invStateQuery .= '&inventory_days=' . (int)$invDaysParam;
}
$tabParam = $_GET['tab'] ?? $_POST['tab'] ?? 'kpi';
if (!in_array($tabParam, ['kpi', 'inventory'], true)) {
    $tabParam = 'kpi';
}
$invStateQuery .= '&tab=' . urlencode($tabParam);
$isKioskPost = (string)($_POST['kiosk'] ?? $_GET['kiosk'] ?? '') === '1';
$kioskParam = $isKioskPost ? '&kiosk=1' : '';
$denyAccess = function ($message, $redirectAction = null, $forceJson = false) use ($storeId, $date) {
    $isJsonRequest = $forceJson
        || isset($_POST['quick_update_on_hand'])
        || isset($_POST['save_snapshot'])
        || isset($_POST['save_daily_sale'])
        || isset($_POST['save_daily_purchase'])
        || isset($_POST['update_daily_on_hand'])
        || isset($_POST['mark_received']);
    if ($isJsonRequest) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
    $actionParam = $redirectAction ?: ($_POST['action'] ?? $_GET['action'] ?? 'dashboard');
    $kiosk = ((string)($_POST['kiosk'] ?? $_GET['kiosk'] ?? '') === '1') ? '&kiosk=1' : '';
    header("Location: index.php?action={$actionParam}&store={$storeId}&date={$date}{$kiosk}&error=" . urlencode($message));
    exit;
};

// Time Clock punch JSON API (used by kiosk offline queue sync)
if (isset($_POST['timeclock_punch_json'])) {
    header('Content-Type: application/json');
    $storeIdTc = intval($_POST['store_id'] ?? 0);
    $employeeIdTc = intval($_POST['employee_id'] ?? 0);
    $punchType = ($_POST['punch_type'] ?? '') === 'out' ? 'out' : 'in';
    $deviceId = trim((string)($_POST['device_id'] ?? 'unknown'));
    $payloadMeta = [
        'queued_at' => $_POST['queued_at'] ?? null,
        'gps_status' => $_POST['gps_status'] ?? 'unavailable',
        'kiosk' => (string)($_POST['kiosk'] ?? '')
    ];
    if (!currentUserCan('employee_self_service')) {
        logKioskSyncAttempt($storeIdTc, $employeeIdTc, $deviceId, $punchType, 'failed', 'Permission denied.', $payloadMeta);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to punch in/out.']);
        exit;
    }
    $pinTc = trim((string)($_POST['pin'] ?? ''));
    $gpsStatus = $_POST['gps_status'] ?? 'unavailable';
    if (!in_array($gpsStatus, ['ok', 'denied', 'unavailable'], true)) {
        $gpsStatus = 'unavailable';
    }
    if ($storeIdTc <= 0 || $employeeIdTc <= 0 || $pinTc === '') {
        logKioskSyncAttempt($storeIdTc, $employeeIdTc, $deviceId, $punchType, 'failed', 'Employee and PIN are required.', $payloadMeta);
        echo json_encode(['success' => false, 'message' => 'Employee and PIN are required.']);
        exit;
    }
    $employee = verifyEmployeePinForStore($employeeIdTc, $storeIdTc, $pinTc);
    if (!$employee) {
        logKioskSyncAttempt($storeIdTc, $employeeIdTc, $deviceId, $punchType, 'failed', 'Invalid employee/PIN for this location.', $payloadMeta);
        echo json_encode(['success' => false, 'message' => 'Invalid employee/PIN for this location.']);
        exit;
    }
    $meta = [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'note' => !empty($_POST['punch_note']) ? trim((string)$_POST['punch_note']) : null,
        'gps_lat' => ($_POST['gps_lat'] ?? '') !== '' ? (float)$_POST['gps_lat'] : null,
        'gps_lng' => ($_POST['gps_lng'] ?? '') !== '' ? (float)$_POST['gps_lng'] : null,
        'gps_accuracy_m' => ($_POST['gps_accuracy_m'] ?? '') !== '' ? (float)$_POST['gps_accuracy_m'] : null,
        'gps_captured' => $gpsStatus === 'ok',
        'gps_status' => $gpsStatus
    ];
    $result = $punchType === 'out'
        ? clockOutEmployee((int)$employee['id'], $storeIdTc, $meta)
        : clockInEmployee((int)$employee['id'], $storeIdTc, $meta);
    $resultMessage = (string)($result['message'] ?? ($punchType === 'out' ? 'Clock-out failed.' : 'Clock-in failed.'));
    logKioskSyncAttempt(
        $storeIdTc,
        (int)$employee['id'],
        $deviceId,
        $punchType,
        !empty($result['success']) ? 'success' : 'failed',
        $resultMessage,
        $payloadMeta
    );
    echo json_encode([
        'success' => !empty($result['success']),
        'message' => $resultMessage
    ]);
    exit;
}

// Time Clock schedule drag/drop JSON API
if (isset($_POST['timeclock_schedule_dragdrop_json'])) {
    header('Content-Type: application/json');
    if (!currentUserCan('timeclock_manager')) {
        $denyAccess('Manager role required to edit schedules.', 'timeclock', true);
    }
    $storeIdTc = intval($_POST['store_id'] ?? 0);
    $operation = trim((string)($_POST['operation'] ?? ''));
    $managerName = trim((string)($_POST['manager_name'] ?? ''));
    if ($storeIdTc <= 0 || $managerName === '' || $operation === '') {
        echo json_encode(['success' => false, 'message' => 'Missing required schedule parameters.']);
        exit;
    }

    $toUtcFromLocal = function ($local) {
        $local = trim((string)$local);
        if ($local === '') return null;
        try {
            $dt = new DateTime($local, new DateTimeZone(TIMEZONE));
            $dt->setTimezone(new DateTimeZone('UTC'));
            return $dt->format('Y-m-d H:i:sP');
        } catch (Throwable $e) {
            return null;
        }
    };

    if ($operation === 'create') {
        $employeeIdTc = intval($_POST['employee_id'] ?? 0);
        $startUtc = $toUtcFromLocal($_POST['start_local'] ?? '');
        $endUtc = $toUtcFromLocal($_POST['end_local'] ?? '');
        $breakMinutes = intval($_POST['break_minutes'] ?? 0);
        $roleName = trim((string)($_POST['role_name'] ?? 'Employee'));
        $result = addScheduleShift([
            'employee_id' => $employeeIdTc,
            'store_id' => $storeIdTc,
            'role_name' => $roleName !== '' ? $roleName : 'Employee',
            'start_utc' => $startUtc ?: '',
            'end_utc' => $endUtc ?: '',
            'break_minutes' => max(0, $breakMinutes),
            'manager_name' => $managerName,
            'note' => 'DRAGDROP'
        ]);
        echo json_encode([
            'success' => !empty($result['success']),
            'shift_id' => (int)($result['shift_id'] ?? 0),
            'message' => (string)($result['message'] ?? 'Unable to create shift.')
        ]);
        exit;
    }

    if ($operation === 'update') {
        $shiftId = intval($_POST['shift_id'] ?? 0);
        $employeeIdTc = intval($_POST['employee_id'] ?? 0);
        $startUtc = $toUtcFromLocal($_POST['start_local'] ?? '');
        $endUtc = $toUtcFromLocal($_POST['end_local'] ?? '');
        $breakMinutes = intval($_POST['break_minutes'] ?? 0);
        $roleName = trim((string)($_POST['role_name'] ?? 'Employee'));
        $result = updateScheduleShift($shiftId, [
            'employee_id' => $employeeIdTc,
            'store_id' => $storeIdTc,
            'role_name' => $roleName !== '' ? $roleName : 'Employee',
            'start_utc' => $startUtc ?: '',
            'end_utc' => $endUtc ?: '',
            'break_minutes' => max(0, $breakMinutes),
            'manager_name' => $managerName,
            'note' => 'DRAGDROP'
        ]);
        echo json_encode(['success' => !empty($result['success']), 'message' => (string)($result['message'] ?? 'Unable to update shift.')]);
        exit;
    }

    if ($operation === 'delete') {
        $shiftId = intval($_POST['shift_id'] ?? 0);
        $result = deleteScheduleShift($shiftId, $storeIdTc, $managerName);
        echo json_encode(['success' => !empty($result['success']), 'message' => (string)($result['message'] ?? 'Unable to delete shift.')]);
        exit;
    }

    if ($operation === 'publish_week') {
        $weekStart = trim((string)($_POST['week_start_date'] ?? ''));
        $weekEnd = trim((string)($_POST['week_end_date'] ?? ''));
        $result = publishScheduleWeek($storeIdTc, $weekStart, $weekEnd, $managerName);
        echo json_encode(['success' => !empty($result['success']), 'message' => (string)($result['message'] ?? 'Unable to publish week.')]);
        exit;
    }

    if ($operation === 'unpublish_week') {
        $weekStart = trim((string)($_POST['week_start_date'] ?? ''));
        $weekEnd = trim((string)($_POST['week_end_date'] ?? ''));
        $result = unpublishScheduleWeek($storeIdTc, $weekStart, $weekEnd, $managerName);
        echo json_encode(['success' => !empty($result['success']), 'message' => (string)($result['message'] ?? 'Unable to unpublish week.')]);
        exit;
    }

    if ($operation === 'copy_previous_week') {
        $weekStart = trim((string)($_POST['week_start_date'] ?? ''));
        $weekEnd = trim((string)($_POST['week_end_date'] ?? ''));
        $result = copyScheduleFromPreviousWeek($storeIdTc, $weekStart, $weekEnd, $managerName);
        echo json_encode([
            'success' => !empty($result['success']),
            'added' => (int)($result['added'] ?? 0),
            'skipped' => (int)($result['skipped'] ?? 0),
            'message' => (string)($result['message'] ?? 'Unable to copy previous week.')
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unsupported schedule operation.']);
    exit;
}

// Time Clock punch (employee PIN flow)
if (isset($_POST['timeclock_punch'])) {
    if (!currentUserCan('employee_self_service')) {
        $denyAccess('You do not have permission to punch in/out.', 'timeclock');
    }
    $storeIdTc = intval($_POST['store_id'] ?? 0);
    $employeeIdTc = intval($_POST['employee_id'] ?? 0);
    $pinTc = trim((string)($_POST['pin'] ?? ''));
    $punchType = ($_POST['punch_type'] ?? '') === 'out' ? 'out' : 'in';
    $gpsStatus = $_POST['gps_status'] ?? 'unavailable';
    if (!in_array($gpsStatus, ['ok', 'denied', 'unavailable'], true)) {
        $gpsStatus = 'unavailable';
    }

    if ($storeIdTc <= 0 || $employeeIdTc <= 0 || $pinTc === '') {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$date}{$kioskParam}&error=" . urlencode('Employee and PIN are required.'));
        exit;
    }

    $employee = verifyEmployeePinForStore($employeeIdTc, $storeIdTc, $pinTc);
    if (!$employee) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$date}{$kioskParam}&error=" . urlencode('Invalid employee/PIN for this location.'));
        exit;
    }

    $meta = [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'note' => !empty($_POST['punch_note']) ? trim((string)$_POST['punch_note']) : null,
        'gps_lat' => ($_POST['gps_lat'] ?? '') !== '' ? (float)$_POST['gps_lat'] : null,
        'gps_lng' => ($_POST['gps_lng'] ?? '') !== '' ? (float)$_POST['gps_lng'] : null,
        'gps_accuracy_m' => ($_POST['gps_accuracy_m'] ?? '') !== '' ? (float)$_POST['gps_accuracy_m'] : null,
        'gps_captured' => $gpsStatus === 'ok',
        'gps_status' => $gpsStatus
    ];

    $result = $punchType === 'out'
        ? clockOutEmployee((int)$employee['id'], $storeIdTc, $meta)
        : clockInEmployee((int)$employee['id'], $storeIdTc, $meta);

    if (!empty($result['success'])) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$date}{$kioskParam}&success=" . urlencode($result['message']));
    } else {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$date}{$kioskParam}&error=" . urlencode($result['message'] ?? 'Time clock punch failed.'));
    }
    exit;
}

if (isset($_POST['timeclock_edit_request_submit'])) {
    if (!currentUserCan('employee_self_service')) {
        $denyAccess('You do not have permission to submit edit requests.', 'timeclock');
    }
    $storeIdTc = intval($_POST['store_id'] ?? 0);
    $employeeIdTc = intval($_POST['employee_id'] ?? 0);
    $pinTc = trim((string)($_POST['pin'] ?? ''));
    $requestType = strtoupper(trim((string)($_POST['request_type'] ?? '')));
    $reason = trim((string)($_POST['request_reason'] ?? ''));
    $allowedTypes = ['MISS_CLOCK_IN', 'MISS_CLOCK_OUT', 'ADJUST_SHIFT'];
    if (!in_array($requestType, $allowedTypes, true)) {
        $requestType = 'ADJUST_SHIFT';
    }

    if ($storeIdTc <= 0 || $employeeIdTc <= 0 || $pinTc === '' || $reason === '') {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$date}&error=" . urlencode('Employee, PIN, request type, and reason are required.'));
        exit;
    }
    $employee = verifyEmployeePinForStore($employeeIdTc, $storeIdTc, $pinTc);
    if (!$employee) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$date}&error=" . urlencode('Invalid employee/PIN for edit request.'));
        exit;
    }

    $reqInUtc = parseLocalDateTimeToUtc($_POST['requested_clock_in_local'] ?? null);
    $reqOutUtc = parseLocalDateTimeToUtc($_POST['requested_clock_out_local'] ?? null);
    $shiftIdReq = !empty($_POST['request_shift_id']) ? intval($_POST['request_shift_id']) : null;

    if ($requestType === 'MISS_CLOCK_IN' && !$reqInUtc) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$date}&error=" . urlencode('Requested clock-in time is required.'));
        exit;
    }
    if ($requestType === 'MISS_CLOCK_OUT' && !$reqOutUtc) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$date}&error=" . urlencode('Requested clock-out time is required.'));
        exit;
    }
    if ($requestType === 'MISS_CLOCK_OUT' && !$shiftIdReq) {
        $openShift = getOpenShiftForEmployeeStore($employeeIdTc, $storeIdTc);
        if ($openShift && !empty($openShift['id'])) {
            $shiftIdReq = (int)$openShift['id'];
        } else {
            header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$date}&error=" . urlencode('No open shift found for missed clock-out. Ask manager to use Adjust Existing Shift.'));
            exit;
        }
    }
    if ($requestType === 'ADJUST_SHIFT' && !$shiftIdReq) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$date}&error=" . urlencode('Select a shift to adjust.'));
        exit;
    }
    if ($requestType === 'ADJUST_SHIFT' && !$reqInUtc && !$reqOutUtc) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$date}&error=" . urlencode('Provide at least one adjusted time.'));
        exit;
    }

    $ok = createPunchEditRequest([
        'employee_id' => $employeeIdTc,
        'employee_name' => $employee['full_name'] ?? 'employee',
        'store_id' => $storeIdTc,
        'shift_id' => $shiftIdReq,
        'request_type' => $requestType,
        'requested_clock_in_utc' => $reqInUtc,
        'requested_clock_out_utc' => $reqOutUtc,
        'reason' => $reason
    ]);
    if ($ok) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$date}&success=" . urlencode('Edit request submitted for manager review.'));
    } else {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$date}&error=" . urlencode('Failed to submit edit request.'));
    }
    exit;
}

if (isset($_POST['timeclock_edit_request_review'])) {
    if (!currentUserCan('timeclock_manager')) {
        $denyAccess('Manager role required to review edit requests.', 'timeclock');
    }
    $storeIdTc = intval($_POST['store_id'] ?? 0);
    $requestId = intval($_POST['request_id'] ?? 0);
    $decision = strtoupper(trim((string)($_POST['review_decision'] ?? '')));
    $managerName = trim((string)($_POST['manager_name'] ?? ''));
    $managerNote = trim((string)($_POST['manager_note'] ?? ''));

    if ($storeIdTc <= 0 || $requestId <= 0) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$date}&error=" . urlencode('Invalid request review parameters.'));
        exit;
    }

    $result = processPunchEditRequest($requestId, $storeIdTc, $decision, $managerName, $managerNote);
    if (!empty($result['success'])) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$date}&success=" . urlencode($result['message']));
    } else {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$date}&error=" . urlencode($result['message'] ?? 'Request review failed.'));
    }
    exit;
}

if (isset($_POST['timeclock_schedule_add_shift'])) {
    if (!currentUserCan('timeclock_manager')) {
        $denyAccess('Manager role required to add schedule shifts.', 'timeclock');
    }
    $storeIdTc = intval($_POST['store_id'] ?? 0);
    $employeeIdTc = intval($_POST['employee_id'] ?? 0);
    $managerName = trim((string)($_POST['manager_name'] ?? ''));
    $startUtc = parseLocalDateTimeToUtc($_POST['schedule_start_local'] ?? null);
    $endUtc = parseLocalDateTimeToUtc($_POST['schedule_end_local'] ?? null);
    $breakMinutes = intval($_POST['break_minutes'] ?? 0);
    $roleName = trim((string)($_POST['role_name'] ?? 'Employee'));
    $note = trim((string)($_POST['schedule_note'] ?? ''));
    $dateParam = $_POST['date'] ?? $date;

    if ($storeIdTc <= 0 || $employeeIdTc <= 0 || !$startUtc || !$endUtc || $managerName === '') {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&error=" . urlencode('Manager, employee, start, and end are required.'));
        exit;
    }

    $result = addScheduleShift([
        'employee_id' => $employeeIdTc,
        'store_id' => $storeIdTc,
        'role_name' => $roleName !== '' ? $roleName : 'Employee',
        'start_utc' => $startUtc,
        'end_utc' => $endUtc,
        'break_minutes' => max(0, $breakMinutes),
        'manager_name' => $managerName,
        'note' => $note !== '' ? $note : null
    ]);
    if (!empty($result['success'])) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&success=" . urlencode($result['message']));
    } else {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&error=" . urlencode($result['message'] ?? 'Unable to add shift.'));
    }
    exit;
}

if (isset($_POST['timeclock_schedule_add_shift_day'])) {
    if (!currentUserCan('timeclock_manager')) {
        $denyAccess('Manager role required to add schedule shifts.', 'timeclock');
    }
    $storeIdTc = intval($_POST['store_id'] ?? 0);
    $employeeIdTc = intval($_POST['employee_id'] ?? 0);
    $managerName = trim((string)($_POST['manager_name'] ?? ''));
    $scheduleDate = trim((string)($_POST['schedule_date'] ?? ''));
    $startTime = trim((string)($_POST['schedule_start_time'] ?? ''));
    $endTime = trim((string)($_POST['schedule_end_time'] ?? ''));
    $breakMinutes = intval($_POST['break_minutes'] ?? 0);
    $roleName = trim((string)($_POST['role_name'] ?? 'Employee'));
    $dateParam = $_POST['date'] ?? $date;

    if ($storeIdTc <= 0 || $employeeIdTc <= 0 || $managerName === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduleDate) || !preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&error=" . urlencode('Day, employee, manager, start, and end are required.'));
        exit;
    }

    try {
        $startLocalDt = new DateTime($scheduleDate . ' ' . $startTime . ':00', new DateTimeZone(TIMEZONE));
        $endLocalDt = new DateTime($scheduleDate . ' ' . $endTime . ':00', new DateTimeZone(TIMEZONE));
        if ($endLocalDt <= $startLocalDt) {
            $endLocalDt->modify('+1 day'); // support overnight shifts
        }
        $startUtc = parseLocalDateTimeToUtc($startLocalDt->format('Y-m-d H:i:s'));
        $endUtc = parseLocalDateTimeToUtc($endLocalDt->format('Y-m-d H:i:s'));
    } catch (Throwable $e) {
        $startUtc = null;
        $endUtc = null;
    }

    if (!$startUtc || !$endUtc) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&error=" . urlencode('Invalid date/time for shift.'));
        exit;
    }

    $result = addScheduleShift([
        'employee_id' => $employeeIdTc,
        'store_id' => $storeIdTc,
        'role_name' => $roleName !== '' ? $roleName : 'Employee',
        'start_utc' => $startUtc,
        'end_utc' => $endUtc,
        'break_minutes' => max(0, $breakMinutes),
        'manager_name' => $managerName
    ]);
    if (!empty($result['success'])) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&success=" . urlencode($result['message']));
    } else {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&error=" . urlencode($result['message'] ?? 'Unable to add shift.'));
    }
    exit;
}

if (isset($_POST['timeclock_schedule_publish_week'])) {
    if (!currentUserCan('timeclock_manager')) {
        $denyAccess('Manager role required to publish schedules.', 'timeclock');
    }
    $storeIdTc = intval($_POST['store_id'] ?? 0);
    $managerName = trim((string)($_POST['manager_name'] ?? ''));
    $weekStart = trim((string)($_POST['week_start_date'] ?? ''));
    $weekEnd = trim((string)($_POST['week_end_date'] ?? ''));
    $dateParam = $_POST['date'] ?? $date;
    if ($storeIdTc <= 0 || $managerName === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekEnd)) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&error=" . urlencode('Valid week and manager name are required.'));
        exit;
    }
    $result = publishScheduleWeek($storeIdTc, $weekStart, $weekEnd, $managerName);
    if (!empty($result['success'])) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&success=" . urlencode($result['message']));
    } else {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&error=" . urlencode($result['message'] ?? 'Unable to publish schedule.'));
    }
    exit;
}

if (isset($_POST['timeclock_payroll_period_save'])) {
    if (!currentUserCan('timeclock_manager')) {
        $denyAccess('Manager role required to manage payroll periods.', 'timeclock');
    }
    $storeIdTc = intval($_POST['store_id'] ?? 0);
    $managerName = trim((string)($_POST['manager_name'] ?? ''));
    $startDate = trim((string)($_POST['payroll_start_date'] ?? ''));
    $endDate = trim((string)($_POST['payroll_end_date'] ?? ''));
    $dateParam = $_POST['date'] ?? $date;
    $result = createOrGetPayrollPeriod($storeIdTc, $startDate, $endDate, $managerName);
    if (!empty($result['success'])) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&success=" . urlencode($result['message']));
    } else {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&error=" . urlencode($result['message'] ?? 'Failed to save payroll period.'));
    }
    exit;
}

if (isset($_POST['timeclock_payroll_run'])) {
    if (!currentUserCan('timeclock_manager')) {
        $denyAccess('Manager role required to run payroll.', 'timeclock');
    }
    $storeIdTc = intval($_POST['store_id'] ?? 0);
    $periodId = intval($_POST['payroll_period_id'] ?? 0);
    $managerName = trim((string)($_POST['manager_name'] ?? ''));
    $dateParam = $_POST['date'] ?? $date;
    $result = runPayrollForPeriod($periodId, $storeIdTc, $managerName);
    if (!empty($result['success'])) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&success=" . urlencode($result['message']));
    } else {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&error=" . urlencode($result['message'] ?? 'Failed to run payroll.'));
    }
    exit;
}

if (isset($_POST['timeclock_payroll_lock'])) {
    if (!currentUserCan('timeclock_manager')) {
        $denyAccess('Manager role required to lock payroll periods.', 'timeclock');
    }
    $storeIdTc = intval($_POST['store_id'] ?? 0);
    $periodId = intval($_POST['payroll_period_id'] ?? 0);
    $managerName = trim((string)($_POST['manager_name'] ?? ''));
    $dateParam = $_POST['date'] ?? $date;
    $result = lockPayrollPeriod($periodId, $storeIdTc, $managerName);
    if (!empty($result['success'])) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&payroll_period_id={$periodId}&success=" . urlencode($result['message']));
    } else {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&payroll_period_id={$periodId}&error=" . urlencode($result['message'] ?? 'Failed to lock payroll period.'));
    }
    exit;
}

if (isset($_POST['timeclock_payroll_unlock'])) {
    if (!currentUserCan('timeclock_manager')) {
        $denyAccess('Manager role required to unlock payroll periods.', 'timeclock');
    }
    $storeIdTc = intval($_POST['store_id'] ?? 0);
    $periodId = intval($_POST['payroll_period_id'] ?? 0);
    $managerName = trim((string)($_POST['manager_name'] ?? ''));
    $dateParam = $_POST['date'] ?? $date;
    $result = unlockPayrollPeriod($periodId, $storeIdTc, $managerName);
    if (!empty($result['success'])) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&payroll_period_id={$periodId}&success=" . urlencode($result['message']));
    } else {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&payroll_period_id={$periodId}&error=" . urlencode($result['message'] ?? 'Failed to unlock payroll period.'));
    }
    exit;
}

if (isset($_POST['timeclock_geofence_settings_save'])) {
    if (!currentUserCan('timeclock_manager')) {
        $denyAccess('Manager role required to update geofence/kiosk settings.', 'timeclock');
    }
    $storeIdTc = intval($_POST['store_id'] ?? 0);
    $managerName = trim((string)($_POST['manager_name'] ?? ''));
    $dateParam = $_POST['date'] ?? $date;
    if ($storeIdTc <= 0 || $managerName === '') {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&error=" . urlencode('Manager name is required to save location settings.'));
        exit;
    }
    $enabled = isset($_POST['geofence_enabled']) ? '1' : '0';
    $lat = (float)($_POST['geofence_lat'] ?? 0);
    $lng = (float)($_POST['geofence_lng'] ?? 0);
    $radius = max(5, min(10000, (int)($_POST['geofence_radius_m'] ?? 120)));
    $policy = (string)($_POST['geofence_policy'] ?? 'warn');
    if (!in_array($policy, ['warn', 'block'], true)) {
        $policy = 'warn';
    }
    $allowNoGps = isset($_POST['geofence_allow_no_gps']) ? '1' : '0';
    $kioskIdleSeconds = max(30, min(600, (int)($_POST['kiosk_idle_seconds'] ?? 75)));
    $alertOpenFailureThreshold = max(1, min(100, (int)($_POST['kiosk_alert_open_failure_threshold'] ?? 3)));
    $alertStaleMinutes = max(5, min(1440, (int)($_POST['kiosk_alert_stale_minutes'] ?? 60)));
    $noShowGraceMinutes = max(0, min(180, (int)($_POST['no_show_grace_minutes'] ?? 15)));
    $opsOpen = is_array($_POST['ops_open'] ?? null) ? $_POST['ops_open'] : [];
    $opsClose = is_array($_POST['ops_close'] ?? null) ? $_POST['ops_close'] : [];
    $opsEnabled = is_array($_POST['ops_enabled'] ?? null) ? $_POST['ops_enabled'] : [];
    $dowKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    $operatingHours = [];
    foreach ($dowKeys as $dow) {
        $openVal = isset($opsOpen[$dow]) && preg_match('/^\d{2}:\d{2}$/', (string)$opsOpen[$dow]) ? (string)$opsOpen[$dow] : '09:00';
        $closeVal = isset($opsClose[$dow]) && preg_match('/^\d{2}:\d{2}$/', (string)$opsClose[$dow]) ? (string)$opsClose[$dow] : '21:00';
        $operatingHours[$dow] = [
            'enabled' => isset($opsEnabled[$dow]),
            'open' => $openVal,
            'close' => $closeVal,
        ];
    }

    $settingsToSave = [
        'geofence_enabled' => $enabled,
        'geofence_lat' => number_format($lat, 7, '.', ''),
        'geofence_lng' => number_format($lng, 7, '.', ''),
        'geofence_radius_m' => (string)$radius,
        'geofence_policy' => $policy,
        'geofence_allow_no_gps' => $allowNoGps,
        'kiosk_idle_seconds' => (string)$kioskIdleSeconds,
        'kiosk_alert_open_failure_threshold' => (string)$alertOpenFailureThreshold,
        'kiosk_alert_stale_minutes' => (string)$alertStaleMinutes,
        'no_show_grace_minutes' => (string)$noShowGraceMinutes,
        'store_operating_hours_json' => json_encode($operatingHours),
    ];
    $allOk = true;
    foreach ($settingsToSave as $key => $value) {
        if (!upsertTimeclockSetting($key, $value, 'store', $storeIdTc)) {
            $allOk = false;
            break;
        }
    }
    if ($allOk) {
        logTimeclockAudit($storeIdTc, null, $managerName, 'GEOFENCE_SETTINGS_UPDATED', ['settings' => $settingsToSave]);
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&success=" . urlencode('Location/kiosk settings saved.'));
    } else {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&error=" . urlencode('Failed to save location/kiosk settings.'));
    }
    exit;
}

if (isset($_POST['timeclock_kiosk_failure_resolve'])) {
    if (!currentUserCan('timeclock_manager')) {
        $denyAccess('Manager role required to resolve kiosk sync failures.', 'timeclock');
    }
    $storeIdTc = intval($_POST['store_id'] ?? 0);
    $syncLogId = intval($_POST['sync_log_id'] ?? 0);
    $managerName = trim((string)($_POST['manager_name'] ?? ''));
    $resolutionStatus = strtoupper(trim((string)($_POST['resolution_status'] ?? 'RESOLVED')));
    $resolutionNote = trim((string)($_POST['resolution_note'] ?? ''));
    $dateParam = $_POST['date'] ?? $date;
    $res = resolveKioskSyncFailure($syncLogId, $storeIdTc, $managerName, $resolutionStatus, $resolutionNote !== '' ? $resolutionNote : null);
    if (!empty($res['success'])) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&success=" . urlencode($res['message'] ?? 'Failure updated.'));
    } else {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&error=" . urlencode($res['message'] ?? 'Failed to update failure.'));
    }
    exit;
}

if (isset($_POST['timeclock_kiosk_failure_resolve_all'])) {
    if (!currentUserCan('timeclock_manager')) {
        $denyAccess('Manager role required to resolve kiosk sync failures.', 'timeclock');
    }
    $storeIdTc = intval($_POST['store_id'] ?? 0);
    $managerName = trim((string)($_POST['manager_name'] ?? ''));
    $resolutionStatus = strtoupper(trim((string)($_POST['resolution_status'] ?? 'RESOLVED')));
    $resolutionNote = trim((string)($_POST['resolution_note'] ?? ''));
    $dateParam = $_POST['date'] ?? $date;
    $res = resolveAllOpenKioskSyncFailures($storeIdTc, $managerName, $resolutionStatus, $resolutionNote !== '' ? $resolutionNote : null);
    if (!empty($res['success'])) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&success=" . urlencode($res['message'] ?? 'Failures updated.'));
    } else {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&error=" . urlencode($res['message'] ?? 'Failed to update failures.'));
    }
    exit;
}

if (isset($_POST['timeclock_task_create'])) {
    if (!currentUserCan('timeclock_manager')) {
        $denyAccess('Manager role required to create tasks.', 'timeclock');
    }
    $storeIdTc = intval($_POST['store_id'] ?? 0);
    $dateParam = $_POST['date'] ?? $date;
    $taskDate = trim((string)($_POST['task_date'] ?? $dateParam));
    $title = trim((string)($_POST['task_title'] ?? ''));
    $details = trim((string)($_POST['task_details'] ?? ''));
    $assignedEmployeeId = intval($_POST['assigned_employee_id'] ?? 0);
    $scheduleShiftId = intval($_POST['schedule_shift_id'] ?? 0);
    $managerName = trim((string)($_POST['manager_name'] ?? ''));
    $res = createTimeclockTask(
        $storeIdTc,
        $taskDate,
        $title,
        $details,
        $assignedEmployeeId > 0 ? $assignedEmployeeId : null,
        $scheduleShiftId > 0 ? $scheduleShiftId : null,
        $managerName !== '' ? $managerName : 'manager'
    );
    if (!empty($res['success'])) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$taskDate}&panel=tc_panel_tasks&success=" . urlencode($res['message'] ?? 'Task created.'));
    } else {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&panel=tc_panel_tasks&error=" . urlencode($res['message'] ?? 'Failed to create task.'));
    }
    exit;
}

if (isset($_POST['timeclock_task_toggle'])) {
    $storeIdTc = intval($_POST['store_id'] ?? 0);
    $taskId = intval($_POST['task_id'] ?? 0);
    $taskDate = trim((string)($_POST['task_date'] ?? $date));
    $nextStatus = strtoupper(trim((string)($_POST['task_status'] ?? 'DONE')));
    if (!in_array($nextStatus, ['OPEN', 'DONE'], true)) {
        $nextStatus = 'DONE';
    }
    $task = getTimeclockTaskById($taskId, $storeIdTc);
    if (!$task) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$taskDate}&panel=tc_panel_tasks&error=" . urlencode('Task not found.'));
        exit;
    }

    $actorName = trim((string)($_POST['manager_name'] ?? ''));
    $canManagerOverride = currentUserCan('timeclock_manager') && $actorName !== '';
    if (!$canManagerOverride) {
        if (!currentUserCan('employee_self_service')) {
            $denyAccess('You do not have permission to update tasks.', 'timeclock');
        }
        $employeeIdTc = intval($_POST['employee_id'] ?? 0);
        $pinTc = trim((string)($_POST['pin'] ?? ''));
        if ($employeeIdTc <= 0 || $pinTc === '') {
            header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$taskDate}&panel=tc_panel_tasks&error=" . urlencode('Employee and PIN are required.'));
            exit;
        }
        $employee = verifyEmployeePinForStore($employeeIdTc, $storeIdTc, $pinTc);
        if (!$employee) {
            header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$taskDate}&panel=tc_panel_tasks&error=" . urlencode('Invalid employee/PIN for this location.'));
            exit;
        }
        if (!empty($task['assigned_employee_id']) && (int)$task['assigned_employee_id'] !== (int)$employee['id']) {
            header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$taskDate}&panel=tc_panel_tasks&error=" . urlencode('Task is assigned to a different employee.'));
            exit;
        }
        $actorName = trim((string)($employee['full_name'] ?? 'employee'));
    }

    $res = updateTimeclockTaskStatus($taskId, $storeIdTc, $nextStatus, $actorName);
    if (!empty($res['success'])) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$taskDate}&panel=tc_panel_tasks&success=" . urlencode($res['message'] ?? 'Task updated.'));
    } else {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$taskDate}&panel=tc_panel_tasks&error=" . urlencode($res['message'] ?? 'Failed to update task.'));
    }
    exit;
}

if (isset($_POST['timeclock_task_delete'])) {
    if (!currentUserCan('timeclock_manager')) {
        $denyAccess('Manager role required to delete tasks.', 'timeclock');
    }
    $storeIdTc = intval($_POST['store_id'] ?? 0);
    $taskId = intval($_POST['task_id'] ?? 0);
    $taskDate = trim((string)($_POST['task_date'] ?? $date));
    $managerName = trim((string)($_POST['manager_name'] ?? ''));
    $res = deleteTimeclockTask($taskId, $storeIdTc, $managerName !== '' ? $managerName : 'manager');
    if (!empty($res['success'])) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$taskDate}&panel=tc_panel_tasks&success=" . urlencode($res['message'] ?? 'Task deleted.'));
    } else {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$taskDate}&panel=tc_panel_tasks&error=" . urlencode($res['message'] ?? 'Failed to delete task.'));
    }
    exit;
}

if (isset($_POST['timeclock_pto_settings_save'])) {
    if (!currentUserCan('timeclock_manager')) {
        $denyAccess('Manager role required to update PTO settings.', 'timeclock');
    }
    $storeIdTc = intval($_POST['store_id'] ?? 0);
    $managerName = trim((string)($_POST['manager_name'] ?? ''));
    $dateParam = $_POST['date'] ?? $date;
    if ($storeIdTc <= 0 || $managerName === '') {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&error=" . urlencode('Manager name is required to save PTO settings.'));
        exit;
    }

    $ptoMethod = trim((string)($_POST['pto_accrual_method'] ?? 'per_hour_worked'));
    $allowedPtoMethods = ['per_hour_worked', 'per_pay_period', 'annual_lump_sum'];
    if (!in_array($ptoMethod, $allowedPtoMethods, true)) {
        $ptoMethod = 'per_hour_worked';
    }
    $sickMode = trim((string)($_POST['sick_policy_mode'] ?? 'per_30_hours'));
    $allowedSickModes = ['none', 'per_30_hours', 'custom_per_hour', 'fixed_annual'];
    if (!in_array($sickMode, $allowedSickModes, true)) {
        $sickMode = 'per_30_hours';
    }
    $holidayMode = trim((string)($_POST['holiday_policy_mode'] ?? 'fixed_paid_holidays'));
    $allowedHolidayModes = ['none', 'fixed_paid_holidays', 'premium_if_worked'];
    if (!in_array($holidayMode, $allowedHolidayModes, true)) {
        $holidayMode = 'fixed_paid_holidays';
    }

    $settingsToSave = [
        'pto_accrual_method' => $ptoMethod,
        'pto_minutes_per_hour' => number_format(max(0, (float)($_POST['pto_minutes_per_hour'] ?? 1.15)), 2, '.', ''),
        'pto_exclude_overtime' => isset($_POST['pto_exclude_overtime']) ? '1' : '0',
        'pto_annual_cap_minutes' => (string)max(0, (int)($_POST['pto_annual_cap_minutes'] ?? 2400)),
        'pto_waiting_period_days' => (string)max(0, (int)($_POST['pto_waiting_period_days'] ?? 0)),
        'sick_policy_mode' => $sickMode,
        'sick_minutes_per_hour' => number_format(max(0, (float)($_POST['sick_minutes_per_hour'] ?? 2.0)), 2, '.', ''),
        'holiday_policy_mode' => $holidayMode,
        'holiday_pay_multiplier' => number_format(max(1, (float)($_POST['holiday_pay_multiplier'] ?? 1.5)), 2, '.', '')
    ];

    $allOk = true;
    foreach ($settingsToSave as $k => $v) {
        if (!upsertTimeclockSetting($k, $v, 'global', null)) {
            $allOk = false;
            break;
        }
    }
    if ($allOk) {
        logTimeclockAudit($storeIdTc, null, $managerName, 'PTO_POLICY_UPDATED', [
            'policy' => $settingsToSave
        ]);
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&success=" . urlencode('PTO/leave settings saved.'));
    } else {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&error=" . urlencode('Failed to save PTO settings.'));
    }
    exit;
}

if (isset($_POST['timeclock_pto_request_submit'])) {
    if (!currentUserCan('employee_self_service')) {
        $denyAccess('You do not have permission to submit PTO requests.', 'timeclock');
    }
    $storeIdTc = intval($_POST['store_id'] ?? 0);
    $employeeIdTc = intval($_POST['employee_id'] ?? 0);
    $pinTc = trim((string)($_POST['pin'] ?? ''));
    $startDate = trim((string)($_POST['pto_start_date'] ?? ''));
    $endDate = trim((string)($_POST['pto_end_date'] ?? ''));
    $hours = (float)($_POST['pto_hours'] ?? 0);
    $minutesRequested = (int)round(max(0, $hours) * 60);
    $reason = trim((string)($_POST['pto_reason'] ?? ''));
    $dateParam = $_POST['date'] ?? $date;

    if ($storeIdTc <= 0 || $employeeIdTc <= 0 || $pinTc === '') {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&error=" . urlencode('Employee and PIN are required for PTO request.'));
        exit;
    }
    $employee = verifyEmployeePinForStore($employeeIdTc, $storeIdTc, $pinTc);
    if (!$employee) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&error=" . urlencode('Invalid employee/PIN for PTO request.'));
        exit;
    }
    $res = createPtoRequest($employeeIdTc, $storeIdTc, $startDate, $endDate, $minutesRequested, $reason, $employee['full_name'] ?? 'employee');
    if (!empty($res['success'])) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&success=" . urlencode($res['message']));
    } else {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&error=" . urlencode($res['message'] ?? 'Failed to submit PTO request.'));
    }
    exit;
}

if (isset($_POST['timeclock_pto_request_review'])) {
    if (!currentUserCan('timeclock_manager')) {
        $denyAccess('Manager role required to review PTO requests.', 'timeclock');
    }
    $storeIdTc = intval($_POST['store_id'] ?? 0);
    $requestId = intval($_POST['request_id'] ?? 0);
    $decision = strtoupper(trim((string)($_POST['review_decision'] ?? '')));
    $managerName = trim((string)($_POST['manager_name'] ?? ''));
    $managerNote = trim((string)($_POST['manager_note'] ?? ''));
    $dateParam = $_POST['date'] ?? $date;
    $res = reviewPtoRequest($requestId, $storeIdTc, $decision, $managerName, $managerNote);
    if (!empty($res['success'])) {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&success=" . urlencode($res['message']));
    } else {
        header("Location: index.php?action=timeclock&store={$storeIdTc}&date={$dateParam}&error=" . urlencode($res['message'] ?? 'Failed to review PTO request.'));
    }
    exit;
}

// KPI bulk/single save
if (isset($_POST['save_kpi'])) {
    if (!currentUserCan('kpi_write')) {
        $denyAccess('You have view-only access for KPIs.', 'dashboard');
    }
    if (isset($_POST['bulk_save']) && is_array($_POST['dates'])) {
        $saved = 0;
        $errors = 0;
        foreach ($_POST['dates'] as $entryDate) {
            $data = [
                'store_id' => intval($_POST['store_id']),
                'entry_date' => $entryDate,
                'bank_balance' => floatval($_POST['bank_balance'][$entryDate] ?? 0),
                'safe_balance' => floatval($_POST['safe_balance'][$entryDate] ?? 0),
                'sales_today' => floatval($_POST['sales_today'][$entryDate] ?? 0),
                'cogs_today' => floatval($_POST['cogs_today'][$entryDate] ?? 0),
                'labor_today' => floatval($_POST['labor_today'][$entryDate] ?? 0),
                'avg_daily_overhead' => floatval($_POST['avg_daily_overhead'][$entryDate] ?? 0),
                'updated_by' => $_POST['updated_by'] ?? null
            ];
            if (saveDailyKpi($data)) {
                $saved++;
            } else {
                $errors++;
            }
        }
        if ($saved > 0) {
            $successMessage = "Saved $saved day(s) successfully" . ($errors > 0 ? " ($errors errors)" : "");
        } else {
            $errorMessage = "Error saving KPI data.";
        }
    } else {
        $data = [
            'store_id' => intval($_POST['store_id']),
            'entry_date' => $_POST['entry_date'],
            'bank_balance' => floatval($_POST['bank_balance'] ?? 0),
            'safe_balance' => floatval($_POST['safe_balance'] ?? 0),
            'sales_today' => floatval($_POST['sales_today'] ?? 0),
            'cogs_today' => floatval($_POST['cogs_today'] ?? 0),
            'labor_today' => floatval($_POST['labor_today'] ?? 0),
            'avg_daily_overhead' => floatval($_POST['avg_daily_overhead'] ?? 0),
            'updated_by' => $_POST['updated_by'] ?? null
        ];
        if (saveDailyKpi($data)) {
            $successMessage = "KPI data saved successfully!";
        } else {
            $errorMessage = "Error saving KPI data. Please try again.";
        }
    }
    $viewParam = isset($_POST['view']) ? "&view={$_POST['view']}" : "";
    $modeParam = (($_POST['kpi_mode'] ?? '') === 'edit') ? '&mode=edit' : '&mode=view';
    $storeIdParam = isset($_POST['store_id']) ? intval($_POST['store_id']) : $storeId;
    header("Location: index.php?action=dashboard&store={$storeIdParam}&date={$date}{$viewParam}{$modeParam}&inventory_limit=" . urlencode($invLimitParam) . "&tab=" . urlencode($tabParam));
    exit;
}

if (isset($_POST['save_product'])) {
    if (!currentUserCan('inventory_write')) {
        $denyAccess('Manager role required for inventory changes.', 'dashboard');
    }
    $data = [
        'id' => isset($_POST['product_id']) ? intval($_POST['product_id']) : null,
        'sku' => $_POST['sku'] ?? '',
        'name' => $_POST['name'] ?? '',
        'description' => $_POST['description'] ?? null,
        'unit_type' => $_POST['unit_type'] ?? 'unit'
    ];
    if (saveProduct($data)) {
        $successMessage = "Product saved successfully!";
    } else {
        $errorMessage = "Error saving product.";
    }
    header("Location: index.php?action=dashboard&store={$storeId}&date={$date}&view={$view}&{$invStateQuery}");
    exit;
}

if (isset($_POST['delete_product'])) {
    if (!currentUserCan('inventory_write')) {
        $denyAccess('Manager role required for inventory changes.', 'dashboard');
    }
    if (deleteProduct(intval($_POST['product_id']))) {
        $successMessage = "Product deleted successfully!";
    } else {
        $errorMessage = "Error deleting product.";
    }
    header("Location: index.php?action=dashboard&store={$storeId}&date={$date}&view={$view}&{$invStateQuery}");
    exit;
}

if (isset($_POST['save_vendor'])) {
    if (!currentUserCan('inventory_write')) {
        $denyAccess('Manager role required for inventory changes.', 'dashboard');
    }
    $data = [
        'id' => isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : null,
        'name' => $_POST['vendor_name'] ?? '',
        'contact_name' => $_POST['contact_name'] ?? null,
        'phone' => $_POST['phone'] ?? null,
        'email' => $_POST['email'] ?? null,
        'order_method' => $_POST['order_method'] ?? null,
        'cutoff_time' => $_POST['cutoff_time'] ?? null,
        'typical_lead_time' => $_POST['typical_lead_time'] ?? null,
        'shipping_speed_notes' => $_POST['shipping_speed_notes'] ?? null,
        'free_ship_threshold' => isset($_POST['free_ship_threshold']) ? floatval($_POST['free_ship_threshold']) : null,
        'account_info' => $_POST['account_info'] ?? null,
        'password' => $_POST['password'] ?? null,
        'notes' => $_POST['vendor_notes'] ?? null,
        'rating' => isset($_POST['rating']) ? intval($_POST['rating']) : 3,
        'is_preferred' => isset($_POST['is_preferred']),
        'is_active' => isset($_POST['is_active'])
    ];
    if (saveVendor($data)) {
        $successMessage = "Vendor saved successfully!";
    } else {
        $errorMessage = "Error saving vendor.";
    }
    header("Location: index.php?action=dashboard&store={$storeId}&date={$date}&view={$view}&{$invStateQuery}");
    exit;
}

if (isset($_POST['delete_vendor'])) {
    if (!currentUserCan('inventory_write')) {
        $denyAccess('Manager role required for inventory changes.', 'dashboard');
    }
    if (deleteVendor(intval($_POST['vendor_id']))) {
        $successMessage = "Vendor deleted successfully!";
    } else {
        $errorMessage = "Error deleting vendor.";
    }
    header("Location: index.php?action=dashboard&store={$storeId}&date={$date}&view={$view}&inventory_limit=" . urlencode($invLimitParam) . "&tab=" . urlencode($tabParam));
    exit;
}

if (isset($_POST['quick_update_on_hand'])) {
    if (!currentUserCan('inventory_write')) {
        $denyAccess('Manager role required for inventory changes.', null, true);
    }
    header('Content-Type: application/json');
    $inventoryId = intval($_POST['inventory_id'] ?? 0);
    $onHand = floatval($_POST['on_hand'] ?? 0);
    $snapshotDate = isset($_POST['snapshot_date']) ? $_POST['snapshot_date'] : null;
    if ($inventoryId > 0) {
        try {
            $pdo = getDB();
            $row = $pdo->prepare("SELECT store_id, product_id FROM inventory WHERE id = ?");
            $row->execute([$inventoryId]);
            $inv = $row->fetch(\PDO::FETCH_ASSOC);
            if (!$inv) {
                echo json_encode(['success' => false, 'message' => 'Inventory not found']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE inventory SET on_hand = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            if (!$stmt->execute([$onHand, $inventoryId])) {
                echo json_encode(['success' => false, 'message' => 'Update failed']);
                exit;
            }
            if ($snapshotDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $snapshotDate)) {
                saveInventorySnapshot($inv['store_id'], $inv['product_id'], $snapshotDate, $onHand);
            }
            echo json_encode(['success' => true, 'message' => 'Updated successfully']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid inventory ID']);
    }
    exit;
}

if (isset($_POST['save_snapshot'])) {
    if (!currentUserCan('inventory_write')) {
        $denyAccess('Manager role required for inventory changes.', null, true);
    }
    header('Content-Type: application/json');
    $storeIdSnap = intval($_POST['store_id'] ?? 0);
    $productId = intval($_POST['product_id'] ?? 0);
    $snapshotDate = isset($_POST['snapshot_date']) ? $_POST['snapshot_date'] : null;
    $entryDate = isset($_POST['entry_date']) ? $_POST['entry_date'] : null;
    $onHand = floatval($_POST['on_hand'] ?? 0);
    if ($storeIdSnap > 0 && $productId > 0 && $snapshotDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $snapshotDate)) {
        if (saveInventorySnapshot($storeIdSnap, $productId, $snapshotDate, $onHand)) {
            if ($entryDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate) && $snapshotDate === $entryDate) {
                updateInventoryOnHandFromSnapshot($storeIdSnap, $productId, $onHand);
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Save failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    }
    exit;
}

if (isset($_POST['save_daily_sale'])) {
    if (!currentUserCan('inventory_write')) {
        $denyAccess('Manager role required for inventory changes.', null, true);
    }
    header('Content-Type: application/json');
    $storeIdSale = intval($_POST['store_id'] ?? 0);
    $productId = intval($_POST['product_id'] ?? 0);
    $saleDate = isset($_POST['sale_date']) ? $_POST['sale_date'] : null;
    $quantity = floatval($_POST['quantity'] ?? 0);
    if ($storeIdSale > 0 && $productId > 0 && $saleDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $saleDate)) {
        if (saveProductDailySales($storeIdSale, $productId, $saleDate, $quantity)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Save failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    }
    exit;
}

if (isset($_POST['save_daily_purchase'])) {
    if (!currentUserCan('inventory_write')) {
        $denyAccess('Manager role required for inventory changes.', null, true);
    }
    header('Content-Type: application/json');
    $storeIdPurch = intval($_POST['store_id'] ?? 0);
    $productId = intval($_POST['product_id'] ?? 0);
    $purchaseDate = isset($_POST['purchase_date']) ? $_POST['purchase_date'] : null;
    $quantity = floatval($_POST['quantity'] ?? 0);
    if ($storeIdPurch > 0 && $productId > 0 && $purchaseDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseDate)) {
        if (saveProductDailyPurchase($storeIdPurch, $productId, $purchaseDate, $quantity)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Save failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    }
    exit;
}

if (isset($_POST['update_daily_on_hand'])) {
    if (!currentUserCan('inventory_write')) {
        $denyAccess('Manager role required for inventory changes.', null, true);
    }
    header('Content-Type: application/json');
    $storeIdRecalc = intval($_POST['store_id'] ?? 0);
    $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : null;
    $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : null;
    if ($storeIdRecalc > 0 && $startDate && $endDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        if (recalcAndSaveOnHandForRange($storeIdRecalc, $startDate, $endDate)) {
            $snapshots = getInventorySnapshotsMap($storeIdRecalc, $startDate, $endDate);
            echo json_encode(['success' => true, 'snapshots' => $snapshots]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Recalc failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    }
    exit;
}

if (isset($_POST['mark_received'])) {
    if (!currentUserCan('inventory_write')) {
        $denyAccess('Manager role required for inventory changes.', null, true);
    }
    header('Content-Type: application/json');
    $orderId = intval($_POST['order_id'] ?? 0);
    $productId = intval($_POST['product_id'] ?? 0);
    $storeIdRecv = intval($_POST['store_id'] ?? 0);
    $quantity = floatval($_POST['quantity'] ?? 0);
    $receivedDate = !empty($_POST['received_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['received_date'])
        ? $_POST['received_date'] : date('Y-m-d');
    if ($orderId > 0 && $productId > 0 && $storeIdRecv > 0 && $quantity > 0) {
        try {
            $pdo = getDB();
            $pdo->beginTransaction();

            $orderStmt = $pdo->prepare("
                SELECT id, store_id, product_id, vendor_id, quantity, unit_cost, order_date, expected_delivery_date, notes
                FROM orders
                WHERE id = ?
                LIMIT 1
            ");
            $orderStmt->execute([$orderId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Order not found']);
                exit;
            }
            if ((int)$order['store_id'] !== $storeIdRecv || (int)$order['product_id'] !== $productId) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Order/store/product mismatch']);
                exit;
            }
            $orderedQty = (float)($order['quantity'] ?? 0);
            if ($orderedQty <= 0) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Order has invalid quantity']);
                exit;
            }
            if ($quantity > $orderedQty) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Received qty cannot exceed ordered qty']);
                exit;
            }

            $remainingQty = $orderedQty - $quantity;
            $updatedNotes = trim((string)($order['notes'] ?? ''));
            if ($remainingQty > 0) {
                $updatedNotes = trim($updatedNotes . ' | Partial receive: ' . rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.') . ' on ' . $receivedDate);
            }

            $closeStmt = $pdo->prepare("
                UPDATE orders
                SET quantity = ?, status = 'RECEIVED', received_date = ?, notes = ?
                WHERE id = ?
            ");
            if (!$closeStmt->execute([$quantity, $receivedDate, $updatedNotes, $orderId])) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Failed to update order']);
                exit;
            }

            if ($remainingQty > 0) {
                $newNotes = trim((string)($order['notes'] ?? ''));
                $newNotes = trim($newNotes . ' | Auto-created remaining qty from partial receive of order #' . $orderId . '.');
                $insertRemaining = $pdo->prepare("
                    INSERT INTO orders
                        (store_id, vendor_id, product_id, quantity, unit_cost, total_cost, status, order_date, received_date, expected_delivery_date, notes)
                    VALUES
                        (?, ?, ?, ?, ?, ?, 'ORDERED', ?, NULL, ?, ?)
                ");
                if (!$insertRemaining->execute([
                    (int)$order['store_id'],
                    (int)$order['vendor_id'],
                    (int)$order['product_id'],
                    $remainingQty,
                    (float)($order['unit_cost'] ?? 0),
                    $remainingQty * (float)($order['unit_cost'] ?? 0),
                    !empty($order['order_date']) ? $order['order_date'] : date('Y-m-d'),
                    $order['expected_delivery_date'] ?? null,
                    $newNotes
                ])) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Failed to create remaining order']);
                    exit;
                }
            }

            $invStmt = $pdo->prepare("SELECT on_hand FROM inventory WHERE store_id = ? AND product_id = ?");
            $invStmt->execute([$storeIdRecv, $productId]);
            $inventory = $invStmt->fetch(PDO::FETCH_ASSOC);
            $currentOnHand = $inventory ? (float)$inventory['on_hand'] : 0;
            $newOnHand = $currentOnHand + $quantity;
            $updateStmt = $pdo->prepare("UPDATE inventory SET on_hand = ?, updated_at = CURRENT_TIMESTAMP WHERE store_id = ? AND product_id = ?");
            $updateStmt->execute([$newOnHand, $storeIdRecv, $productId]);
            saveInventorySnapshot($storeIdRecv, $productId, $receivedDate, $newOnHand);
            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => $remainingQty > 0 ? 'Partial receive saved and remaining order kept open' : 'Order marked as received',
                'new_on_hand' => $newOnHand,
                'remaining_qty' => $remainingQty
            ]);
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error marking order as received: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    }
    exit;
}

if (isset($_POST['save_inventory'])) {
    if (!currentUserCan('inventory_write')) {
        $denyAccess('Manager role required for inventory changes.', 'dashboard');
    }
    $data = [
        'id' => !empty($_POST['inventory_id']) ? intval($_POST['inventory_id']) : null,
        'store_id' => intval($_POST['store_id']),
        'product_id' => intval($_POST['product_id']),
        'on_hand' => (float) round((float)($_POST['on_hand'] ?? 0)),
        'reorder_point' => (float) round((float)($_POST['reorder_point'] ?? 0)),
        'target_max' => (float) round((float)($_POST['target_max'] ?? 0)),
        'vendor_id' => !empty($_POST['vendor_id']) ? intval($_POST['vendor_id']) : null,
        'vendor_sku' => $_POST['vendor_sku'] ?? null,
        'vendor_link' => $_POST['vendor_link'] ?? null,
        'lead_time_days' => intval($_POST['lead_time_days'] ?? 0),
        'unit_cost' => floatval($_POST['unit_cost'] ?? 0),
        'avg_daily_usage' => (float) round((float)($_POST['avg_daily_usage'] ?? 0)),
        'days_of_stock' => intval($_POST['days_of_stock'] ?? 7),
        'substitution_product_id' => !empty($_POST['substitution_product_id']) ? intval($_POST['substitution_product_id']) : null,
        'notes' => $_POST['inventory_notes'] ?? null
    ];
    if (!empty($data['id']) && !empty($data['product_id']) && isset($_POST['product_sku'])) {
        $skuResult = updateProductSku($data['product_id'], $_POST['product_sku']);
        if (!$skuResult['success']) {
            $errMsg = isset($skuResult['error']) && $skuResult['error'] === 'duplicate'
                ? 'That SKU/Barcode is already used by another product.'
                : 'SKU/Barcode cannot be empty.';
            header("Location: index.php?action=dashboard&store={$storeId}&date={$date}&view={$view}&{$invStateQuery}&error=" . urlencode($errMsg));
            exit;
        }
    }
    if (saveInventory($data)) {
        $msg = isset($data['id']) ? "Inventory updated successfully!" : "Product added to inventory successfully!";
        header("Location: index.php?action=dashboard&store={$storeId}&date={$date}&view={$view}&{$invStateQuery}&success=" . urlencode($msg));
    } else {
        header("Location: index.php?action=dashboard&store={$storeId}&date={$date}&view={$view}&{$invStateQuery}&error=" . urlencode("Error saving inventory."));
    }
    exit;
}

if (isset($_POST['save_order'])) {
    if (!currentUserCan('inventory_write')) {
        $denyAccess('Manager role required for inventory changes.', 'dashboard');
    }
    $data = [
        'id' => isset($_POST['order_id']) ? intval($_POST['order_id']) : null,
        'store_id' => intval($_POST['store_id']),
        'vendor_id' => intval($_POST['vendor_id']),
        'product_id' => intval($_POST['product_id']),
        'quantity' => floatval($_POST['quantity'] ?? 0),
        'unit_cost' => floatval($_POST['unit_cost'] ?? 0),
        'status' => $_POST['order_status'] ?? 'REQUESTED',
        'order_date' => $_POST['order_date'] ?? date('Y-m-d'),
        'received_date' => $_POST['received_date'] ?? null,
        'notes' => $_POST['order_notes'] ?? null
    ];
    if (saveOrder($data)) {
        $successMessage = "Order saved successfully!";
    } else {
        $errorMessage = "Error saving order.";
    }
    header("Location: index.php?action=dashboard&store={$storeId}&date={$date}&view={$view}&{$invStateQuery}");
    exit;
}

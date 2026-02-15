<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/timeclock-functions.php';

function report($name, $pass, $extra = '') {
    echo $name . ': ' . ($pass ? 'PASS' : 'FAIL');
    if ($extra !== '') {
        echo ' - ' . $extra;
    }
    echo PHP_EOL;
}

$stores = getAllStores();
if (empty($stores)) {
    report('Stores available', false, 'No stores found');
    exit(1);
}
$storeId = (int)$stores[0]['id'];
$employees = getTimeClockEmployeesForStore($storeId);
if (empty($employees)) {
    report('Employees available', false, 'No employees mapped to store');
    exit(1);
}
$employeeId = (int)$employees[0]['id'];

$mgr = 'AutoTestManager';
$role = 'AUTOTEST_ROLE';
$weekStartDate = '2099-01-05';
$currentPeriodStart = (new DateTime('first day of this month'))->format('Y-m-d');
$currentPeriodEnd = (new DateTime('last day of this month'))->format('Y-m-d');
$currentPeriodId = 0;
$tmpOpenShiftId = 0;
$lockTestEmployeeId = 0;

try {
    $pdo = getDB();
    $insEmp = $pdo->prepare("
        INSERT INTO employees (full_name, role_name, pin_hash, hourly_rate_cents)
        VALUES (?, 'Employee', ?, 1500)
        RETURNING id
    ");
    $insEmp->execute([
        'AUTOTEST LOCK GUARD EMP',
        password_hash('9999', PASSWORD_DEFAULT)
    ]);
    $empRow = $insEmp->fetch(PDO::FETCH_ASSOC);
    $lockTestEmployeeId = (int)($empRow['id'] ?? 0);
    if ($lockTestEmployeeId > 0) {
        $mapEmp = $pdo->prepare("
            INSERT INTO employee_locations (employee_id, store_id, is_active)
            VALUES (?, ?, TRUE)
        ");
        $mapEmp->execute([$lockTestEmployeeId, $storeId]);
    }
} catch (Throwable $e) {
    report('Create lock-test employee', false, $e->getMessage());
}

$start = parseLocalDateTimeToUtc('2099-01-05 09:00:00');
$end = parseLocalDateTimeToUtc('2099-01-05 17:00:00');
$resultAdd = addScheduleShift([
    'employee_id' => $employeeId,
    'store_id' => $storeId,
    'role_name' => $role,
    'start_utc' => $start,
    'end_utc' => $end,
    'break_minutes' => 30,
    'manager_name' => $mgr
]);

$resultOverlap = addScheduleShift([
    'employee_id' => $employeeId,
    'store_id' => $storeId,
    'role_name' => $role,
    'start_utc' => parseLocalDateTimeToUtc('2099-01-05 12:00:00'),
    'end_utc' => parseLocalDateTimeToUtc('2099-01-05 15:00:00'),
    'break_minutes' => 0,
    'manager_name' => $mgr
]);

$week = getWeekRangeForDate($weekStartDate);
$shifts = getScheduleShiftsForStoreWeek($storeId, $week['start'], $week['end']);
$calendar = buildScheduleCalendarData($shifts, $week['start'], $week['end'], 9, 21);

report('Add schedule shift', !empty($resultAdd['success']), $resultAdd['message'] ?? '');
report(
    'Overlap detection',
    empty($resultOverlap['success']) && strpos(strtolower((string)($resultOverlap['message'] ?? '')), 'overlap') !== false,
    $resultOverlap['message'] ?? ''
);
report('Calendar has 7 days', count($calendar['days']) === 7, (string)count($calendar['days']));

$gapMinutes = (int)($calendar['coverage_by_day']['2099-01-05']['gap_minutes'] ?? -1);
report('Coverage gap computed', $gapMinutes >= 0, (string)$gapMinutes . ' minutes');
report('Employee hours computed', !empty($calendar['employee_hours']), (string)count($calendar['employee_hours']) . ' rows');

$pub = publishScheduleWeek($storeId, $week['start'], $week['end'], $mgr);
report('Publish week workflow', !empty($pub['success']), $pub['message'] ?? '');

// PTO request + approval smoke
upsertTimeclockSetting('pto_accrual_method', 'per_hour_worked', 'global', null);
upsertTimeclockSetting('pto_minutes_per_hour', '60.00', 'global', null);
upsertTimeclockSetting('pto_exclude_overtime', '1', 'global', null);
upsertTimeclockSetting('pto_annual_cap_minutes', '100000', 'global', null);
upsertTimeclockSetting('pto_waiting_period_days', '0', 'global', null);
$tmpPtoShiftId = 0;
try {
    $pdo = getDB();
    $insPtoShift = $pdo->prepare("
        INSERT INTO time_shifts (employee_id, store_id, clock_in_utc, clock_out_utc, clock_in_note, clock_out_note)
        VALUES (?, ?, ?, ?, 'AUTOTEST_PTO_ACCRUAL', 'AUTOTEST_PTO_ACCRUAL')
        RETURNING id
    ");
    $insPtoShift->execute([
        $employeeId,
        $storeId,
        parseLocalDateTimeToUtc('2026-01-07 09:00:00'),
        parseLocalDateTimeToUtc('2026-01-07 17:00:00')
    ]);
    $tmpPtoShift = $insPtoShift->fetch(PDO::FETCH_ASSOC);
    $tmpPtoShiftId = (int)($tmpPtoShift['id'] ?? 0);
} catch (Throwable $e) {
    report('Insert PTO accrual shift', false, $e->getMessage());
}
$preBal = recalcPtoBalanceForEmployee($employeeId, $storeId);
$reqMinutes = max(60, min(480, (int)($preBal['available_minutes'] ?? 0)));
$ptoReq = createPtoRequest(
    $employeeId,
    $storeId,
    '2099-01-10',
    '2099-01-10',
    $reqMinutes,
    'AUTOTEST PTO',
    $employees[0]['full_name'] ?? 'Auto Employee'
);
report('PTO request submit', !empty($ptoReq['success']), $ptoReq['message'] ?? '');
$pendingPto = getPendingPtoRequestsByStore($storeId);
$ptoRequestId = 0;
foreach ($pendingPto as $r) {
    if ((int)$r['employee_id'] === $employeeId && (string)$r['reason'] === 'AUTOTEST PTO') {
        $ptoRequestId = (int)$r['id'];
        break;
    }
}
if ($ptoRequestId > 0) {
    $ptoReview = reviewPtoRequest($ptoRequestId, $storeId, 'APPROVED', $mgr, 'autotest approve');
    report('PTO request approve', !empty($ptoReview['success']), $ptoReview['message'] ?? '');
}
$balances = getPtoBalancesByStore($storeId);
$foundBal = null;
foreach ($balances as $b) {
    if ((int)$b['employee_id'] === $employeeId) {
        $foundBal = $b;
        break;
    }
}
report('PTO balance available', $foundBal !== null, $foundBal ? ('avail=' . (int)$foundBal['available_minutes']) : 'none');

// Payroll smoke tests on far-future period tied to AUTOTEST shift above.
$periodStart = '2099-01-01';
$periodEnd = '2099-01-31';
$pp = createOrGetPayrollPeriod($storeId, $periodStart, $periodEnd, $mgr);
report('Payroll period save', !empty($pp['success']), $pp['message'] ?? '');
$periodId = !empty($pp['period_id']) ? (int)$pp['period_id'] : 0;
$tmpShiftId = 0;
if ($periodId > 0) {
    // Insert one actual worked shift so payroll run has source data.
    try {
        $pdo = getDB();
        $ins = $pdo->prepare("
            INSERT INTO time_shifts (employee_id, store_id, clock_in_utc, clock_out_utc, clock_in_note, clock_out_note)
            VALUES (?, ?, ?, ?, 'AUTOTEST_PAYROLL', 'AUTOTEST_PAYROLL')
            RETURNING id
        ");
        $ins->execute([
            $employeeId,
            $storeId,
            parseLocalDateTimeToUtc('2099-01-06 09:00:00'),
            parseLocalDateTimeToUtc('2099-01-06 17:00:00')
        ]);
        $tmpRow = $ins->fetch(PDO::FETCH_ASSOC);
        $tmpShiftId = (int)($tmpRow['id'] ?? 0);
    } catch (Throwable $e) {
        report('Insert payroll source shift', false, $e->getMessage());
    }

    $run = runPayrollForPeriod($periodId, $storeId, $mgr);
    report('Payroll run process', !empty($run['success']), $run['message'] ?? '');
    $runs = getPayrollRunsByPeriod($periodId);
    report('Payroll run rows available', !empty($runs), (string)count($runs) . ' rows');
    $ptoPaidMinutes = 0;
    foreach ($runs as $rr) {
        if ((int)$rr['employee_id'] === $employeeId) {
            $ptoPaidMinutes = (int)$rr['pto_minutes'];
            break;
        }
    }
    report('Payroll includes PTO paid minutes', $ptoPaidMinutes >= $reqMinutes && $reqMinutes > 0, (string)$ptoPaidMinutes);
    $csv = getPayrollCsvContent($periodId, $storeId);
    report('Payroll CSV generation', is_string($csv) && strlen($csv) > 20, is_string($csv) ? ('len=' . strlen($csv)) : 'null');

    $lock = lockPayrollPeriod($periodId, $storeId, $mgr);
    report('Payroll period lock', !empty($lock['success']), $lock['message'] ?? '');
    $runLocked = runPayrollForPeriod($periodId, $storeId, $mgr);
    report('Payroll run blocked when locked', empty($runLocked['success']) && strpos(strtolower((string)($runLocked['message'] ?? '')), 'locked') !== false, $runLocked['message'] ?? '');
    $lockedSchedule = addScheduleShift([
        'employee_id' => $employeeId,
        'store_id' => $storeId,
        'role_name' => $role,
        'start_utc' => parseLocalDateTimeToUtc('2099-01-10 10:00:00'),
        'end_utc' => parseLocalDateTimeToUtc('2099-01-10 12:00:00'),
        'break_minutes' => 0,
        'manager_name' => $mgr,
        'note' => 'AUTOTEST locked schedule'
    ]);
    report('Schedule add blocked when payroll locked', empty($lockedSchedule['success']) && strpos(strtolower((string)($lockedSchedule['message'] ?? '')), 'locked') !== false, $lockedSchedule['message'] ?? '');
    $lockedPtoReq = createPtoRequest(
        $employeeId,
        $storeId,
        '2099-01-12',
        '2099-01-12',
        60,
        'AUTOTEST PTO LOCKED',
        'AUTOTEST'
    );
    report('PTO request blocked when payroll locked', empty($lockedPtoReq['success']) && strpos(strtolower((string)($lockedPtoReq['message'] ?? '')), 'locked') !== false, $lockedPtoReq['message'] ?? '');
    $lockedEditSubmit = createPunchEditRequest([
        'employee_id' => $employeeId,
        'store_id' => $storeId,
        'shift_id' => $tmpShiftId > 0 ? $tmpShiftId : null,
        'request_type' => 'ADJUST_SHIFT',
        'requested_clock_in_utc' => null,
        'requested_clock_out_utc' => parseLocalDateTimeToUtc('2099-01-06 18:00:00'),
        'reason' => 'AUTOTEST EDIT LOCKED',
        'employee_name' => 'AUTOTEST'
    ]);
    $lockedEditRequestId = 0;
    if ($lockedEditSubmit) {
        $pendingEdits = getPendingPunchEditRequestsByStore($storeId);
        foreach ($pendingEdits as $er) {
            if ((int)$er['employee_id'] === $employeeId && (string)$er['reason'] === 'AUTOTEST EDIT LOCKED') {
                $lockedEditRequestId = (int)$er['id'];
                break;
            }
        }
    }
    $lockedEditReview = $lockedEditRequestId > 0
        ? processPunchEditRequest($lockedEditRequestId, $storeId, 'APPROVED', $mgr, 'autotest locked period')
        : ['success' => false, 'message' => 'Could not create locked edit request for test.'];
    report('Time edit approval blocked when payroll locked', empty($lockedEditReview['success']) && strpos(strtolower((string)($lockedEditReview['message'] ?? '')), 'locked') !== false, $lockedEditReview['message'] ?? '');
    $unlock = unlockPayrollPeriod($periodId, $storeId, $mgr);
    report('Payroll period unlock', !empty($unlock['success']), $unlock['message'] ?? '');

    $currentPeriod = createOrGetPayrollPeriod($storeId, $currentPeriodStart, $currentPeriodEnd, $mgr);
    report('Current payroll period save', !empty($currentPeriod['success']), $currentPeriod['message'] ?? '');
    $currentPeriodId = !empty($currentPeriod['period_id']) ? (int)$currentPeriod['period_id'] : 0;
    if ($currentPeriodId > 0) {
        $lockCurrent = lockPayrollPeriod($currentPeriodId, $storeId, $mgr);
        report('Current payroll period lock', !empty($lockCurrent['success']), $lockCurrent['message'] ?? '');

        $punchEmployeeId = $lockTestEmployeeId > 0 ? $lockTestEmployeeId : $employeeId;
        $clockInLocked = clockInEmployee($punchEmployeeId, $storeId, ['note' => 'AUTOTEST CLOCKIN LOCKED']);
        report('Clock-in blocked when payroll locked', empty($clockInLocked['success']) && strpos(strtolower((string)($clockInLocked['message'] ?? '')), 'locked') !== false, $clockInLocked['message'] ?? '');

        try {
            $pdo = getDB();
            $insertOpen = $pdo->prepare("
                INSERT INTO time_shifts (employee_id, store_id, clock_in_utc, clock_in_note)
                VALUES (?, ?, ?, 'AUTOTEST_OPEN_SHIFT_LOCKED')
                RETURNING id
            ");
            $insertOpen->execute([
                $punchEmployeeId,
                $storeId,
                (new DateTime('now', new DateTimeZone('UTC')))->modify('-2 hours')->format('Y-m-d H:i:sP')
            ]);
            $openRow = $insertOpen->fetch(PDO::FETCH_ASSOC);
            $tmpOpenShiftId = (int)($openRow['id'] ?? 0);
        } catch (Throwable $e) {
            report('Insert open shift for lock test', false, $e->getMessage());
        }
        $clockOutLocked = clockOutEmployee($punchEmployeeId, $storeId, ['note' => 'AUTOTEST CLOCKOUT LOCKED']);
        report('Clock-out blocked when payroll locked', empty($clockOutLocked['success']) && strpos(strtolower((string)($clockOutLocked['message'] ?? '')), 'locked') !== false, $clockOutLocked['message'] ?? '');

        $unlockCurrent = unlockPayrollPeriod($currentPeriodId, $storeId, $mgr);
        report('Current payroll period unlock', !empty($unlockCurrent['success']), $unlockCurrent['message'] ?? '');
    }
}

try {
    $pdo = getDB();
    $del = $pdo->prepare("DELETE FROM timeclock_schedule_shifts WHERE role_name = ?");
    $del->execute([$role]);
    $delPayrollRuns = $pdo->prepare("DELETE FROM timeclock_payroll_runs WHERE payroll_period_id IN (SELECT id FROM timeclock_payroll_periods WHERE store_id = ? AND period_start_date = ? AND period_end_date = ?)");
    $delPayrollRuns->execute([$storeId, $periodStart, $periodEnd]);
    $delPayrollPeriods = $pdo->prepare("DELETE FROM timeclock_payroll_periods WHERE store_id = ? AND period_start_date = ? AND period_end_date = ?");
    $delPayrollPeriods->execute([$storeId, $periodStart, $periodEnd]);
    $delPayrollRunsCurrent = $pdo->prepare("DELETE FROM timeclock_payroll_runs WHERE payroll_period_id IN (SELECT id FROM timeclock_payroll_periods WHERE store_id = ? AND period_start_date = ? AND period_end_date = ?)");
    $delPayrollRunsCurrent->execute([$storeId, $currentPeriodStart, $currentPeriodEnd]);
    $delPayrollPeriodsCurrent = $pdo->prepare("DELETE FROM timeclock_payroll_periods WHERE store_id = ? AND period_start_date = ? AND period_end_date = ?");
    $delPayrollPeriodsCurrent->execute([$storeId, $currentPeriodStart, $currentPeriodEnd]);
    $delPto = $pdo->prepare("DELETE FROM timeclock_pto_requests WHERE store_id = ? AND employee_id = ? AND reason LIKE 'AUTOTEST PTO%'");
    $delPto->execute([$storeId, $employeeId]);
    $delEdit = $pdo->prepare("DELETE FROM timeclock_edit_requests WHERE store_id = ? AND employee_id = ? AND reason = 'AUTOTEST EDIT LOCKED'");
    $delEdit->execute([$storeId, $employeeId]);
    if ($tmpShiftId > 0) {
        $delShift = $pdo->prepare("DELETE FROM time_shifts WHERE id = ?");
        $delShift->execute([$tmpShiftId]);
    } else {
        $delShift = $pdo->prepare("DELETE FROM time_shifts WHERE clock_in_note = 'AUTOTEST_PAYROLL' AND clock_out_note = 'AUTOTEST_PAYROLL'");
        $delShift->execute();
    }
    if ($tmpPtoShiftId > 0) {
        $delShift2 = $pdo->prepare("DELETE FROM time_shifts WHERE id = ?");
        $delShift2->execute([$tmpPtoShiftId]);
    } else {
        $delShift2 = $pdo->prepare("DELETE FROM time_shifts WHERE clock_in_note = 'AUTOTEST_PTO_ACCRUAL' AND clock_out_note = 'AUTOTEST_PTO_ACCRUAL'");
        $delShift2->execute();
    }
    if ($tmpOpenShiftId > 0) {
        $delOpen = $pdo->prepare("DELETE FROM time_shifts WHERE id = ?");
        $delOpen->execute([$tmpOpenShiftId]);
    } else {
        $delOpen = $pdo->prepare("DELETE FROM time_shifts WHERE clock_in_note = 'AUTOTEST_OPEN_SHIFT_LOCKED'");
        $delOpen->execute();
    }
    if ($lockTestEmployeeId > 0) {
        $delLockEmp = $pdo->prepare("DELETE FROM employees WHERE id = ?");
        $delLockEmp->execute([$lockTestEmployeeId]);
    }
    recalcPtoBalanceForEmployee($employeeId, $storeId);
    upsertTimeclockSetting('pto_minutes_per_hour', '1.15', 'global', null);
    upsertTimeclockSetting('pto_annual_cap_minutes', '2400', 'global', null);
} catch (Throwable $e) {
    report('Cleanup test rows', false, $e->getMessage());
    exit(1);
}
report('Cleanup test rows', true);

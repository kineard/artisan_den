<?php

function getTimeClockEmployeesForStore($storeId) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT e.id, e.full_name, e.is_active
            FROM employees e
            INNER JOIN employee_locations el ON el.employee_id = e.id
            WHERE el.store_id = ? AND el.is_active = TRUE AND e.is_active = TRUE
            ORDER BY e.full_name ASC
        ");
        $stmt->execute([(int)$storeId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('getTimeClockEmployeesForStore error: ' . $e->getMessage());
        return [];
    }
}

function getTimeClockRoleOptions($storeId) {
    $defaults = ['Employee', 'Cashier', 'Manager', 'Stock'];
    $roles = [];
    foreach ($defaults as $r) {
        $roles[$r] = true;
    }
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT DISTINCT role_name AS role_value
            FROM employees e
            INNER JOIN employee_locations el ON el.employee_id = e.id
            WHERE el.store_id = ? AND el.is_active = TRUE AND e.is_active = TRUE
              AND role_name IS NOT NULL AND TRIM(role_name) <> ''
            UNION
            SELECT DISTINCT role_name AS role_value
            FROM timeclock_schedule_shifts
            WHERE store_id = ? AND role_name IS NOT NULL AND TRIM(role_name) <> ''
        ");
        $stmt->execute([(int)$storeId, (int)$storeId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as $row) {
            $name = trim((string)($row['role_value'] ?? ''));
            if ($name !== '') {
                $roles[$name] = true;
            }
        }
    } catch (Throwable $e) {
        error_log('getTimeClockRoleOptions error: ' . $e->getMessage());
    }
    $out = array_keys($roles);
    usort($out, function ($a, $b) { return strcasecmp($a, $b); });
    return $out;
}

function getEmployeeByIdForStore($employeeId, $storeId) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT e.id, e.full_name, e.pin_hash, e.is_active
            FROM employees e
            INNER JOIN employee_locations el ON el.employee_id = e.id
            WHERE e.id = ? AND el.store_id = ? AND el.is_active = TRUE
            LIMIT 1
        ");
        $stmt->execute([(int)$employeeId, (int)$storeId]);
        return $stmt->fetch();
    } catch (Throwable $e) {
        error_log('getEmployeeByIdForStore error: ' . $e->getMessage());
        return null;
    }
}

function verifyEmployeePinForStore($employeeId, $storeId, $pin) {
    $employee = getEmployeeByIdForStore($employeeId, $storeId);
    if (!$employee || empty($employee['is_active'])) {
        return null;
    }
    $pinHash = (string)($employee['pin_hash'] ?? '');
    if ($pinHash === '' || !password_verify((string)$pin, $pinHash)) {
        return null;
    }
    return $employee;
}

function getOpenShiftForEmployeeStore($employeeId, $storeId) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT ts.*, e.full_name
            FROM time_shifts ts
            INNER JOIN employees e ON e.id = ts.employee_id
            WHERE ts.employee_id = ? AND ts.store_id = ? AND ts.clock_out_utc IS NULL
            ORDER BY ts.clock_in_utc DESC
            LIMIT 1
        ");
        $stmt->execute([(int)$employeeId, (int)$storeId]);
        return $stmt->fetch();
    } catch (Throwable $e) {
        error_log('getOpenShiftForEmployeeStore error: ' . $e->getMessage());
        return null;
    }
}

function getOpenShiftsByStore($storeId) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT ts.id, ts.employee_id, ts.store_id, ts.clock_in_utc, ts.clock_in_note, e.full_name
            FROM time_shifts ts
            INNER JOIN employees e ON e.id = ts.employee_id
            WHERE ts.store_id = ? AND ts.clock_out_utc IS NULL
            ORDER BY ts.clock_in_utc ASC
        ");
        $stmt->execute([(int)$storeId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('getOpenShiftsByStore error: ' . $e->getMessage());
        return [];
    }
}

function getRecentShiftEventsByStore($storeId, $limit = 20) {
    try {
        $pdo = getDB();
        $safeLimit = max(1, min(100, (int)$limit));
        $stmt = $pdo->prepare("
            SELECT te.event_type, te.event_utc, te.gps_status, te.geofence_pass, te.geofence_distance_m, te.note, e.full_name
            FROM time_punch_events te
            INNER JOIN employees e ON e.id = te.employee_id
            WHERE te.store_id = ?
            ORDER BY te.event_utc DESC
            LIMIT $safeLimit
        ");
        $stmt->execute([(int)$storeId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('getRecentShiftEventsByStore error: ' . $e->getMessage());
        return [];
    }
}

function getRecentShiftsByStore($storeId, $limit = 50) {
    try {
        $pdo = getDB();
        $safeLimit = max(1, min(200, (int)$limit));
        $stmt = $pdo->prepare("
            SELECT ts.id, ts.employee_id, ts.clock_in_utc, ts.clock_out_utc, e.full_name
            FROM time_shifts ts
            INNER JOIN employees e ON e.id = ts.employee_id
            WHERE ts.store_id = ?
            ORDER BY ts.clock_in_utc DESC
            LIMIT $safeLimit
        ");
        $stmt->execute([(int)$storeId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('getRecentShiftsByStore error: ' . $e->getMessage());
        return [];
    }
}

function getTimeclockTaskById($taskId, $storeId) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT t.*, e.full_name AS assigned_employee_name
            FROM timeclock_tasks t
            LEFT JOIN employees e ON e.id = t.assigned_employee_id
            WHERE t.id = ? AND t.store_id = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$taskId, (int)$storeId]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        error_log('getTimeclockTaskById error: ' . $e->getMessage());
        return null;
    }
}

function getTimeclockTasksForStoreDate($storeId, $dateYmd) {
    try {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$dateYmd)) {
            return [];
        }
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT t.*,
                   e.full_name AS assigned_employee_name,
                   s.role_name AS assigned_shift_role,
                   s.start_utc AS assigned_shift_start_utc,
                   s.end_utc AS assigned_shift_end_utc
            FROM timeclock_tasks t
            LEFT JOIN employees e ON e.id = t.assigned_employee_id
            LEFT JOIN timeclock_schedule_shifts s ON s.id = t.schedule_shift_id
            WHERE t.store_id = ? AND t.task_date = ?
            ORDER BY
                CASE WHEN t.status = 'OPEN' THEN 0 ELSE 1 END,
                t.created_at ASC
        ");
        $stmt->execute([(int)$storeId, (string)$dateYmd]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('getTimeclockTasksForStoreDate error: ' . $e->getMessage());
        return [];
    }
}

function createTimeclockTask($storeId, $taskDateYmd, $title, $details, $assignedEmployeeId, $scheduleShiftId, $createdBy) {
    try {
        if ((int)$storeId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$taskDateYmd)) {
            return ['success' => false, 'message' => 'Invalid task date/store.'];
        }
        $title = trim((string)$title);
        if ($title === '') {
            return ['success' => false, 'message' => 'Task title is required.'];
        }
        $assignedEmployeeId = (int)$assignedEmployeeId;
        $scheduleShiftId = (int)$scheduleShiftId;
        if ($scheduleShiftId > 0) {
            $shift = getScheduleShiftById($scheduleShiftId, (int)$storeId);
            if (!$shift) {
                return ['success' => false, 'message' => 'Selected schedule shift not found.'];
            }
            if ($assignedEmployeeId <= 0) {
                $assignedEmployeeId = (int)($shift['employee_id'] ?? 0);
            }
        }
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO timeclock_tasks
                (store_id, task_date, schedule_shift_id, assigned_employee_id, title, details, status, created_by, updated_at)
            VALUES
                (?, ?, ?, ?, ?, ?, 'OPEN', ?, CURRENT_TIMESTAMP)
            RETURNING id
        ");
        $stmt->execute([
            (int)$storeId,
            (string)$taskDateYmd,
            $scheduleShiftId > 0 ? $scheduleShiftId : null,
            $assignedEmployeeId > 0 ? $assignedEmployeeId : null,
            $title,
            trim((string)$details) !== '' ? trim((string)$details) : null,
            trim((string)$createdBy) !== '' ? trim((string)$createdBy) : null
        ]);
        $row = $stmt->fetch();
        $taskId = (int)($row['id'] ?? 0);
        logTimeclockAudit((int)$storeId, $assignedEmployeeId > 0 ? $assignedEmployeeId : null, (string)$createdBy, 'TASK_CREATED', [
            'task_id' => $taskId,
            'task_date' => $taskDateYmd,
            'title' => $title
        ]);
        return ['success' => true, 'task_id' => $taskId, 'message' => 'Task created.'];
    } catch (Throwable $e) {
        error_log('createTimeclockTask error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Unable to create task.'];
    }
}

function updateTimeclockTaskStatus($taskId, $storeId, $status, $actorName) {
    try {
        $status = strtoupper(trim((string)$status));
        if (!in_array($status, ['OPEN', 'DONE'], true)) {
            return ['success' => false, 'message' => 'Invalid task status.'];
        }
        $task = getTimeclockTaskById((int)$taskId, (int)$storeId);
        if (!$task) {
            return ['success' => false, 'message' => 'Task not found.'];
        }
        $pdo = getDB();
        if ($status === 'DONE') {
            $stmt = $pdo->prepare("
                UPDATE timeclock_tasks
                SET status = 'DONE', completed_at = CURRENT_TIMESTAMP, completed_by = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND store_id = ?
            ");
            $stmt->execute([trim((string)$actorName), (int)$taskId, (int)$storeId]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE timeclock_tasks
                SET status = 'OPEN', completed_at = NULL, completed_by = NULL, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND store_id = ?
            ");
            $stmt->execute([(int)$taskId, (int)$storeId]);
        }
        logTimeclockAudit((int)$storeId, !empty($task['assigned_employee_id']) ? (int)$task['assigned_employee_id'] : null, (string)$actorName, 'TASK_STATUS_UPDATED', [
            'task_id' => (int)$taskId,
            'status' => $status
        ]);
        return ['success' => true, 'message' => $status === 'DONE' ? 'Task marked done.' : 'Task reopened.'];
    } catch (Throwable $e) {
        error_log('updateTimeclockTaskStatus error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Unable to update task status.'];
    }
}

function deleteTimeclockTask($taskId, $storeId, $actorName) {
    try {
        $task = getTimeclockTaskById((int)$taskId, (int)$storeId);
        if (!$task) {
            return ['success' => false, 'message' => 'Task not found.'];
        }
        $pdo = getDB();
        $stmt = $pdo->prepare("DELETE FROM timeclock_tasks WHERE id = ? AND store_id = ?");
        $stmt->execute([(int)$taskId, (int)$storeId]);
        logTimeclockAudit((int)$storeId, !empty($task['assigned_employee_id']) ? (int)$task['assigned_employee_id'] : null, (string)$actorName, 'TASK_DELETED', [
            'task_id' => (int)$taskId
        ]);
        return ['success' => true, 'message' => 'Task deleted.'];
    } catch (Throwable $e) {
        error_log('deleteTimeclockTask error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Unable to delete task.'];
    }
}

function hasWorkedShiftOverlap($employeeId, $storeId, $startUtc, $endUtc) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT id
            FROM time_shifts
            WHERE employee_id = ?
              AND store_id = ?
              AND clock_in_utc < ?
              AND COALESCE(clock_out_utc, CURRENT_TIMESTAMP) > ?
            LIMIT 1
        ");
        $stmt->execute([(int)$employeeId, (int)$storeId, (string)$endUtc, (string)$startUtc]);
        return (bool)$stmt->fetch();
    } catch (Throwable $e) {
        error_log('hasWorkedShiftOverlap error: ' . $e->getMessage());
        return false;
    }
}

function createTimePunchEvent($data) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO time_punch_events
                (employee_id, store_id, shift_id, event_type, event_utc, event_ip, event_user_agent, gps_lat, gps_lng, gps_accuracy_m, gps_captured, gps_status, geofence_pass, geofence_distance_m, note, created_by)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            (int)$data['employee_id'],
            (int)$data['store_id'],
            !empty($data['shift_id']) ? (int)$data['shift_id'] : null,
            (string)$data['event_type'],
            (string)$data['event_utc'],
            $data['event_ip'] ?? null,
            $data['event_user_agent'] ?? null,
            $data['gps_lat'] ?? null,
            $data['gps_lng'] ?? null,
            $data['gps_accuracy_m'] ?? null,
            !empty($data['gps_captured']),
            $data['gps_status'] ?? 'unavailable',
            isset($data['geofence_pass']) ? (bool)$data['geofence_pass'] : null,
            $data['geofence_distance_m'] ?? null,
            $data['note'] ?? null,
            $data['created_by'] ?? 'employee'
        ]);
    } catch (Throwable $e) {
        error_log('createTimePunchEvent error: ' . $e->getMessage());
        return false;
    }
}

function clockInEmployee($employeeId, $storeId, $meta = []) {
    $openShift = getOpenShiftForEmployeeStore($employeeId, $storeId);
    if ($openShift) {
        return ['success' => false, 'message' => 'Employee already has an open shift.'];
    }

    try {
        $pdo = getDB();
        $nowUtc = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:sP');
        $localDate = getLocalDateYmdFromUtcOrNull($nowUtc);
        if ($localDate && hasLockedPayrollPeriodOverlap((int)$storeId, $localDate, $localDate)) {
            return ['success' => false, 'message' => 'Cannot clock in inside a locked payroll period. Unlock period first.'];
        }
        $geo = evaluateGeofenceForPunch((int)$storeId, $meta);
        if (!empty($geo['blocked'])) {
            return ['success' => false, 'message' => (string)($geo['message'] ?? 'Punch blocked by geofence policy.')];
        }
        if (array_key_exists('geofence_pass', $geo)) {
            $meta['geofence_pass'] = $geo['geofence_pass'];
        }
        if (array_key_exists('geofence_distance_m', $geo)) {
            $meta['geofence_distance_m'] = $geo['geofence_distance_m'];
        }
        $stmt = $pdo->prepare("
            INSERT INTO time_shifts
                (employee_id, store_id, clock_in_utc, clock_in_ip, clock_in_user_agent, clock_in_note, gps_lat, gps_lng, gps_accuracy_m, gps_captured, gps_status, geofence_pass, geofence_distance_m)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        ");
        $stmt->execute([
            (int)$employeeId,
            (int)$storeId,
            $nowUtc,
            $meta['ip'] ?? null,
            $meta['user_agent'] ?? null,
            $meta['note'] ?? null,
            $meta['gps_lat'] ?? null,
            $meta['gps_lng'] ?? null,
            $meta['gps_accuracy_m'] ?? null,
            !empty($meta['gps_captured']),
            $meta['gps_status'] ?? 'unavailable',
            isset($meta['geofence_pass']) ? (bool)$meta['geofence_pass'] : null,
            $meta['geofence_distance_m'] ?? null
        ]);
        $row = $stmt->fetch();
        $shiftId = isset($row['id']) ? (int)$row['id'] : null;

        createTimePunchEvent([
            'employee_id' => $employeeId,
            'store_id' => $storeId,
            'shift_id' => $shiftId,
            'event_type' => 'CLOCK_IN',
            'event_utc' => $nowUtc,
            'event_ip' => $meta['ip'] ?? null,
            'event_user_agent' => $meta['user_agent'] ?? null,
            'gps_lat' => $meta['gps_lat'] ?? null,
            'gps_lng' => $meta['gps_lng'] ?? null,
            'gps_accuracy_m' => $meta['gps_accuracy_m'] ?? null,
            'gps_captured' => !empty($meta['gps_captured']),
            'gps_status' => $meta['gps_status'] ?? 'unavailable',
            'geofence_pass' => isset($meta['geofence_pass']) ? (bool)$meta['geofence_pass'] : null,
            'geofence_distance_m' => $meta['geofence_distance_m'] ?? null,
            'note' => $meta['note'] ?? null,
            'created_by' => 'employee'
        ]);

        $msg = 'Clock-in successful.';
        if (!empty($geo['message'])) {
            $msg .= ' ' . $geo['message'];
        }
        return ['success' => true, 'message' => $msg];
    } catch (Throwable $e) {
        error_log('clockInEmployee error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Clock-in failed.'];
    }
}

function clockOutEmployee($employeeId, $storeId, $meta = []) {
    $openShift = getOpenShiftForEmployeeStore($employeeId, $storeId);
    if (!$openShift) {
        return ['success' => false, 'message' => 'No open shift found to clock out.'];
    }

    try {
        $pdo = getDB();
        $nowUtc = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:sP');
        [$lockStartDate, $lockEndDate] = getLocalDateRangeFromUtc((string)($openShift['clock_in_utc'] ?? null), $nowUtc);
        if ($lockStartDate && hasLockedPayrollPeriodOverlap((int)$storeId, $lockStartDate, $lockEndDate)) {
            return ['success' => false, 'message' => 'Cannot clock out inside a locked payroll period. Unlock period first.'];
        }
        $geo = evaluateGeofenceForPunch((int)$storeId, $meta);
        if (!empty($geo['blocked'])) {
            return ['success' => false, 'message' => (string)($geo['message'] ?? 'Punch blocked by geofence policy.')];
        }
        if (array_key_exists('geofence_pass', $geo)) {
            $meta['geofence_pass'] = $geo['geofence_pass'];
        }
        if (array_key_exists('geofence_distance_m', $geo)) {
            $meta['geofence_distance_m'] = $geo['geofence_distance_m'];
        }
        $stmt = $pdo->prepare("
            UPDATE time_shifts
            SET clock_out_utc = ?, clock_out_ip = ?, clock_out_user_agent = ?, clock_out_note = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $nowUtc,
            $meta['ip'] ?? null,
            $meta['user_agent'] ?? null,
            $meta['note'] ?? null,
            (int)$openShift['id']
        ]);

        createTimePunchEvent([
            'employee_id' => $employeeId,
            'store_id' => $storeId,
            'shift_id' => (int)$openShift['id'],
            'event_type' => 'CLOCK_OUT',
            'event_utc' => $nowUtc,
            'event_ip' => $meta['ip'] ?? null,
            'event_user_agent' => $meta['user_agent'] ?? null,
            'gps_lat' => $meta['gps_lat'] ?? null,
            'gps_lng' => $meta['gps_lng'] ?? null,
            'gps_accuracy_m' => $meta['gps_accuracy_m'] ?? null,
            'gps_captured' => !empty($meta['gps_captured']),
            'gps_status' => $meta['gps_status'] ?? 'unavailable',
            'geofence_pass' => isset($meta['geofence_pass']) ? (bool)$meta['geofence_pass'] : null,
            'geofence_distance_m' => $meta['geofence_distance_m'] ?? null,
            'note' => $meta['note'] ?? null,
            'created_by' => 'employee'
        ]);

        $msg = 'Clock-out successful.';
        if (!empty($geo['message'])) {
            $msg .= ' ' . $geo['message'];
        }
        return ['success' => true, 'message' => $msg];
    } catch (Throwable $e) {
        error_log('clockOutEmployee error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Clock-out failed.'];
    }
}

function getGeofenceSettingsForStore($storeId) {
    $policyRaw = (string)getTimeclockSettingValue('geofence_policy', (int)$storeId, 'warn');
    $policy = in_array($policyRaw, ['warn', 'block'], true) ? $policyRaw : 'warn';
    return [
        'enabled' => getTimeclockSettingValue('geofence_enabled', (int)$storeId, '0') === '1',
        'lat' => (float)getTimeclockSettingValue('geofence_lat', (int)$storeId, '0'),
        'lng' => (float)getTimeclockSettingValue('geofence_lng', (int)$storeId, '0'),
        'radius_m' => max(5, (int)getTimeclockSettingValue('geofence_radius_m', (int)$storeId, '120')),
        'policy' => $policy,
        'allow_no_gps' => getTimeclockSettingValue('geofence_allow_no_gps', (int)$storeId, '1') === '1',
        'kiosk_idle_seconds' => max(30, min(600, (int)getTimeclockSettingValue('kiosk_idle_seconds', (int)$storeId, '75'))),
        'alert_open_failure_threshold' => max(1, min(100, (int)getTimeclockSettingValue('kiosk_alert_open_failure_threshold', (int)$storeId, '3'))),
        'alert_stale_minutes' => max(5, min(1440, (int)getTimeclockSettingValue('kiosk_alert_stale_minutes', (int)$storeId, '60'))),
        'no_show_grace_minutes' => max(0, min(180, (int)getTimeclockSettingValue('no_show_grace_minutes', (int)$storeId, '15'))),
    ];
}

function getDefaultStoreOperatingHoursMap() {
    return [
        'mon' => ['enabled' => true, 'open' => '09:00', 'close' => '21:00'],
        'tue' => ['enabled' => true, 'open' => '09:00', 'close' => '21:00'],
        'wed' => ['enabled' => true, 'open' => '09:00', 'close' => '21:00'],
        'thu' => ['enabled' => true, 'open' => '09:00', 'close' => '21:00'],
        'fri' => ['enabled' => true, 'open' => '09:00', 'close' => '21:00'],
        'sat' => ['enabled' => true, 'open' => '09:00', 'close' => '21:00'],
        'sun' => ['enabled' => true, 'open' => '09:00', 'close' => '21:00'],
    ];
}

function normalizeStoreOperatingHoursMap($rawMap) {
    $defaults = getDefaultStoreOperatingHoursMap();
    $out = [];
    foreach ($defaults as $dow => $def) {
        $src = (is_array($rawMap) && isset($rawMap[$dow]) && is_array($rawMap[$dow])) ? $rawMap[$dow] : [];
        $open = isset($src['open']) && preg_match('/^\d{2}:\d{2}$/', (string)$src['open']) ? (string)$src['open'] : $def['open'];
        $close = isset($src['close']) && preg_match('/^\d{2}:\d{2}$/', (string)$src['close']) ? (string)$src['close'] : $def['close'];
        $enabled = array_key_exists('enabled', $src) ? !empty($src['enabled']) : !empty($def['enabled']);
        $out[$dow] = [
            'enabled' => (bool)$enabled,
            'open' => $open,
            'close' => $close,
        ];
    }
    return $out;
}

function getStoreOperatingHoursMap($storeId) {
    $raw = getTimeclockSettingValue('store_operating_hours_json', (int)$storeId, null);
    if (!is_string($raw) || trim($raw) === '') {
        $raw = getTimeclockSettingValue('store_operating_hours_json', null, '');
    }
    $parsed = null;
    if (is_string($raw) && trim($raw) !== '') {
        try {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $parsed = $decoded;
            }
        } catch (Throwable $e) {
            $parsed = null;
        }
    }
    return normalizeStoreOperatingHoursMap($parsed);
}

function buildOperatingHoursByDate($startDateYmd, $endDateYmd, $operatingHoursMap) {
    $map = normalizeStoreOperatingHoursMap($operatingHoursMap);
    $dowKeys = [1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat', 7 => 'sun'];
    $out = [];
    try {
        $cursor = new DateTime((string)$startDateYmd, new DateTimeZone(TIMEZONE));
        $end = new DateTime((string)$endDateYmd, new DateTimeZone(TIMEZONE));
        while ($cursor <= $end) {
            $dow = (int)$cursor->format('N');
            $key = $dowKeys[$dow] ?? 'mon';
            $out[$cursor->format('Y-m-d')] = $map[$key] ?? ['enabled' => true, 'open' => '09:00', 'close' => '21:00'];
            $cursor->modify('+1 day');
        }
    } catch (Throwable $e) {
        return [];
    }
    return $out;
}

function getOperatingHoursForDate($storeId, $dateYmd) {
    $map = getStoreOperatingHoursMap((int)$storeId);
    $dowKeys = [1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat', 7 => 'sun'];
    try {
        $d = new DateTime((string)$dateYmd, new DateTimeZone(TIMEZONE));
        $key = $dowKeys[(int)$d->format('N')] ?? 'mon';
        return $map[$key] ?? ['enabled' => true, 'open' => '09:00', 'close' => '21:00'];
    } catch (Throwable $e) {
        return ['enabled' => true, 'open' => '09:00', 'close' => '21:00'];
    }
}

function haversineMeters($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371000.0;
    $dLat = deg2rad((float)$lat2 - (float)$lat1);
    $dLng = deg2rad((float)$lng2 - (float)$lng1);
    $a = sin($dLat / 2) * sin($dLat / 2)
        + cos(deg2rad((float)$lat1)) * cos(deg2rad((float)$lat2))
        * sin($dLng / 2) * sin($dLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    return $earthRadius * $c;
}

function evaluateGeofenceForPunch($storeId, array $meta) {
    $settings = getGeofenceSettingsForStore((int)$storeId);
    if (empty($settings['enabled'])) {
        return ['blocked' => false, 'geofence_pass' => null, 'geofence_distance_m' => null, 'message' => null];
    }
    $siteLat = (float)($settings['lat'] ?? 0);
    $siteLng = (float)($settings['lng'] ?? 0);
    if (abs($siteLat) < 0.000001 && abs($siteLng) < 0.000001) {
        return ['blocked' => false, 'geofence_pass' => null, 'geofence_distance_m' => null, 'message' => null];
    }

    $hasGps = !empty($meta['gps_captured']) && isset($meta['gps_lat']) && isset($meta['gps_lng']);
    if (!$hasGps) {
        if (!empty($settings['allow_no_gps'])) {
            return ['blocked' => false, 'geofence_pass' => null, 'geofence_distance_m' => null, 'message' => 'GPS unavailable (allowed by policy).'];
        }
        return ['blocked' => true, 'geofence_pass' => false, 'geofence_distance_m' => null, 'message' => 'Punch blocked: GPS is required at this store.'];
    }

    $distance = haversineMeters((float)$meta['gps_lat'], (float)$meta['gps_lng'], $siteLat, $siteLng);
    $pass = $distance <= (float)($settings['radius_m'] ?? 120);
    if ($pass) {
        return ['blocked' => false, 'geofence_pass' => true, 'geofence_distance_m' => round($distance, 2), 'message' => null];
    }
    if (($settings['policy'] ?? 'warn') === 'block') {
        return [
            'blocked' => true,
            'geofence_pass' => false,
            'geofence_distance_m' => round($distance, 2),
            'message' => 'Punch blocked: outside store geofence (' . (int)round($distance) . 'm).'
        ];
    }
    return [
        'blocked' => false,
        'geofence_pass' => false,
        'geofence_distance_m' => round($distance, 2),
        'message' => 'Warning: outside store geofence (' . (int)round($distance) . 'm).'
    ];
}

function formatUtcTimestampForDisplay($utcTs, $timezone = TIMEZONE) {
    if (empty($utcTs)) {
        return '-';
    }
    try {
        $dt = new DateTime($utcTs, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone($timezone));
        return $dt->format('M j, g:i A');
    } catch (Throwable $e) {
        return (string)$utcTs;
    }
}

function parseLocalDateTimeToUtc($localDateTime) {
    if (empty($localDateTime)) {
        return null;
    }
    try {
        $local = new DateTime($localDateTime, new DateTimeZone(TIMEZONE));
        $local->setTimezone(new DateTimeZone('UTC'));
        return $local->format('Y-m-d H:i:sP');
    } catch (Throwable $e) {
        return null;
    }
}

function logTimeclockAudit($storeId, $employeeId, $actorName, $actionType, $details = null) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO timeclock_audit_log
                (store_id, employee_id, actor_name, action_type, details_json)
            VALUES
                (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $storeId ? (int)$storeId : null,
            $employeeId ? (int)$employeeId : null,
            (string)$actorName,
            (string)$actionType,
            $details ? json_encode($details) : null
        ]);
    } catch (Throwable $e) {
        error_log('logTimeclockAudit error: ' . $e->getMessage());
        return false;
    }
}

function createPunchEditRequest($data) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO timeclock_edit_requests
                (employee_id, store_id, shift_id, request_type, requested_clock_in_utc, requested_clock_out_utc, reason, status)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, 'PENDING')
        ");
        $ok = $stmt->execute([
            (int)$data['employee_id'],
            (int)$data['store_id'],
            !empty($data['shift_id']) ? (int)$data['shift_id'] : null,
            (string)$data['request_type'],
            $data['requested_clock_in_utc'] ?? null,
            $data['requested_clock_out_utc'] ?? null,
            (string)$data['reason']
        ]);
        if ($ok) {
            logTimeclockAudit(
                (int)$data['store_id'],
                (int)$data['employee_id'],
                (string)($data['employee_name'] ?? 'employee'),
                'EDIT_REQUEST_SUBMITTED',
                [
                    'request_type' => (string)$data['request_type'],
                    'shift_id' => !empty($data['shift_id']) ? (int)$data['shift_id'] : null
                ]
            );
        }
        return $ok;
    } catch (Throwable $e) {
        error_log('createPunchEditRequest error: ' . $e->getMessage());
        return false;
    }
}

function getPendingPunchEditRequestsByStore($storeId) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT r.*, e.full_name
            FROM timeclock_edit_requests r
            INNER JOIN employees e ON e.id = r.employee_id
            WHERE r.store_id = ? AND r.status = 'PENDING'
            ORDER BY r.submitted_at ASC
        ");
        $stmt->execute([(int)$storeId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('getPendingPunchEditRequestsByStore error: ' . $e->getMessage());
        return [];
    }
}

function getRecentPunchEditRequestsByStore($storeId, $limit = 20) {
    try {
        $pdo = getDB();
        $safeLimit = max(1, min(100, (int)$limit));
        $stmt = $pdo->prepare("
            SELECT r.*, e.full_name
            FROM timeclock_edit_requests r
            INNER JOIN employees e ON e.id = r.employee_id
            WHERE r.store_id = ?
            ORDER BY r.submitted_at DESC
            LIMIT $safeLimit
        ");
        $stmt->execute([(int)$storeId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('getRecentPunchEditRequestsByStore error: ' . $e->getMessage());
        return [];
    }
}

function getRecentTimeclockAuditByStore($storeId, $limit = 20) {
    try {
        $pdo = getDB();
        $safeLimit = max(1, min(100, (int)$limit));
        $stmt = $pdo->prepare("
            SELECT created_at, actor_name, action_type, details_json
            FROM timeclock_audit_log
            WHERE store_id = ?
            ORDER BY created_at DESC
            LIMIT $safeLimit
        ");
        $stmt->execute([(int)$storeId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('getRecentTimeclockAuditByStore error: ' . $e->getMessage());
        return [];
    }
}

function logKioskSyncAttempt($storeId, $employeeId, $deviceId, $punchType, $syncStatus, $resultMessage = null, $payload = null) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO timeclock_kiosk_sync_log
                (store_id, employee_id, device_id, punch_type, sync_status, result_message, payload_json)
            VALUES
                (?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            (int)$storeId,
            $employeeId ? (int)$employeeId : null,
            trim((string)$deviceId) !== '' ? trim((string)$deviceId) : 'unknown',
            ($punchType === 'out') ? 'out' : 'in',
            ($syncStatus === 'success') ? 'success' : 'failed',
            $resultMessage !== null ? (string)$resultMessage : null,
            $payload ? json_encode($payload) : null
        ]);
    } catch (Throwable $e) {
        error_log('logKioskSyncAttempt error: ' . $e->getMessage());
        return false;
    }
}

function getRecentKioskSyncLogsByStore($storeId, $limit = 100) {
    try {
        $pdo = getDB();
        $safeLimit = max(1, min(500, (int)$limit));
        $stmt = $pdo->prepare("
            SELECT k.id, k.created_at, k.device_id, k.punch_type, k.sync_status, k.result_message, k.payload_json,
                   k.resolution_status, k.resolved_at, k.resolved_by, k.resolution_note, e.full_name
            FROM timeclock_kiosk_sync_log k
            LEFT JOIN employees e ON e.id = k.employee_id
            WHERE k.store_id = ?
            ORDER BY k.created_at DESC
            LIMIT $safeLimit
        ");
        $stmt->execute([(int)$storeId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('getRecentKioskSyncLogsByStore error: ' . $e->getMessage());
        return [];
    }
}

function getKioskSyncDeviceSummaryByStore($storeId, $limit = 20) {
    try {
        $pdo = getDB();
        $safeLimit = max(1, min(100, (int)$limit));
        $stmt = $pdo->prepare("
            SELECT
                device_id,
                COUNT(*) AS total_attempts,
                SUM(CASE WHEN sync_status = 'failed' THEN 1 ELSE 0 END) AS failed_attempts,
                SUM(CASE WHEN sync_status = 'failed' AND resolution_status = 'OPEN' THEN 1 ELSE 0 END) AS unresolved_failed_attempts,
                MAX(CASE WHEN sync_status = 'success' THEN created_at ELSE NULL END) AS last_success_at,
                MAX(CASE WHEN sync_status = 'failed' THEN created_at ELSE NULL END) AS last_failure_at,
                MAX(created_at) AS last_seen_at
            FROM timeclock_kiosk_sync_log
            WHERE store_id = ?
            GROUP BY device_id
            ORDER BY last_seen_at DESC
            LIMIT $safeLimit
        ");
        $stmt->execute([(int)$storeId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('getKioskSyncDeviceSummaryByStore error: ' . $e->getMessage());
        return [];
    }
}

function getOpenKioskSyncFailuresByStore($storeId, $limit = 100) {
    try {
        $pdo = getDB();
        $safeLimit = max(1, min(500, (int)$limit));
        $stmt = $pdo->prepare("
            SELECT k.id, k.created_at, k.device_id, k.punch_type, k.result_message, k.payload_json, e.full_name
            FROM timeclock_kiosk_sync_log k
            LEFT JOIN employees e ON e.id = k.employee_id
            WHERE k.store_id = ? AND k.sync_status = 'failed' AND k.resolution_status = 'OPEN'
            ORDER BY k.created_at DESC
            LIMIT $safeLimit
        ");
        $stmt->execute([(int)$storeId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('getOpenKioskSyncFailuresByStore error: ' . $e->getMessage());
        return [];
    }
}

function resolveKioskSyncFailure($logId, $storeId, $managerName, $resolutionStatus = 'RESOLVED', $note = null) {
    $resolutionStatus = strtoupper(trim((string)$resolutionStatus));
    if (!in_array($resolutionStatus, ['RESOLVED', 'IGNORED', 'OPEN'], true)) {
        return ['success' => false, 'message' => 'Invalid resolution status.'];
    }
    if (trim((string)$managerName) === '') {
        return ['success' => false, 'message' => 'Manager name required.'];
    }
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            UPDATE timeclock_kiosk_sync_log
            SET resolution_status = ?,
                resolved_at = CASE WHEN ? = 'OPEN' THEN NULL ELSE CURRENT_TIMESTAMP END,
                resolved_by = CASE WHEN ? = 'OPEN' THEN NULL ELSE ? END,
                resolution_note = ?
            WHERE id = ? AND store_id = ? AND sync_status = 'failed'
        ");
        $stmt->execute([
            $resolutionStatus,
            $resolutionStatus,
            $resolutionStatus,
            trim((string)$managerName),
            $note !== null ? trim((string)$note) : null,
            (int)$logId,
            (int)$storeId
        ]);
        if ($stmt->rowCount() <= 0) {
            return ['success' => false, 'message' => 'Kiosk sync failure row not found.'];
        }
        logTimeclockAudit((int)$storeId, null, trim((string)$managerName), 'KIOSK_SYNC_FAILURE_' . $resolutionStatus, [
            'sync_log_id' => (int)$logId,
            'note' => $note !== null ? trim((string)$note) : null
        ]);
        return ['success' => true, 'message' => 'Kiosk sync failure updated.'];
    } catch (Throwable $e) {
        error_log('resolveKioskSyncFailure error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update kiosk sync failure.'];
    }
}

function resolveAllOpenKioskSyncFailures($storeId, $managerName, $resolutionStatus = 'RESOLVED', $note = null) {
    $resolutionStatus = strtoupper(trim((string)$resolutionStatus));
    if (!in_array($resolutionStatus, ['RESOLVED', 'IGNORED'], true)) {
        return ['success' => false, 'message' => 'Invalid bulk resolution status.'];
    }
    if (trim((string)$managerName) === '') {
        return ['success' => false, 'message' => 'Manager name required.'];
    }
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            UPDATE timeclock_kiosk_sync_log
            SET resolution_status = ?,
                resolved_at = CURRENT_TIMESTAMP,
                resolved_by = ?,
                resolution_note = ?
            WHERE store_id = ? AND sync_status = 'failed' AND resolution_status = 'OPEN'
        ");
        $stmt->execute([
            $resolutionStatus,
            trim((string)$managerName),
            $note !== null ? trim((string)$note) : null,
            (int)$storeId
        ]);
        $count = (int)$stmt->rowCount();
        logTimeclockAudit((int)$storeId, null, trim((string)$managerName), 'KIOSK_SYNC_FAILURE_BULK_' . $resolutionStatus, [
            'updated_rows' => $count,
            'note' => $note !== null ? trim((string)$note) : null
        ]);
        return ['success' => true, 'count' => $count, 'message' => 'Updated ' . $count . ' kiosk sync failures.'];
    } catch (Throwable $e) {
        error_log('resolveAllOpenKioskSyncFailures error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update kiosk sync failures.'];
    }
}

function getTimeclockShiftById($shiftId, $storeId) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM time_shifts WHERE id = ? AND store_id = ? LIMIT 1");
        $stmt->execute([(int)$shiftId, (int)$storeId]);
        return $stmt->fetch();
    } catch (Throwable $e) {
        error_log('getTimeclockShiftById error: ' . $e->getMessage());
        return null;
    }
}

function processPunchEditRequest($requestId, $storeId, $decision, $managerName, $managerNote = null) {
    $decision = strtoupper((string)$decision);
    if (!in_array($decision, ['APPROVED', 'DENIED'], true)) {
        return ['success' => false, 'message' => 'Invalid decision.'];
    }
    if (trim((string)$managerName) === '') {
        return ['success' => false, 'message' => 'Manager name is required.'];
    }

    try {
        $pdo = getDB();
        $pdo->beginTransaction();

        $reqStmt = $pdo->prepare("
            SELECT r.*, e.full_name
            FROM timeclock_edit_requests r
            INNER JOIN employees e ON e.id = r.employee_id
            WHERE r.id = ? AND r.store_id = ?
            LIMIT 1
        ");
        $reqStmt->execute([(int)$requestId, (int)$storeId]);
        $request = $reqStmt->fetch(PDO::FETCH_ASSOC);
        if (!$request) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Request not found.'];
        }
        if (($request['status'] ?? '') !== 'PENDING') {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Request is already reviewed.'];
        }

        if ($decision === 'APPROVED') {
            $employeeId = (int)$request['employee_id'];
            $reqType = (string)$request['request_type'];
            $inUtc = $request['requested_clock_in_utc'] ?? null;
            $outUtc = $request['requested_clock_out_utc'] ?? null;

            if ($reqType === 'MISS_CLOCK_IN') {
                if (!$inUtc) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Missing requested clock-in time.'];
                }
                [$lockStartDate, $lockEndDate] = getLocalDateRangeFromUtc($inUtc, $outUtc);
                if ($lockStartDate && hasLockedPayrollPeriodOverlap((int)$storeId, $lockStartDate, $lockEndDate)) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Cannot approve time edit inside a locked payroll period. Unlock period first.'];
                }
                $insertShift = $pdo->prepare("
                    INSERT INTO time_shifts
                        (employee_id, store_id, clock_in_utc, clock_out_utc, clock_in_note, clock_out_note)
                    VALUES
                        (?, ?, ?, ?, ?, ?)
                    RETURNING id
                ");
                $insertShift->execute([
                    $employeeId,
                    (int)$storeId,
                    $inUtc,
                    $outUtc,
                    'Created from approved missed punch request',
                    $outUtc ? 'Created from approved missed punch request' : null
                ]);
                $createdShift = $insertShift->fetch(PDO::FETCH_ASSOC);
                $shiftId = isset($createdShift['id']) ? (int)$createdShift['id'] : null;
                if ($shiftId) {
                    createTimePunchEvent([
                        'employee_id' => $employeeId,
                        'store_id' => (int)$storeId,
                        'shift_id' => $shiftId,
                        'event_type' => 'EDIT_REQUEST',
                        'event_utc' => (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:sP'),
                        'note' => 'Approved MISS_CLOCK_IN request #' . (int)$requestId,
                        'created_by' => 'manager'
                    ]);
                }
            } elseif ($reqType === 'MISS_CLOCK_OUT') {
                if (!$outUtc) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Missing requested clock-out time.'];
                }
                $shiftId = !empty($request['shift_id']) ? (int)$request['shift_id'] : null;
                $targetShift = $shiftId ? getTimeclockShiftById($shiftId, (int)$storeId) : getOpenShiftForEmployeeStore($employeeId, (int)$storeId);
                if (!$targetShift) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'No matching open shift to update for missed clock-out request.'];
                }
                if (!empty($targetShift['clock_out_utc'])) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Target shift is already clocked out. Use Adjust Shift request if you need a correction.'];
                }
                [$lockStartDate, $lockEndDate] = getLocalDateRangeFromUtc((string)($targetShift['clock_in_utc'] ?? null), $outUtc);
                if ($lockStartDate && hasLockedPayrollPeriodOverlap((int)$storeId, $lockStartDate, $lockEndDate)) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Cannot approve time edit inside a locked payroll period. Unlock period first.'];
                }
                $updateShift = $pdo->prepare("
                    UPDATE time_shifts
                    SET clock_out_utc = ?, clock_out_note = ?
                    WHERE id = ?
                ");
                $updateShift->execute([
                    $outUtc,
                    'Set from approved missed punch request',
                    (int)$targetShift['id']
                ]);
                createTimePunchEvent([
                    'employee_id' => $employeeId,
                    'store_id' => (int)$storeId,
                    'shift_id' => (int)$targetShift['id'],
                    'event_type' => 'EDIT_REQUEST',
                    'event_utc' => (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:sP'),
                    'note' => 'Approved MISS_CLOCK_OUT request #' . (int)$requestId,
                    'created_by' => 'manager'
                ]);
            } else {
                $shiftId = !empty($request['shift_id']) ? (int)$request['shift_id'] : null;
                if (!$shiftId) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Adjust-shift request requires a shift.'];
                }
                $targetShift = getTimeclockShiftById($shiftId, (int)$storeId);
                if (!$targetShift) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Shift not found for adjustment.'];
                }
                $effectiveInUtc = $inUtc !== null ? $inUtc : ($targetShift['clock_in_utc'] ?? null);
                $effectiveOutUtc = $outUtc !== null ? $outUtc : ($targetShift['clock_out_utc'] ?? null);
                [$lockStartDate, $lockEndDate] = getLocalDateRangeFromUtc($effectiveInUtc, $effectiveOutUtc);
                if ($lockStartDate && hasLockedPayrollPeriodOverlap((int)$storeId, $lockStartDate, $lockEndDate)) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Cannot approve time edit inside a locked payroll period. Unlock period first.'];
                }
                $updateShift = $pdo->prepare("
                    UPDATE time_shifts
                    SET clock_in_utc = COALESCE(?, clock_in_utc),
                        clock_out_utc = COALESCE(?, clock_out_utc),
                        clock_in_note = CASE WHEN ?::boolean THEN 'Adjusted from approved request' ELSE clock_in_note END,
                        clock_out_note = CASE WHEN ?::boolean THEN 'Adjusted from approved request' ELSE clock_out_note END
                    WHERE id = ?
                ");
                $updateShift->execute([
                    $inUtc,
                    $outUtc,
                    $inUtc !== null,
                    $outUtc !== null,
                    $shiftId
                ]);
                createTimePunchEvent([
                    'employee_id' => $employeeId,
                    'store_id' => (int)$storeId,
                    'shift_id' => $shiftId,
                    'event_type' => 'EDIT_REQUEST',
                    'event_utc' => (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:sP'),
                    'note' => 'Approved ADJUST_SHIFT request #' . (int)$requestId,
                    'created_by' => 'manager'
                ]);
            }
        }

        $reviewStmt = $pdo->prepare("
            UPDATE timeclock_edit_requests
            SET status = ?, reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ?, manager_note = ?
            WHERE id = ?
        ");
        $reviewStmt->execute([
            $decision,
            trim((string)$managerName),
            $managerNote ? trim((string)$managerNote) : null,
            (int)$requestId
        ]);

        logTimeclockAudit(
            (int)$storeId,
            (int)$request['employee_id'],
            trim((string)$managerName),
            'EDIT_REQUEST_' . $decision,
            [
                'request_id' => (int)$requestId,
                'request_type' => $request['request_type'],
                'manager_note' => $managerNote
            ]
        );

        $pdo->commit();
        return ['success' => true, 'message' => $decision === 'APPROVED' ? 'Request approved.' : 'Request denied.'];
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('processPunchEditRequest error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to process request.'];
    }
}

function getWeekRangeForDate($dateYmd) {
    try {
        $d = new DateTime($dateYmd, new DateTimeZone(TIMEZONE));
    } catch (Throwable $e) {
        $d = new DateTime('now', new DateTimeZone(TIMEZONE));
    }
    $dayOfWeek = (int)$d->format('w'); // 0 sun..6 sat
    $mondayOffset = $dayOfWeek === 0 ? -6 : (1 - $dayOfWeek);
    $monday = clone $d;
    $monday->modify($mondayOffset . ' days');
    $sunday = clone $monday;
    $sunday->modify('+6 days');
    return [
        'start' => $monday->format('Y-m-d'),
        'end' => $sunday->format('Y-m-d')
    ];
}

function getScheduleShiftsForStoreWeek($storeId, $weekStartYmd, $weekEndYmd) {
    try {
        $pdo = getDB();
        $weekStartUtc = (new DateTime($weekStartYmd . ' 00:00:00', new DateTimeZone(TIMEZONE)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:sP');
        $weekEndUtc = (new DateTime($weekEndYmd . ' 23:59:59', new DateTimeZone(TIMEZONE)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:sP');
        $stmt = $pdo->prepare("
            SELECT s.*, e.full_name
            FROM timeclock_schedule_shifts s
            INNER JOIN employees e ON e.id = s.employee_id
            WHERE s.store_id = ?
              AND s.start_utc <= ?
              AND s.end_utc >= ?
            ORDER BY s.start_utc ASC
        ");
        $stmt->execute([(int)$storeId, $weekEndUtc, $weekStartUtc]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('getScheduleShiftsForStoreWeek error: ' . $e->getMessage());
        return [];
    }
}

function getScheduleShiftsForStoreRange($storeId, $startDateYmd, $endDateYmd) {
    try {
        $pdo = getDB();
        $startUtc = (new DateTime($startDateYmd . ' 00:00:00', new DateTimeZone(TIMEZONE)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:sP');
        $endUtc = (new DateTime($endDateYmd . ' 23:59:59', new DateTimeZone(TIMEZONE)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:sP');
        $stmt = $pdo->prepare("
            SELECT s.*, e.full_name
            FROM timeclock_schedule_shifts s
            INNER JOIN employees e ON e.id = s.employee_id
            WHERE s.store_id = ?
              AND s.start_utc <= ?
              AND s.end_utc >= ?
            ORDER BY s.start_utc ASC
        ");
        $stmt->execute([(int)$storeId, $endUtc, $startUtc]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('getScheduleShiftsForStoreRange error: ' . $e->getMessage());
        return [];
    }
}

function getMissedClockInAlertsForStoreDate($storeId, $dateYmd, $graceMinutes = 15, $limit = 100) {
    try {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$dateYmd)) {
            return [];
        }
        $safeLimit = max(1, min(500, (int)$limit));
        $safeGrace = max(0, min(180, (int)$graceMinutes));
        $rows = getScheduleShiftsForStoreRange((int)$storeId, (string)$dateYmd, (string)$dateYmd);
        if (empty($rows)) {
            return [];
        }
        $tz = new DateTimeZone(TIMEZONE);
        $nowUtc = new DateTime('now', new DateTimeZone('UTC'));
        $alerts = [];
        foreach ($rows as $shift) {
            if (count($alerts) >= $safeLimit) {
                break;
            }
            $empId = (int)($shift['employee_id'] ?? 0);
            $startUtc = (string)($shift['start_utc'] ?? '');
            $endUtc = (string)($shift['end_utc'] ?? '');
            if ($empId <= 0 || $startUtc === '' || $endUtc === '') {
                continue;
            }
            try {
                $startLocal = new DateTime($startUtc, new DateTimeZone('UTC'));
                $endLocal = new DateTime($endUtc, new DateTimeZone('UTC'));
                $startLocal->setTimezone($tz);
                $endLocal->setTimezone($tz);
            } catch (Throwable $inner) {
                continue;
            }
            // Missed clock-in alert is tied to shifts that start on this selected date.
            if ($startLocal->format('Y-m-d') !== (string)$dateYmd) {
                continue;
            }
            $graceLocal = clone $startLocal;
            if ($safeGrace > 0) {
                $graceLocal->modify('+' . $safeGrace . ' minutes');
            }
            $graceUtc = clone $graceLocal;
            $graceUtc->setTimezone(new DateTimeZone('UTC'));
            if ($nowUtc < $graceUtc) {
                continue; // still inside grace period
            }
            if (hasApprovedPtoOverlapForEmployee($empId, (string)$dateYmd, (string)$dateYmd)) {
                continue;
            }
            if (hasWorkedShiftOverlap($empId, (int)$storeId, $startUtc, $endUtc)) {
                continue;
            }
            $alerts[] = [
                'employee_id' => $empId,
                'full_name' => (string)($shift['full_name'] ?? ('Employee #' . $empId)),
                'role_name' => (string)($shift['role_name'] ?? 'Employee'),
                'shift_id' => (int)($shift['id'] ?? 0),
                'scheduled_start_local' => $startLocal->format('M j, g:i A'),
                'scheduled_end_local' => $endLocal->format('M j, g:i A'),
                'minutes_late' => max(0, (int)floor(($nowUtc->getTimestamp() - $graceUtc->getTimestamp()) / 60)),
            ];
        }
        return $alerts;
    } catch (Throwable $e) {
        error_log('getMissedClockInAlertsForStoreDate error: ' . $e->getMessage());
        return [];
    }
}

function getApprovedPtoRequestsByStoreRange($storeId, $startDateYmd, $endDateYmd, $limit = 400) {
    try {
        $safeLimit = max(1, min(1000, (int)$limit));
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT r.id, r.employee_id, r.request_start_date, r.request_end_date, r.requested_minutes, e.full_name
            FROM timeclock_pto_requests r
            INNER JOIN employees e ON e.id = r.employee_id
            WHERE r.store_id = ?
              AND r.status = 'APPROVED'
              AND r.request_start_date <= ?
              AND r.request_end_date >= ?
            ORDER BY r.request_start_date ASC
            LIMIT $safeLimit
        ");
        $stmt->execute([(int)$storeId, (string)$endDateYmd, (string)$startDateYmd]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('getApprovedPtoRequestsByStoreRange error: ' . $e->getMessage());
        return [];
    }
}

function getWorkedShiftsByStoreRange($storeId, $startDateYmd, $endDateYmd, $limit = 1000) {
    try {
        $safeLimit = max(1, min(3000, (int)$limit));
        $pdo = getDB();
        $startUtc = (new DateTime($startDateYmd . ' 00:00:00', new DateTimeZone(TIMEZONE)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:sP');
        $endUtc = (new DateTime($endDateYmd . ' 23:59:59', new DateTimeZone(TIMEZONE)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:sP');
        $stmt = $pdo->prepare("
            SELECT ts.id, ts.employee_id, ts.clock_in_utc, ts.clock_out_utc, e.full_name
            FROM time_shifts ts
            INNER JOIN employees e ON e.id = ts.employee_id
            WHERE ts.store_id = ?
              AND ts.clock_in_utc <= ?
              AND COALESCE(ts.clock_out_utc, ts.clock_in_utc) >= ?
            ORDER BY ts.clock_in_utc DESC
            LIMIT $safeLimit
        ");
        $stmt->execute([(int)$storeId, $endUtc, $startUtc]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('getWorkedShiftsByStoreRange error: ' . $e->getMessage());
        return [];
    }
}

function getScheduleWeekStatus($storeId, $weekStartYmd, $weekEndYmd) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT *
            FROM timeclock_schedule_weeks
            WHERE store_id = ? AND week_start_date = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$storeId, $weekStartYmd]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
        return [
            'store_id' => (int)$storeId,
            'week_start_date' => $weekStartYmd,
            'week_end_date' => $weekEndYmd,
            'status' => 'DRAFT',
            'published_at' => null,
            'published_by' => null
        ];
    } catch (Throwable $e) {
        error_log('getScheduleWeekStatus error: ' . $e->getMessage());
        return null;
    }
}

function detectScheduleOverlap($employeeId, $storeId, $startUtc, $endUtc) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT id
            FROM timeclock_schedule_shifts
            WHERE employee_id = ?
              AND store_id = ?
              AND start_utc < ?
              AND end_utc > ?
            LIMIT 1
        ");
        $stmt->execute([(int)$employeeId, (int)$storeId, $endUtc, $startUtc]);
        return (bool)$stmt->fetch();
    } catch (Throwable $e) {
        error_log('detectScheduleOverlap error: ' . $e->getMessage());
        return false;
    }
}

function detectScheduleOverlapExcludingShift($employeeId, $storeId, $startUtc, $endUtc, $excludeShiftId = 0) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT id
            FROM timeclock_schedule_shifts
            WHERE employee_id = ?
              AND store_id = ?
              AND id <> ?
              AND start_utc < ?
              AND end_utc > ?
            LIMIT 1
        ");
        $stmt->execute([(int)$employeeId, (int)$storeId, (int)$excludeShiftId, $endUtc, $startUtc]);
        return (bool)$stmt->fetch();
    } catch (Throwable $e) {
        error_log('detectScheduleOverlapExcludingShift error: ' . $e->getMessage());
        return false;
    }
}

function detectScheduleOverlapAnyStore($employeeId, $startUtc, $endUtc) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT id
            FROM timeclock_schedule_shifts
            WHERE employee_id = ?
              AND start_utc < ?
              AND end_utc > ?
            LIMIT 1
        ");
        $stmt->execute([(int)$employeeId, $endUtc, $startUtc]);
        return (bool)$stmt->fetch();
    } catch (Throwable $e) {
        error_log('detectScheduleOverlapAnyStore error: ' . $e->getMessage());
        return false;
    }
}

function detectScheduleOverlapAnyStoreExcludingShift($employeeId, $startUtc, $endUtc, $excludeShiftId = 0) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT id
            FROM timeclock_schedule_shifts
            WHERE employee_id = ?
              AND id <> ?
              AND start_utc < ?
              AND end_utc > ?
            LIMIT 1
        ");
        $stmt->execute([(int)$employeeId, (int)$excludeShiftId, $endUtc, $startUtc]);
        return (bool)$stmt->fetch();
    } catch (Throwable $e) {
        error_log('detectScheduleOverlapAnyStoreExcludingShift error: ' . $e->getMessage());
        return false;
    }
}

function hasApprovedPtoOverlapForEmployee($employeeId, $startDateYmd, $endDateYmd) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT id
            FROM timeclock_pto_requests
            WHERE employee_id = ?
              AND status = 'APPROVED'
              AND request_start_date <= ?
              AND request_end_date >= ?
            LIMIT 1
        ");
        $stmt->execute([(int)$employeeId, (string)$endDateYmd, (string)$startDateYmd]);
        return (bool)$stmt->fetch();
    } catch (Throwable $e) {
        error_log('hasApprovedPtoOverlapForEmployee error: ' . $e->getMessage());
        return false;
    }
}

function getScheduleShiftById($shiftId, $storeId) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT s.*, e.full_name
            FROM timeclock_schedule_shifts s
            INNER JOIN employees e ON e.id = s.employee_id
            WHERE s.id = ? AND s.store_id = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$shiftId, (int)$storeId]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        error_log('getScheduleShiftById error: ' . $e->getMessage());
        return null;
    }
}

function addScheduleShift($data) {
    try {
        $employeeId = (int)$data['employee_id'];
        $storeId = (int)$data['store_id'];
        $startUtc = (string)$data['start_utc'];
        $endUtc = (string)$data['end_utc'];
        if ($employeeId <= 0 || $storeId <= 0 || $startUtc === '' || $endUtc === '') {
            return ['success' => false, 'message' => 'Missing required shift fields.'];
        }
        if (strtotime($endUtc) <= strtotime($startUtc)) {
            return ['success' => false, 'message' => 'Shift end must be after shift start.'];
        }
        if (detectScheduleOverlapAnyStore($employeeId, $startUtc, $endUtc)) {
            return ['success' => false, 'message' => 'Shift conflicts with this employee schedule at another store/time.'];
        }
        $tz = new DateTimeZone(TIMEZONE);
        $startLocal = new DateTime($startUtc, new DateTimeZone('UTC'));
        $endLocal = new DateTime($endUtc, new DateTimeZone('UTC'));
        $startLocal->setTimezone($tz);
        $endLocal->setTimezone($tz);
        $startDateYmd = $startLocal->format('Y-m-d');
        $endDateYmd = $endLocal->format('Y-m-d');
        if (hasApprovedPtoOverlapForEmployee($employeeId, $startDateYmd, $endDateYmd)) {
            return ['success' => false, 'message' => 'Employee has approved PTO/vacation during this date range.'];
        }
        if (hasLockedPayrollPeriodOverlap($storeId, $startDateYmd, $endDateYmd)) {
            return ['success' => false, 'message' => 'This schedule date range is inside a locked payroll period. Unlock period first.'];
        }

        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO timeclock_schedule_shifts
                (employee_id, store_id, role_name, start_utc, end_utc, break_minutes, status, last_modified_by, note)
            VALUES
                (?, ?, ?, ?, ?, ?, 'DRAFT', ?, ?)
            RETURNING id
        ");
        $ok = $stmt->execute([
            $employeeId,
            $storeId,
            $data['role_name'] ?? 'Employee',
            $startUtc,
            $endUtc,
            max(0, (int)($data['break_minutes'] ?? 0)),
            $data['manager_name'] ?? null,
            $data['note'] ?? null
        ]);
        $created = $ok ? $stmt->fetch() : null;
        $shiftId = (int)($created['id'] ?? 0);
        if ($ok) {
            logTimeclockAudit(
                $storeId,
                $employeeId,
                (string)($data['manager_name'] ?? 'manager'),
                'SCHEDULE_SHIFT_ADDED',
                [
                    'shift_id' => $shiftId,
                    'start_utc' => $startUtc,
                    'end_utc' => $endUtc
                ]
            );
            return ['success' => true, 'shift_id' => $shiftId, 'message' => 'Shift added to schedule.'];
        }
        return ['success' => false, 'message' => 'Unable to add shift.'];
    } catch (Throwable $e) {
        error_log('addScheduleShift error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Unable to add shift.'];
    }
}

function updateScheduleShift($shiftId, $data) {
    try {
        $shiftId = (int)$shiftId;
        $employeeId = (int)$data['employee_id'];
        $storeId = (int)$data['store_id'];
        $startUtc = (string)$data['start_utc'];
        $endUtc = (string)$data['end_utc'];
        if ($shiftId <= 0 || $employeeId <= 0 || $storeId <= 0 || $startUtc === '' || $endUtc === '') {
            return ['success' => false, 'message' => 'Missing required shift fields.'];
        }
        if (strtotime($endUtc) <= strtotime($startUtc)) {
            return ['success' => false, 'message' => 'Shift end must be after shift start.'];
        }
        $existing = getScheduleShiftById($shiftId, $storeId);
        if (!$existing) {
            return ['success' => false, 'message' => 'Shift not found.'];
        }
        if (detectScheduleOverlapAnyStoreExcludingShift($employeeId, $startUtc, $endUtc, $shiftId)) {
            return ['success' => false, 'message' => 'Shift conflicts with this employee schedule at another store/time.'];
        }
        $tz = new DateTimeZone(TIMEZONE);
        $startLocal = new DateTime($startUtc, new DateTimeZone('UTC'));
        $endLocal = new DateTime($endUtc, new DateTimeZone('UTC'));
        $startLocal->setTimezone($tz);
        $endLocal->setTimezone($tz);
        $startDateYmd = $startLocal->format('Y-m-d');
        $endDateYmd = $endLocal->format('Y-m-d');
        if (hasApprovedPtoOverlapForEmployee($employeeId, $startDateYmd, $endDateYmd)) {
            return ['success' => false, 'message' => 'Employee has approved PTO/vacation during this date range.'];
        }
        if (hasLockedPayrollPeriodOverlap($storeId, $startDateYmd, $endDateYmd)) {
            return ['success' => false, 'message' => 'This schedule date range is inside a locked payroll period. Unlock period first.'];
        }

        $pdo = getDB();
        $stmt = $pdo->prepare("
            UPDATE timeclock_schedule_shifts
            SET employee_id = ?, role_name = ?, start_utc = ?, end_utc = ?, break_minutes = ?, last_modified_by = ?, note = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND store_id = ?
        ");
        $ok = $stmt->execute([
            $employeeId,
            $data['role_name'] ?? 'Employee',
            $startUtc,
            $endUtc,
            max(0, (int)($data['break_minutes'] ?? 0)),
            $data['manager_name'] ?? null,
            $data['note'] ?? null,
            $shiftId,
            $storeId
        ]);
        if ($ok) {
            logTimeclockAudit(
                $storeId,
                $employeeId,
                (string)($data['manager_name'] ?? 'manager'),
                'SCHEDULE_SHIFT_UPDATED',
                ['shift_id' => $shiftId, 'start_utc' => $startUtc, 'end_utc' => $endUtc]
            );
            return ['success' => true, 'message' => 'Shift updated.'];
        }
        return ['success' => false, 'message' => 'Unable to update shift.'];
    } catch (Throwable $e) {
        error_log('updateScheduleShift error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Unable to update shift.'];
    }
}

function deleteScheduleShift($shiftId, $storeId, $managerName) {
    try {
        $shiftId = (int)$shiftId;
        $storeId = (int)$storeId;
        $managerName = trim((string)$managerName);
        if ($shiftId <= 0 || $storeId <= 0 || $managerName === '') {
            return ['success' => false, 'message' => 'Shift, store, and manager are required.'];
        }
        $existing = getScheduleShiftById($shiftId, $storeId);
        if (!$existing) {
            return ['success' => false, 'message' => 'Shift not found.'];
        }
        $tz = new DateTimeZone(TIMEZONE);
        $startLocal = new DateTime((string)$existing['start_utc'], new DateTimeZone('UTC'));
        $endLocal = new DateTime((string)$existing['end_utc'], new DateTimeZone('UTC'));
        $startLocal->setTimezone($tz);
        $endLocal->setTimezone($tz);
        $startDateYmd = $startLocal->format('Y-m-d');
        $endDateYmd = $endLocal->format('Y-m-d');
        if (hasLockedPayrollPeriodOverlap($storeId, $startDateYmd, $endDateYmd)) {
            return ['success' => false, 'message' => 'This shift is inside a locked payroll period. Unlock period first.'];
        }
        $pdo = getDB();
        $stmt = $pdo->prepare("DELETE FROM timeclock_schedule_shifts WHERE id = ? AND store_id = ?");
        $ok = $stmt->execute([$shiftId, $storeId]);
        if ($ok) {
            logTimeclockAudit(
                $storeId,
                (int)($existing['employee_id'] ?? 0),
                $managerName,
                'SCHEDULE_SHIFT_DELETED',
                ['shift_id' => $shiftId]
            );
            return ['success' => true, 'message' => 'Shift deleted.'];
        }
        return ['success' => false, 'message' => 'Unable to delete shift.'];
    } catch (Throwable $e) {
        error_log('deleteScheduleShift error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Unable to delete shift.'];
    }
}

function publishScheduleWeek($storeId, $weekStartYmd, $weekEndYmd, $managerName) {
    if (trim((string)$managerName) === '') {
        return ['success' => false, 'message' => 'Manager name is required to publish.'];
    }
    try {
        $pdo = getDB();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        $upsertWeek = $pdo->prepare("
            INSERT INTO timeclock_schedule_weeks
                (store_id, week_start_date, week_end_date, status, published_at, published_by, updated_at)
            VALUES
                (?, ?, ?, 'PUBLISHED', CURRENT_TIMESTAMP, ?, CURRENT_TIMESTAMP)
            ON CONFLICT (store_id, week_start_date)
            DO UPDATE SET
                week_end_date = EXCLUDED.week_end_date,
                status = 'PUBLISHED',
                published_at = CURRENT_TIMESTAMP,
                published_by = EXCLUDED.published_by,
                updated_at = CURRENT_TIMESTAMP
        ");
        $upsertWeek->execute([(int)$storeId, $weekStartYmd, $weekEndYmd, trim((string)$managerName)]);

        $weekStartUtc = (new DateTime($weekStartYmd . ' 00:00:00', new DateTimeZone(TIMEZONE)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:sP');
        $weekEndUtc = (new DateTime($weekEndYmd . ' 23:59:59', new DateTimeZone(TIMEZONE)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:sP');
        $markShifts = $pdo->prepare("
            UPDATE timeclock_schedule_shifts
            SET status = 'PUBLISHED', published_at = CURRENT_TIMESTAMP, last_modified_by = ?
            WHERE store_id = ?
              AND start_utc <= ?
              AND end_utc >= ?
        ");
        $markShifts->execute([trim((string)$managerName), (int)$storeId, $weekEndUtc, $weekStartUtc]);

        logTimeclockAudit(
            (int)$storeId,
            null,
            trim((string)$managerName),
            'SCHEDULE_WEEK_PUBLISHED',
            ['week_start' => $weekStartYmd, 'week_end' => $weekEndYmd]
        );
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->commit();
        }
        return ['success' => true, 'message' => 'Schedule week published.'];
    } catch (Throwable $e) {
        if (isset($pdo) && (!isset($ownsTransaction) || $ownsTransaction) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('publishScheduleWeek error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Unable to publish schedule week.'];
    }
}

function unpublishScheduleWeek($storeId, $weekStartYmd, $weekEndYmd, $managerName) {
    if (trim((string)$managerName) === '') {
        return ['success' => false, 'message' => 'Manager name is required to unpublish.'];
    }
    try {
        $pdo = getDB();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        $upsertWeek = $pdo->prepare("
            INSERT INTO timeclock_schedule_weeks
                (store_id, week_start_date, week_end_date, status, published_at, published_by, updated_at)
            VALUES
                (?, ?, ?, 'DRAFT', NULL, NULL, CURRENT_TIMESTAMP)
            ON CONFLICT (store_id, week_start_date)
            DO UPDATE SET
                week_end_date = EXCLUDED.week_end_date,
                status = 'DRAFT',
                published_at = NULL,
                published_by = NULL,
                updated_at = CURRENT_TIMESTAMP
        ");
        $upsertWeek->execute([(int)$storeId, $weekStartYmd, $weekEndYmd]);

        $weekStartUtc = (new DateTime($weekStartYmd . ' 00:00:00', new DateTimeZone(TIMEZONE)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:sP');
        $weekEndUtc = (new DateTime($weekEndYmd . ' 23:59:59', new DateTimeZone(TIMEZONE)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:sP');
        $markShifts = $pdo->prepare("
            UPDATE timeclock_schedule_shifts
            SET status = 'DRAFT', published_at = NULL, last_modified_by = ?
            WHERE store_id = ?
              AND start_utc <= ?
              AND end_utc >= ?
        ");
        $markShifts->execute([trim((string)$managerName), (int)$storeId, $weekEndUtc, $weekStartUtc]);

        logTimeclockAudit(
            (int)$storeId,
            null,
            trim((string)$managerName),
            'SCHEDULE_WEEK_UNPUBLISHED',
            ['week_start' => $weekStartYmd, 'week_end' => $weekEndYmd]
        );
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->commit();
        }
        return ['success' => true, 'message' => 'Schedule week moved to draft.'];
    } catch (Throwable $e) {
        if (isset($pdo) && (!isset($ownsTransaction) || $ownsTransaction) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('unpublishScheduleWeek error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Unable to unpublish schedule week.'];
    }
}

function copyScheduleFromPreviousWeek($storeId, $targetWeekStartYmd, $targetWeekEndYmd, $managerName) {
    if (trim((string)$managerName) === '') {
        return ['success' => false, 'message' => 'Manager name is required to copy schedule.'];
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$targetWeekStartYmd) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$targetWeekEndYmd)) {
        return ['success' => false, 'message' => 'Invalid week range.'];
    }
    try {
        $tz = new DateTimeZone(TIMEZONE);
        $targetWeekStart = new DateTime($targetWeekStartYmd . ' 00:00:00', $tz);
        $prevWeekStart = (clone $targetWeekStart)->modify('-7 days');
        $prevWeekEnd = (clone $prevWeekStart)->modify('+6 days');
        $prevStartYmd = $prevWeekStart->format('Y-m-d');
        $prevEndYmd = $prevWeekEnd->format('Y-m-d');

        $sourceShifts = getScheduleShiftsForStoreWeek((int)$storeId, $prevStartYmd, $prevEndYmd);
        if (empty($sourceShifts)) {
            return ['success' => false, 'message' => 'No shifts found in previous week to copy.'];
        }

        $added = 0;
        $skipped = 0;
        foreach ($sourceShifts as $src) {
            try {
                $srcStartLocal = new DateTime((string)$src['start_utc'], new DateTimeZone('UTC'));
                $srcEndLocal = new DateTime((string)$src['end_utc'], new DateTimeZone('UTC'));
                $srcStartLocal->setTimezone($tz);
                $srcEndLocal->setTimezone($tz);
                $srcStartDay = new DateTime($srcStartLocal->format('Y-m-d') . ' 00:00:00', $tz);
                $dayOffset = (int)$prevWeekStart->diff($srcStartDay)->format('%r%a');
                $targetDay = (clone $targetWeekStart)->modify(($dayOffset >= 0 ? '+' : '') . $dayOffset . ' days');

                $startLocalTs = $targetDay->format('Y-m-d') . ' ' . $srcStartLocal->format('H:i:s');
                $overnightDays = (int)$srcStartLocal->diff($srcEndLocal)->format('%a');
                $targetEndDay = clone $targetDay;
                if ($overnightDays > 0) {
                    $targetEndDay->modify('+' . $overnightDays . ' days');
                }
                $endLocalTs = $targetEndDay->format('Y-m-d') . ' ' . $srcEndLocal->format('H:i:s');
                $startUtc = parseLocalDateTimeToUtc($startLocalTs);
                $endUtc = parseLocalDateTimeToUtc($endLocalTs);
                if (!$startUtc || !$endUtc) {
                    $skipped++;
                    continue;
                }
                $result = addScheduleShift([
                    'employee_id' => (int)($src['employee_id'] ?? 0),
                    'store_id' => (int)$storeId,
                    'role_name' => (string)($src['role_name'] ?? 'Employee'),
                    'start_utc' => $startUtc,
                    'end_utc' => $endUtc,
                    'break_minutes' => (int)($src['break_minutes'] ?? 0),
                    'manager_name' => trim((string)$managerName),
                    'note' => 'COPIED_PREV_WEEK'
                ]);
                if (!empty($result['success'])) {
                    $added++;
                } else {
                    $skipped++;
                }
            } catch (Throwable $inner) {
                $skipped++;
            }
        }
        if ($added <= 0) {
            return ['success' => false, 'message' => 'No shifts copied (all skipped due to conflicts/locks).'];
        }
        return ['success' => true, 'added' => $added, 'skipped' => $skipped, 'message' => "Copied {$added} shifts from previous week. Skipped {$skipped}."];
    } catch (Throwable $e) {
        error_log('copyScheduleFromPreviousWeek error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Unable to copy previous week schedule.'];
    }
}

function buildScheduleCalendarData($scheduleShifts, $weekStartYmd, $weekEndYmd, $openHourStart = 9, $openHourEnd = 21, $openHoursByDate = null) {
    $days = [];
    $shiftsByDay = [];
    $employeeHours = [];

    try {
        $cursor = new DateTime($weekStartYmd, new DateTimeZone(TIMEZONE));
        $weekEnd = new DateTime($weekEndYmd, new DateTimeZone(TIMEZONE));
        while ($cursor <= $weekEnd) {
            $ymd = $cursor->format('Y-m-d');
            $days[] = [
                'date' => $ymd,
                'label' => $cursor->format('D M j')
            ];
            $shiftsByDay[$ymd] = [];
            $cursor->modify('+1 day');
        }
    } catch (Throwable $e) {
        return [
            'days' => [],
            'shifts_by_day' => [],
            'coverage_by_day' => [],
            'employee_hours' => []
        ];
    }

    $coverageByDay = [];
    foreach ($days as $day) {
        $coverageByDay[$day['date']] = [
            'required_minutes' => 0,
            'covered_minutes' => 0,
            'gap_minutes' => 0,
            'gap_windows' => []
        ];
    }

    $localShifts = [];
    foreach ($scheduleShifts as $shift) {
        try {
            $startLocal = new DateTime((string)$shift['start_utc'], new DateTimeZone('UTC'));
            $endLocal = new DateTime((string)$shift['end_utc'], new DateTimeZone('UTC'));
            $startLocal->setTimezone(new DateTimeZone(TIMEZONE));
            $endLocal->setTimezone(new DateTimeZone(TIMEZONE));
            if ($endLocal <= $startLocal) {
                continue;
            }

            $hours = (($endLocal->getTimestamp() - $startLocal->getTimestamp()) / 3600) - (((int)($shift['break_minutes'] ?? 0)) / 60);
            $hours = max(0, $hours);
            $empId = (int)$shift['employee_id'];
            $empName = (string)($shift['full_name'] ?? ('Employee #' . $empId));
            if (!isset($employeeHours[$empId])) {
                $employeeHours[$empId] = [
                    'employee_id' => $empId,
                    'employee_name' => $empName,
                    'hours' => 0,
                    'overtime_hours' => 0
                ];
            }
            $employeeHours[$empId]['hours'] += $hours;

            $localShifts[] = [
                'employee_id' => $empId,
                'employee_name' => $empName,
                'role_name' => (string)($shift['role_name'] ?? 'Employee'),
                'status' => (string)($shift['status'] ?? 'DRAFT'),
                'break_minutes' => (int)($shift['break_minutes'] ?? 0),
                'start_local' => $startLocal,
                'end_local' => $endLocal,
                'start_utc' => $shift['start_utc'],
                'end_utc' => $shift['end_utc']
            ];
        } catch (Throwable $e) {
            // ignore bad row
        }
    }

    foreach ($employeeHours as $empId => $row) {
        $ot = max(0, (float)$row['hours'] - 40.0);
        $employeeHours[$empId]['overtime_hours'] = $ot;
    }

    foreach ($days as $day) {
        $dayDate = $day['date'];
        $dayStart = new DateTime($dayDate . ' 00:00:00', new DateTimeZone(TIMEZONE));
        $dayEnd = new DateTime($dayDate . ' 23:59:59', new DateTimeZone(TIMEZONE));
        $openStartStr = str_pad((string)$openHourStart, 2, '0', STR_PAD_LEFT) . ':00';
        $openEndStr = str_pad((string)$openHourEnd, 2, '0', STR_PAD_LEFT) . ':00';
        $isOpen = true;
        if (is_array($openHoursByDate) && isset($openHoursByDate[$dayDate]) && is_array($openHoursByDate[$dayDate])) {
            $cfg = $openHoursByDate[$dayDate];
            $isOpen = !empty($cfg['enabled']);
            if (isset($cfg['open']) && preg_match('/^\d{2}:\d{2}$/', (string)$cfg['open'])) {
                $openStartStr = (string)$cfg['open'];
            }
            if (isset($cfg['close']) && preg_match('/^\d{2}:\d{2}$/', (string)$cfg['close'])) {
                $openEndStr = (string)$cfg['close'];
            }
        }
        if (!$isOpen) {
            $coverageByDay[$dayDate] = [
                'required_minutes' => 0,
                'covered_minutes' => 0,
                'gap_minutes' => 0,
                'gap_windows' => []
            ];
            continue;
        }
        $openStart = new DateTime($dayDate . ' ' . $openStartStr . ':00', new DateTimeZone(TIMEZONE));
        $openEnd = new DateTime($dayDate . ' ' . $openEndStr . ':00', new DateTimeZone(TIMEZONE));
        if ($openEnd <= $openStart) {
            $openEnd->modify('+1 day');
        }

        $intervals = [];
        foreach ($localShifts as $s) {
            $sStart = $s['start_local'];
            $sEnd = $s['end_local'];
            if ($sEnd < $dayStart || $sStart > $dayEnd) {
                continue;
            }

            $displayStart = clone $sStart;
            if ($displayStart < $dayStart) $displayStart = clone $dayStart;
            $displayEnd = clone $sEnd;
            if ($displayEnd > $dayEnd) $displayEnd = clone $dayEnd;

            $shiftsByDay[$dayDate][] = [
                'employee_name' => $s['employee_name'],
                'role_name' => $s['role_name'],
                'status' => $s['status'],
                'break_minutes' => $s['break_minutes'],
                'display_start' => $displayStart->format('g:i A'),
                'display_end' => $displayEnd->format('g:i A')
            ];

            $ovStartTs = max($sStart->getTimestamp(), $openStart->getTimestamp());
            $ovEndTs = min($sEnd->getTimestamp(), $openEnd->getTimestamp());
            if ($ovEndTs > $ovStartTs) {
                $intervals[] = [$ovStartTs, $ovEndTs];
            }
        }

        usort($intervals, function ($a, $b) { return $a[0] <=> $b[0]; });
        $merged = [];
        foreach ($intervals as $intv) {
            if (empty($merged) || $intv[0] > $merged[count($merged) - 1][1]) {
                $merged[] = $intv;
            } else {
                $last = count($merged) - 1;
                $merged[$last][1] = max($merged[$last][1], $intv[1]);
            }
        }

        $coveredMinutes = 0;
        foreach ($merged as $m) {
            $coveredMinutes += (int)round(($m[1] - $m[0]) / 60);
        }
        $requiredMinutes = max(0, (int)round(($openEnd->getTimestamp() - $openStart->getTimestamp()) / 60));
        $gapMinutes = max(0, $requiredMinutes - $coveredMinutes);

        $gapWindows = [];
        $cursorTs = $openStart->getTimestamp();
        foreach ($merged as $m) {
            if ($m[0] > $cursorTs) {
                $gapWindows[] = [
                    'start' => date('g:i A', $cursorTs),
                    'end' => date('g:i A', $m[0])
                ];
            }
            $cursorTs = max($cursorTs, $m[1]);
        }
        if ($cursorTs < $openEnd->getTimestamp()) {
            $gapWindows[] = [
                'start' => date('g:i A', $cursorTs),
                'end' => date('g:i A', $openEnd->getTimestamp())
            ];
        }

        $coverageByDay[$dayDate] = [
            'required_minutes' => $requiredMinutes,
            'covered_minutes' => $coveredMinutes,
            'gap_minutes' => $gapMinutes,
            'gap_windows' => $gapWindows
        ];
    }

    usort($employeeHours, function ($a, $b) {
        return strcmp($a['employee_name'], $b['employee_name']);
    });

    return [
        'days' => $days,
        'shifts_by_day' => $shiftsByDay,
        'coverage_by_day' => $coverageByDay,
        'employee_hours' => $employeeHours
    ];
}

function getTimeclockSettingValue($settingKey, $storeId = null, $defaultValue = null) {
    try {
        $pdo = getDB();
        if ($storeId !== null) {
            $stmt = $pdo->prepare("
                SELECT setting_value
                FROM timeclock_settings
                WHERE scope = 'store' AND store_id = ? AND setting_key = ?
                ORDER BY updated_at DESC, id DESC
                LIMIT 1
            ");
            $stmt->execute([(int)$storeId, (string)$settingKey]);
            $row = $stmt->fetch();
            if ($row && isset($row['setting_value'])) {
                return $row['setting_value'];
            }
        }
        $stmt = $pdo->prepare("
            SELECT setting_value
            FROM timeclock_settings
            WHERE scope = 'global' AND store_id IS NULL AND setting_key = ?
            ORDER BY updated_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([(string)$settingKey]);
        $row = $stmt->fetch();
        if ($row && isset($row['setting_value'])) {
            return $row['setting_value'];
        }
    } catch (Throwable $e) {
        error_log('getTimeclockSettingValue error: ' . $e->getMessage());
    }
    return $defaultValue;
}

function getTimeclockSettingsMap(array $settingKeys, $storeId = null) {
    $out = [];
    foreach ($settingKeys as $k) {
        $out[$k] = getTimeclockSettingValue($k, $storeId, null);
    }
    return $out;
}

function upsertTimeclockSetting($settingKey, $settingValue, $scope = 'global', $storeId = null) {
    try {
        $scope = ($scope === 'store') ? 'store' : 'global';
        $pdo = getDB();
        if ($scope === 'store') {
            $storeIdVal = (int)$storeId;
            $upd = $pdo->prepare("
                UPDATE timeclock_settings
                SET setting_value = ?, updated_at = CURRENT_TIMESTAMP
                WHERE scope = 'store' AND store_id = ? AND setting_key = ?
            ");
            $upd->execute([(string)$settingValue, $storeIdVal, (string)$settingKey]);
            if ($upd->rowCount() > 0) {
                return true;
            }
            $ins = $pdo->prepare("
                INSERT INTO timeclock_settings (scope, store_id, setting_key, setting_value, created_at, updated_at)
                VALUES ('store', ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            return $ins->execute([$storeIdVal, (string)$settingKey, (string)$settingValue]);
        }

        $upd = $pdo->prepare("
            UPDATE timeclock_settings
            SET setting_value = ?, updated_at = CURRENT_TIMESTAMP
            WHERE scope = 'global' AND store_id IS NULL AND setting_key = ?
        ");
        $upd->execute([(string)$settingValue, (string)$settingKey]);
        if ($upd->rowCount() > 0) {
            return true;
        }
        $ins = $pdo->prepare("
            INSERT INTO timeclock_settings (scope, store_id, setting_key, setting_value, created_at, updated_at)
            VALUES ('global', NULL, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        return $ins->execute([(string)$settingKey, (string)$settingValue]);
    } catch (Throwable $e) {
        error_log('upsertTimeclockSetting error: ' . $e->getMessage());
        return false;
    }
}

function getPayrollPeriodsByStore($storeId, $limit = 20) {
    try {
        $safeLimit = max(1, min(100, (int)$limit));
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT *
            FROM timeclock_payroll_periods
            WHERE store_id = ?
            ORDER BY period_start_date DESC
            LIMIT $safeLimit
        ");
        $stmt->execute([(int)$storeId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('getPayrollPeriodsByStore error: ' . $e->getMessage());
        return [];
    }
}

function createOrGetPayrollPeriod($storeId, $startDate, $endDate, $managerName) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$endDate)) {
        return ['success' => false, 'message' => 'Invalid payroll period dates.'];
    }
    if (trim((string)$managerName) === '') {
        return ['success' => false, 'message' => 'Manager name is required.'];
    }
    if ($startDate > $endDate) {
        return ['success' => false, 'message' => 'Payroll start must be before end.'];
    }
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO timeclock_payroll_periods
                (store_id, period_start_date, period_end_date, status, processed_by)
            VALUES
                (?, ?, ?, 'OPEN', ?)
            ON CONFLICT (store_id, period_start_date, period_end_date)
            DO UPDATE SET updated_at = CURRENT_TIMESTAMP
            WHERE timeclock_payroll_periods.status <> 'LOCKED'
            RETURNING id
        ");
        $stmt->execute([(int)$storeId, $startDate, $endDate, trim((string)$managerName)]);
        $row = $stmt->fetch();
        if (!$row || empty($row['id'])) {
            $existingLocked = $pdo->prepare("
                SELECT id
                FROM timeclock_payroll_periods
                WHERE store_id = ? AND period_start_date = ? AND period_end_date = ? AND status = 'LOCKED'
                LIMIT 1
            ");
            $existingLocked->execute([(int)$storeId, $startDate, $endDate]);
            if ($existingLocked->fetch()) {
                return ['success' => false, 'message' => 'Payroll period is locked and cannot be modified until unlocked.'];
            }
            return ['success' => false, 'message' => 'Could not save payroll period.'];
        }
        $periodId = (int)($row['id'] ?? 0);
        logTimeclockAudit((int)$storeId, null, trim((string)$managerName), 'PAYROLL_PERIOD_UPSERT', [
            'period_id' => $periodId,
            'start' => $startDate,
            'end' => $endDate
        ]);
        return ['success' => true, 'period_id' => $periodId, 'message' => 'Payroll period saved.'];
    } catch (Throwable $e) {
        error_log('createOrGetPayrollPeriod error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Could not save payroll period.'];
    }
}

function getPayrollPeriodById($periodId, $storeId) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT *
            FROM timeclock_payroll_periods
            WHERE id = ? AND store_id = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$periodId, (int)$storeId]);
        return $stmt->fetch();
    } catch (Throwable $e) {
        error_log('getPayrollPeriodById error: ' . $e->getMessage());
        return null;
    }
}

function getPayrollRunsByPeriod($periodId) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT r.*, e.full_name
            FROM timeclock_payroll_runs r
            INNER JOIN employees e ON e.id = r.employee_id
            WHERE r.payroll_period_id = ?
            ORDER BY e.full_name ASC
        ");
        $stmt->execute([(int)$periodId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('getPayrollRunsByPeriod error: ' . $e->getMessage());
        return [];
    }
}

function calculateWorkedMinutesInRange($shiftStartTs, $shiftEndTs, $rangeStartTs, $rangeEndTs, $breakMinutes = 0) {
    $ovStart = max((int)$shiftStartTs, (int)$rangeStartTs);
    $ovEnd = min((int)$shiftEndTs, (int)$rangeEndTs);
    if ($ovEnd <= $ovStart) return 0;
    $totalMinutes = (int)floor(($ovEnd - $ovStart) / 60);
    if ($totalMinutes <= 0) return 0;

    // Pro-rate break by overlap ratio.
    $fullShiftMinutes = max(1, (int)floor(($shiftEndTs - $shiftStartTs) / 60));
    $breakApplied = (int)round(max(0, (int)$breakMinutes) * ($totalMinutes / $fullShiftMinutes));
    return max(0, $totalMinutes - $breakApplied);
}

function getApprovedPtoMinutesForEmployeeInPeriod($employeeId, $storeId, $periodStartYmd, $periodEndYmd) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT request_start_date, request_end_date, requested_minutes
            FROM timeclock_pto_requests
            WHERE employee_id = ?
              AND store_id = ?
              AND status = 'APPROVED'
              AND request_start_date <= ?
              AND request_end_date >= ?
        ");
        $stmt->execute([(int)$employeeId, (int)$storeId, $periodEndYmd, $periodStartYmd]);
        $rows = $stmt->fetchAll();
        $total = 0;
        foreach ($rows as $row) {
            $reqStart = (string)$row['request_start_date'];
            $reqEnd = (string)$row['request_end_date'];
            $reqMinutes = max(0, (int)$row['requested_minutes']);
            if ($reqMinutes <= 0) continue;

            $s = max($periodStartYmd, $reqStart);
            $e = min($periodEndYmd, $reqEnd);
            if ($s > $e) continue;

            $reqDays = ((new DateTime($reqEnd))->diff(new DateTime($reqStart)))->days + 1;
            $ovDays = ((new DateTime($e))->diff(new DateTime($s)))->days + 1;
            if ($reqDays <= 0 || $ovDays <= 0) continue;
            $total += (int)round($reqMinutes * ($ovDays / $reqDays));
        }
        return max(0, $total);
    } catch (Throwable $e) {
        error_log('getApprovedPtoMinutesForEmployeeInPeriod error: ' . $e->getMessage());
        return 0;
    }
}

function runPayrollForPeriod($periodId, $storeId, $managerName) {
    if (trim((string)$managerName) === '') {
        return ['success' => false, 'message' => 'Manager name is required.'];
    }
    $period = getPayrollPeriodById($periodId, $storeId);
    if (!$period) {
        return ['success' => false, 'message' => 'Payroll period not found.'];
    }
    if (($period['status'] ?? '') === 'LOCKED') {
        return ['success' => false, 'message' => 'Payroll period is locked.'];
    }

    try {
        $tz = new DateTimeZone(TIMEZONE);
        $periodStartLocal = new DateTime($period['period_start_date'] . ' 00:00:00', $tz);
        $periodEndLocal = new DateTime($period['period_end_date'] . ' 23:59:59', $tz);
        $periodStartUtc = clone $periodStartLocal;
        $periodStartUtc->setTimezone(new DateTimeZone('UTC'));
        $periodEndUtc = clone $periodEndLocal;
        $periodEndUtc->setTimezone(new DateTimeZone('UTC'));

        $otMultiplier = (float)getTimeclockSettingValue('ot_multiplier', (int)$storeId, '1.5');
        if ($otMultiplier <= 1) $otMultiplier = 1.5;
        $defaultBurdenPct = (float)getTimeclockSettingValue('default_burden_percent', (int)$storeId, '12');
        if ($defaultBurdenPct < 0) $defaultBurdenPct = 0;

        $pdo = getDB();
        $shiftStmt = $pdo->prepare("
            SELECT s.employee_id, s.clock_in_utc, s.clock_out_utc, 0 AS break_minutes,
                   e.full_name, e.hourly_rate_cents, e.burden_percent
            FROM time_shifts s
            INNER JOIN employees e ON e.id = s.employee_id
            WHERE s.store_id = ?
              AND s.clock_out_utc IS NOT NULL
              AND s.clock_in_utc <= ?
              AND s.clock_out_utc >= ?
        ");
        $shiftStmt->execute([(int)$storeId, $periodEndUtc->format('Y-m-d H:i:sP'), $periodStartUtc->format('Y-m-d H:i:sP')]);
        $shifts = $shiftStmt->fetchAll();

        $weeklyMinutesByEmployee = [];
        $employeeMeta = [];
        foreach ($shifts as $shift) {
            $empId = (int)$shift['employee_id'];
            if (!isset($employeeMeta[$empId])) {
                $employeeMeta[$empId] = [
                    'name' => $shift['full_name'],
                    'hourly_rate_cents' => (int)$shift['hourly_rate_cents'],
                    'burden_percent' => (float)$shift['burden_percent']
                ];
            }

            $shiftStart = new DateTime((string)$shift['clock_in_utc'], new DateTimeZone('UTC'));
            $shiftEnd = new DateTime((string)$shift['clock_out_utc'], new DateTimeZone('UTC'));
            $shiftStart->setTimezone($tz);
            $shiftEnd->setTimezone($tz);
            if ($shiftEnd <= $shiftStart) continue;

            $cursor = clone $shiftStart;
            $breakMinutes = (int)$shift['break_minutes'];
            while ($cursor < $shiftEnd) {
                $dayEnd = (clone $cursor)->setTime(23, 59, 59);
                $segmentEnd = $shiftEnd < $dayEnd ? clone $shiftEnd : $dayEnd;

                $segStartTs = $cursor->getTimestamp();
                $segEndTs = $segmentEnd->getTimestamp();
                $periodStartTs = $periodStartLocal->getTimestamp();
                $periodEndTs = $periodEndLocal->getTimestamp();
                $minutes = calculateWorkedMinutesInRange($segStartTs, $segEndTs, $periodStartTs, $periodEndTs, $breakMinutes);
                if ($minutes > 0) {
                    $weekStart = (clone $cursor);
                    $dow = (int)$weekStart->format('w');
                    $offset = $dow === 0 ? -6 : (1 - $dow);
                    $weekStart->modify($offset . ' days')->setTime(0, 0, 0);
                    $weekKey = $weekStart->format('Y-m-d');
                    if (!isset($weeklyMinutesByEmployee[$empId])) $weeklyMinutesByEmployee[$empId] = [];
                    if (!isset($weeklyMinutesByEmployee[$empId][$weekKey])) $weeklyMinutesByEmployee[$empId][$weekKey] = 0;
                    $weeklyMinutesByEmployee[$empId][$weekKey] += $minutes;
                }
                $cursor = (clone $segmentEnd)->modify('+1 second');
            }
        }

        $results = [];
        foreach ($weeklyMinutesByEmployee as $empId => $weeks) {
            $regularMin = 0;
            $otMin = 0;
            foreach ($weeks as $mins) {
                $regularMin += min(2400, (int)$mins); // 40h/week
                $otMin += max(0, (int)$mins - 2400);
            }
            $rate = max(0, (int)($employeeMeta[$empId]['hourly_rate_cents'] ?? 0));
            $burdenPct = (float)($employeeMeta[$empId]['burden_percent'] ?? 0);
            if ($burdenPct <= 0) $burdenPct = $defaultBurdenPct;

            $regularPay = (int)round(($regularMin / 60) * $rate);
            $otPay = (int)round(($otMin / 60) * $rate * $otMultiplier);
            $ptoMin = getApprovedPtoMinutesForEmployeeInPeriod((int)$empId, (int)$storeId, (string)$period['period_start_date'], (string)$period['period_end_date']);
            $ptoPay = (int)round(($ptoMin / 60) * $rate);
            $gross = $regularPay + $otPay + $ptoPay;
            $loadedCost = (int)round($gross * (1 + ($burdenPct / 100)));
            $totalMinutes = $regularMin + $otMin + $ptoMin;
            $effective = $totalMinutes > 0 ? (int)round($loadedCost / ($totalMinutes / 60)) : 0;

            $results[] = [
                'employee_id' => (int)$empId,
                'regular_minutes' => (int)$regularMin,
                'overtime_minutes' => (int)$otMin,
                'pto_minutes' => (int)$ptoMin,
                'gross_cents' => (int)$gross,
                'loaded_cost_cents' => (int)$loadedCost,
                'effective_hourly_cents' => (int)$effective,
                'rates_snapshot_json' => json_encode([
                    'ot_multiplier' => $otMultiplier,
                    'burden_percent_used' => $burdenPct,
                    'hourly_rate_cents' => $rate,
                    'default_burden_percent' => $defaultBurdenPct,
                    'pto_minutes_paid' => (int)$ptoMin
                ])
            ];
        }

        $pdo->beginTransaction();
        $delOld = $pdo->prepare("DELETE FROM timeclock_payroll_runs WHERE payroll_period_id = ?");
        $delOld->execute([(int)$periodId]);
        $insRun = $pdo->prepare("
            INSERT INTO timeclock_payroll_runs
                (payroll_period_id, employee_id, store_id, regular_minutes, overtime_minutes, pto_minutes, gross_cents, loaded_cost_cents, effective_hourly_cents, rates_snapshot_json)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($results as $row) {
            $insRun->execute([
                (int)$periodId,
                (int)$row['employee_id'],
                (int)$storeId,
                (int)$row['regular_minutes'],
                (int)$row['overtime_minutes'],
                (int)$row['pto_minutes'],
                (int)$row['gross_cents'],
                (int)$row['loaded_cost_cents'],
                (int)$row['effective_hourly_cents'],
                (string)$row['rates_snapshot_json']
            ]);
        }
        $updPeriod = $pdo->prepare("
            UPDATE timeclock_payroll_periods
            SET status = 'PROCESSED',
                processed_at = CURRENT_TIMESTAMP,
                processed_by = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $updPeriod->execute([trim((string)$managerName), (int)$periodId]);
        logTimeclockAudit((int)$storeId, null, trim((string)$managerName), 'PAYROLL_RUN_PROCESSED', [
            'period_id' => (int)$periodId,
            'employee_count' => count($results)
        ]);
        $pdo->commit();
        return ['success' => true, 'message' => 'Payroll summary processed.', 'rows' => count($results)];
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        error_log('runPayrollForPeriod error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Payroll processing failed.'];
    }
}

function getPayrollCsvContent($periodId, $storeId) {
    $period = getPayrollPeriodById($periodId, $storeId);
    if (!$period) return null;
    $rows = getPayrollRunsByPeriod($periodId);
    $f = fopen('php://temp', 'r+');
    fputcsv($f, [
        'Employee',
        'Regular Hours',
        'OT Hours',
        'PTO Hours',
        'Gross Wages',
        'Loaded Cost',
        'Effective Loaded Hourly',
        'Rates Snapshot JSON'
    ]);
    foreach ($rows as $r) {
        fputcsv($f, [
            $r['full_name'],
            number_format(((int)$r['regular_minutes']) / 60, 2, '.', ''),
            number_format(((int)$r['overtime_minutes']) / 60, 2, '.', ''),
            number_format(((int)$r['pto_minutes']) / 60, 2, '.', ''),
            number_format(((int)$r['gross_cents']) / 100, 2, '.', ''),
            number_format(((int)$r['loaded_cost_cents']) / 100, 2, '.', ''),
            number_format(((int)$r['effective_hourly_cents']) / 100, 2, '.', ''),
            $r['rates_snapshot_json']
        ]);
    }
    rewind($f);
    $csv = stream_get_contents($f);
    fclose($f);
    return $csv;
}

function lockPayrollPeriod($periodId, $storeId, $managerName) {
    if (trim((string)$managerName) === '') {
        return ['success' => false, 'message' => 'Manager name is required to lock period.'];
    }
    $period = getPayrollPeriodById($periodId, $storeId);
    if (!$period) {
        return ['success' => false, 'message' => 'Payroll period not found.'];
    }
    if (($period['status'] ?? '') === 'LOCKED') {
        return ['success' => true, 'message' => 'Payroll period already locked.'];
    }
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            UPDATE timeclock_payroll_periods
            SET status = 'LOCKED',
                locked_at = CURRENT_TIMESTAMP,
                locked_by = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND store_id = ?
        ");
        $stmt->execute([trim((string)$managerName), (int)$periodId, (int)$storeId]);
        logTimeclockAudit((int)$storeId, null, trim((string)$managerName), 'PAYROLL_PERIOD_LOCKED', [
            'period_id' => (int)$periodId
        ]);
        return ['success' => true, 'message' => 'Payroll period locked.'];
    } catch (Throwable $e) {
        error_log('lockPayrollPeriod error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Unable to lock payroll period.'];
    }
}

function unlockPayrollPeriod($periodId, $storeId, $managerName) {
    if (trim((string)$managerName) === '') {
        return ['success' => false, 'message' => 'Manager name is required to unlock period.'];
    }
    $period = getPayrollPeriodById($periodId, $storeId);
    if (!$period) {
        return ['success' => false, 'message' => 'Payroll period not found.'];
    }
    if (($period['status'] ?? '') !== 'LOCKED') {
        return ['success' => true, 'message' => 'Payroll period is not locked.'];
    }
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            UPDATE timeclock_payroll_periods
            SET status = 'OPEN',
                locked_at = NULL,
                locked_by = NULL,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND store_id = ?
        ");
        $stmt->execute([(int)$periodId, (int)$storeId]);
        logTimeclockAudit((int)$storeId, null, trim((string)$managerName), 'PAYROLL_PERIOD_UNLOCKED', [
            'period_id' => (int)$periodId
        ]);
        return ['success' => true, 'message' => 'Payroll period unlocked.'];
    } catch (Throwable $e) {
        error_log('unlockPayrollPeriod error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Unable to unlock payroll period.'];
    }
}

function hasLockedPayrollPeriodOverlap($storeId, $startDateYmd, $endDateYmd) {
    try {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$startDateYmd) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$endDateYmd)) {
            return false;
        }
        if ($startDateYmd > $endDateYmd) {
            $tmp = $startDateYmd;
            $startDateYmd = $endDateYmd;
            $endDateYmd = $tmp;
        }
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT id
            FROM timeclock_payroll_periods
            WHERE store_id = ?
              AND status = 'LOCKED'
              AND period_start_date <= ?
              AND period_end_date >= ?
            LIMIT 1
        ");
        $stmt->execute([(int)$storeId, $endDateYmd, $startDateYmd]);
        return (bool)$stmt->fetch();
    } catch (Throwable $e) {
        error_log('hasLockedPayrollPeriodOverlap error: ' . $e->getMessage());
        return false;
    }
}

function getLocalDateYmdFromUtcOrNull($utcTs) {
    try {
        $raw = trim((string)$utcTs);
        if ($raw === '') {
            return null;
        }
        $dt = new DateTime($raw, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone(TIMEZONE));
        return $dt->format('Y-m-d');
    } catch (Throwable $e) {
        return null;
    }
}

function getLocalDateRangeFromUtc($startUtc, $endUtc) {
    $start = getLocalDateYmdFromUtcOrNull($startUtc);
    $end = getLocalDateYmdFromUtcOrNull($endUtc);
    if ($start === null && $end === null) {
        return [null, null];
    }
    if ($start === null) {
        $start = $end;
    }
    if ($end === null) {
        $end = $start;
    }
    if ($start > $end) {
        $tmp = $start;
        $start = $end;
        $end = $tmp;
    }
    return [$start, $end];
}

function getEmployeeWorkedMinutesByWeekInRange($employeeId, $storeId, $startDateYmd, $endDateYmd) {
    $weeklyMinutes = [];
    try {
        $tz = new DateTimeZone(TIMEZONE);
        $rangeStart = new DateTime($startDateYmd . ' 00:00:00', $tz);
        $rangeEnd = new DateTime($endDateYmd . ' 23:59:59', $tz);
        $rangeStartUtc = (clone $rangeStart)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:sP');
        $rangeEndUtc = (clone $rangeEnd)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:sP');

        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT clock_in_utc, clock_out_utc
            FROM time_shifts
            WHERE employee_id = ?
              AND store_id = ?
              AND clock_out_utc IS NOT NULL
              AND clock_in_utc <= ?
              AND clock_out_utc >= ?
        ");
        $stmt->execute([(int)$employeeId, (int)$storeId, $rangeEndUtc, $rangeStartUtc]);
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) {
            $in = new DateTime((string)$r['clock_in_utc'], new DateTimeZone('UTC'));
            $out = new DateTime((string)$r['clock_out_utc'], new DateTimeZone('UTC'));
            $in->setTimezone($tz);
            $out->setTimezone($tz);
            if ($out <= $in) continue;
            $cursor = clone $in;
            while ($cursor < $out) {
                $dayEnd = (clone $cursor)->setTime(23, 59, 59);
                $segEnd = $out < $dayEnd ? clone $out : $dayEnd;
                $mins = calculateWorkedMinutesInRange(
                    $cursor->getTimestamp(),
                    $segEnd->getTimestamp(),
                    $rangeStart->getTimestamp(),
                    $rangeEnd->getTimestamp(),
                    0
                );
                if ($mins > 0) {
                    $wk = clone $cursor;
                    $dow = (int)$wk->format('w');
                    $offset = $dow === 0 ? -6 : (1 - $dow);
                    $wk->modify($offset . ' days')->setTime(0, 0, 0);
                    $weekKey = $wk->format('Y-m-d');
                    if (!isset($weeklyMinutes[$weekKey])) $weeklyMinutes[$weekKey] = 0;
                    $weeklyMinutes[$weekKey] += $mins;
                }
                $cursor = (clone $segEnd)->modify('+1 second');
            }
        }
    } catch (Throwable $e) {
        error_log('getEmployeeWorkedMinutesByWeekInRange error: ' . $e->getMessage());
    }
    return $weeklyMinutes;
}

function getEmployeeRegularAndOtMinutesInRange($employeeId, $storeId, $startDateYmd, $endDateYmd) {
    $weekly = getEmployeeWorkedMinutesByWeekInRange($employeeId, $storeId, $startDateYmd, $endDateYmd);
    $regular = 0;
    $ot = 0;
    foreach ($weekly as $mins) {
        $regular += min(2400, (int)$mins);
        $ot += max(0, (int)$mins - 2400);
    }
    return ['regular_minutes' => $regular, 'ot_minutes' => $ot];
}

function calculatePtoAccruedMinutesForEmployee($employeeId, $storeId) {
    try {
        $today = (new DateTime('now', new DateTimeZone(TIMEZONE)))->format('Y-m-d');
        $method = (string)getTimeclockSettingValue('pto_accrual_method', null, 'per_hour_worked');
        $minutesPerHour = (float)getTimeclockSettingValue('pto_minutes_per_hour', null, '1.15');
        $excludeOt = ((string)getTimeclockSettingValue('pto_exclude_overtime', null, '1') === '1');
        $annualCap = (int)getTimeclockSettingValue('pto_annual_cap_minutes', null, '2400');
        $waitDays = (int)getTimeclockSettingValue('pto_waiting_period_days', null, '0');
        if ($minutesPerHour < 0) $minutesPerHour = 0;
        if ($annualCap < 0) $annualCap = 0;
        if ($waitDays < 0) $waitDays = 0;

        $pdo = getDB();
        $minStmt = $pdo->prepare("SELECT MIN(clock_in_utc) AS first_clock FROM time_shifts WHERE employee_id = ? AND store_id = ?");
        $minStmt->execute([(int)$employeeId, (int)$storeId]);
        $firstClock = $minStmt->fetch(PDO::FETCH_ASSOC);
        if (empty($firstClock['first_clock'])) {
            return 0;
        }
        $firstLocal = new DateTime((string)$firstClock['first_clock'], new DateTimeZone('UTC'));
        $firstLocal->setTimezone(new DateTimeZone(TIMEZONE));
        $eligibleDate = (clone $firstLocal)->modify('+' . $waitDays . ' days')->format('Y-m-d');
        if ($today < $eligibleDate) {
            return 0;
        }

        $mins = getEmployeeRegularAndOtMinutesInRange($employeeId, $storeId, $eligibleDate, $today);
        $workedForAccrual = $excludeOt ? (int)$mins['regular_minutes'] : ((int)$mins['regular_minutes'] + (int)$mins['ot_minutes']);

        if ($method === 'annual_lump_sum') {
            return $annualCap;
        }
        if ($method === 'per_pay_period') {
            $weeks = max(1, (int)ceil(((new DateTime($today))->getTimestamp() - (new DateTime($eligibleDate))->getTimestamp()) / (7 * 24 * 3600)));
            $periods = max(1, (int)ceil($weeks / 2));
            $perPeriod = (int)round($annualCap / 26);
            $accrued = $perPeriod * $periods;
            return ($annualCap > 0) ? min($annualCap, $accrued) : $accrued;
        }

        $accrued = (int)round(($workedForAccrual / 60) * $minutesPerHour);
        if ($annualCap > 0) {
            $accrued = min($annualCap, $accrued);
        }
        return max(0, $accrued);
    } catch (Throwable $e) {
        error_log('calculatePtoAccruedMinutesForEmployee error: ' . $e->getMessage());
        return 0;
    }
}

function recalcPtoBalanceForEmployee($employeeId, $storeId) {
    try {
        $accrued = calculatePtoAccruedMinutesForEmployee($employeeId, $storeId);
        $pdo = getDB();
        $usedStmt = $pdo->prepare("
            SELECT COALESCE(SUM(requested_minutes), 0) AS used_minutes
            FROM timeclock_pto_requests
            WHERE employee_id = ? AND store_id = ? AND status = 'APPROVED'
        ");
        $usedStmt->execute([(int)$employeeId, (int)$storeId]);
        $used = (int)($usedStmt->fetch(PDO::FETCH_ASSOC)['used_minutes'] ?? 0);

        $pendingStmt = $pdo->prepare("
            SELECT COALESCE(SUM(requested_minutes), 0) AS pending_minutes
            FROM timeclock_pto_requests
            WHERE employee_id = ? AND store_id = ? AND status = 'PENDING'
        ");
        $pendingStmt->execute([(int)$employeeId, (int)$storeId]);
        $pending = (int)($pendingStmt->fetch(PDO::FETCH_ASSOC)['pending_minutes'] ?? 0);

        $available = max(0, $accrued - $used);
        $upsert = $pdo->prepare("
            INSERT INTO timeclock_pto_balances
                (employee_id, store_id, accrued_minutes, used_minutes, pending_minutes, available_minutes, last_recalculated_at)
            VALUES
                (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT (employee_id, store_id)
            DO UPDATE SET
                accrued_minutes = EXCLUDED.accrued_minutes,
                used_minutes = EXCLUDED.used_minutes,
                pending_minutes = EXCLUDED.pending_minutes,
                available_minutes = EXCLUDED.available_minutes,
                last_recalculated_at = CURRENT_TIMESTAMP
        ");
        $upsert->execute([(int)$employeeId, (int)$storeId, $accrued, $used, $pending, $available]);
        return [
            'employee_id' => (int)$employeeId,
            'store_id' => (int)$storeId,
            'accrued_minutes' => $accrued,
            'used_minutes' => $used,
            'pending_minutes' => $pending,
            'available_minutes' => $available
        ];
    } catch (Throwable $e) {
        error_log('recalcPtoBalanceForEmployee error: ' . $e->getMessage());
        return null;
    }
}

function recalcPtoBalancesForStore($storeId) {
    $out = [];
    $employees = getTimeClockEmployeesForStore($storeId);
    foreach ($employees as $e) {
        $bal = recalcPtoBalanceForEmployee((int)$e['id'], (int)$storeId);
        if ($bal) $out[] = $bal;
    }
    return $out;
}

function getPtoBalancesByStore($storeId) {
    try {
        recalcPtoBalancesForStore($storeId);
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT b.*, e.full_name
            FROM timeclock_pto_balances b
            INNER JOIN employees e ON e.id = b.employee_id
            WHERE b.store_id = ?
            ORDER BY e.full_name ASC
        ");
        $stmt->execute([(int)$storeId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('getPtoBalancesByStore error: ' . $e->getMessage());
        return [];
    }
}

function createPtoRequest($employeeId, $storeId, $startDate, $endDate, $requestedMinutes, $reason, $employeeName = 'employee') {
    try {
        if ($requestedMinutes <= 0) {
            return ['success' => false, 'message' => 'Requested PTO minutes must be greater than zero.'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$endDate)) {
            return ['success' => false, 'message' => 'Invalid PTO date range.'];
        }
        if ($startDate > $endDate) {
            return ['success' => false, 'message' => 'PTO start date must be before end date.'];
        }
        if (trim((string)$reason) === '') {
            return ['success' => false, 'message' => 'PTO reason is required.'];
        }
        if (hasLockedPayrollPeriodOverlap($storeId, $startDate, $endDate)) {
            return ['success' => false, 'message' => 'This PTO date range is inside a locked payroll period. Unlock period first.'];
        }

        $bal = recalcPtoBalanceForEmployee($employeeId, $storeId);
        if ($bal && ((int)$bal['available_minutes'] < (int)$requestedMinutes)) {
            return ['success' => false, 'message' => 'Requested PTO exceeds available balance.'];
        }

        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO timeclock_pto_requests
                (employee_id, store_id, request_start_date, request_end_date, requested_minutes, reason, status)
            VALUES
                (?, ?, ?, ?, ?, ?, 'PENDING')
        ");
        $stmt->execute([(int)$employeeId, (int)$storeId, $startDate, $endDate, (int)$requestedMinutes, trim((string)$reason)]);
        logTimeclockAudit((int)$storeId, (int)$employeeId, (string)$employeeName, 'PTO_REQUEST_SUBMITTED', [
            'start' => $startDate,
            'end' => $endDate,
            'minutes' => (int)$requestedMinutes
        ]);
        recalcPtoBalanceForEmployee($employeeId, $storeId);
        return ['success' => true, 'message' => 'PTO request submitted.'];
    } catch (Throwable $e) {
        error_log('createPtoRequest error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to submit PTO request.'];
    }
}

function getPendingPtoRequestsByStore($storeId) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT r.*, e.full_name
            FROM timeclock_pto_requests r
            INNER JOIN employees e ON e.id = r.employee_id
            WHERE r.store_id = ? AND r.status = 'PENDING'
            ORDER BY r.submitted_at ASC
        ");
        $stmt->execute([(int)$storeId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('getPendingPtoRequestsByStore error: ' . $e->getMessage());
        return [];
    }
}

function getRecentPtoRequestsByStore($storeId, $limit = 30) {
    try {
        $safe = max(1, min(200, (int)$limit));
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT r.*, e.full_name
            FROM timeclock_pto_requests r
            INNER JOIN employees e ON e.id = r.employee_id
            WHERE r.store_id = ?
            ORDER BY r.submitted_at DESC
            LIMIT $safe
        ");
        $stmt->execute([(int)$storeId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('getRecentPtoRequestsByStore error: ' . $e->getMessage());
        return [];
    }
}

function reviewPtoRequest($requestId, $storeId, $decision, $managerName, $managerNote = null) {
    $decision = strtoupper((string)$decision);
    if (!in_array($decision, ['APPROVED', 'DENIED'], true)) {
        return ['success' => false, 'message' => 'Invalid PTO review decision.'];
    }
    if (trim((string)$managerName) === '') {
        return ['success' => false, 'message' => 'Manager name is required.'];
    }
    try {
        $pdo = getDB();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            SELECT *
            FROM timeclock_pto_requests
            WHERE id = ? AND store_id = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$requestId, (int)$storeId]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$req) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'PTO request not found.'];
        }
        if (($req['status'] ?? '') !== 'PENDING') {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'PTO request already reviewed.'];
        }
        if ($decision === 'APPROVED' && hasLockedPayrollPeriodOverlap((int)$storeId, (string)$req['request_start_date'], (string)$req['request_end_date'])) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Cannot approve PTO inside a locked payroll period. Unlock period first.'];
        }

        if ($decision === 'APPROVED') {
            $bal = recalcPtoBalanceForEmployee((int)$req['employee_id'], (int)$storeId);
            if ($bal && ((int)$bal['available_minutes'] < (int)$req['requested_minutes'])) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Not enough available PTO to approve this request.'];
            }
        }

        $upd = $pdo->prepare("
            UPDATE timeclock_pto_requests
            SET status = ?, reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ?, manager_note = ?
            WHERE id = ?
        ");
        $upd->execute([$decision, trim((string)$managerName), $managerNote ? trim((string)$managerNote) : null, (int)$requestId]);

        $pdo->commit();
        recalcPtoBalanceForEmployee((int)$req['employee_id'], (int)$storeId);
        logTimeclockAudit((int)$storeId, (int)$req['employee_id'], trim((string)$managerName), 'PTO_REQUEST_' . $decision, [
            'request_id' => (int)$requestId,
            'minutes' => (int)$req['requested_minutes']
        ]);
        return ['success' => true, 'message' => $decision === 'APPROVED' ? 'PTO request approved.' : 'PTO request denied.'];
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        error_log('reviewPtoRequest error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to review PTO request.'];
    }
}

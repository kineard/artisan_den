<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/timeclock-functions.php';

try {
    $pdo = getDB();
    $pdo->beginTransaction();

    // Ensure stores exist.
    $pdo->exec("INSERT INTO stores (name) VALUES ('23rd St'), ('Pier Park') ON CONFLICT (name) DO NOTHING");

    $stores = $pdo->query("SELECT id, name FROM stores WHERE name IN ('23rd St', 'Pier Park')")->fetchAll(PDO::FETCH_ASSOC);
    $storeMap = [];
    foreach ($stores as $store) {
        $storeMap[$store['name']] = (int)$store['id'];
    }

    $employees = [
        ['name' => 'Alex Manager', 'role' => 'Manager', 'rate_cents' => 2800, 'pin' => '1111', 'stores' => ['23rd St', 'Pier Park']],
        ['name' => 'Sam Cashier', 'role' => 'Employee', 'rate_cents' => 1650, 'pin' => '2222', 'stores' => ['23rd St']],
        ['name' => 'Jordan Stock', 'role' => 'Employee', 'rate_cents' => 1725, 'pin' => '3333', 'stores' => ['Pier Park']],
    ];

    $findEmployeeStmt = $pdo->prepare("SELECT id FROM employees WHERE full_name = ? LIMIT 1");
    $insertEmployeeStmt = $pdo->prepare("
        INSERT INTO employees (full_name, role_name, pin_hash, hourly_rate_cents, burden_percent, is_active)
        VALUES (?, ?, ?, ?, ?, TRUE)
        RETURNING id
    ");
    $assignStoreStmt = $pdo->prepare("
        INSERT INTO employee_locations (employee_id, store_id, is_active)
        VALUES (?, ?, TRUE)
        ON CONFLICT (employee_id, store_id) DO UPDATE SET is_active = EXCLUDED.is_active
    ");

    $employeeIdByName = [];
    foreach ($employees as $emp) {
        $findEmployeeStmt->execute([$emp['name']]);
        $existing = $findEmployeeStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $employeeId = (int)$existing['id'];
            $update = $pdo->prepare("UPDATE employees SET role_name = ?, hourly_rate_cents = ?, pin_hash = ?, is_active = TRUE, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $update->execute([
                $emp['role'],
                (int)$emp['rate_cents'],
                password_hash((string)$emp['pin'], PASSWORD_DEFAULT),
                $employeeId
            ]);
        } else {
            $insertEmployeeStmt->execute([
                $emp['name'],
                $emp['role'],
                password_hash((string)$emp['pin'], PASSWORD_DEFAULT),
                (int)$emp['rate_cents'],
                0
            ]);
            $created = $insertEmployeeStmt->fetch(PDO::FETCH_ASSOC);
            $employeeId = (int)$created['id'];
        }
        $employeeIdByName[$emp['name']] = $employeeId;

        foreach ($emp['stores'] as $storeName) {
            if (!isset($storeMap[$storeName])) {
                continue;
            }
            $assignStoreStmt->execute([$employeeId, (int)$storeMap[$storeName]]);
        }
    }

    // Seed a realistic 4-week schedule so the calendar has enough volume to preview.
    $seedManager = 'Seed Script';
    $seedNote = 'SEED_DEMO_SCHEDULE';
    $tz = new DateTimeZone(TIMEZONE);
    $baseWeekStart = new DateTime('monday this week', $tz);

    $deleteSeedShiftsStmt = $pdo->prepare("DELETE FROM timeclock_schedule_shifts WHERE note = ?");
    $deleteSeedShiftsStmt->execute([$seedNote]);

    $seedPlan = [
        '23rd St' => [
            // Sam primary cashier coverage.
            ['employee' => 'Sam Cashier', 'role' => 'Cashier', 'days' => [0, 1, 2, 3, 4], 'start' => '09:00', 'end' => '17:00', 'break' => 30],
            // Alex manager swing coverage.
            ['employee' => 'Alex Manager', 'role' => 'Manager', 'days' => [0, 2, 4], 'start' => '12:00', 'end' => '20:00', 'break' => 30],
            // Weekend lighter staffing.
            ['employee' => 'Sam Cashier', 'role' => 'Cashier', 'days' => [5], 'start' => '10:00', 'end' => '16:00', 'break' => 30],
        ],
        'Pier Park' => [
            ['employee' => 'Jordan Stock', 'role' => 'Stock', 'days' => [0, 1, 2, 3, 4], 'start' => '10:00', 'end' => '18:00', 'break' => 30],
            ['employee' => 'Alex Manager', 'role' => 'Manager', 'days' => [1, 3], 'start' => '11:00', 'end' => '19:00', 'break' => 30],
            ['employee' => 'Jordan Stock', 'role' => 'Stock', 'days' => [6], 'start' => '11:00', 'end' => '15:00', 'break' => 15],
        ],
    ];

    $weeksToSeed = 4;
    $seededShiftCount = 0;
    $firstWeekStartYmd = '';
    $lastWeekEndYmd = '';
    for ($w = 0; $w < $weeksToSeed; $w++) {
        $weekStart = (clone $baseWeekStart)->modify('+' . $w . ' week');
        $weekEnd = (clone $weekStart)->modify('+6 days');
        $weekStartYmd = $weekStart->format('Y-m-d');
        $weekEndYmd = $weekEnd->format('Y-m-d');
        if ($firstWeekStartYmd === '') {
            $firstWeekStartYmd = $weekStartYmd;
        }
        $lastWeekEndYmd = $weekEndYmd;

        foreach ($seedPlan as $storeName => $shifts) {
            $storeId = (int)($storeMap[$storeName] ?? 0);
            if ($storeId <= 0) {
                continue;
            }
            foreach ($shifts as $item) {
                $empId = (int)($employeeIdByName[$item['employee']] ?? 0);
                if ($empId <= 0) {
                    continue;
                }
                foreach ((array)$item['days'] as $offset) {
                    $day = (clone $weekStart)->modify('+' . (int)$offset . ' days');
                    $dayYmd = $day->format('Y-m-d');
                    $startUtc = parseLocalDateTimeToUtc($dayYmd . ' ' . $item['start'] . ':00');
                    $endUtc = parseLocalDateTimeToUtc($dayYmd . ' ' . $item['end'] . ':00');
                    if (!$startUtc || !$endUtc) {
                        continue;
                    }
                    $addRes = addScheduleShift([
                        'employee_id' => $empId,
                        'store_id' => $storeId,
                        'role_name' => (string)$item['role'],
                        'start_utc' => $startUtc,
                        'end_utc' => $endUtc,
                        'break_minutes' => (int)$item['break'],
                        'manager_name' => $seedManager,
                        'note' => $seedNote
                    ]);
                    if (!empty($addRes['success'])) {
                        $seededShiftCount++;
                    }
                }
            }
            publishScheduleWeek($storeId, $weekStartYmd, $weekEndYmd, $seedManager);
        }
    }

    $pdo->commit();

    echo "Time Clock demo seed complete.\n";
    echo "Demo PINs:\n";
    echo " - Alex Manager: 1111\n";
    echo " - Sam Cashier: 2222\n";
    echo " - Jordan Stock: 3333\n";
    echo "Seeded schedule range: {$firstWeekStartYmd} to {$lastWeekEndYmd}\n";
    echo "Seeded shifts: {$seededShiftCount}\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Seed failed: " . $e->getMessage() . "\n");
    exit(1);
}

<?php
require_once __DIR__ . '/../config.php';
function calculateCashAvailable($bankBalance, $safeBalance) {
    return floatval($bankBalance) + floatval($safeBalance);
}
function calculateProfit($sales, $cogs, $labor, $overhead) {
    return floatval($sales) - floatval($cogs) - floatval($labor) - floatval($overhead);
}
function calculateLaborPercentage($labor, $sales) {
    if (floatval($sales) == 0) return 0;
    return (floatval($labor) / floatval($sales)) * 100;
}
function getStore($storeId) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM stores WHERE id = ?");
        $stmt->execute([$storeId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error getting store: " . $e->getMessage());
        return null;
    }
}
function getAllStores() {
    try {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT * FROM stores ORDER BY name");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting stores: " . $e->getMessage());
        return [];
    }
}
function getDailyKpi($storeId, $date) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM daily_kpis WHERE store_id = ? AND entry_date = ?");
    $stmt->execute([$storeId, $date]);
    return $stmt->fetch();
}
function saveDailyKpi($data) {
    $pdo = getDB();
    $existing = getDailyKpi($data['store_id'], $data['entry_date']);
    if ($existing) {
        $stmt = $pdo->prepare("UPDATE daily_kpis SET bank_balance = ?, safe_balance = ?, sales_today = ?, cogs_today = ?, labor_today = ?, avg_daily_overhead = ?, updated_by = ? WHERE id = ?");
        return $stmt->execute([$data['bank_balance'], $data['safe_balance'], $data['sales_today'], $data['cogs_today'], $data['labor_today'], $data['avg_daily_overhead'], $data['updated_by'] ?? null, $existing['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO daily_kpis (store_id, entry_date, bank_balance, safe_balance, sales_today, cogs_today, labor_today, avg_daily_overhead, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$data['store_id'], $data['entry_date'], $data['bank_balance'], $data['safe_balance'], $data['sales_today'], $data['cogs_today'], $data['labor_today'], $data['avg_daily_overhead'], $data['updated_by'] ?? null]);
    }
}
function getHistoricalKpis($storeId = null, $limit = 50) {
    $pdo = getDB();
    if ($storeId) {
        $stmt = $pdo->prepare("SELECT dk.*, s.name as store_name FROM daily_kpis dk JOIN stores s ON dk.store_id = s.id WHERE dk.store_id = ? ORDER BY dk.entry_date DESC LIMIT ?");
        $stmt->execute([$storeId, $limit]);
    } else {
        $stmt = $pdo->prepare("SELECT dk.*, s.name as store_name FROM daily_kpis dk JOIN stores s ON dk.store_id = s.id ORDER BY dk.entry_date DESC LIMIT ?");
        $stmt->execute([$limit]);
    }
    return $stmt->fetchAll();
}
function getKpisForDateRange($storeId, $startDate, $endDate) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM daily_kpis WHERE store_id = ? AND entry_date >= ? AND entry_date <= ? ORDER BY entry_date ASC");
        $stmt->execute([$storeId, $startDate, $endDate]);
        $results = $stmt->fetchAll();
        // Create a map for quick lookup
        $kpiMap = [];
        foreach ($results as $kpi) {
            $kpiMap[$kpi['entry_date']] = $kpi;
        }
        return $kpiMap;
    } catch (PDOException $e) {
        error_log("Error getting KPIs for date range: " . $e->getMessage());
        return [];
    }
}
function getDateRangeForView($view, $referenceDate) {
    try {
        $date = new DateTime($referenceDate);
        switch ($view) {
            case 'week':
                // Get Monday of the week
                $dayOfWeek = (int)$date->format('w'); // 0 = Sunday, 1 = Monday, etc.
                $mondayOffset = $dayOfWeek == 0 ? -6 : 1 - $dayOfWeek;
                $date->modify("$mondayOffset days");
                $startDate = $date->format('Y-m-d');
                $date->modify('+6 days');
                $endDate = $date->format('Y-m-d');
                break;
            case 'month':
                $date->modify('first day of this month');
                $startDate = $date->format('Y-m-d');
                $date->modify('last day of this month');
                $endDate = $date->format('Y-m-d');
                break;
            case 'custom':
            default:
                // Default to 7 days
                $startDate = (clone $date)->modify('-6 days')->format('Y-m-d');
                $endDate = $date->format('Y-m-d');
                break;
        }
        return ['start' => $startDate, 'end' => $endDate];
    } catch (Exception $e) {
        error_log("Error in getDateRangeForView: " . $e->getMessage());
        // Return default week range on error
        $date = new DateTime($referenceDate);
        $date->modify('-6 days');
        return ['start' => $date->format('Y-m-d'), 'end' => $referenceDate];
    }
}
function generateDateArray($startDate, $endDate) {
    $dates = [];
    $current = new DateTime($startDate);
    $end = new DateTime($endDate);
    while ($current <= $end) {
        $dates[] = $current->format('Y-m-d');
        $current->modify('+1 day');
    }
    return $dates;
}/**
 * Fri/Sat/Sun are not input until Monday. For a week (Mon–Sun), we only "have" Fri–Sun
 * once we're on Monday of the following week or later.
 * Returns dates that we treat as having data for charting/display.
 * $asOfDate: typically today or reference date.
 */
function getDatesWithDataForRange($startDate, $endDate, $asOfDate, $view = 'week') {
    $all = generateDateArray($startDate, $endDate);
    if ($view !== 'week') {
        return $all;
    }
    try {
        $nextMonday = (new DateTime($endDate))->modify('+1 day');
        $asOf = new DateTime($asOfDate);
        if ($asOf < $nextMonday) {
            return array_values(array_filter($all, function ($d) {
                $w = (int)(new DateTime($d))->format('w');
                return $w >= 1 && $w <= 4;
            }));
        }
    } catch (Exception $e) {
        // ignore
    }
    return $all;
}
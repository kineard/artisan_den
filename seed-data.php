<?php
/**
 * Seed Data Script for Artisan Den KPI System
 * This script populates the database with sample data for testing
 */

require_once 'config.php';
require_once 'includes/functions.php';

echo "=== Artisan Den Seed Data Script ===\n\n";

// Get stores
$stores = getAllStores();
if (empty($stores)) {
    echo "Error: No stores found. Please run setup-db.sh first.\n";
    exit(1);
}

echo "Found " . count($stores) . " store(s)\n\n";

// Generate data for the last 30 days
$startDate = new DateTime();
$startDate->modify('-30 days');
$endDate = new DateTime();

$totalRecords = 0;

foreach ($stores as $store) {
    echo "Seeding data for: {$store['name']} (ID: {$store['id']})\n";
    
    $current = clone $startDate;
    $recordsForStore = 0;
    
    while ($current <= $endDate) {
        $dateStr = $current->format('Y-m-d');
        
        // Skip if data already exists
        $existing = getDailyKpi($store['id'], $dateStr);
        if ($existing) {
            $current->modify('+1 day');
            continue;
        }
        
        // Generate realistic sample data
        // Sales vary by day of week (weekends higher)
        $dayOfWeek = (int)$current->format('w');
        $isWeekend = ($dayOfWeek == 0 || $dayOfWeek == 6);
        
        $baseSales = $isWeekend ? rand(2000, 4000) : rand(1500, 3000);
        $sales = $baseSales + rand(-200, 200);
        $cogs = round($sales * (rand(40, 60) / 100), 2);
        $labor = round($sales * (rand(25, 35) / 100), 2);
        $overhead = rand(200, 400);
        
        // Bank and safe balances (accumulate over time)
        $bankBalance = rand(5000, 15000);
        $safeBalance = rand(500, 2000);
        
        $data = [
            'store_id' => $store['id'],
            'entry_date' => $dateStr,
            'bank_balance' => $bankBalance,
            'safe_balance' => $safeBalance,
            'sales_today' => $sales,
            'cogs_today' => $cogs,
            'labor_today' => $labor,
            'avg_daily_overhead' => $overhead,
            'updated_by' => 'Seed Script'
        ];
        
        if (saveDailyKpi($data)) {
            $recordsForStore++;
            $totalRecords++;
        }
        
        $current->modify('+1 day');
    }
    
    echo "  Created $recordsForStore records\n\n";
}

echo "=== Seed Complete ===\n";
echo "Total records created: $totalRecords\n";
echo "\nYou can now view the data at: http://localhost:8001/\n";

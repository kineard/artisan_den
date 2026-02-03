<?php
/**
 * Seed Inventory Data Script for Artisan Den
 * This script populates the database with sample inventory data
 */

require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/inventory-functions.php';

echo "=== Artisan Den Inventory Seed Script ===\n\n";

// Get stores
$stores = getAllStores();
if (empty($stores)) {
    echo "Error: No stores found. Please run setup-db.sh first.\n";
    exit(1);
}

echo "Found " . count($stores) . " store(s)\n\n";

// Sample products
$products = [
    ['sku' => 'TOP10-ITEM-1', 'name' => 'NOVO 2X PODS', 'unit_type' => 'unit'],
    ['sku' => 'TOP10-ITEM-2', 'name' => 'DELTA FLOWER', 'unit_type' => 'gram'],
    ['sku' => 'TOP10-ITEM-3', 'name' => 'MELLOW FELLOW', 'unit_type' => 'box'],
    ['sku' => 'TOP10-ITEM-4', 'name' => 'VAPE CARTRIDGE', 'unit_type' => 'unit'],
    ['sku' => 'TOP10-ITEM-5', 'name' => 'DISPOSABLE VAPE', 'unit_type' => 'unit'],
    ['sku' => 'TOP10-ITEM-6', 'name' => 'EDIBLE GUMMIES', 'unit_type' => 'box'],
    ['sku' => 'TOP10-ITEM-7', 'name' => 'PRE-ROLL PACK', 'unit_type' => 'box'],
    ['sku' => 'TOP10-ITEM-8', 'name' => 'CONCENTRATE JAR', 'unit_type' => 'gram'],
    ['sku' => 'TOP10-ITEM-9', 'name' => 'NOVO MASTER', 'unit_type' => 'unit'],
    ['sku' => 'TOP10-ITEM-10', 'name' => 'DELTA FLOWER X2', 'unit_type' => 'gram'],
];

// Sample vendors
$vendors = [
    [
        'name' => 'VAPORBEAST',
        'contact_name' => 'Jeremy Royer',
        'phone' => '(864)504-5305',
        'email' => 'jeremy@vaporbeast.com',
        'order_method' => 'text/call',
        'cutoff_time' => 'NO SPECIFIC',
        'typical_lead_time' => '2 DAY OPTION OR 4 DAY',
        'shipping_speed_notes' => '2 DAY OPTION OR 4 DAY',
        'free_ship_threshold' => 25,
        'account_info' => 'GENERAL.ARTISANDEN@GMAIL',
        'password' => 'dviaqwplo054',
        'rating' => 4,
        'is_preferred' => true,
        'is_active' => true
    ],
    [
        'name' => 'CALI EXTRA X',
        'contact_name' => 'Sales Team',
        'phone' => 'CALL',
        'email' => 'sales@caliextrax.com',
        'order_method' => 'call',
        'cutoff_time' => 'WEDNESDAY 1PM',
        'typical_lead_time' => 'SAME DAY',
        'shipping_speed_notes' => 'NEXT DAY',
        'free_ship_threshold' => 0,
        'account_info' => 'NO ONLINE ACCOUNT',
        'password' => null,
        'rating' => 5,
        'is_preferred' => true,
        'is_active' => true
    ],
    [
        'name' => 'PANHANDLE WHOLESALE',
        'contact_name' => 'Order Desk',
        'phone' => '(850)555-1234',
        'email' => 'orders@panhandle.com',
        'order_method' => 'text',
        'cutoff_time' => 'FRIDAY 3PM',
        'typical_lead_time' => '3-5 DAYS',
        'shipping_speed_notes' => 'STANDARD SHIPPING',
        'free_ship_threshold' => 100,
        'account_info' => 'ARTISANDEN_ACCT',
        'password' => 'panhandle2024',
        'rating' => 3,
        'is_preferred' => false,
        'is_active' => true
    ],
    [
        'name' => 'AARNA',
        'contact_name' => 'Customer Service',
        'phone' => '(555)123-4567',
        'email' => 'info@aarna.com',
        'order_method' => 'site',
        'cutoff_time' => 'NO SPECIFIC',
        'typical_lead_time' => '5-7 DAYS',
        'shipping_speed_notes' => 'STANDARD',
        'free_ship_threshold' => 50,
        'account_info' => 'artisan_den',
        'password' => 'aarna_pass',
        'rating' => 2,
        'is_preferred' => false,
        'is_active' => true
    ]
];

// Seed products
echo "Seeding products...\n";
$productIds = [];
foreach ($products as $productData) {
    // Check if product exists
    $existing = null;
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
        $stmt->execute([$productData['sku']]);
        $existing = $stmt->fetch();
    } catch (PDOException $e) {
        // Table might not exist yet
    }
    
    if ($existing) {
        echo "  Product {$productData['sku']} already exists\n";
        $productIds[$productData['sku']] = $existing['id'];
    } else {
        if (saveProduct($productData)) {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
            $stmt->execute([$productData['sku']]);
            $newProduct = $stmt->fetch();
            if ($newProduct) {
                $productIds[$productData['sku']] = $newProduct['id'];
                echo "  Created product: {$productData['sku']} - {$productData['name']}\n";
            }
        }
    }
}
echo "\n";

// Seed vendors
echo "Seeding vendors...\n";
$vendorIds = [];
$pdo = getDB();

foreach ($vendors as $vendorData) {
    // Check if vendor exists
    $existing = null;
    try {
        $stmt = $pdo->prepare("SELECT id FROM vendors WHERE name = ?");
        $stmt->execute([$vendorData['name']]);
        $existing = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error checking vendor: " . $e->getMessage());
    }
    
    if ($existing) {
        // Update existing vendor to ensure data is correct
        $vendorData['id'] = $existing['id'];
        $result = saveVendor($vendorData);
        if ($result) {
            $vendorIds[$vendorData['name']] = $existing['id'];
            echo "  Updated vendor: {$vendorData['name']} (Rating: {$vendorData['rating']} stars)\n";
        } else {
            // If update failed, try to fix the boolean fields directly
            try {
                $isPref = ($vendorData['is_preferred'] === true) ? 'true' : 'false';
                $isAct = ($vendorData['is_active'] === true) ? 'true' : 'false';
                $fixStmt = $pdo->prepare("UPDATE vendors SET is_preferred = ?::boolean, is_active = ?::boolean, rating = ? WHERE id = ?");
                $fixStmt->execute([$isPref, $isAct, $vendorData['rating'], $existing['id']]);
                $vendorIds[$vendorData['name']] = $existing['id'];
                echo "  Fixed vendor: {$vendorData['name']} (Rating: {$vendorData['rating']} stars)\n";
            } catch (PDOException $e) {
                $vendorIds[$vendorData['name']] = $existing['id'];
                echo "  Vendor {$vendorData['name']} already exists (could not update)\n";
            }
        }
    } else {
        // Ensure boolean values are properly set
        $vendorData['is_preferred'] = isset($vendorData['is_preferred']) ? (bool)$vendorData['is_preferred'] : false;
        $vendorData['is_active'] = isset($vendorData['is_active']) ? (bool)$vendorData['is_active'] : true;
        
        $result = saveVendor($vendorData);
        if ($result) {
            $stmt = $pdo->prepare("SELECT id FROM vendors WHERE name = ?");
            $stmt->execute([$vendorData['name']]);
            $newVendor = $stmt->fetch();
            if ($newVendor) {
                $vendorIds[$vendorData['name']] = $newVendor['id'];
                echo "  Created vendor: {$vendorData['name']} (Rating: {$vendorData['rating']} stars)\n";
            } else {
                echo "  Warning: Vendor {$vendorData['name']} saved but not found\n";
            }
        } else {
            echo "  Error creating vendor: {$vendorData['name']}\n";
            // Try direct SQL insert as fallback
            try {
                $isPref = ($vendorData['is_preferred'] === true) ? 'true' : 'false';
                $isAct = ($vendorData['is_active'] === true) ? 'true' : 'false';
                $directStmt = $pdo->prepare("INSERT INTO vendors (name, contact_name, phone, email, order_method, cutoff_time, typical_lead_time, shipping_speed_notes, free_ship_threshold, account_info, password, notes, rating, is_preferred, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?::boolean, ?::boolean)");
                $directStmt->execute([
                    $vendorData['name'], $vendorData['contact_name'] ?? null, $vendorData['phone'] ?? null, $vendorData['email'] ?? null,
                    $vendorData['order_method'] ?? null, $vendorData['cutoff_time'] ?? null, $vendorData['typical_lead_time'] ?? null,
                    $vendorData['shipping_speed_notes'] ?? null, $vendorData['free_ship_threshold'] ?? null,
                    $vendorData['account_info'] ?? null, $vendorData['password'] ?? null, $vendorData['notes'] ?? null,
                    $vendorData['rating'],
                    $isPref, $isAct
                ]);
                $stmt = $pdo->prepare("SELECT id FROM vendors WHERE name = ?");
                $stmt->execute([$vendorData['name']]);
                $newVendor = $stmt->fetch();
                if ($newVendor) {
                    $vendorIds[$vendorData['name']] = $newVendor['id'];
                    echo "  Created vendor (direct SQL): {$vendorData['name']} (Rating: {$vendorData['rating']} stars)\n";
                }
            } catch (PDOException $e) {
                echo "    Direct SQL also failed: " . $e->getMessage() . "\n";
            }
        }
    }
}
echo "\n";

// Seed inventory for each store
foreach ($stores as $store) {
    echo "Seeding inventory for: {$store['name']} (ID: {$store['id']})\n";
    $inventoryCount = 0;
    
    // Assign inventory items — all with 25 quantity so entering sales visibly updates On Hand
    $inventoryData = [
        ['sku' => 'TOP10-ITEM-1', 'on_hand' => 25, 'reorder_point' => 20, 'target_max' => 60, 'vendor' => 'VAPORBEAST', 'vendor_sku' => 'TEXT MESSAGE', 'lead_time' => 4, 'unit_cost' => 41.25],
        ['sku' => 'TOP10-ITEM-2', 'on_hand' => 25, 'reorder_point' => 50, 'target_max' => 226.5, 'vendor' => 'CALI EXTRA X', 'vendor_sku' => 'DELTA-FLWR', 'lead_time' => 1, 'unit_cost' => 8.50],
        ['sku' => 'TOP10-ITEM-3', 'on_hand' => 25, 'reorder_point' => 10, 'target_max' => 303, 'vendor' => 'PANHANDLE WHOLESALE', 'vendor_sku' => 'MF-BOX', 'lead_time' => 5, 'unit_cost' => 12.00],
        ['sku' => 'TOP10-ITEM-4', 'on_hand' => 25, 'reorder_point' => 30, 'target_max' => 100, 'vendor' => 'VAPORBEAST', 'vendor_sku' => 'VC-001', 'lead_time' => 4, 'unit_cost' => 15.75],
        ['sku' => 'TOP10-ITEM-5', 'on_hand' => 25, 'reorder_point' => 25, 'target_max' => 75, 'vendor' => 'VAPORBEAST', 'vendor_sku' => 'DV-001', 'lead_time' => 4, 'unit_cost' => 22.50],
        ['sku' => 'TOP10-ITEM-6', 'on_hand' => 25, 'reorder_point' => 20, 'target_max' => 50, 'vendor' => 'CALI EXTRA X', 'vendor_sku' => 'ED-GUM', 'lead_time' => 2, 'unit_cost' => 18.00],
        ['sku' => 'TOP10-ITEM-7', 'on_hand' => 25, 'reorder_point' => 15, 'target_max' => 40, 'vendor' => 'PANHANDLE WHOLESALE', 'vendor_sku' => 'PR-PACK', 'lead_time' => 5, 'unit_cost' => 25.00],
        ['sku' => 'TOP10-ITEM-8', 'on_hand' => 25, 'reorder_point' => 20, 'target_max' => 60, 'vendor' => 'CALI EXTRA X', 'vendor_sku' => 'CONC-JAR', 'lead_time' => 2, 'unit_cost' => 35.00],
        ['sku' => 'TOP10-ITEM-9', 'on_hand' => 25, 'reorder_point' => 5, 'target_max' => 15, 'vendor' => 'VAPORBEAST', 'vendor_sku' => 'NOVO-M', 'lead_time' => 4, 'unit_cost' => 45.00],
        ['sku' => 'TOP10-ITEM-10', 'on_hand' => 25, 'reorder_point' => 50, 'target_max' => 226.5, 'vendor' => 'CALI EXTRA X', 'vendor_sku' => 'DELTA-FLWR-X2', 'lead_time' => 1, 'unit_cost' => 9.00],
    ];
    
    foreach ($inventoryData as $inv) {
        if (!isset($productIds[$inv['sku']]) || !isset($vendorIds[$inv['vendor']])) {
            if (!isset($productIds[$inv['sku']])) {
                echo "    Warning: Product {$inv['sku']} not found\n";
            }
            if (!isset($vendorIds[$inv['vendor']])) {
                echo "    Warning: Vendor {$inv['vendor']} not found\n";
            }
            continue;
        }
        
        // Check if inventory already exists
        $existing = null;
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT id FROM inventory WHERE store_id = ? AND product_id = ?");
            $stmt->execute([$store['id'], $productIds[$inv['sku']]]);
            $existing = $stmt->fetch();
        } catch (PDOException $e) {
            // Continue
        }
        
        if ($existing) {
            continue; // Skip existing
        }
        
        // Calculate avg daily usage based on target max (rough estimate)
        $estimatedDailyUsage = $inv['target_max'] > 0 ? ($inv['target_max'] / 7) : 0;
        
        $data = [
            'store_id' => $store['id'],
            'product_id' => $productIds[$inv['sku']],
            'on_hand' => $inv['on_hand'],
            'reorder_point' => $inv['reorder_point'],
            'target_max' => $inv['target_max'],
            'avg_daily_usage' => $estimatedDailyUsage,
            'days_of_stock' => 7,
            'vendor_id' => $vendorIds[$inv['vendor']],
            'vendor_sku' => $inv['vendor_sku'],
            'lead_time_days' => $inv['lead_time'],
            'unit_cost' => $inv['unit_cost']
        ];
        
        if (saveInventory($data)) {
            $inventoryCount++;
        } else {
            echo "    Error saving inventory for {$inv['sku']}\n";
        }
    }
    
    // Set all inventory on_hand to 25 for this store (so existing rows get 25 too)
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("UPDATE inventory SET on_hand = 25 WHERE store_id = ?");
        $stmt->execute([$store['id']]);
        echo "  Set on_hand = 25 for all inventory in {$store['name']}\n";
    } catch (PDOException $e) {
        echo "  Warning: could not update on_hand: " . $e->getMessage() . "\n";
    }
    
    echo "  Created $inventoryCount inventory items\n\n";
    
    // Seed historical daily on-hand snapshots and daily sales (last 90 days — all snapshots = 25 so recalc uses 25)
    echo "Seeding historical daily data for: {$store['name']} (last 90 days)...\n";
    $pdo = getDB();
    $today = new DateTime();
    $snapshotCount = 0;
    $salesCount = 0;
    $purchaseCount = 0;
    
    foreach ($inventoryData as $inv) {
        if (!isset($productIds[$inv['sku']])) continue;
        
        $productId = $productIds[$inv['sku']];
        $baseSales = $inv['target_max'] > 0 ? ($inv['target_max'] / 7) : 5;
        
        for ($daysAgo = 90; $daysAgo >= 0; $daysAgo--) {
            $date = clone $today;
            $date->modify("-$daysAgo days");
            $dateStr = $date->format('Y-m-d');
            
            $dayOfWeek = (int)$date->format('w');
            if ($dayOfWeek == 0 || $dayOfWeek == 6) {
                $dailySales = $baseSales * (0.5 + (rand(0, 30) / 100));
            } else {
                $dailySales = $baseSales * (0.8 + (rand(0, 40) / 100));
            }
            
            // All snapshots = 25 so recalc starting point is 25; entering sales will visibly reduce On Hand
            $onHand = 25;
            
            // Save snapshot
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO inventory_snapshots (store_id, product_id, snapshot_date, on_hand, updated_at)
                    VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                    ON CONFLICT (store_id, product_id, snapshot_date)
                    DO UPDATE SET on_hand = EXCLUDED.on_hand, updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$store['id'], $productId, $dateStr, $onHand]);
                $snapshotCount++;
            } catch (PDOException $e) {
                // Skip if error
            }
            
            // Save daily sales
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO product_daily_sales (store_id, product_id, sale_date, quantity_sold, updated_at)
                    VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                    ON CONFLICT (store_id, product_id, sale_date)
                    DO UPDATE SET quantity_sold = EXCLUDED.quantity_sold, updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$store['id'], $productId, $dateStr, $dailySales]);
                $salesCount++;
            } catch (PDOException $e) {
                // Skip if error
            }
        }
    }
    
    // Seed manual daily purchases (product_daily_purchases) for a few products to show that scenario
    echo "Seeding manual daily purchases (sample scenarios)...\n";
    try {
        foreach (['TOP10-ITEM-2', 'TOP10-ITEM-4', 'TOP10-ITEM-8'] as $sku) {
            if (!isset($productIds[$sku])) continue;
            $productId = $productIds[$sku];
            for ($d = 1; $d <= 90; $d += 12) { // Every ~12 days a manual purchase
                $date = clone $today;
                $date->modify("-$d days");
                $dateStr = $date->format('Y-m-d');
                $qty = $sku === 'TOP10-ITEM-2' ? 25 : ($sku === 'TOP10-ITEM-4' ? 10 : 5);
                $stmt = $pdo->prepare("
                    INSERT INTO product_daily_purchases (store_id, product_id, purchase_date, quantity_received, updated_at)
                    VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                    ON CONFLICT (store_id, product_id, purchase_date)
                    DO UPDATE SET quantity_received = EXCLUDED.quantity_received, updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$store['id'], $productId, $dateStr, $qty]);
                $purchaseCount++;
            }
        }
        echo "  Created $purchaseCount manual purchase records\n";
    } catch (PDOException $e) {
        echo "  Note: product_daily_purchases table may not exist: " . $e->getMessage() . "\n";
    }
    
    // Seed orders: some ORDERED (pending), some RECEIVED (so purchases from orders show in chart)
    echo "Seeding orders (pending + received scenarios)...\n";
    $orderCount = 0;
    $yesterday = (clone $today)->modify('-1 day')->format('Y-m-d');
    $threeDaysAgo = (clone $today)->modify('-3 days')->format('Y-m-d');
    $lastWeek = (clone $today)->modify('-7 days')->format('Y-m-d');
    
    $orderScenarios = [
        ['sku' => 'TOP10-ITEM-5', 'vendor' => 'VAPORBEAST', 'qty' => 30, 'unit_cost' => 22.50, 'status' => 'ORDERED', 'order_date' => null, 'received_date' => null],
        ['sku' => 'TOP10-ITEM-9', 'vendor' => 'VAPORBEAST', 'qty' => 10, 'unit_cost' => 45.00, 'status' => 'ORDERED', 'order_date' => null, 'received_date' => null],
        ['sku' => 'TOP10-ITEM-3', 'vendor' => 'PANHANDLE WHOLESALE', 'qty' => 20, 'unit_cost' => 12.00, 'status' => 'ORDERED', 'order_date' => null, 'received_date' => null],
        ['sku' => 'TOP10-ITEM-2', 'vendor' => 'CALI EXTRA X', 'qty' => 50, 'unit_cost' => 8.50, 'status' => 'RECEIVED', 'order_date' => $threeDaysAgo, 'received_date' => $yesterday],
        ['sku' => 'TOP10-ITEM-8', 'vendor' => 'CALI EXTRA X', 'qty' => 15, 'unit_cost' => 35.00, 'status' => 'RECEIVED', 'order_date' => $lastWeek, 'received_date' => $threeDaysAgo],
        ['sku' => 'TOP10-ITEM-6', 'vendor' => 'CALI EXTRA X', 'qty' => 25, 'unit_cost' => 18.00, 'status' => 'RECEIVED', 'order_date' => $lastWeek, 'received_date' => $yesterday],
    ];
    
    foreach ($orderScenarios as $ord) {
        if (!isset($productIds[$ord['sku']]) || !isset($vendorIds[$ord['vendor']])) continue;
        $productId = $productIds[$ord['sku']];
        $vendorId = $vendorIds[$ord['vendor']];
        $orderDate = $ord['order_date'] ?? (clone $today)->modify('-2 days')->format('Y-m-d');
        $data = [
            'store_id' => $store['id'],
            'vendor_id' => $vendorId,
            'product_id' => $productId,
            'quantity' => $ord['qty'],
            'unit_cost' => $ord['unit_cost'],
            'status' => $ord['status'],
            'order_date' => $orderDate,
            'received_date' => $ord['received_date'],
        ];
        if (saveOrder($data)) {
            $orderCount++;
        }
    }
    echo "  Created $orderCount orders (pending + received)\n";
    echo "  Total: $snapshotCount snapshots, $salesCount daily sales\n\n";
}

echo "=== Seed Complete ===\n";
echo "Products: " . count($productIds) . "\n";
echo "Vendors: " . count($vendorIds) . "\n";
echo "\nYou can now view the inventory at: http://localhost:8001/\n";

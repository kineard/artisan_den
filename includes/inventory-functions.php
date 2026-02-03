<?php
require_once __DIR__ . '/../config.php';

// Product functions
function getAllProducts() {
    try {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT * FROM products ORDER BY name");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting products: " . $e->getMessage());
        return [];
    }
}

function getProduct($id) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error getting product: " . $e->getMessage());
        return null;
    }
}

function saveProduct($data) {
    try {
        $pdo = getDB();
        if (isset($data['id']) && $data['id']) {
            $stmt = $pdo->prepare("UPDATE products SET sku = ?, name = ?, description = ?, unit_type = ? WHERE id = ?");
            return $stmt->execute([$data['sku'], $data['name'], $data['description'] ?? null, $data['unit_type'] ?? 'unit', $data['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO products (sku, name, description, unit_type) VALUES (?, ?, ?, ?)");
            return $stmt->execute([$data['sku'], $data['name'], $data['description'] ?? null, $data['unit_type'] ?? 'unit']);
        }
    } catch (PDOException $e) {
        error_log("Error saving product: " . $e->getMessage());
        return false;
    }
}

function deleteProduct($id) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log("Error deleting product: " . $e->getMessage());
        return false;
    }
}

// Vendor functions
function getAllVendors() {
    try {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT * FROM vendors ORDER BY is_preferred DESC, name");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting vendors: " . $e->getMessage());
        return [];
    }
}

function getVendor($id) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error getting vendor: " . $e->getMessage());
        return null;
    }
}

function saveVendor($data) {
    try {
        $pdo = getDB();
        $rating = isset($data['rating']) ? intval($data['rating']) : 3;
        $rating = max(1, min(5, $rating)); // Ensure 1-5 range
        
        // Convert to PostgreSQL-safe boolean strings (PDO can send PHP false as '' which PG rejects)
        $isPreferred = false;
        if (isset($data['is_preferred'])) {
            $val = $data['is_preferred'];
            $isPreferred = ($val === true || $val === 'true' || $val === '1' || $val === 1);
        }
        $isActive = true;
        if (isset($data['is_active'])) {
            $val = $data['is_active'];
            $isActive = ($val === true || $val === 'true' || $val === '1' || $val === 1);
        }
        $isPreferredPg = $isPreferred ? 'true' : 'false';
        $isActivePg = $isActive ? 'true' : 'false';

        if (isset($data['id']) && $data['id']) {
            $stmt = $pdo->prepare("UPDATE vendors SET name = ?, contact_name = ?, phone = ?, email = ?, order_method = ?, cutoff_time = ?, typical_lead_time = ?, shipping_speed_notes = ?, free_ship_threshold = ?, account_info = ?, password = ?, notes = ?, rating = ?, is_preferred = ?::boolean, is_active = ?::boolean WHERE id = ?");
            return $stmt->execute([
                $data['name'], $data['contact_name'] ?? null, $data['phone'] ?? null, $data['email'] ?? null,
                $data['order_method'] ?? null, $data['cutoff_time'] ?? null, $data['typical_lead_time'] ?? null,
                $data['shipping_speed_notes'] ?? null, $data['free_ship_threshold'] ?? null,
                $data['account_info'] ?? null, $data['password'] ?? null, $data['notes'] ?? null,
                $rating,
                $isPreferredPg,
                $isActivePg,
                $data['id']
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO vendors (name, contact_name, phone, email, order_method, cutoff_time, typical_lead_time, shipping_speed_notes, free_ship_threshold, account_info, password, notes, rating, is_preferred, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?::boolean, ?::boolean)");
            return $stmt->execute([
                $data['name'], $data['contact_name'] ?? null, $data['phone'] ?? null, $data['email'] ?? null,
                $data['order_method'] ?? null, $data['cutoff_time'] ?? null, $data['typical_lead_time'] ?? null,
                $data['shipping_speed_notes'] ?? null, $data['free_ship_threshold'] ?? null,
                $data['account_info'] ?? null, $data['password'] ?? null, $data['notes'] ?? null,
                $rating,
                $isPreferredPg,
                $isActivePg
            ]);
        }
    } catch (PDOException $e) {
        error_log("Error saving vendor: " . $e->getMessage());
        return false;
    }
}

function deleteVendor($id) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("DELETE FROM vendors WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log("Error deleting vendor: " . $e->getMessage());
        return false;
    }
}

// Inventory functions
function getInventoryForStore($storeId, $sortBy = 'status') {
    try {
        $pdo = getDB();
        
        // Build ORDER BY clause based on sortBy
        $orderBy = 'p.name ASC';
        switch ($sortBy) {
            case 'status':
                $orderBy = 'CASE 
                    WHEN i.on_hand <= 0 THEN 1
                    WHEN i.on_hand <= i.reorder_point THEN 2
                    ELSE 3
                END, p.name ASC';
                break;
            case 'suggested_order':
                $orderBy = '(i.target_max - i.on_hand) DESC, p.name ASC';
                break;
            case 'vendor_rating':
                $orderBy = 'v.rating DESC NULLS LAST, p.name ASC';
                break;
            case 'avg_7day_sales':
                $orderBy = 'avg_7day DESC NULLS LAST, p.name ASC';
                break;
            case 'product_name':
            default:
                $orderBy = 'p.name ASC';
                break;
        }
        
        // Get pending orders for this store (status = 'ORDERED' and no received_date)
        $pendingOrdersStmt = $pdo->prepare("
            SELECT id, product_id, order_date, expected_delivery_date, quantity, status
            FROM orders
            WHERE store_id = ? AND status = 'ORDERED' AND received_date IS NULL
        ");
        $pendingOrdersStmt->execute([$storeId]);
        $pendingOrders = [];
        foreach ($pendingOrdersStmt->fetchAll(PDO::FETCH_ASSOC) as $order) {
            $pendingOrders[$order['product_id']] = $order;
        }
        
        // Get 7-day and 30-day averages for all products
        $asOfDate = date('Y-m-d');
        $averages = getProductAveragesForStore($storeId, $asOfDate);
        
        // For avg_7day_sales sort, we need to sort in PHP after adding averages
        $needsPhpSort = ($sortBy === 'avg_7day_sales');
        if ($needsPhpSort) {
            // Use default sort for SQL, will sort by avg_7day in PHP after
            $orderBy = 'p.name ASC';
        }
        
        $stmt = $pdo->prepare("
            SELECT i.*, p.sku, p.name as product_name, p.unit_type, 
                   v.name as vendor_name, v.phone as vendor_phone, v.email as vendor_email, v.rating as vendor_rating,
                   sub.sku as substitution_sku, sub.name as substitution_name
            FROM inventory i
            JOIN products p ON i.product_id = p.id
            LEFT JOIN vendors v ON i.vendor_id = v.id
            LEFT JOIN products sub ON i.substitution_product_id = sub.id
            WHERE i.store_id = ?
            ORDER BY $orderBy
        ");
        $stmt->execute([$storeId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add pending order info and averages to each item
        foreach ($items as &$item) {
            $pid = $item['product_id'];
            $item['pending_order'] = $pendingOrders[$pid] ?? null;
            $item['has_pending_order'] = !empty($pendingOrders[$pid]);
            $item['avg_7day_sales'] = $averages[$pid]['avg_7day'] ?? null;
            $item['avg_30day_sales'] = $averages[$pid]['avg_30day'] ?? null;
        }
        
        // Sort by 7-day average if requested (after adding averages)
        if ($needsPhpSort) {
            usort($items, function($a, $b) {
                $avgA = $a['avg_7day_sales'] ?? 0;
                $avgB = $b['avg_7day_sales'] ?? 0;
                if ($avgA == $avgB) {
                    // Secondary sort by product name
                    return strcmp($a['product_name'], $b['product_name']);
                }
                return $avgB <=> $avgA; // Descending (highest first)
            });
        }
        
        return $items;
    } catch (PDOException $e) {
        error_log("Error getting inventory: " . $e->getMessage());
        return [];
    }
}

function getInventoryItem($storeId, $productId) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM inventory WHERE store_id = ? AND product_id = ?");
        $stmt->execute([$storeId, $productId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error getting inventory item: " . $e->getMessage());
        return null;
    }
}

function getInventoryById($id) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT i.*, p.sku, p.name as product_name, p.unit_type
            FROM inventory i
            JOIN products p ON i.product_id = p.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting inventory by id: " . $e->getMessage());
        return null;
    }
}

function saveInventory($data) {
    try {
        $pdo = getDB();
        $existing = getInventoryItem($data['store_id'], $data['product_id']);
        
        // Auto-calculate reorder point and target max if avg_daily_usage is provided
        $reorderPoint = $data['reorder_point'] ?? 0;
        $targetMax = $data['target_max'] ?? 0;
        $daysOfStock = $data['days_of_stock'] ?? 7;
        
        if (isset($data['avg_daily_usage']) && $data['avg_daily_usage'] > 0) {
            $leadTime = $data['lead_time_days'] ?? 0;
            if ($reorderPoint == 0) {
                $reorderPoint = calculateReorderPointForDays($data['avg_daily_usage'], $leadTime, $daysOfStock);
            }
            if ($targetMax == 0) {
                $targetMax = calculateTargetMaxForDays($data['avg_daily_usage'], $daysOfStock);
            }
        }
        
        if ($existing) {
            $stmt = $pdo->prepare("UPDATE inventory SET on_hand = ?, reorder_point = ?, target_max = ?, avg_daily_usage = ?, days_of_stock = ?, vendor_id = ?, vendor_sku = ?, vendor_link = ?, lead_time_days = ?, unit_cost = ?, substitution_product_id = ?, notes = ? WHERE id = ?");
            return $stmt->execute([
                $data['on_hand'] ?? 0, $reorderPoint, $targetMax,
                $data['avg_daily_usage'] ?? 0, $daysOfStock,
                $data['vendor_id'] ?? null, $data['vendor_sku'] ?? null, $data['vendor_link'] ?? null,
                $data['lead_time_days'] ?? 0, $data['unit_cost'] ?? 0,
                $data['substitution_product_id'] ?? null,
                $data['notes'] ?? null,
                $existing['id']
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO inventory (store_id, product_id, on_hand, reorder_point, target_max, avg_daily_usage, days_of_stock, vendor_id, vendor_sku, vendor_link, lead_time_days, unit_cost, substitution_product_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            return $stmt->execute([
                $data['store_id'], $data['product_id'], $data['on_hand'] ?? 0, $reorderPoint, $targetMax,
                $data['avg_daily_usage'] ?? 0, $daysOfStock,
                $data['vendor_id'] ?? null, $data['vendor_sku'] ?? null,
                $data['vendor_link'] ?? null, $data['lead_time_days'] ?? 0, $data['unit_cost'] ?? 0,
                $data['substitution_product_id'] ?? null,
                $data['notes'] ?? null
            ]);
        }
    } catch (PDOException $e) {
        error_log("Error saving inventory: " . $e->getMessage());
        return false;
    }
}

function calculateInventoryStatus($onHand, $reorderPoint) {
    if ($onHand <= 0) return 'OUT';
    if ($onHand <= $reorderPoint) return 'LOW';
    return 'OK';
}

function calculateSuggestedOrder($onHand, $targetMax, $status) {
    if ($status === 'OK') return 0;
    return max(0, $targetMax - $onHand);
}

function calculateReorderPointForDays($avgDailyUsage, $leadTimeDays, $daysOfStock = 7) {
    // Reorder point = (avg daily usage * lead time) + (avg daily usage * days of stock)
    // This ensures we have enough stock to last through lead time + target days
    return ($avgDailyUsage * $leadTimeDays) + ($avgDailyUsage * $daysOfStock);
}

function calculateTargetMaxForDays($avgDailyUsage, $daysOfStock = 7) {
    // Target max = average daily usage * days of stock
    return $avgDailyUsage * $daysOfStock;
}

/**
 * Calculate suggested order quantity, excluding items with pending orders
 * Uses 7-day average if available, otherwise uses avg_daily_usage
 */
function calculateSuggestedOrderWithAverages($onHand, $targetMax, $reorderPoint, $hasPendingOrder, $avg7Day = null, $avgDailyUsage = null) {
    // Don't suggest order if there's a pending order
    if ($hasPendingOrder) {
        return 0;
    }
    
    // Use 7-day average for calculation if available, otherwise use avg_daily_usage
    $status = calculateInventoryStatus($onHand, $reorderPoint);
    if ($status === 'OK') {
        return 0;
    }
    
    // Calculate suggested order based on target_max - on_hand
    return max(0, $targetMax - $onHand);
}

function calculateSuggestedOrderForDays($onHand, $avgDailyUsage, $leadTimeDays, $daysOfStock = 7) {
    // Suggested order = enough to last (lead time + days of stock) - current on hand
    $needed = ($avgDailyUsage * $leadTimeDays) + ($avgDailyUsage * $daysOfStock);
    return max(0, $needed - $onHand);
}

function getProductSalesHistory($storeId, $productId, $days = 30) {
    // This would need to be implemented based on actual sales data
    // For now, return null - will be calculated from historical data when available
    return null;
}

// Order functions
function getOrdersForStore($storeId, $status = null) {
    try {
        $pdo = getDB();
        if ($status) {
            $stmt = $pdo->prepare("
                SELECT o.*, p.sku, p.name as product_name, v.name as vendor_name
                FROM orders o
                JOIN products p ON o.product_id = p.id
                JOIN vendors v ON o.vendor_id = v.id
                WHERE o.store_id = ? AND o.status = ?
                ORDER BY o.created_at DESC
            ");
            $stmt->execute([$storeId, $status]);
        } else {
            $stmt = $pdo->prepare("
                SELECT o.*, p.sku, p.name as product_name, v.name as vendor_name
                FROM orders o
                JOIN products p ON o.product_id = p.id
                JOIN vendors v ON o.vendor_id = v.id
                WHERE o.store_id = ?
                ORDER BY o.created_at DESC
            ");
            $stmt->execute([$storeId]);
        }
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting orders: " . $e->getMessage());
        return [];
    }
}

function saveOrder($data) {
    try {
        $pdo = getDB();
        $totalCost = ($data['quantity'] ?? 0) * ($data['unit_cost'] ?? 0);
        $orderDate = $data['order_date'] ?? date('Y-m-d');
        
        // Calculate expected_delivery_date: order_date + lead_time_days
        $expectedDeliveryDate = null;
        if (!empty($data['vendor_id']) && !empty($data['product_id'])) {
            // Get lead_time_days from inventory for this product
            $invStmt = $pdo->prepare("SELECT lead_time_days FROM inventory WHERE store_id = ? AND product_id = ?");
            $invStmt->execute([$data['store_id'], $data['product_id']]);
            $inventory = $invStmt->fetch(PDO::FETCH_ASSOC);
            $leadTimeDays = $inventory ? (int)($inventory['lead_time_days'] ?? 0) : 0;
            
            if ($leadTimeDays > 0) {
                $orderDateObj = new DateTime($orderDate);
                $orderDateObj->modify("+{$leadTimeDays} days");
                $expectedDeliveryDate = $orderDateObj->format('Y-m-d');
            }
        }
        
        if (isset($data['id']) && $data['id']) {
            $stmt = $pdo->prepare("UPDATE orders SET quantity = ?, unit_cost = ?, total_cost = ?, status = ?, order_date = ?, received_date = ?, expected_delivery_date = ?, notes = ? WHERE id = ?");
            return $stmt->execute([
                $data['quantity'] ?? 0, $data['unit_cost'] ?? 0, $totalCost,
                $data['status'] ?? 'REQUESTED', $orderDate, $data['received_date'] ?? null,
                $expectedDeliveryDate, $data['notes'] ?? null, $data['id']
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO orders (store_id, vendor_id, product_id, quantity, unit_cost, total_cost, status, order_date, received_date, expected_delivery_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            return $stmt->execute([
                $data['store_id'], $data['vendor_id'], $data['product_id'],
                $data['quantity'] ?? 0, $data['unit_cost'] ?? 0, $totalCost,
                $data['status'] ?? 'ORDERED', $orderDate,
                !empty($data['received_date']) ? $data['received_date'] : null,
                $expectedDeliveryDate,
                $data['notes'] ?? null
            ]);
        }
    } catch (PDOException $e) {
        error_log("Error saving order: " . $e->getMessage());
        return false;
    }
}

// Inventory snapshots (daily on-hand per product/store) for tracking and extrapolated sales
function getInventorySnapshotsMap($storeId, $startDate, $endDate) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT product_id, snapshot_date, on_hand
            FROM inventory_snapshots
            WHERE store_id = ? AND snapshot_date >= ? AND snapshot_date <= ?
            ORDER BY snapshot_date
        ");
        $stmt->execute([$storeId, $startDate, $endDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($rows as $r) {
            $map[$r['product_id']][$r['snapshot_date']] = (float)$r['on_hand'];
        }
        return $map;
    } catch (PDOException $e) {
        error_log("Error getting inventory snapshots: " . $e->getMessage());
        return [];
    }
}

function saveInventorySnapshot($storeId, $productId, $snapshotDate, $onHand) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO inventory_snapshots (store_id, product_id, snapshot_date, on_hand, updated_at)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT (store_id, product_id, snapshot_date)
            DO UPDATE SET on_hand = EXCLUDED.on_hand, updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$storeId, $productId, $snapshotDate, $onHand]);
        return true;
    } catch (PDOException $e) {
        error_log("Error saving inventory snapshot: " . $e->getMessage());
        return false;
    }
}

/**
 * Update inventory.on_hand for a (store, product). Used when saving a Daily On-Hand cell
 * so the Inventory & Reorder list reflects the latest inputted value.
 */
function updateInventoryOnHandFromSnapshot($storeId, $productId, $onHand) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("UPDATE inventory SET on_hand = ?, updated_at = CURRENT_TIMESTAMP WHERE store_id = ? AND product_id = ?");
        $stmt->execute([$onHand, $storeId, $productId]);
        return true;
    } catch (PDOException $e) {
        error_log("Error updating inventory on_hand: " . $e->getMessage());
        return false;
    }
}

/** Received qty per product per date (orders with status RECEIVED, received_date set) */
function getReceivedByProductDate($storeId, $startDate, $endDate) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT product_id, received_date, COALESCE(SUM(quantity), 0) as qty
            FROM orders
            WHERE store_id = ? AND status = 'RECEIVED' AND received_date IS NOT NULL
              AND received_date >= ? AND received_date <= ?
            GROUP BY product_id, received_date
        ");
        $stmt->execute([$storeId, $startDate, $endDate]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[$r['product_id']][$r['received_date']] = (float)$r['qty'];
        }
        return $map;
    } catch (PDOException $e) {
        error_log("Error getting received by product/date: " . $e->getMessage());
        return [];
    }
}

/** Sales per product per date from product_daily_sales (user-entered, no extrapolation) */
function getSalesByProductDate($storeId, $startDate, $endDate) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT product_id, sale_date, COALESCE(quantity_sold, 0) as qty
            FROM product_daily_sales
            WHERE store_id = ? AND sale_date >= ? AND sale_date <= ?
        ");
        $stmt->execute([$storeId, $startDate, $endDate]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[$r['product_id']][$r['sale_date']] = (float)$r['qty'];
        }
        return $map;
    } catch (PDOException $e) {
        error_log("Error getting sales by product/date: " . $e->getMessage());
        return [];
    }
}

/** Manual purchases per product per date (product_daily_purchases table) */
function getManualPurchasesByProductDate($storeId, $startDate, $endDate) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT product_id, purchase_date, COALESCE(quantity_received, 0) as qty
            FROM product_daily_purchases
            WHERE store_id = ? AND purchase_date >= ? AND purchase_date <= ?
        ");
        $stmt->execute([$storeId, $startDate, $endDate]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[$r['product_id']][$r['purchase_date']] = (float)$r['qty'];
        }
        return $map;
    } catch (PDOException $e) {
        error_log("Error getting manual purchases by product/date: " . $e->getMessage());
        return [];
    }
}

/** Total purchases per product per date = received from orders + manual entries */
function getPurchasesByProductDate($storeId, $startDate, $endDate) {
    $received = getReceivedByProductDate($storeId, $startDate, $endDate);
    $manual = getManualPurchasesByProductDate($storeId, $startDate, $endDate);
    $allProducts = array_unique(array_merge(array_keys($received), array_keys($manual)));
    $dates = generateDateArray($startDate, $endDate);
    $map = [];
    foreach ($allProducts as $pid) {
        foreach ($dates as $d) {
            $r = isset($received[$pid][$d]) ? $received[$pid][$d] : 0;
            $m = isset($manual[$pid][$d]) ? $manual[$pid][$d] : 0;
            $map[$pid][$d] = $r + $m;
        }
    }
    return $map;
}

/**
 * Save or update manual daily purchase for a product
 */
function saveProductDailyPurchase($storeId, $productId, $purchaseDate, $quantity) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO product_daily_purchases (store_id, product_id, purchase_date, quantity_received, updated_at)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT (store_id, product_id, purchase_date)
            DO UPDATE SET quantity_received = EXCLUDED.quantity_received, updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$storeId, $productId, $purchaseDate, $quantity]);
        return true;
    } catch (PDOException $e) {
        error_log("Error saving product daily purchase: " . $e->getMessage());
        return false;
    }
}

/**
 * Recalculate and save on-hand for a date range using: on_hand[d] = on_hand[d-1] + purchases[d] - sales[d].
 * Starting on-hand for first date = snapshot(day before start) or inventory.on_hand.
 * Saves to inventory_snapshots; updates inventory.on_hand for the last date in range.
 */
function recalcAndSaveOnHandForRange($storeId, $startDate, $endDate, array $productIds = []) {
    $dates = generateDateArray($startDate, $endDate);
    if (empty($dates)) return false;
    $snapshotsMap = getInventorySnapshotsMap($storeId, $startDate, $endDate);
    $salesMap = getSalesByProductDate($storeId, $startDate, $endDate);
    $purchasesMap = getPurchasesByProductDate($storeId, $startDate, $endDate);
    $dayBeforeStart = (new DateTime($startDate))->modify('-1 day')->format('Y-m-d');
    $snapshotsDayBefore = getInventorySnapshotsMap($storeId, $dayBeforeStart, $dayBeforeStart);

    try {
        $pdo = getDB();
        $invStmt = $pdo->prepare("SELECT product_id, on_hand FROM inventory WHERE store_id = ?");
        $invStmt->execute([$storeId]);
        $invOnHand = [];
        while ($row = $invStmt->fetch(PDO::FETCH_ASSOC)) {
            $invOnHand[$row['product_id']] = (float)$row['on_hand'];
        }

        $products = !empty($productIds) ? $productIds : array_unique(array_merge(
            array_keys($salesMap),
            array_keys($purchasesMap),
            array_keys($snapshotsMap),
            array_keys($invOnHand)
        ));

        foreach ($products as $pid) {
            $prev = isset($snapshotsDayBefore[$pid][$dayBeforeStart])
                ? $snapshotsDayBefore[$pid][$dayBeforeStart]
                : (isset($invOnHand[$pid]) ? $invOnHand[$pid] : 0);
            foreach ($dates as $d) {
                $sales = isset($salesMap[$pid][$d]) ? $salesMap[$pid][$d] : 0;
                $purch = isset($purchasesMap[$pid][$d]) ? $purchasesMap[$pid][$d] : 0;
                $onHand = $prev + $purch - $sales;
                $onHand = max(0, $onHand);
                saveInventorySnapshot($storeId, $pid, $d, $onHand);
                $prev = $onHand;
            }
            updateInventoryOnHandFromSnapshot($storeId, $pid, $prev);
        }
        return true;
    } catch (PDOException $e) {
        error_log("Error recalc on-hand: " . $e->getMessage());
        return false;
    }
}

/** Extrapolated sales per product per date: prev_on_hand + received - curr_on_hand */
function getExtrapolatedSalesByProductDate($storeId, $startDate, $endDate, $snapshotsMap, $receivedMap) {
    $dates = generateDateArray($startDate, $endDate);
    $sales = [];
    foreach (array_keys($snapshotsMap) as $pid) {
        $prev = null;
        foreach ($dates as $d) {
            $curr = $snapshotsMap[$pid][$d] ?? null;
            $rec = isset($receivedMap[$pid][$d]) ? $receivedMap[$pid][$d] : 0;
            if ($prev !== null && $curr !== null) {
                $s = $prev + $rec - $curr;
                $sales[$pid][$d] = max(0, $s);
                // Store daily sales in product_daily_sales table
                saveProductDailySales($storeId, $pid, $d, $sales[$pid][$d]);
            } elseif ($curr !== null) {
                $sales[$pid][$d] = 0;
                saveProductDailySales($storeId, $pid, $d, 0);
            }
            if ($curr !== null) {
                $prev = $curr;
            }
        }
    }
    return $sales;
}

/**
 * Save or update daily sales for a product
 */
function saveProductDailySales($storeId, $productId, $saleDate, $quantitySold) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO product_daily_sales (store_id, product_id, sale_date, quantity_sold, updated_at)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT (store_id, product_id, sale_date)
            DO UPDATE SET quantity_sold = EXCLUDED.quantity_sold, updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$storeId, $productId, $saleDate, $quantitySold]);
        return true;
    } catch (PDOException $e) {
        error_log("Error saving product daily sales: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculate 7-day average sales for a product
 * Returns average or null if less than 7 days of data
 */
function get7DayAverageSales($storeId, $productId, $asOfDate = null) {
    if ($asOfDate === null) {
        $asOfDate = date('Y-m-d');
    }
    try {
        $pdo = getDB();
        $endDate = new DateTime($asOfDate);
        $startDate = clone $endDate;
        $startDate->modify('-6 days'); // 7 days total (including today)
        
        $stmt = $pdo->prepare("
            SELECT COALESCE(AVG(quantity_sold), 0) as avg_sales, COUNT(*) as days_count
            FROM product_daily_sales
            WHERE store_id = ? AND product_id = ? 
              AND sale_date >= ? AND sale_date <= ?
        ");
        $stmt->execute([$storeId, $productId, $startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Require minimum 7 days of data
        if ($result && (int)$result['days_count'] >= 7) {
            return (float)$result['avg_sales'];
        }
        return null; // Not enough data
    } catch (PDOException $e) {
        error_log("Error calculating 7-day average: " . $e->getMessage());
        return null;
    }
}

/**
 * Calculate 30-day average sales for a product
 * Returns average or null if less than 30 days of data (for display as "N/A")
 */
function get30DayAverageSales($storeId, $productId, $asOfDate = null) {
    if ($asOfDate === null) {
        $asOfDate = date('Y-m-d');
    }
    try {
        $pdo = getDB();
        $endDate = new DateTime($asOfDate);
        $startDate = clone $endDate;
        $startDate->modify('-29 days'); // 30 days total (including today)
        
        $stmt = $pdo->prepare("
            SELECT COALESCE(AVG(quantity_sold), 0) as avg_sales, COUNT(*) as days_count
            FROM product_daily_sales
            WHERE store_id = ? AND product_id = ? 
              AND sale_date >= ? AND sale_date <= ?
        ");
        $stmt->execute([$storeId, $productId, $startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Require minimum 30 days for valid average
        if ($result && (int)$result['days_count'] >= 30) {
            return (float)$result['avg_sales'];
        }
        return null; // Not enough data (will display as "N/A")
    } catch (PDOException $e) {
        error_log("Error calculating 30-day average: " . $e->getMessage());
        return null;
    }
}

/**
 * Get averages for all products in a store (for bulk display)
 * Returns array: [product_id => ['avg_7day' => value, 'avg_30day' => value]]
 */
function getProductAveragesForStore($storeId, $asOfDate = null) {
    if ($asOfDate === null) {
        $asOfDate = date('Y-m-d');
    }
    $averages = [];
    try {
        $pdo = getDB();
        $products = $pdo->prepare("SELECT DISTINCT product_id FROM inventory WHERE store_id = ?");
        $products->execute([$storeId]);
        $productIds = $products->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($productIds as $pid) {
            $averages[$pid] = [
                'avg_7day' => get7DayAverageSales($storeId, $pid, $asOfDate),
                'avg_30day' => get30DayAverageSales($storeId, $pid, $asOfDate)
            ];
        }
    } catch (PDOException $e) {
        error_log("Error getting product averages: " . $e->getMessage());
    }
    return $averages;
}

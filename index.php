<?php
require_once 'config.php';
require_once 'includes/helpers.php';
require_once 'includes/functions.php';
require_once 'includes/inventory-functions.php';
$action = $_GET['action'] ?? 'dashboard';
$storeId = isset($_GET['store']) ? intval($_GET['store']) : null;
$date = $_GET['date'] ?? date('Y-m-d');
$view = $_GET['view'] ?? 'week'; // week, month, or custom
$customDays = isset($_GET['days']) ? intval($_GET['days']) : 7;
$chartProductId = isset($_GET['chart_product_id']) ? intval($_GET['chart_product_id']) : null;
$chartDays = isset($_GET['chart_days']) ? intval($_GET['chart_days']) : 30;
if (!in_array($chartDays, [7, 30, 90], true)) $chartDays = 30;
$stores = getAllStores();
if (!$storeId && !empty($stores)) {
    $storeId = $stores[0]['id'];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_kpi'])) {
    // Handle bulk save for multiple dates
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
        // Single date save (backward compatibility)
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
    $storeIdParam = isset($_POST['store_id']) ? intval($_POST['store_id']) : $storeId;
    header("Location: index.php?action=dashboard&store={$storeIdParam}&date={$date}{$viewParam}");
    exit;
}

// Handle inventory operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_product'])) {
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
        header("Location: index.php?action=dashboard&store={$storeId}&date={$date}&view={$view}");
        exit;
    }
    
    if (isset($_POST['delete_product'])) {
        if (deleteProduct(intval($_POST['product_id']))) {
            $successMessage = "Product deleted successfully!";
        } else {
            $errorMessage = "Error deleting product.";
        }
        header("Location: index.php?action=dashboard&store={$storeId}&date={$date}&view={$view}");
        exit;
    }
    
    if (isset($_POST['save_vendor'])) {
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
        header("Location: index.php?action=dashboard&store={$storeId}&date={$date}&view={$view}");
        exit;
    }
    
    if (isset($_POST['delete_vendor'])) {
        if (deleteVendor(intval($_POST['vendor_id']))) {
            $successMessage = "Vendor deleted successfully!";
        } else {
            $errorMessage = "Error deleting vendor.";
        }
        header("Location: index.php?action=dashboard&store={$storeId}&date={$date}&view={$view}");
        exit;
    }
    
    // Quick update for on_hand (AJAX) — updates inventory.on_hand and optionally saves daily snapshot
    if (isset($_POST['quick_update_on_hand']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
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
    
    // Save daily on-hand snapshot (inventory table date cell) — only update inventory.on_hand when saving the entry date
    if (isset($_POST['save_snapshot']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        $storeId = intval($_POST['store_id'] ?? 0);
        $productId = intval($_POST['product_id'] ?? 0);
        $snapshotDate = isset($_POST['snapshot_date']) ? $_POST['snapshot_date'] : null;
        $entryDate = isset($_POST['entry_date']) ? $_POST['entry_date'] : null;
        $onHand = floatval($_POST['on_hand'] ?? 0);
        if ($storeId > 0 && $productId > 0 && $snapshotDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $snapshotDate)) {
            if (saveInventorySnapshot($storeId, $productId, $snapshotDate, $onHand)) {
                if ($entryDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate) && $snapshotDate === $entryDate) {
                    updateInventoryOnHandFromSnapshot($storeId, $productId, $onHand);
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

    // Save daily sale (user-entered sales; V2 logic)
    if (isset($_POST['save_daily_sale']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        $storeId = intval($_POST['store_id'] ?? 0);
        $productId = intval($_POST['product_id'] ?? 0);
        $saleDate = isset($_POST['sale_date']) ? $_POST['sale_date'] : null;
        $quantity = floatval($_POST['quantity'] ?? 0);
        if ($storeId > 0 && $productId > 0 && $saleDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $saleDate)) {
            if (saveProductDailySales($storeId, $productId, $saleDate, $quantity)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Save failed']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        }
        exit;
    }

    // Save daily purchase (manual purchase/transfer; V2 logic)
    if (isset($_POST['save_daily_purchase']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        $storeId = intval($_POST['store_id'] ?? 0);
        $productId = intval($_POST['product_id'] ?? 0);
        $purchaseDate = isset($_POST['purchase_date']) ? $_POST['purchase_date'] : null;
        $quantity = floatval($_POST['quantity'] ?? 0);
        if ($storeId > 0 && $productId > 0 && $purchaseDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseDate)) {
            if (saveProductDailyPurchase($storeId, $productId, $purchaseDate, $quantity)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Save failed']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        }
        exit;
    }

    // Update daily on-hand (recalc from Starting + Sales + Purchases; V2 logic)
    if (isset($_POST['update_daily_on_hand']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        $storeId = intval($_POST['store_id'] ?? 0);
        $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : null;
        $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : null;
        if ($storeId > 0 && $startDate && $endDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            if (recalcAndSaveOnHandForRange($storeId, $startDate, $endDate)) {
                $snapshots = getInventorySnapshotsMap($storeId, $startDate, $endDate);
                echo json_encode(['success' => true, 'snapshots' => $snapshots]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Recalc failed']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        }
        exit;
    }
    
    // Mark order as received
    if (isset($_POST['mark_received']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        $orderId = intval($_POST['order_id'] ?? 0);
        $productId = intval($_POST['product_id'] ?? 0);
        $storeId = intval($_POST['store_id'] ?? 0);
        $quantity = floatval($_POST['quantity'] ?? 0);
        $receivedDate = date('Y-m-d');
        
        if ($orderId > 0 && $productId > 0 && $storeId > 0) {
            try {
                $pdo = getDB();
                
                // Update order status and received_date
                $stmt = $pdo->prepare("UPDATE orders SET status = 'RECEIVED', received_date = ? WHERE id = ?");
                if (!$stmt->execute([$receivedDate, $orderId])) {
                    echo json_encode(['success' => false, 'message' => 'Failed to update order']);
                    exit;
                }
                
                // Get current on_hand
                $invStmt = $pdo->prepare("SELECT on_hand FROM inventory WHERE store_id = ? AND product_id = ?");
                $invStmt->execute([$storeId, $productId]);
                $inventory = $invStmt->fetch(PDO::FETCH_ASSOC);
                $currentOnHand = $inventory ? (float)$inventory['on_hand'] : 0;
                $newOnHand = $currentOnHand + $quantity;
                
                // Update inventory on_hand
                $updateStmt = $pdo->prepare("UPDATE inventory SET on_hand = ?, updated_at = CURRENT_TIMESTAMP WHERE store_id = ? AND product_id = ?");
                $updateStmt->execute([$newOnHand, $storeId, $productId]);
                
                // Update daily on-hand snapshot for today
                saveInventorySnapshot($storeId, $productId, $receivedDate, $newOnHand);
                
                echo json_encode(['success' => true, 'message' => 'Order marked as received', 'new_on_hand' => $newOnHand]);
            } catch (PDOException $e) {
                error_log("Error marking order as received: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        }
        exit;
    }
    
    if (isset($_POST['save_inventory'])) {
        $data = [
            'id' => !empty($_POST['inventory_id']) ? intval($_POST['inventory_id']) : null,
            'store_id' => intval($_POST['store_id']),
            'product_id' => intval($_POST['product_id']),
            'on_hand' => floatval($_POST['on_hand'] ?? 0),
            'reorder_point' => floatval($_POST['reorder_point'] ?? 0),
            'target_max' => floatval($_POST['target_max'] ?? 0),
            'vendor_id' => !empty($_POST['vendor_id']) ? intval($_POST['vendor_id']) : null,
            'vendor_sku' => $_POST['vendor_sku'] ?? null,
            'vendor_link' => $_POST['vendor_link'] ?? null,
            'lead_time_days' => intval($_POST['lead_time_days'] ?? 0),
            'unit_cost' => floatval($_POST['unit_cost'] ?? 0),
            'avg_daily_usage' => floatval($_POST['avg_daily_usage'] ?? 0),
            'days_of_stock' => intval($_POST['days_of_stock'] ?? 7),
            'substitution_product_id' => !empty($_POST['substitution_product_id']) ? intval($_POST['substitution_product_id']) : null,
            'notes' => $_POST['inventory_notes'] ?? null
        ];
        if (saveInventory($data)) {
            $successMessage = isset($data['id']) ? "Inventory updated successfully!" : "Product added to inventory successfully!";
        } else {
            $errorMessage = "Error saving inventory.";
        }
        header("Location: index.php?action=dashboard&store={$storeId}&date={$date}&view={$view}");
        exit;
    }
    
    if (isset($_POST['save_order'])) {
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
        header("Location: index.php?action=dashboard&store={$storeId}&date={$date}&view={$view}");
        exit;
    }
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
// Get date range and KPIs for spreadsheet view
$dateRange = null;
$kpiMap = [];
$dateArray = [];
if ($storeId && ($action === 'dashboard' || $action === 'entry')) {
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
// Ensure we always have a date range for charts when on dashboard/entry with store
if (($action === 'dashboard' || $action === 'entry') && $storeId && (empty($dateArray) || !is_array($dateArray))) {
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
$snapshotsMap = [];
$receivedMap = [];
$salesMap = [];
$purchasesMap = [];
$extrapolatedSales = [];
if ($storeId && ($action === 'dashboard' || $action === 'entry')) {
    try {
        // Get inventory items (sorted by user preference for inventory list)
        $inventoryItems = getInventoryForStore($storeId, $inventorySort);
        $products = getAllProducts();
        $vendors = getAllVendors();
        $orders = getOrdersForStore($storeId);
        if (!empty($dateArray) && isset($startDate) && isset($endDate)) {
            $snapshotsMap = getInventorySnapshotsMap($storeId, $startDate, $endDate);
            $receivedMap = getReceivedByProductDate($storeId, $startDate, $endDate);
            $salesMap = getSalesByProductDate($storeId, $startDate, $endDate);
            $purchasesMap = getPurchasesByProductDate($storeId, $startDate, $endDate);
            $manualPurchasesMap = getManualPurchasesByProductDate($storeId, $startDate, $endDate);
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
if ($chartProductId && $storeId && ($action === 'dashboard' || $action === 'entry')) {
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
include 'includes/header.php';
?>
<div class="nav">
    <a href="?action=dashboard&store=<?php echo $storeId; ?>&date=<?php echo $date; ?>" class="<?php echo $action === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
    <a href="?action=entry&store=<?php echo $storeId; ?>&date=<?php echo $date; ?>" class="<?php echo $action === 'entry' ? 'active' : ''; ?>">Data Entry</a>
    <a href="?action=history&store=<?php echo $storeId; ?>" class="<?php echo $action === 'history' ? 'active' : ''; ?>">History</a>
    <a href="import.php">Import Lightspeed</a>
</div>
<?php if (isset($successMessage)): ?>
    <div class="message success"><?php echo htmlspecialchars($successMessage); ?></div>
<?php endif; ?>
<?php if (isset($errorMessage)): ?>
    <div class="message error"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>
<?php if ($action === 'dashboard' || $action === 'entry'): ?>
    <?php if (!$storeId || empty($stores)): ?>
        <div class="message error">
            <p>No stores found. Please set up the database first.</p>
            <p>Run: <code>make seed</code> or set up the database using <code>setup-db.sh</code></p>
        </div>
    <?php else: ?>
    <div class="header">
        <div>
            <h1><?php echo APP_NAME; ?></h1>
            <div class="store-info">Store: <strong><?php echo htmlspecialchars($currentStore['name'] ?? 'N/A'); ?></strong></div>
            <div class="store-selector">
                <?php foreach ($stores as $store): ?>
                    <a href="?action=<?php echo $action; ?>&store=<?php echo $store['id']; ?>&date=<?php echo $date; ?>&view=<?php echo $view; ?>" class="store-pill <?php echo ($store['id'] == $storeId) ? 'active' : ''; ?>" data-store-id="<?php echo $store['id']; ?>"><?php echo htmlspecialchars($store['name']); ?></a>
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
    <form method="POST" action="" id="spreadsheet-form">
        <input type="hidden" name="store_id" value="<?php echo $storeId; ?>">
        <input type="hidden" name="view" value="<?php echo $view; ?>">
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
                                               <?php echo $action === 'dashboard' ? 'readonly' : ''; ?>>
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
        <?php if ($action === 'entry'): ?>
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
    $showChartSection = ($action === 'dashboard' || $action === 'entry') && $storeId;
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
            <canvas id="kpiChart" style="max-height: 500px; height: 500px;"></canvas>
        </div>
    </div>
    
    <script>
        // Prepare chart data (assign to window so it's available when main.js runs)
        window.chartData = {
            labels: <?php 
                try {
                    echo json_encode(array_map(function($d) { 
                        try {
                            $dateObj = new DateTime($d);
                            return $dateObj->format('M j');
                        } catch (Exception $e) {
                            return $d;
                        }
                    }, $dateArray));
                } catch (Exception $e) {
                    echo json_encode([]);
                }
            ?>,
            datasets: [
                {
                    label: 'Sales',
                    data: <?php echo json_encode(array_map(function($d) use ($kpiMap) {
                        $kpi = $kpiMap[$d] ?? null;
                        return floatval($kpi ? ($kpi['sales_today'] ?? 0) : 0);
                    }, $dateArray)); ?>,
                    borderColor: 'rgb(52, 152, 219)',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    fill: true,
                    tension: 0.4,
                    hidden: false
                },
                {
                    label: 'COGS',
                    data: <?php echo json_encode(array_map(function($d) use ($kpiMap) {
                        $kpi = $kpiMap[$d] ?? null;
                        return floatval($kpi ? ($kpi['cogs_today'] ?? 0) : 0);
                    }, $dateArray)); ?>,
                    borderColor: 'rgb(231, 76, 60)',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    fill: true,
                    tension: 0.4,
                    hidden: true
                },
                {
                    label: 'Labor',
                    data: <?php echo json_encode(array_map(function($d) use ($kpiMap) {
                        $kpi = $kpiMap[$d] ?? null;
                        return floatval($kpi ? ($kpi['labor_today'] ?? 0) : 0);
                    }, $dateArray)); ?>,
                    borderColor: 'rgb(241, 196, 15)',
                    backgroundColor: 'rgba(241, 196, 15, 0.1)',
                    fill: true,
                    tension: 0.4,
                    hidden: true
                },
                {
                    label: 'Overhead',
                    data: <?php echo json_encode(array_map(function($d) use ($kpiMap) {
                        $kpi = $kpiMap[$d] ?? null;
                        return floatval($kpi ? ($kpi['avg_daily_overhead'] ?? 0) : 0);
                    }, $dateArray)); ?>,
                    borderColor: 'rgb(155, 89, 182)',
                    backgroundColor: 'rgba(155, 89, 182, 0.1)',
                    fill: true,
                    tension: 0.4,
                    hidden: true
                },
                {
                    label: 'Profit',
                    data: <?php echo json_encode(array_map(function($d) use ($kpiMap) {
                        $kpi = $kpiMap[$d] ?? null;
                        $sales = floatval($kpi ? ($kpi['sales_today'] ?? 0) : 0);
                        $cogs = floatval($kpi ? ($kpi['cogs_today'] ?? 0) : 0);
                        $labor = floatval($kpi ? ($kpi['labor_today'] ?? 0) : 0);
                        $overhead = floatval($kpi ? ($kpi['avg_daily_overhead'] ?? 0) : 0);
                        return $sales - $cogs - $labor - $overhead;
                    }, $dateArray)); ?>,
                    borderColor: 'rgb(39, 174, 96)',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    fill: true,
                    tension: 0.4,
                    hidden: true
                },
                {
                    label: 'Labor %',
                    data: <?php echo json_encode(array_map(function($d) use ($kpiMap) {
                        $kpi = $kpiMap[$d] ?? null;
                        $sales = floatval($kpi ? ($kpi['sales_today'] ?? 0) : 0);
                        $labor = floatval($kpi ? ($kpi['labor_today'] ?? 0) : 0);
                        return $sales > 0 ? ($labor / $sales) * 100 : 0;
                    }, $dateArray)); ?>,
                    borderColor: 'rgb(230, 126, 34)',
                    backgroundColor: 'rgba(230, 126, 34, 0.1)',
                    fill: true,
                    tension: 0.4,
                    hidden: true,
                    yAxisID: 'y1'
                }
            ]
        };
        // Create chart when Chart.js is available (handles CDN load order)
        function initKpiChartNow() {
            var el = document.getElementById('kpiChart');
            if (!el || !window.chartData || !window.chartData.datasets) return false;
            if (typeof Chart === 'undefined') return false;
            if (window.kpiChart) try { window.kpiChart.destroy(); } catch (e) {}
            window.kpiChart = new Chart(el.getContext('2d'), {
                type: 'line',
                data: window.chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { type: 'linear', display: true, position: 'left', beginAtZero: true, ticks: { callback: function(v) { return typeof formatCurrency === 'function' ? formatCurrency(v) : '$' + Number(v).toLocaleString(); } } },
                        y1: { type: 'linear', display: true, position: 'right', beginAtZero: true, max: 100, ticks: { callback: function(v) { return v.toFixed(1) + '%'; } }, grid: { drawOnChartArea: false } }
                    }
                }
            });
            // Attach individual series toggles (legend) and Show All / Hide All
            if (typeof createCustomLegend === 'function') createCustomLegend();
            if (typeof setupChartControls === 'function') setupChartControls();
            return true;
        }
        if (!initKpiChartNow()) {
            var attempts = 0;
            var t = setInterval(function() {
                if (initKpiChartNow() || ++attempts > 40) clearInterval(t);
            }, 100);
        }
    </script>
    <?php else: ?>
    <script>
        window.chartData = null;
    </script>
    <?php endif; // end chart section ?>
    
    <!-- Inventory Management Section -->
    <?php if ($storeId && ($action === 'dashboard' || $action === 'entry')): ?>
    <div class="inventory-container">
        <div class="inventory-header">
            <h2>
                <button type="button" class="collapse-toggle" onclick="toggleInventoryCollapse()">
                    <span id="inventory-toggle-icon">▼</span> Inventory Management
                </button>
            </h2>
            <div class="inventory-actions">
                <button type="button" class="btn btn-primary" onclick="showProductModal()">+ Add Product</button>
                <button type="button" class="btn btn-primary" onclick="showVendorModal()">+ Add Vendor</button>
                <?php if (!empty($vendors)): ?>
                <span class="vendor-list-inline" style="margin-left: 12px; font-size: 13px; color: #000;">
                    Edit vendor: <?php foreach ($vendors as $v): ?>
                    <button type="button" class="btn-small" onclick="showVendorModal(<?php echo (int)$v['id']; ?>)"><?php echo htmlspecialchars($v['name']); ?></button>
                    <?php endforeach; ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
        <div id="inventory-content" class="inventory-content">
            <?php include 'includes/inventory-section.php'; ?>
        </div>
    </div>
    
    <?php
    $chartBaseUrl = '?action=dashboard&store=' . (int)$storeId . '&date=' . urlencode($date) . '&view=' . urlencode($view ?? 'week');
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
            <canvas id="inventoryChart" style="max-height: 400px; height: 400px;"></canvas>
            <script>
                (function() {
                    const labels = <?php echo json_encode(array_map(function($d) {
                        try { return (new DateTime($d))->format('M j'); } catch (Exception $e) { return $d; }
                    }, $chartDateArray)); ?>;
                    const onHandData = <?php echo json_encode(array_map(function($d) use ($chartDateArray, $chartOnHandByDate) {
                        return isset($chartOnHandByDate[$d]) ? $chartOnHandByDate[$d] : null;
                    }, $chartDateArray)); ?>;
                    const salesData = <?php echo json_encode(array_map(function($d) use ($chartDateArray, $chartSalesByDate) {
                        return $chartSalesByDate[$d] ?? 0;
                    }, $chartDateArray)); ?>;
                    const purchasesData = <?php echo json_encode(array_map(function($d) use ($chartDateArray, $chartPurchasesByDate) {
                        return $chartPurchasesByDate[$d] ?? 0;
                    }, $chartDateArray)); ?>;
                    const unitCost = <?php echo json_encode($chartProductUnitCost); ?>;
                    const priceData = labels.map(() => unitCost);
                    
                    const ctx = document.getElementById('inventoryChart');
                    if (!ctx) return;
                    if (window.inventoryChart) try { window.inventoryChart.destroy(); } catch (e) {}
                    window.inventoryChart = new Chart(ctx.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [
                                { label: 'On Hand', data: onHandData, borderColor: 'rgb(52, 152, 219)', backgroundColor: 'rgba(52, 152, 219, 0.1)', fill: true, tension: 0.4, yAxisID: 'y', hidden: false },
                                { label: 'Sales', data: salesData, borderColor: 'rgb(231, 76, 60)', backgroundColor: 'rgba(231, 76, 60, 0.1)', fill: true, tension: 0.4, yAxisID: 'y', hidden: false },
                                { label: 'Purchases', data: purchasesData, borderColor: 'rgb(46, 204, 113)', backgroundColor: 'rgba(46, 204, 113, 0.1)', fill: true, tension: 0.4, yAxisID: 'y', hidden: false },
                                { label: 'Price (unit cost)', data: priceData, borderColor: 'rgb(155, 89, 182)', borderDash: [5, 5], fill: false, tension: 0, yAxisID: 'y1', hidden: false }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { intersect: false, mode: 'index' },
                            plugins: {
                                legend: {
                                    display: true,
                                    onClick: function(e) { e.stopPropagation(); },
                                    labels: { color: '#000' }
                                }
                            },
                            scales: {
                                y: {
                                    type: 'linear', position: 'left', beginAtZero: true,
                                    title: { display: true, text: 'Qty', color: '#000' },
                                    ticks: { color: '#000' }
                                },
                                y1: {
                                    type: 'linear', position: 'right', beginAtZero: true,
                                    title: { display: true, text: 'Price ($)', color: '#000' },
                                    ticks: { color: '#000' },
                                    grid: { drawOnChartArea: false }
                                },
                                x: { ticks: { color: '#000' } }
                            }
                        }
                    });
                    function updateInventoryToggleButtons() {
                        if (!window.inventoryChart) return;
                        const m0 = window.inventoryChart.getDatasetMeta(0);
                        const m1 = window.inventoryChart.getDatasetMeta(1);
                        const m2 = window.inventoryChart.getDatasetMeta(2);
                        const m3 = window.inventoryChart.getDatasetMeta(3);
                        var el; el = document.getElementById('toggle-onhand'); if (el) el.textContent = m0.hidden ? 'Show On-Hand' : 'Hide On-Hand';
                        el = document.getElementById('toggle-sales'); if (el) el.textContent = m1.hidden ? 'Show Sales' : 'Hide Sales';
                        el = document.getElementById('toggle-purchases'); if (el) el.textContent = m2.hidden ? 'Show Purchases' : 'Hide Purchases';
                        el = document.getElementById('toggle-price'); if (el) el.textContent = m3.hidden ? 'Show Price' : 'Hide Price';
                    }
                    window.updateInventoryToggleButtons = updateInventoryToggleButtons;
                    updateInventoryToggleButtons();
                })();
            </script>
            <?php else: ?>
            <p id="inventory-chart-placeholder" style="padding: 40px; text-align: center; color: #000;">Select a product by clicking its SKU/Barcode in the table above.</p>
            <canvas id="inventoryChart" style="max-height: 400px; height: 400px; display: none;"></canvas>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function toggleInventoryChartCollapse() {
            const el = document.getElementById('inventory-chart-content');
            const icon = document.getElementById('inventory-chart-toggle-icon');
            if (!el) return;
            el.classList.toggle('collapsed');
            icon.textContent = el.classList.contains('collapsed') ? '▶' : '▼';
        }
        function toggleInventoryChartSeries(seriesName) {
            if (!window.inventoryChart) return;
            let index = -1;
            switch(seriesName) { case 'onhand': index = 0; break; case 'sales': index = 1; break; case 'purchases': index = 2; break; case 'price': index = 3; break; }
            if (index >= 0) {
                const meta = window.inventoryChart.getDatasetMeta(index);
                meta.hidden = !meta.hidden;
                window.inventoryChart.update('none');
                if (window.updateInventoryToggleButtons) window.updateInventoryToggleButtons();
            }
        }
        function showAllInventorySeries() {
            if (!window.inventoryChart) return;
            window.inventoryChart.data.datasets.forEach((d, i) => { const m = window.inventoryChart.getDatasetMeta(i); m.hidden = false; });
            window.inventoryChart.update('none');
            if (window.updateInventoryToggleButtons) window.updateInventoryToggleButtons();
        }
    </script>
    
    <?php endif; ?>
    
    <?php endif; // end storeId/empty stores check (closes line 115) ?>
<?php elseif ($action === 'history'): ?>
    <div class="header">
        <h1>Historical KPI Data</h1>
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
<?php endif; // end if ($action === 'dashboard' || $action === 'entry') ?>
<?php include 'includes/modals.php'; ?>
<?php include 'includes/footer.php'; ?>

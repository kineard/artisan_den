<?php
/**
 * Fix database schema and seed inventory data
 */

require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/inventory-functions.php';

echo "=== Fixing Database Schema and Seeding ===\n\n";

$pdo = getDB();

// Step 1: Add rating column if missing
echo "Step 1: Checking vendors table...\n";
try {
    $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'vendors' AND column_name = 'rating'");
    $hasRating = $stmt->fetch();
    
    if (!$hasRating) {
        echo "  Adding rating column to vendors...\n";
        $pdo->exec("ALTER TABLE vendors ADD COLUMN rating INTEGER DEFAULT 3");
        $pdo->exec("ALTER TABLE vendors ADD CONSTRAINT vendors_rating_check CHECK (rating >= 1 AND rating <= 5)");
        echo "  ✓ Rating column added\n";
    } else {
        echo "  ✓ Rating column already exists\n";
    }
} catch (PDOException $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}

// Step 2: Add inventory columns if missing
echo "\nStep 2: Checking inventory table...\n";
$inventoryColumns = ['avg_daily_usage', 'days_of_stock', 'substitution_product_id'];
foreach ($inventoryColumns as $col) {
    try {
        $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'inventory' AND column_name = '$col'");
        $exists = $stmt->fetch();
        
        if (!$exists) {
            echo "  Adding $col column...\n";
            if ($col === 'substitution_product_id') {
                $pdo->exec("ALTER TABLE inventory ADD COLUMN $col INTEGER");
                $pdo->exec("ALTER TABLE inventory ADD CONSTRAINT inventory_substitution_fk FOREIGN KEY ($col) REFERENCES products(id) ON DELETE SET NULL");
            } elseif ($col === 'days_of_stock') {
                $pdo->exec("ALTER TABLE inventory ADD COLUMN $col INTEGER DEFAULT 7");
            } else {
                $pdo->exec("ALTER TABLE inventory ADD COLUMN $col DECIMAL(10,3) DEFAULT 0");
            }
            echo "  ✓ $col added\n";
        } else {
            echo "  ✓ $col already exists\n";
        }
    } catch (PDOException $e) {
        echo "  Error adding $col: " . $e->getMessage() . "\n";
    }
}

// Step 2.5: Create inventory_snapshots table if missing
echo "\nStep 2.5: Checking inventory_snapshots table...\n";
$snapTable = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'inventory_snapshots'")->fetch();
if ($snapTable) {
    echo "  ✓ inventory_snapshots exists\n";
} else {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS inventory_snapshots (
            id SERIAL PRIMARY KEY,
            store_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            snapshot_date DATE NOT NULL,
            on_hand DECIMAL(10,3) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (store_id, product_id, snapshot_date),
            FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )");
        try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_inventory_snapshots_store_date ON inventory_snapshots(store_id, snapshot_date)"); } catch (PDOException $x) {}
        try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_inventory_snapshots_product_date ON inventory_snapshots(product_id, snapshot_date)"); } catch (PDOException $x) {}
        echo "  ✓ inventory_snapshots created\n";
    } catch (PDOException $ex) {
        echo "  Error: " . $ex->getMessage() . "\n";
    }
}

// Step 2.6: Create product_daily_sales table if missing
echo "\nStep 2.6: Checking product_daily_sales table...\n";
$salesTable = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'product_daily_sales'")->fetch();
if ($salesTable) {
    echo "  ✓ product_daily_sales exists\n";
} else {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_daily_sales (
            id SERIAL PRIMARY KEY,
            store_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            sale_date DATE NOT NULL,
            quantity_sold DECIMAL(10,3) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (store_id, product_id, sale_date),
            FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )");
        try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_product_daily_sales_store_product_date ON product_daily_sales(store_id, product_id, sale_date)"); } catch (PDOException $x) {}
        try { $pdo->exec("CREATE TRIGGER update_product_daily_sales_updated_at BEFORE UPDATE ON product_daily_sales FOR EACH ROW EXECUTE FUNCTION update_updated_at_column()"); } catch (PDOException $x) {}
        echo "  ✓ product_daily_sales created\n";
    } catch (PDOException $ex) {
        echo "  Error: " . $ex->getMessage() . "\n";
    }
}

// Step 2.7: Add expected_delivery_date to orders table if missing
echo "\nStep 2.7: Checking orders table for expected_delivery_date...\n";
try {
    $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'orders' AND column_name = 'expected_delivery_date'");
    $hasExpectedDate = $stmt->fetch();
    
    if (!$hasExpectedDate) {
        echo "  Adding expected_delivery_date column to orders...\n";
        $pdo->exec("ALTER TABLE orders ADD COLUMN expected_delivery_date DATE");
        echo "  ✓ expected_delivery_date column added\n";
    } else {
        echo "  ✓ expected_delivery_date column already exists\n";
    }
} catch (PDOException $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}

// Step 2.8: Create product_daily_purchases table if missing (manual purchases/transfers)
echo "\nStep 2.8: Checking product_daily_purchases table...\n";
$purchTable = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'product_daily_purchases'")->fetch();
if ($purchTable) {
    echo "  ✓ product_daily_purchases exists\n";
} else {
    try {
        $pdo->exec(file_get_contents(__DIR__ . '/database/migrate-daily-purchases.sql'));
        echo "  ✓ product_daily_purchases created\n";
    } catch (PDOException $ex) {
        echo "  Error: " . $ex->getMessage() . "\n";
    }
}

// Step 3: Run inventory seed
echo "\nStep 3: Running inventory seed script...\n";
require_once __DIR__ . '/seed-inventory.php';

// Step 4: Run KPI seed (for Data Visualization chart)
echo "\nStep 4: Running KPI seed (for Data Visualization chart)...\n";
if (file_exists(__DIR__ . '/seed-data.php')) {
    require_once __DIR__ . '/seed-data.php';
} else {
    echo "  (seed-data.php not found - run it manually to populate KPI chart)\n";
}

echo "\n=== Complete ===\n";

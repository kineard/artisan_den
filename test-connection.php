<?php
require_once 'config.php';
require_once 'includes/helpers.php';
echo "Testing database connection...\n\n";
try {
    $pdo = getDB();
    echo "✓ Database connection successful!\n\n";
    $stmt = $pdo->query("SELECT * FROM stores ORDER BY name");
    $stores = $stmt->fetchAll();
    echo "Stores found: " . count($stores) . "\n";
    foreach ($stores as $store) {
        echo "  - " . e($store['name']) . " (ID: " . $store['id'] . ")\n";
    }
    echo "\n✓ Database test complete!\n";
} catch (PDOException $e) {
    echo "✗ Database connection failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

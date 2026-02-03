<?php
echo "=== Artisan Den Environment Validation ===\n\n";

$errors = [];
$success = [];

// Test 1: PHP Version
echo "[1] Checking PHP version...\n";
$phpVersion = phpversion();
if (version_compare($phpVersion, '8.1.0', '>=')) {
    $success[] = "PHP version: $phpVersion";
    echo "  ✓ PHP $phpVersion\n";
} else {
    $errors[] = "PHP version $phpVersion is too old (need 8.1+)";
    echo "  ✗ PHP $phpVersion (need 8.1+)\n";
}

// Test 2: Required PHP Extensions
echo "[2] Checking PHP extensions...\n";
$required = ['pdo', 'pdo_pgsql', 'mbstring', 'session'];
foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        $success[] = "Extension $ext loaded";
        echo "  ✓ $ext\n";
    } else {
        $errors[] = "Missing extension: $ext";
        echo "  ✗ $ext (missing)\n";
    }
}

// Test 3: File Structure
echo "[3] Checking file structure...\n";
$requiredFiles = [
    'config.php',
    'includes/helpers.php',
    'includes/functions.php',
    'includes/header.php',
    'includes/footer.php'
];
foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        $success[] = "File exists: $file";
        echo "  ✓ $file\n";
    } else {
        $errors[] = "Missing file: $file";
        echo "  ✗ $file (missing)\n";
    }
}

// Test 4: PHP Syntax
echo "[4] Checking PHP syntax...\n";
$phpFiles = ['config.php', 'includes/helpers.php', 'includes/functions.php'];
foreach ($phpFiles as $file) {
    $output = shell_exec("php -l $file 2>&1");
    if (strpos($output, 'No syntax errors') !== false) {
        $success[] = "Syntax OK: $file";
        echo "  ✓ $file\n";
    } else {
        $errors[] = "Syntax error in $file";
        echo "  ✗ $file has syntax errors\n";
    }
}

// Test 5: Database Connection
echo "[5] Testing database connection...\n";
try {
    require_once 'config.php';
    $pdo = getDB();
    $success[] = "Database connection successful";
    echo "  ✓ Connected to PostgreSQL\n";
    
    // Test 6: Database Tables
    echo "[6] Checking database tables...\n";
    $tables = ['stores', 'daily_kpis', 'users', 'lightspeed_imports'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_name = '$table'");
        $exists = $stmt->fetchColumn() > 0;
        if ($exists) {
            $success[] = "Table exists: $table";
            echo "  ✓ $table\n";
        } else {
            $errors[] = "Missing table: $table";
            echo "  ✗ $table (missing)\n";
        }
    }
    
    // Test 7: Stores Data
    echo "[7] Checking stores data...\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM stores");
    $count = $stmt->fetchColumn();
    if ($count >= 2) {
        $success[] = "Stores populated: $count stores";
        echo "  ✓ Found $count stores\n";
        $stmt = $pdo->query("SELECT name FROM stores ORDER BY name");
        while ($row = $stmt->fetch()) {
            echo "    - " . $row['name'] . "\n";
        }
    } else {
        $errors[] = "Only $count stores found (expected 2)";
        echo "  ✗ Only $count stores (expected 2)\n";
    }
    
    // Test 8: Helper Functions
    echo "[8] Testing helper functions...\n";
    require_once 'includes/helpers.php';
    if (function_exists('e')) {
        $test = e('<script>alert("test")</script>');
        if (strpos($test, '<script>') === false) {
            $success[] = "Helper function e() works";
            echo "  ✓ e() function escapes HTML\n";
        } else {
            $errors[] = "Helper function e() not escaping properly";
            echo "  ✗ e() function not working\n";
        }
    } else {
        $errors[] = "Helper function e() not found";
        echo "  ✗ e() function missing\n";
    }
    
} catch (Exception $e) {
    $errors[] = "Database error: " . $e->getMessage();
    echo "  ✗ Database connection failed: " . $e->getMessage() . "\n";
}

// Summary
echo "\n=== Summary ===\n";
echo "✓ Success: " . count($success) . " checks passed\n";
if (count($errors) > 0) {
    echo "✗ Errors: " . count($errors) . "\n";
    foreach ($errors as $e) {
        echo "  - $e\n";
    }
    echo "\n❌ Environment validation FAILED\n";
    exit(1);
} else {
    echo "\n✅ Environment validation PASSED\n";
    echo "\nReady to proceed with development!\n";
    exit(0);
}

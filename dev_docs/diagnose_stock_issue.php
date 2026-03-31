<?php
/**
 * Stock Decrease Diagnostic Tool
 * 
 * Usage: php dev_docs/diagnose_stock_issue.php [SKU]
 * Example: php dev_docs/diagnose_stock_issue.php LE-270-PAL
 * 
 * This script performs comprehensive diagnostics on the stock decrease mechanism
 * to help identify why stock might not be decreasing properly.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get SKU from command line argument or use default
$testSKU = $argv[1] ?? 'LE-270-PAL';

echo "================================================================================\n";
echo "STOCK DECREASE DIAGNOSTIC TOOL\n";
echo "================================================================================\n";
echo "SKU to test: $testSKU\n";
echo "Current time: " . date('Y-m-d H:i:s') . "\n";
echo "================================================================================\n\n";

// Change to the repository root
chdir(__DIR__ . '/..');
require_once __DIR__ . '/../init.php';

// Test 1: File System Check
echo "TEST 1: FILE SYSTEM CHECK\n";
echo "----------------------------------------\n";
$stockFilePath = __DIR__ . '/../data/stock.json';
echo "Stock file path: $stockFilePath\n";
echo "File exists: " . (file_exists($stockFilePath) ? 'YES ✓' : 'NO ✗') . "\n";

if (!file_exists($stockFilePath)) {
    echo "ERROR: Stock file does not exist!\n";
    exit(1);
}

$fileStats = stat($stockFilePath);
if ($fileStats === false) {
    echo "ERROR: Could not get file stats\n";
    exit(1);
}
echo "File size: " . $fileStats['size'] . " bytes\n";
echo "Last modified: " . date('Y-m-d H:i:s', $fileStats['mtime']) . "\n";
echo "Last accessed: " . date('Y-m-d H:i:s', $fileStats['atime']) . "\n";
echo "Owner UID: " . $fileStats['uid'] . "\n";
echo "Group GID: " . $fileStats['gid'] . "\n";
echo "Permissions: " . substr(sprintf('%o', $fileStats['mode']), -4) . "\n";
echo "Is readable: " . (is_readable($stockFilePath) ? 'YES ✓' : 'NO ✗') . "\n";
echo "Is writable: " . (is_writable($stockFilePath) ? 'YES ✓' : 'NO ✗') . "\n";

if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
    $currentUser = posix_getpwuid(posix_geteuid());
    $userName = $currentUser ? $currentUser['name'] : 'unknown';
    echo "Current PHP user: " . $userName . " (UID: " . posix_geteuid() . ")\n";
} else {
    echo "Current PHP user: N/A (POSIX functions not available)\n";
}
echo "\n";

// Test 2: SKU Existence and Data Integrity
echo "TEST 2: SKU EXISTENCE AND DATA INTEGRITY\n";
echo "----------------------------------------\n";
$stock = loadJSON('stock.json');
echo "Total SKUs in stock.json: " . count($stock) . "\n";

if (!isset($stock[$testSKU])) {
    echo "ERROR: SKU '$testSKU' not found in stock.json!\n";
    echo "\nAvailable Limited Edition SKUs:\n";
    foreach ($stock as $sku => $data) {
        if (strpos($sku, 'LE-') === 0) {
            echo "  - $sku (qty: {$data['quantity']})\n";
        }
    }
    exit(1);
}

$skuData = $stock[$testSKU];
echo "SKU '$testSKU' found ✓\n";
echo "Current quantity: " . $skuData['quantity'] . "\n";
echo "Product ID: " . $skuData['productId'] . "\n";
echo "Volume: " . $skuData['volume'] . "\n";
echo "Fragrance: " . $skuData['fragrance'] . "\n";
echo "Low stock threshold: " . $skuData['lowStockThreshold'] . "\n";
echo "\n";

if ($skuData['quantity'] <= 0) {
    echo "WARNING: Current quantity is 0 or negative. Cannot test decrease.\n";
    echo "Please update stock manually in admin panel first.\n";
    exit(1);
}

// Test 3: Direct File Read/Write Test
echo "TEST 3: DIRECT FILE READ/WRITE\n";
echo "----------------------------------------\n";
$beforeWrite = filemtime($stockFilePath);
echo "File mtime before test: " . date('Y-m-d H:i:s', $beforeWrite) . "\n";

// Read raw file content
$rawContent = file_get_contents($stockFilePath);
if ($rawContent === false) {
    echo "ERROR: Failed to read file!\n";
    exit(1);
}
$jsonData = json_decode($rawContent, true);

// Verify JSON is valid
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "ERROR: Invalid JSON - " . json_last_error_msg() . "\n";
    exit(1);
}
echo "JSON parsing: OK ✓\n";
$directReadQty = $jsonData[$testSKU]['quantity'];
echo "Direct file read quantity: $directReadQty\n";
echo "\n";

// Test 4: decreaseStock() Function Test
echo "TEST 4: DECREASE STOCK FUNCTION\n";
echo "----------------------------------------\n";
$initialQty = $stock[$testSKU]['quantity'];
echo "Initial quantity: $initialQty\n";
echo "Calling decreaseStock('$testSKU', 1)...\n\n";

// Capture start time for timing analysis
$startTime = microtime(true);

// Call decreaseStock
$result = decreaseStock($testSKU, 1);

$endTime = microtime(true);
$executionTime = round(($endTime - $startTime) * 1000, 2);

echo "\ndecreaseStock() execution time: {$executionTime}ms\n";
echo "decreaseStock() returned: " . ($result ? 'TRUE (success) ✓' : 'FALSE (failed) ✗') . "\n";

// Verify immediately with different methods
echo "\nVerification (multiple methods):\n";

// Method 1: loadJSON (goes through helper function)
$stockViaHelper = loadJSON('stock.json');
$qtyViaHelper = $stockViaHelper[$testSKU]['quantity'];
echo "  1. Via loadJSON(): $qtyViaHelper\n";

// Method 2: Direct file read
clearstatcache(true, $stockFilePath);
$rawAfterContent = file_get_contents($stockFilePath);
$jsonAfterData = json_decode($rawAfterContent, true);
$qtyViaDirect = $jsonAfterData[$testSKU]['quantity'];
echo "  2. Via direct file_get_contents(): $qtyViaDirect\n";

// Method 3: Re-open file
$fp = fopen($stockFilePath, 'r');
$qtyViaFopen = 'ERROR';
if ($fp) {
    $fileStats = fstat($fp);
    if ($fileStats !== false && $fileStats['size'] > 0) {
        $fileContent = fread($fp, $fileStats['size']);
        if ($fileContent !== false) {
            $jsonFromFopen = json_decode($fileContent, true);
            if ($jsonFromFopen !== null && isset($jsonFromFopen[$testSKU]['quantity'])) {
                $qtyViaFopen = $jsonFromFopen[$testSKU]['quantity'];
            }
        }
    }
    fclose($fp);
}
echo "  3. Via fopen/fread(): $qtyViaFopen\n";

$expectedQty = $initialQty - 1;
echo "\nExpected quantity: $expectedQty\n";

// Check if all methods agree
$allMatch = ($qtyViaHelper === $expectedQty && $qtyViaDirect === $expectedQty && $qtyViaFopen === $expectedQty);
echo "All methods match expected: " . ($allMatch ? 'YES ✓' : 'NO ✗') . "\n";

if (!$allMatch) {
    echo "\nERROR: Quantity mismatch detected!\n";
    echo "This indicates a problem with file writing or reading.\n";
}

// Check file modification time
clearstatcache(true, $stockFilePath);
$afterWrite = filemtime($stockFilePath);
echo "\nFile mtime after test: " . date('Y-m-d H:i:s', $afterWrite) . "\n";
echo "File was modified: " . ($afterWrite > $beforeWrite ? 'YES ✓' : 'NO ✗') . "\n";
echo "\n";

// Test 5: Persistence Test
echo "TEST 5: PERSISTENCE (5 SECOND WAIT)\n";
echo "----------------------------------------\n";
echo "Waiting 5 seconds to verify data persists...\n";
sleep(5);

clearstatcache(true, $stockFilePath);
$persistStock = loadJSON('stock.json');
$persistQty = $persistStock[$testSKU]['quantity'];
echo "Quantity after 5s: $persistQty\n";
echo "Still matches expected: " . ($persistQty == $expectedQty ? 'YES ✓' : 'NO ✗') . "\n";
echo "\n";

// Test 6: External Modification Detection
echo "TEST 6: EXTERNAL MODIFICATION DETECTION\n";
echo "----------------------------------------\n";
$mtimeBefore = filemtime($stockFilePath);
echo "Monitoring file for 3 seconds for external modifications...\n";

$modified = false;
for ($i = 0; $i < 6; $i++) {
    usleep(500000); // 0.5 second
    clearstatcache(true, $stockFilePath);
    $mtimeNow = filemtime($stockFilePath);
    if ($mtimeNow > $mtimeBefore) {
        $modified = true;
        echo "WARNING: File was modified during monitoring!\n";
        echo "Modification detected at: " . date('Y-m-d H:i:s', $mtimeNow) . "\n";
        break;
    }
}

if (!$modified) {
    echo "No external modifications detected ✓\n";
}
echo "\n";

// Test 7: Restore Original Quantity
echo "TEST 7: CLEANUP - RESTORE ORIGINAL QUANTITY\n";
echo "----------------------------------------\n";
echo "Restoring '$testSKU' to original quantity $initialQty...\n";
$restoreStock = loadJSON('stock.json');
$restoreStock[$testSKU]['quantity'] = $initialQty;
$restoreResult = saveJSON('stock.json', $restoreStock);
echo "Restore result: " . ($restoreResult ? 'SUCCESS ✓' : 'FAILED ✗') . "\n";

$verifyRestore = loadJSON('stock.json');
$verifyQty = $verifyRestore[$testSKU]['quantity'];
echo "Verified restored quantity: $verifyQty\n";
echo "Matches original: " . ($verifyQty == $initialQty ? 'YES ✓' : 'NO ✗') . "\n";
echo "\n";

// Summary
echo "================================================================================\n";
echo "DIAGNOSTIC SUMMARY\n";
echo "================================================================================\n";
echo "SKU: $testSKU\n";
echo "Test result: " . ($allMatch && $result && $persistQty == $expectedQty ? 'ALL TESTS PASSED ✓✓✓' : 'SOME TESTS FAILED - SEE ABOVE') . "\n";
echo "\n";

if ($allMatch && $result && $persistQty == $expectedQty) {
    echo "The stock decrease mechanism is working correctly for this SKU.\n";
    echo "\n";
    echo "If you are experiencing issues:\n";
    echo "1. Check error logs during actual checkout for diagnostic messages\n";
    echo "2. Verify browser is not caching admin pages (try hard refresh)\n";
    echo "3. Check for external processes modifying stock.json\n";
    echo "4. Run this script immediately after a failed checkout attempt\n";
} else {
    echo "Issues detected! Review the test output above.\n";
    echo "Possible causes:\n";
    echo "- File permission issues\n";
    echo "- File system problems\n";
    echo "- External process interfering\n";
    echo "- PHP configuration issues (opcache, file locking)\n";
}

echo "================================================================================\n";

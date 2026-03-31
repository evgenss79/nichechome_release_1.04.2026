#!/usr/bin/env php
<?php
/**
 * Stock Sync Dry Run
 * 
 * Shows what would be added to stock.json and branch_stock.json
 * without actually writing any changes.
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/stock/sku_universe.php';

echo "=====================================\n";
echo "STOCK SYNC DRY RUN\n";
echo "=====================================\n\n";

echo "This tool shows what SKUs would be added to stock files\n";
echo "without making any actual changes.\n\n";

// Run initialization in dry-run mode
$result = initializeMissingSkuKeys(true);

if (!$result['success']) {
    echo "❌ ERROR: {$result['error']}\n";
    exit(1);
}

$addedToStock = $result['added_to_stock'];
$addedToBranches = $result['added_to_branches'];

echo "=====================================\n";
echo "RESULTS\n";
echo "=====================================\n\n";

echo "[STOCK.JSON]\n";
if (empty($addedToStock)) {
    echo "✓ No missing SKUs - stock.json is up to date\n";
} else {
    echo "Would add " . count($addedToStock) . " SKUs with qty=0:\n";
    foreach (array_slice($addedToStock, 0, 20) as $sku) {
        echo "  + {$sku}\n";
    }
    if (count($addedToStock) > 20) {
        echo "  ... and " . (count($addedToStock) - 20) . " more\n";
    }
}
echo "\n";

echo "[BRANCH_STOCK.JSON]\n";
if (empty($addedToBranches)) {
    echo "✓ No missing SKUs - branch_stock.json is up to date\n";
} else {
    $totalEntries = 0;
    foreach ($addedToBranches as $sku => $branches) {
        $totalEntries += count($branches);
    }
    echo "Would add " . count($addedToBranches) . " SKUs across branches ({$totalEntries} total entries) with qty=0:\n";
    
    $shown = 0;
    foreach ($addedToBranches as $sku => $branches) {
        if ($shown >= 20) break;
        echo "  + {$sku} (branches: " . implode(', ', $branches) . ")\n";
        $shown++;
    }
    if (count($addedToBranches) > 20) {
        echo "  ... and " . (count($addedToBranches) - 20) . " more SKUs\n";
    }
}
echo "\n";

echo "=====================================\n";
echo "SUMMARY\n";
echo "=====================================\n";

$totalChanges = count($addedToStock) + count($addedToBranches);

if ($totalChanges === 0) {
    echo "✅ Stock files are synchronized with Universe\n";
    echo "No changes needed.\n";
} else {
    echo "⚠️  Found {$totalChanges} SKU entries that need initialization\n";
    echo "\n";
    echo "To apply these changes:\n";
    echo "  1. Review the changes above\n";
    echo "  2. Create backups manually if desired\n";
    echo "  3. Run the sync function in admin panel or via code\n";
    echo "\n";
    echo "Note: This is a DRY RUN - no files were modified\n";
}

exit(0);

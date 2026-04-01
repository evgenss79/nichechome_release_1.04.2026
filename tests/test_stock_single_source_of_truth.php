<?php
require_once __DIR__ . '/../init.php';

$sku = 'SMA-STA-NA';
$branchId = 'branch_1';
$stockPath = __DIR__ . '/../data/stock.json';
$branchStockPath = __DIR__ . '/../data/branch_stock.json';

$originalStockJson = file_get_contents($stockPath);
$originalBranchStockJson = file_get_contents($branchStockPath);
$failures = [];

try {
    $originalStock = loadJSON('stock.json');
    $originalQuantity = (int)($originalStock[$sku]['quantity'] ?? 0);
    $updatedQuantity = $originalQuantity + 3;

    $updateResult = updateConsolidatedStock($sku, $updatedQuantity);
    if (!$updateResult['success']) {
        $failures[] = 'Editing STOCK failed: ' . $updateResult['error'];
    } else {
        $branchItems = getBranchStockItemsFromUniverse($branchId);
        $branchItemsBySku = [];
        foreach ($branchItems as $item) {
            $branchItemsBySku[$item['sku']] = $item;
        }

        $branchViewQuantity = (int)($branchItemsBySku[$sku]['quantity'] ?? -1);
        if ($branchViewQuantity !== $updatedQuantity) {
            $failures[] = "Branch stock view did not immediately mirror STOCK for $sku (expected $updatedQuantity, got $branchViewQuantity)";
        }

        $mirroredBranchStock = loadJSON('branch_stock.json');
        $compatibilityQuantity = (int)($mirroredBranchStock[$branchId][$sku]['quantity'] ?? -1);
        if ($compatibilityQuantity !== $updatedQuantity) {
            $failures[] = "branch_stock.json compatibility mirror was not refreshed for $sku (expected $updatedQuantity, got $compatibilityQuantity)";
        }

        $pickupOk = checkBranchStockForCart($branchId, [[
            'sku' => $sku,
            'productId' => 'smart',
            'name' => 'Aroma Smart',
            'category' => 'accessories',
            'volume' => 'standard',
            'fragrance' => 'none',
            'quantity' => $updatedQuantity
        ]]);

        if (!empty($pickupOk)) {
            $failures[] = 'Pickup validation did not use the updated STOCK quantity for the available case';
        }

        $pickupBlocked = checkBranchStockForCart($branchId, [[
            'sku' => $sku,
            'productId' => 'smart',
            'name' => 'Aroma Smart',
            'category' => 'accessories',
            'volume' => 'standard',
            'fragrance' => 'none',
            'quantity' => $updatedQuantity + 1
        ]]);

        if (empty($pickupBlocked)) {
            $failures[] = 'Pickup validation did not block the unavailable case against the authoritative STOCK quantity';
        }
    }
} finally {
    file_put_contents($stockPath, $originalStockJson);
    file_put_contents($branchStockPath, $originalBranchStockJson);
}

if ($failures) {
    echo "FAIL\n";
    foreach ($failures as $failure) {
        echo "- {$failure}\n";
    }
    exit(1);
}

echo "PASS\n";
echo "- Edited quantity on STOCK and saved successfully\n";
echo "- Branch stock view immediately reflected the same authoritative value\n";
echo "- Pickup validation used the same authoritative STOCK value\n";
echo "- Unavailable pickup case remained blocked correctly\n";

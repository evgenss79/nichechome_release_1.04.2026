<?php
require_once __DIR__ . '/../init.php';

$branchStock = loadBranchStock();
$failures = [];
$candidate = null;

foreach ($branchStock as $branchId => $items) {
    foreach ($items as $sku => $item) {
        $quantity = (int)($item['quantity'] ?? 0);
        if ($quantity > 0) {
            $candidate = [
                'branchId' => $branchId,
                'sku' => $sku,
                'quantity' => $quantity
            ];
            break 2;
        }
    }
}

if ($candidate === null) {
    $failures[] = 'Could not find a branch SKU with positive quantity for pickup validation';
} else {
    $branchId = $candidate['branchId'];
    $sku = $candidate['sku'];
    $available = $candidate['quantity'];

    $reportedQuantity = getBranchStockQuantity($branchId, $sku);
    if ($reportedQuantity !== $available) {
        $failures[] = "getBranchStockQuantity() did not return the stored branch quantity for $sku at $branchId";
    }

    $pickupOk = checkBranchStockForCart($branchId, [[
        'sku' => $sku,
        'productId' => 'test_product',
        'name' => 'Test Product',
        'category' => 'accessories',
        'volume' => 'standard',
        'fragrance' => 'none',
        'quantity' => $available
    ]]);

    if (!empty($pickupOk)) {
        $failures[] = 'Pickup validation rejected a quantity that exists in the selected branch';
    }

    $pickupBlocked = checkBranchStockForCart($branchId, [[
        'sku' => $sku,
        'productId' => 'test_product',
        'name' => 'Test Product',
        'category' => 'accessories',
        'volume' => 'standard',
        'fragrance' => 'none',
        'quantity' => $available + 1
    ]]);

    if (empty($pickupBlocked)) {
        $failures[] = 'Pickup validation did not block a request above the selected branch quantity';
    }
}

if ($failures) {
    echo "FAIL\n";
    foreach ($failures as $failure) {
        echo "- {$failure}\n";
    }
    exit(1);
}

echo "PASS\n";
echo "- Branch stock quantities are read from branch_stock.json per branch\n";
echo "- Pickup validation accepts available branch quantities and blocks unavailable ones\n";

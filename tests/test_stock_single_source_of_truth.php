<?php
require_once __DIR__ . '/../init.php';

$stockPath = __DIR__ . '/../data/stock.json';
$branchStockPath = __DIR__ . '/../data/branch_stock.json';
$originalStockJson = file_get_contents($stockPath);
$originalBranchStockJson = file_get_contents($branchStockPath);
$failures = [];

try {
    $branchStock = loadBranchStock();
    $branchIds = array_keys($branchStock);
    $sku = 'SMA-STA-NA';

    if (count($branchIds) < 3) {
        $failures[] = 'Expected at least 3 branches for branch editing regression test';
    } else {
        $newBranchQuantities = [
            $branchIds[0] => 1,
            $branchIds[1] => 4,
            $branchIds[2] => 7
        ];

        $updateResult = updateConsolidatedStock($sku, $newBranchQuantities);
        if (!$updateResult['success']) {
            $failures[] = 'Editing STOCK failed: ' . $updateResult['error'];
        } else {
            $updatedStock = loadJSON('stock.json');
            $updatedBranchStock = loadBranchStock();
            $consolidated = getConsolidatedStockViewFromUniverse();

            foreach ($newBranchQuantities as $branchId => $expectedQty) {
                $actualQty = (int)($updatedBranchStock[$branchId][$sku]['quantity'] ?? -1);
                if ($actualQty !== $expectedQty) {
                    $failures[] = "Branch quantity mismatch for $branchId (expected $expectedQty, got $actualQty)";
                }

                $viewQty = (int)($consolidated[$sku]['branches'][$branchId] ?? -1);
                if ($viewQty !== $expectedQty) {
                    $failures[] = "Consolidated stock view mismatch for $branchId (expected $expectedQty, got $viewQty)";
                }
            }

            $expectedTotal = array_sum($newBranchQuantities);
            $storedQuantity = (int)($updatedStock[$sku]['quantity'] ?? -1);
            $storedTotalQty = (int)($updatedStock[$sku]['total_qty'] ?? -1);
            $viewTotal = (int)($consolidated[$sku]['total'] ?? -1);

            if ($storedQuantity !== $expectedTotal) {
                $failures[] = "stock.json quantity was not updated to branch sum (expected $expectedTotal, got $storedQuantity)";
            }
            if ($storedTotalQty !== $expectedTotal) {
                $failures[] = "stock.json total_qty was not updated to branch sum (expected $expectedTotal, got $storedTotalQty)";
            }
            if ($viewTotal !== $expectedTotal) {
                $failures[] = "Consolidated stock TOTAL does not equal branch sum (expected $expectedTotal, got $viewTotal)";
            }
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
echo "- Branch quantities remain independently persisted on STOCK\n";
echo "- TOTAL equals the exact sum of the saved branch quantities\n";
echo "- stock.json remains aligned with the branch sum for the edited SKU\n";

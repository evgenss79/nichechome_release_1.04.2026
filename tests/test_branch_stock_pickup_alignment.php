<?php
require_once __DIR__ . '/../init.php';

$failures = [];

$branchItems = getBranchStockItemsFromUniverse('branch_1');
$branchItemsBySku = [];
foreach ($branchItems as $item) {
    $branchItemsBySku[$item['sku']] = $item;
}

$stock = loadJSON('stock.json');
$expectedQuantity = (int)($stock['SMA-STA-NA']['quantity'] ?? 0);

if (!isset($branchItemsBySku['SMA-STA-NA'])) {
    $failures[] = 'Branch stock view is missing SMA-STA-NA for branch_1';
} elseif ((int)$branchItemsBySku['SMA-STA-NA']['quantity'] !== $expectedQuantity) {
    $failures[] = 'Branch stock view does not mirror stock.json for SMA-STA-NA';
}

$normalized = normalizeCartSelection('smart', 'standard', 'none');
if ($normalized['sku'] !== 'SMA-STA-NA') {
    $failures[] = "normalizeCartSelection('smart','standard','none') should return SMA-STA-NA, got {$normalized['sku']}";
}

$pickupErrors = checkBranchStockForCart('branch_1', [[
    'sku' => $normalized['sku'],
    'productId' => 'smart',
    'name' => 'Aroma Smart',
    'category' => 'accessories',
    'volume' => $normalized['volume'],
    'fragrance' => $normalized['fragrance'],
    'quantity' => 1
]]);

if (!empty($pickupErrors)) {
    $failures[] = 'Pickup validation still rejects normalized smart SKU for branch_1';
}

if ($failures) {
    echo "FAIL\n";
    foreach ($failures as $failure) {
        echo "- {$failure}\n";
    }
    exit(1);
}

echo "PASS\n";
echo "- Branch stock view includes SMA-STA-NA with correct branch quantity\n";
echo "- normalizeCartSelection() maps no-fragrance smart accessory to SMA-STA-NA\n";
echo "- Pickup validation accepts the canonical smart SKU for branch_1\n";

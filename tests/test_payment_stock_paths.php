<?php

require_once __DIR__ . '/../init.php';

$repoRoot = dirname(__DIR__);
$ordersPath = $repoRoot . '/data/orders.json';
$stockPath = $repoRoot . '/data/stock.json';
$branchStockPath = $repoRoot . '/data/branch_stock.json';
$ordersBackup = file_get_contents($ordersPath);
$stockBackup = file_get_contents($stockPath);
$branchStockBackup = file_get_contents($branchStockPath);
$serverProcess = null;
$targetSku = generateSKU('limited_palermo', '270ml', 'palermo');

function assertTrue(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function waitForServer(string $baseUrl, int $attempts = 30): void {
    for ($i = 0; $i < $attempts; $i++) {
        $context = stream_context_create(['http' => ['ignore_errors' => true]]);
        $response = @file_get_contents($baseUrl . '/index.php', false, $context);
        if ($response !== false) {
            return;
        }
        usleep(200000);
    }

    throw new RuntimeException('Timed out waiting for PHP test server.');
}

function startPhpServer(string $repoRoot): array {
    $port = random_int(13000, 13999);
    $command = sprintf(
        'php -S 127.0.0.1:%d -t %s',
        $port,
        escapeshellarg($repoRoot)
    );
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['file', sys_get_temp_dir() . '/nichehome-payment-server.log', 'a'],
        2 => ['file', sys_get_temp_dir() . '/nichehome-payment-server.log', 'a'],
    ];
    $process = proc_open($command, $descriptors, $pipes, $repoRoot);
    if (!is_resource($process)) {
        throw new RuntimeException('Failed to start PHP test server.');
    }

    return [
        'process' => $process,
        'base_url' => 'http://127.0.0.1:' . $port,
    ];
}

function httpRequest(string $url, array $options = []): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    foreach ($options as $option => $value) {
        curl_setopt($ch, $option, $value);
    }

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('HTTP request failed: ' . $error);
    }

    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return [
        'status' => $status,
        'headers' => substr($response, 0, $headerSize),
        'body' => substr($response, $headerSize),
    ];
}

try {
    $stock = loadJSON('stock.json');
    assertTrue(isset($stock[$targetSku]), 'Target SKU missing from stock.json for payment stock test.');
    $stock[$targetSku]['quantity'] = 5;
    $stock[$targetSku]['total_qty'] = 5;
    assertTrue(saveJSON('stock.json', $stock), 'Failed to seed stock.json for payment stock test.');
    assertTrue(syncBranchStockCompatibilityFile($stock), 'Failed to sync branch stock compatibility file for payment stock test.');

    $orders = loadOrders();
    $paidOrderId = 'ORD-WEBHOOK-STOCK-TEST';
    $cancelledOrderId = 'ORD-CANCEL-STOCK-TEST';
    $baseOrder = [
        'customer' => [
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'test@example.com',
            'phone' => '+41000000000',
        ],
        'shipping' => [
            'street' => 'Test Street',
            'house' => '1',
            'zip' => '8000',
            'city' => 'Zurich',
            'country' => 'Switzerland',
        ],
        'items' => [[
            'sku' => $targetSku,
            'productId' => 'limited_palermo',
            'name' => 'Limited Palermo',
            'category' => 'limited_edition',
            'volume' => '270ml',
            'fragrance' => 'palermo',
            'price' => 49.9,
            'quantity' => 2,
        ]],
        'pickup_in_branch' => false,
        'pickup_branch_id' => '',
        'subtotal' => 99.8,
        'shipping_cost' => 0,
        'total' => 99.8,
        'status' => 'pending_payment',
        'payment_status' => 'pending',
    ];
    $orders[$paidOrderId] = array_merge($baseOrder, ['id' => $paidOrderId]);
    $orders[$cancelledOrderId] = array_merge($baseOrder, [
        'id' => $cancelledOrderId,
        'items' => [[
            'sku' => $targetSku,
            'productId' => 'limited_palermo',
            'name' => 'Limited Palermo',
            'category' => 'limited_edition',
            'volume' => '270ml',
            'fragrance' => 'palermo',
            'price' => 49.9,
            'quantity' => 1,
        ]],
        'subtotal' => 49.9,
        'total' => 49.9,
    ]);
    assertTrue(saveOrders($orders), 'Failed to seed orders for payment stock test.');

    $server = startPhpServer($repoRoot);
    $serverProcess = $server['process'];
    $baseUrl = $server['base_url'];
    waitForServer($baseUrl);

    $webhookPayload = json_encode([
        'transaction' => [
            'referenceId' => $paidOrderId,
            'status' => 'confirmed',
            'id' => 'txn-test-123',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $webhookResponse = httpRequest($baseUrl . '/webhook_payrexx.php', [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $webhookPayload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    assertTrue($webhookResponse['status'] === 200, 'Webhook success path returned HTTP ' . $webhookResponse['status']);

    $ordersAfterWebhook = loadOrders();
    $stockAfterWebhook = loadJSON('stock.json');
    assertTrue(($ordersAfterWebhook[$paidOrderId]['payment_status'] ?? '') === 'paid', 'Webhook did not mark order payment_status=paid.');
    assertTrue(($ordersAfterWebhook[$paidOrderId]['status'] ?? '') === 'paid', 'Webhook did not mark order status=paid.');
    assertTrue(($ordersAfterWebhook[$paidOrderId]['transaction_id'] ?? '') === 'txn-test-123', 'Webhook did not persist the transaction ID.');
    assertTrue((int)($stockAfterWebhook[$targetSku]['quantity'] ?? -1) === 3, 'Webhook did not decrement stock by the ordered quantity.');

    $cancelResponse = httpRequest($baseUrl . '/payment_cancel.php?orderId=' . rawurlencode($cancelledOrderId) . '&status=cancelled');
    assertTrue($cancelResponse['status'] === 200, 'Payment cancel page returned HTTP ' . $cancelResponse['status']);

    $ordersAfterCancel = loadOrders();
    $stockAfterCancel = loadJSON('stock.json');
    assertTrue(($ordersAfterCancel[$cancelledOrderId]['payment_status'] ?? '') === 'failed', 'Payment cancel flow did not mark payment_status=failed.');
    assertTrue(($ordersAfterCancel[$cancelledOrderId]['status'] ?? '') === 'cancelled', 'Payment cancel flow did not mark status=cancelled.');
    assertTrue((int)($stockAfterCancel[$targetSku]['quantity'] ?? -1) === 3, 'Payment cancel flow changed stock unexpectedly.');

    echo "PASS\n";
} finally {
    if (is_resource($serverProcess)) {
        proc_terminate($serverProcess);
        proc_close($serverProcess);
    }
    if ($ordersBackup !== false) {
        file_put_contents($ordersPath, $ordersBackup);
    }
    if ($stockBackup !== false) {
        file_put_contents($stockPath, $stockBackup);
    }
    if ($branchStockBackup !== false) {
        file_put_contents($branchStockPath, $branchStockBackup);
    }
}

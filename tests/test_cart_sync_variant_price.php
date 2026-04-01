<?php

require_once __DIR__ . '/../init.php';

$repoRoot = dirname(__DIR__);
$productsPath = $repoRoot . '/data/products.json';
$productsBackup = file_get_contents($productsPath);
$productId = 'sync_test_product';
$serverProcess = null;
$cookieFile = tempnam(sys_get_temp_dir(), 'nichehome-cart-cookie-');

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
    $port = random_int(12000, 12999);
    $command = sprintf(
        'php -S 127.0.0.1:%d -t %s',
        $port,
        escapeshellarg($repoRoot)
    );
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['file', sys_get_temp_dir() . '/nichehome-cart-server.log', 'a'],
        2 => ['file', sys_get_temp_dir() . '/nichehome-cart-server.log', 'a'],
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
    $products = loadJSON('products.json');
    $products[$productId] = [
        'id' => $productId,
        'category' => 'aroma_diffusers',
        'name_key' => 'product.' . $productId . '.name',
        'desc_key' => 'product.' . $productId . '.desc',
        'image' => 'Etna.jpg',
        'images' => ['Etna.jpg', 'Bellini.jpg'],
        'allowed_fragrances' => ['bellini', 'eden'],
        'has_fragrance_selector' => true,
        'active' => true,
        'variants' => [
            ['volume' => '100ml', 'fragrance' => 'bellini', 'priceCHF' => 17.5],
            ['volume' => '100ml', 'fragrance' => 'eden', 'priceCHF' => 18.5],
        ],
    ];
    assertTrue(saveJSON('products.json', $products), 'Failed to save sync price test product.');

    $server = startPhpServer($repoRoot);
    $serverProcess = $server['process'];
    $baseUrl = $server['base_url'];
    waitForServer($baseUrl);

    $payload = json_encode([
        'action' => 'sync',
        'cart' => [[
            'sku' => generateSKU($productId, '100ml', 'bellini'),
            'productId' => $productId,
            'name' => 'Sync Test Product',
            'category' => 'aroma_diffusers',
            'volume' => '100ml',
            'fragrance' => 'bellini',
            'price' => 17.5,
            'quantity' => 1,
        ]],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $syncResponse = httpRequest($baseUrl . '/add_to_cart.php', [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assertTrue($syncResponse['status'] === 200, 'Cart sync request failed with HTTP ' . $syncResponse['status']);

    $syncData = json_decode($syncResponse['body'], true);
    assertTrue(is_array($syncData), 'Cart sync response was not valid JSON.');
    assertTrue(!empty($syncData['success']), 'Cart sync did not succeed.');
    assertTrue(abs((float)($syncData['cart'][0]['price'] ?? 0) - 17.5) < 0.001, 'Synced cart price did not preserve the fragrance-specific server price.');
    assertTrue(abs((float)($syncData['cartTotal'] ?? 0) - 17.5) < 0.001, 'Cart total became incorrect after sync.');

    $cartPage = httpRequest($baseUrl . '/cart.php?lang=en', [
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assertTrue($cartPage['status'] === 200, 'Cart page request failed.');
    assertTrue(strpos($cartPage['body'], 'CHF 17.50') !== false, 'Cart page did not render the expected non-zero price.');

    $checkoutPage = httpRequest($baseUrl . '/checkout.php?lang=en', [
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    assertTrue($checkoutPage['status'] === 200, 'Checkout page request failed.');
    assertTrue(strpos($checkoutPage['body'], 'CHF 17.50') !== false, 'Checkout page did not render the expected non-zero price.');

    echo "PASS\n";
} finally {
    if (is_resource($serverProcess)) {
        proc_terminate($serverProcess);
        proc_close($serverProcess);
    }
    if ($productsBackup !== false) {
        file_put_contents($productsPath, $productsBackup);
    }
    if (is_string($cookieFile) && file_exists($cookieFile)) {
        unlink($cookieFile);
    }
}

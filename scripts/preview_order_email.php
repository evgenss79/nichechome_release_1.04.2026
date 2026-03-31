<?php
/**
 * Preview Order Email
 * Generates a sample order email to verify the new template
 */

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/email/templates.php';
require_once __DIR__ . '/../includes/email/mailer.php';

echo "=== ORDER EMAIL PREVIEW ===\n\n";

// Create a sample order with all possible fields
$sampleOrder = [
    'id' => 'ORD-2025-001234',
    'date' => date('Y-m-d H:i:s'),
    'created_at' => date('c'),
    'status' => 'paid',
    'payment_status' => 'paid',
    'transaction_id' => 'TXN-ABC123XYZ789',
    'customer' => [
        'first_name' => 'Maria',
        'last_name' => 'Schmidt',
        'email' => 'maria.schmidt@example.com',
        'phone' => '+41 79 123 45 67'
    ],
    'shipping' => [
        'street' => 'Bahnhofstrasse',
        'house' => '123',
        'zip' => '8001',
        'city' => 'Zürich',
        'country' => 'Switzerland'
    ],
    'payment_method' => 'twint',
    'items' => [
        [
            'name' => 'Aroma Diffuser',
            'sku' => 'DF-250-EDN',
            'volume' => '250ml',
            'fragrance' => 'Eden',
            'quantity' => 2,
            'price' => 29.90,
            'category' => 'aroma_diffusers'
        ],
        [
            'name' => 'Scented Candle',
            'sku' => 'CD-160-FLE',
            'volume' => '160ml',
            'fragrance' => 'Fleur',
            'quantity' => 1,
            'price' => 24.90,
            'category' => 'scented_candles'
        ],
        [
            'name' => 'Home Perfume',
            'sku' => 'HP-50-DUB',
            'volume' => '50ml',
            'fragrance' => 'Dubai',
            'quantity' => 3,
            'price' => 19.90,
            'category' => 'home_perfume'
        ]
    ],
    'subtotal' => 144.40,
    'shipping_cost' => 7.90,
    'total' => 152.30,
    'pickup_in_branch' => false
];

// Test 1: Delivery order
echo "--- TEST 1: Delivery Order (with payment) ---\n\n";
$vars = prepareOrderTemplateVars($sampleOrder);
$rendered = renderEmailTemplate('order_admin', $vars);

echo "Subject: " . $rendered['subject'] . "\n\n";
echo "=== HTML Version (first 500 chars) ===\n";
echo substr($rendered['html'], 0, 500) . "...\n\n";
echo "=== Plain Text Version ===\n";
echo $rendered['text'] . "\n\n";

// Test 2: Pickup order
echo "--- TEST 2: Pickup Order (cash) ---\n\n";
$sampleOrder['pickup_in_branch'] = true;
$sampleOrder['pickup_branch_id'] = 'branch_001';
$sampleOrder['shipping_cost'] = 0.00;
$sampleOrder['total'] = 144.40;
$sampleOrder['payment_method'] = 'cash';
$sampleOrder['status'] = 'awaiting_cash_pickup';
$sampleOrder['payment_status'] = 'pending';
unset($sampleOrder['transaction_id']);

// Mock branch data - save to temporary location instead of overwriting production data
$branches = [
    [
        'id' => 'branch_001',
        'name' => 'NicheHome Zürich HB',
        'address' => 'Bahnhofplatz 1, 8001 Zürich'
    ]
];
$tempBranchFile = '/tmp/test_branches.json';
file_put_contents($tempBranchFile, json_encode($branches, JSON_PRETTY_PRINT));

// Temporarily override the branches file path for testing
$originalBranchesContent = file_exists(__DIR__ . '/../data/branches.json') 
    ? file_get_contents(__DIR__ . '/../data/branches.json') 
    : null;
file_put_contents(__DIR__ . '/../data/branches.json', json_encode($branches, JSON_PRETTY_PRINT));

$vars = prepareOrderTemplateVars($sampleOrder);
$rendered = renderEmailTemplate('order_admin', $vars);

echo "Subject: " . $rendered['subject'] . "\n\n";
echo "=== Plain Text Version ===\n";
echo $rendered['text'] . "\n\n";

// Test email settings
echo "--- EMAIL SETTINGS CHECK ---\n\n";
$settings = loadEmailSettings();
echo "Emails enabled: " . ($settings['enabled'] ? 'YES' : 'NO') . "\n";
echo "Admin orders email: " . ($settings['routing']['admin_orders_email'] ?? 'NOT SET') . "\n";
echo "From email: " . ($settings['smtp']['from_email'] ?? 'NOT SET') . "\n\n";

// Save a sample HTML file for visual inspection
$htmlFile = '/tmp/order_email_preview.html';
$vars = prepareOrderTemplateVars($sampleOrder);
$rendered = renderEmailTemplate('order_admin', $vars);
file_put_contents($htmlFile, $rendered['html']);
echo "✓ Sample HTML saved to: $htmlFile\n";
echo "  You can open this file in a browser to see the full email design.\n\n";

// Restore original branches file if it existed
if ($originalBranchesContent !== null) {
    file_put_contents(__DIR__ . '/../data/branches.json', $originalBranchesContent);
    echo "✓ Original branches.json restored\n\n";
}

echo "=== PREVIEW COMPLETE ===\n";

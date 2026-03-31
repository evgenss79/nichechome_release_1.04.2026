<?php
/**
 * AJAX Endpoint for Favorites Toggle
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['action']) || !isset($data['productId'])) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_request']);
    exit;
}

$action = $data['action'];
$productId = trim($data['productId']);

// Check if customer is logged in
if (!isCustomerLoggedIn()) {
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}

$customerId = getCurrentCustomerId();

// Handle toggle action
if ($action === 'toggle') {
    try {
        $favorites = toggleFavorite($customerId, $productId);
        echo json_encode([
            'success' => true,
            'favorites' => $favorites,
            'productId' => $productId,
            'isInFavorites' => in_array($productId, $favorites, true)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'server_error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Unknown action
http_response_code(400);
echo json_encode(['error' => 'unknown_action']);
exit;

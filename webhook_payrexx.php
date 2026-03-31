<?php
/**
 * Payrexx Webhook Handler
 * Receives payment status notifications from Payrexx
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/payrexx.php';

// Log webhook receipt
error_log('Payrexx Webhook: Received webhook request from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// Read raw POST body
$rawPayload = file_get_contents('php://input');

if (empty($rawPayload)) {
    error_log('Payrexx Webhook: Empty payload received');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Empty payload']);
    exit;
}

// Parse JSON payload
$payload = json_decode($rawPayload, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('Payrexx Webhook: Invalid JSON payload - ' . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

// Get signature from headers - try multiple header formats
$signature = $_SERVER['HTTP_X_PAYREXX_SIGNATURE'] ?? $_SERVER['HTTP_X_API_SIGNATURE_SHA256'] ?? '';

// Verify webhook signature using raw payload (skip verification if no signature provided, e.g. test webhook)
if (!empty($signature)) {
    if (!verifyPayrexxWebhook($rawPayload, $signature)) {
        error_log('Payrexx Webhook: Signature verification failed');
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
        exit;
    }
    error_log('Payrexx Webhook: Signature verification successful');
} else {
    error_log('Payrexx Webhook: No signature provided - skipping verification (test webhook?)');
}

// Extract transaction data
$transaction = $payload['transaction'] ?? $payload;
$referenceId = $transaction['referenceId'] ?? $transaction['reference'] ?? null;
$status = $transaction['status'] ?? null;
$transactionId = $transaction['id'] ?? $transaction['transaction_id'] ?? null;

error_log('Payrexx Webhook: Processing - Reference: ' . ($referenceId ?? 'none') . ', Status: ' . ($status ?? 'none') . ', Transaction: ' . ($transactionId ?? 'none'));

// Allow test hooks without reference ID
if (empty($referenceId)) {
    error_log('Payrexx Webhook: Test webhook received (no reference ID) - returning success');
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Test webhook received']);
    exit;
}

// Load orders
$orders = loadOrders();

if (!is_array($orders)) {
    error_log('Payrexx Webhook: Failed to load orders');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal error']);
    exit;
}

// Find the order by reference ID
$orderFound = false;
foreach ($orders as $orderId => &$order) {
    if ($orderId === $referenceId || ($order['id'] ?? '') === $referenceId) {
        $orderFound = true;
        
        error_log('Payrexx Webhook: Found order ' . $orderId . ' with current status: ' . ($order['status'] ?? 'unknown'));
        
        // Update order based on payment status
        if ($status === 'confirmed' || $status === 'authorized' || $status === 'paid') {
            // Payment successful - only process if not already paid
            $previousStatus = $order['status'] ?? 'unknown';
            $previousPaymentStatus = $order['payment_status'] ?? 'unknown';
            
            $order['status'] = 'paid';
            $order['payment_status'] = 'paid';
            $order['paid_at'] = date('Y-m-d H:i:s');
            $order['transaction_id'] = $transactionId;
            
            error_log('Payrexx Webhook: Order ' . $orderId . ' marked as paid');
            
            // If this is the first time the order is being marked as paid, process it
            if ($previousPaymentStatus !== 'paid') {
                error_log('Payrexx Webhook: Processing newly paid order ' . $orderId);
                
                // Decrease stock
                $cart = $order['items'] ?? [];
                $isPickup = $order['pickup_in_branch'] ?? false;
                $pickupBranchId = $order['pickup_branch_id'] ?? '';
                
                // Validate branch ID if pickup is selected
                if ($isPickup && empty($pickupBranchId)) {
                    error_log("Payrexx Webhook WARNING: Pickup order $orderId has no branch ID, treating as delivery order");
                    $isPickup = false;
                }
                
                error_log("Payrexx Webhook: Starting stock deduction for order $orderId with " . count($cart) . " items");
                
                foreach ($cart as $item) {
                    $sku = $item['sku'] ?? '';
                    $qty = $item['quantity'] ?? 1;
                    $productName = $item['name'] ?? $sku;
                    $category = $item['category'] ?? '';
                    
                    error_log("Payrexx Webhook: Processing item - SKU: $sku, Name: $productName, Qty: $qty");
                    
                    // Special handling for gift sets
                    if ($category === 'gift_sets') {
                        $giftSetItems = $item['meta']['gift_set_items'] ?? $item['items'] ?? [];
                        
                        if (!empty($giftSetItems)) {
                            $skuMap = expandGiftSetToSkuMap($giftSetItems);
                            error_log("Payrexx Webhook: Gift set expanded to " . count($skuMap) . " unique SKUs");
                            
                            foreach ($skuMap as $giftSku => $giftQty) {
                                if ($isPickup) {
                                    decreaseBranchStock($pickupBranchId, $giftSku, $giftQty);
                                } else {
                                    decreaseStock($giftSku, $giftQty);
                                }
                            }
                        }
                        continue;
                    }
                    
                    // Normal product stock decrease
                    if ($isPickup) {
                        decreaseBranchStock($pickupBranchId, $sku, $qty);
                        error_log("Payrexx Webhook: Decreased branch stock - Branch: $pickupBranchId, SKU: $sku, Qty: $qty");
                    } else {
                        decreaseStock($sku, $qty);
                        error_log("Payrexx Webhook: Decreased global stock - SKU: $sku, Qty: $qty");
                    }
                }
                
                // Send confirmation emails
                try {
                    sendOrderConfirmationEmail($order);
                    error_log('Payrexx Webhook: Sent order confirmation email for order ' . $orderId);
                } catch (Exception $e) {
                    error_log('Payrexx Webhook: Failed to send order confirmation email for order ' . $orderId . ': ' . $e->getMessage());
                }
                
                try {
                    sendNewOrderNotification($order);
                    error_log('Payrexx Webhook: Sent new order notification for order ' . $orderId);
                } catch (Exception $e) {
                    error_log('Payrexx Webhook: Failed to send new order notification for order ' . $orderId . ': ' . $e->getMessage());
                }
            }
        } elseif ($status === 'waiting' || $status === 'pending') {
            // Payment pending
            $order['payment_status'] = 'pending';
            error_log('Payrexx Webhook: Order ' . $orderId . ' payment is pending');
        } elseif ($status === 'cancelled' || $status === 'declined' || $status === 'error') {
            // Payment failed or cancelled
            $order['payment_status'] = 'failed';
            $order['status'] = 'cancelled';
            error_log('Payrexx Webhook: Order ' . $orderId . ' payment failed or cancelled');
        }
        
        break;
    }
}
unset($order); // Break reference

if (!$orderFound) {
    error_log('Payrexx Webhook: Order not found for reference ID: ' . $referenceId);
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Order not found']);
    exit;
}

// Save updated orders
if (saveOrders($orders)) {
    error_log('Payrexx Webhook: Successfully updated order ' . $referenceId);
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Order updated']);
} else {
    error_log('Payrexx Webhook: Failed to save order updates for ' . $referenceId);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save order']);
}

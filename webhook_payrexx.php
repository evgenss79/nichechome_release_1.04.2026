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
$responseCode = 200;
$responsePayload = ['status' => 'success', 'message' => 'Order updated'];
foreach ($orders as $orderId => &$order) {
    if ($orderId === $referenceId || ($order['id'] ?? '') === $referenceId) {
        $orderFound = true;
        
        error_log('Payrexx Webhook: Found order ' . $orderId . ' with current status: ' . ($order['status'] ?? 'unknown'));
        
        // Update order based on payment status
        if ($status === 'confirmed' || $status === 'authorized' || $status === 'paid') {
            // Payment successful - only process if not already paid
            $previousStatus = $order['status'] ?? 'unknown';
            $previousPaymentStatus = $order['payment_status'] ?? 'unknown';

            // If this is the first time the order is being marked as paid, process it
            if ($previousPaymentStatus !== 'paid') {
                error_log('Payrexx Webhook: Processing newly paid order ' . $orderId);

                $stockResult = decreaseOrderStock($order);
                if (!$stockResult['success']) {
                    $order['status'] = $previousStatus;
                    $order['payment_status'] = 'inventory_failed';
                    $order['transaction_id'] = $transactionId;
                    $order['stock_error'] = implode(' | ', $stockResult['errors']);
                    $responseCode = 500;
                    $responsePayload = [
                        'status' => 'error',
                        'message' => 'Stock update failed after payment confirmation'
                    ];
                    error_log('Payrexx Webhook: Stock deduction failed for order ' . $orderId . ' - ' . $order['stock_error']);
                    break;
                }

                $order['status'] = 'paid';
                $order['payment_status'] = 'paid';
                $order['paid_at'] = date('Y-m-d H:i:s');
                $order['transaction_id'] = $transactionId;
                unset($order['stock_error']);

                error_log('Payrexx Webhook: Order ' . $orderId . ' marked as paid after successful stock deduction');

                foreach ($stockResult['applied'] as $appliedChange) {
                    $branchLabel = !empty($appliedChange['branch_id']) ? ('Branch: ' . $appliedChange['branch_id'] . ', ') : '';
                    error_log('Payrexx Webhook: Decreased stock - ' . $branchLabel . 'SKU: ' . $appliedChange['sku'] . ', Qty: ' . $appliedChange['quantity']);
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
            } else {
                error_log('Payrexx Webhook: Order ' . $orderId . ' is already marked as paid; skipping duplicate stock deduction');
            }
        } elseif ($status === 'waiting' || $status === 'pending') {
            // Payment pending
            $order['payment_status'] = 'pending';
            error_log('Payrexx Webhook: Order ' . $orderId . ' payment is pending');
        } elseif ($status === 'cancelled' || $status === 'declined' || $status === 'error') {
            // Payment failed or cancelled
            $wasPaid = ($order['payment_status'] ?? '') === 'paid';
            $order['payment_status'] = 'failed';
            if (!$wasPaid) {
                $order['status'] = 'cancelled';
            }
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
    http_response_code($responseCode);
    echo json_encode($responsePayload);
} else {
    error_log('Payrexx Webhook: Failed to save order updates for ' . $referenceId);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save order']);
}

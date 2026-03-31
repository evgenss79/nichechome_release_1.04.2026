<?php
/**
 * Payrexx Payment Integration
 * Functions for creating payments and verifying webhooks
 */

/**
 * Create a Payrexx payment for an order
 * 
 * @param array $order The order array containing order details
 * @return array Returns ['success' => bool, 'paymentUrl' => string, 'error' => string]
 */
function createPayrexxPayment(array $order): array {
    global $CONFIG;
    
    // Validate configuration
    if (empty($CONFIG['payrexx_instance']) || empty($CONFIG['payrexx_api_key']) || empty($CONFIG['app_base_url'])) {
        error_log('Payrexx: Missing configuration - instance, API key, or base URL not set');
        return [
            'success' => false,
            'paymentUrl' => '',
            'error' => 'Payment configuration incomplete'
        ];
    }
    
    // Validate order data
    if (empty($order['id']) || empty($order['total'])) {
        error_log('Payrexx: Invalid order data - missing order ID or total');
        return [
            'success' => false,
            'paymentUrl' => '',
            'error' => 'Invalid order data'
        ];
    }
    
    $orderId = $order['id'];
    $amount = (float)$order['total'];
    
    // Convert amount to cents (Payrexx expects amount in smallest currency unit)
    $amountInCents = (int)round($amount * 100);
    
    // Build callback URLs
    $successUrl = $CONFIG['app_base_url'] . '/payment_success.php?orderId=' . urlencode($orderId);
    $cancelUrl = $CONFIG['app_base_url'] . '/payment_cancel.php?orderId=' . urlencode($orderId);
    $failedUrl = $CONFIG['app_base_url'] . '/payment_cancel.php?orderId=' . urlencode($orderId) . '&status=failed';
    
    // Prepare payment data according to Payrexx Gateway API
    // See: https://developers.payrexx.com/reference/rest-api
    $paymentData = [
        'amount' => $amountInCents,
        'currency' => $CONFIG['currency'] ?? 'CHF',
        'referenceId' => $orderId,
        'purpose' => 'Order #' . $orderId,
        'successRedirectUrl' => $successUrl,
        'failedRedirectUrl' => $failedUrl,
        'cancelRedirectUrl' => $cancelUrl,
    ];
    
    // Add customer contact information to improve checkout experience
    // Note: Customer data is already sanitized in checkout.php before being passed here
    if (!empty($order['customer']['first_name'])) {
        // Extra sanitization as defensive measure
        $paymentData['fields[contact_forename]'] = htmlspecialchars($order['customer']['first_name'], ENT_QUOTES, 'UTF-8');
    }
    if (!empty($order['customer']['last_name'])) {
        $paymentData['fields[contact_surname]'] = htmlspecialchars($order['customer']['last_name'], ENT_QUOTES, 'UTF-8');
    }
    if (!empty($order['customer']['email'])) {
        // Email is validated in checkout.php; this is extra safety
        $paymentData['fields[contact_email]'] = filter_var($order['customer']['email'], FILTER_SANITIZE_EMAIL);
    }
    
    // Add payment method: 'twint' for TWINT, omit for card selection, or use 'visa'/'mastercard' for specific card
    if (($order['payment_method'] ?? '') === 'twint') {
        $paymentData['pm'] = 'twint';
    }
    // For card payments, omit 'pm' to let customer choose between available card methods
    // Note: There is no 'card' identifier in Payrexx API
    
    // Build API endpoint - use official Payrexx REST API
    // Base URL: https://api.payrexx.com/v1.0/:object/:id?instance=:instance
    $apiUrl = 'https://api.payrexx.com/v1.0/Gateway/?instance=' . urlencode($CONFIG['payrexx_instance']);
    
    // Log the API request parameters for debugging (mask sensitive data)
    error_log("Payrexx: Creating payment for order $orderId, amount: CHF " . number_format($amount, 2));
    error_log("Payrexx: API URL: $apiUrl");
    $logData = [
        'amount' => $paymentData['amount'],
        'currency' => $paymentData['currency'],
        'referenceId' => $paymentData['referenceId'],
        'pm' => $paymentData['pm'] ?? 'not set'
    ];
    error_log("Payrexx: Request data: " . json_encode($logData));
    
    // Initialize cURL
    $ch = curl_init($apiUrl);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($paymentData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-KEY: ' . $CONFIG['payrexx_api_key'],  // Use X-API-KEY header for authentication
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Handle cURL errors
    if ($response === false) {
        error_log("Payrexx: cURL error for order $orderId: $curlError");
        return [
            'success' => false,
            'paymentUrl' => '',
            'error' => 'Payment gateway connection failed'
        ];
    }
    
    // Log response status for debugging (don't log full response to avoid exposing sensitive data)
    error_log("Payrexx: API response received for order $orderId - HTTP $httpCode");
    
    // Parse response
    $result = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Payrexx: Failed to parse JSON response for order $orderId: " . json_last_error_msg());
        return [
            'success' => false,
            'paymentUrl' => '',
            'error' => 'Invalid response from payment gateway'
        ];
    }
    
    // Check for API errors in response
    if ($httpCode !== 200) {
        $errorMessage = $result['message'] ?? 'Unknown API error';
        // Log the full response for debugging
        error_log("Payrexx: API error for order $orderId - HTTP $httpCode: $errorMessage");
        error_log("Payrexx: Full API response: " . json_encode($result));
        return [
            'success' => false,
            'paymentUrl' => '',
            'error' => $errorMessage
        ];
    }
    
    // Check for error status in data
    if (isset($result['data'][0]['status']) && $result['data'][0]['status'] === 'error') {
        $errorMessage = $result['data'][0]['message'] ?? $result['message'] ?? 'Payment creation failed';
        // Log the full response for debugging
        error_log("Payrexx: Payment creation error for order $orderId: $errorMessage");
        error_log("Payrexx: Full API response: " . json_encode($result));
        return [
            'success' => false,
            'paymentUrl' => '',
            'error' => $errorMessage
        ];
    }
    
    // Check for successful response with payment link
    if (isset($result['data'][0]['link'])) {
        $paymentUrl = $result['data'][0]['link'];
        error_log("Payrexx: Payment created successfully for order $orderId - URL: $paymentUrl");
        
        return [
            'success' => true,
            'paymentUrl' => $paymentUrl,
            'error' => ''
        ];
    }
    
    // Unexpected response format
    $errorMessage = $result['message'] ?? 'Unexpected response format';
    // Log the full response for debugging
    error_log("Payrexx: Unexpected response for order $orderId - HTTP $httpCode: $errorMessage");
    error_log("Payrexx: Full API response: " . json_encode($result));
    
    return [
        'success' => false,
        'paymentUrl' => '',
        'error' => $errorMessage
    ];
}

/**
 * Verify Payrexx webhook signature
 * 
 * @param string $rawPayload The raw webhook payload string (not parsed)
 * @param string $signature The signature from the webhook headers
 * @return bool True if signature is valid, false otherwise
 */
function verifyPayrexxWebhook(string $rawPayload, string $signature): bool {
    global $CONFIG;
    
    // Check if API key is configured (Payrexx uses API key for webhook HMAC)
    if (empty($CONFIG['payrexx_api_key'])) {
        error_log('Payrexx Webhook: No API key configured for signature verification');
        return false;
    }
    
    // Compute expected signature using HMAC SHA256 on raw payload with API key
    $expectedSignature = hash_hmac('sha256', $rawPayload, $CONFIG['payrexx_api_key']);
    
    // Compare signatures using timing-safe comparison
    $isValid = hash_equals($expectedSignature, $signature);
    
    if (!$isValid) {
        error_log('Payrexx Webhook: Invalid signature');
    }
    
    return $isValid;
}

# Payrexx Integration - Official REST API Implementation

## Overview

This document describes the final, correct implementation of Payrexx payment integration using the **official Payrexx REST API** as documented at https://developers.payrexx.com/reference/rest-api

## Critical Corrections Made

### 1. API Endpoint - Official REST API Base URL

**❌ Previous (Incorrect):**
```php
// Subdomain-based approach - NOT the official API
$apiUrl = 'https://nichehome.payrexx.com/v1/Gateway/';
```

**✅ Now (Correct):**
```php
// Official REST API - instance as query parameter
$apiUrl = 'https://api.payrexx.com/v1.0/Gateway/?instance=' . urlencode($CONFIG['payrexx_instance']);
```

**Key Points:**
- Base URL: `https://api.payrexx.com/v1.0/` (official REST API)
- Instance passed as query parameter: `?instance=nichehome`
- NOT embedded in subdomain
- Reference: https://developers.payrexx.com/reference/rest-api

### 2. Authentication - X-API-KEY Header

**❌ Previous (Incorrect):**
```php
'Authorization: Bearer ' . $CONFIG['payrexx_api_key']
```

**✅ Now (Correct):**
```php
'X-API-KEY: ' . $CONFIG['payrexx_api_key']
```

**Key Points:**
- Payrexx API uses `X-API-KEY` header for authentication
- NOT `Authorization: Bearer`
- Reference: https://developers.payrexx.com/reference/rest-api#authentication

### 3. Customer Contact Fields

**Added customer information to improve checkout experience:**
```php
// Add customer contact information
if (!empty($order['customer']['first_name'])) {
    $paymentData['fields[contact_forename]'] = $order['customer']['first_name'];
}
if (!empty($order['customer']['last_name'])) {
    $paymentData['fields[contact_surname]'] = $order['customer']['last_name'];
}
if (!empty($order['customer']['email'])) {
    $paymentData['fields[contact_email]'] = $order['customer']['email'];
}
```

This pre-fills customer information on the Payrexx payment page.

### 4. Payment Method Identifiers

**✅ Correct Usage:**
```php
// For TWINT payments
if (($order['payment_method'] ?? '') === 'twint') {
    $paymentData['pm'] = 'twint';
}
// For card payments: omit 'pm' to show card selection
// OR use specific identifiers: 'visa', 'mastercard', etc.
```

**Important:**
- There is NO `card` identifier in Payrexx API
- Valid identifiers: `twint`, `visa`, `mastercard`, etc.
- Omit `pm` to let customer choose from available payment methods
- Reference: https://developers.payrexx.com/reference/rest-api#payment-methods

## Complete Payment Request Structure

```php
$paymentData = [
    // Required fields
    'amount' => $amountInCents,              // Amount in smallest currency unit (cents)
    'currency' => 'CHF',                     // Currency code
    'referenceId' => $orderId,               // Your order ID for tracking
    'purpose' => 'Order #' . $orderId,       // Payment description
    
    // Redirect URLs
    'successRedirectUrl' => $successUrl,     // Where to redirect after success
    'failedRedirectUrl' => $failedUrl,       // Where to redirect after failure
    'cancelRedirectUrl' => $cancelUrl,       // Where to redirect if cancelled
    
    // Optional: Customer information (improves UX)
    'fields[contact_forename]' => 'John',    // Customer first name
    'fields[contact_surname]' => 'Doe',      // Customer last name
    'fields[contact_email]' => 'john@example.com', // Customer email
    
    // Optional: Payment method
    'pm' => 'twint'                          // Only for TWINT; omit for card selection
];
```

## API Request Headers

```php
$headers = [
    'X-API-KEY: ' . $CONFIG['payrexx_api_key'],        // Authentication
    'Content-Type: application/x-www-form-urlencoded',  // Content type
    'Accept: application/json'                          // Response format
];
```

## Complete API Request Example

```php
// Build API URL with instance parameter
$apiUrl = 'https://api.payrexx.com/v1.0/Gateway/?instance=' . urlencode($CONFIG['payrexx_instance']);

// Initialize cURL
$ch = curl_init($apiUrl);

// Set options
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($paymentData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-KEY: ' . $CONFIG['payrexx_api_key'],
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

// Execute
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Parse response
$result = json_decode($response, true);

// Check for success
if ($httpCode === 200 && isset($result['data'][0]['link'])) {
    $paymentUrl = $result['data'][0]['link'];
    // Redirect user to payment page
} else {
    // Handle error
    $errorMessage = $result['message'] ?? 'Payment creation failed';
}
```

## API Response Structure

**Success Response (HTTP 200):**
```json
{
  "status": "success",
  "data": [
    {
      "id": 12345,
      "hash": "abc123...",
      "link": "https://nichehome.payrexx.com/pay?tid=abc123..."
    }
  ]
}
```

**Error Response:**
```json
{
  "status": "error",
  "message": "Error description here"
}
```

## Configuration

**Required in `data/config.json`:**
```json
{
  "payrexx_instance": "nichehome",
  "payrexx_api_key": "u04b7NFG77dtgejfVKLRD7w03faNfP",
  "app_base_url": "https://nichehome.ch",
  "currency": "CHF"
}
```

**NOT needed:**
- `payrexx_webhook_secret` - The API key is also used for webhook HMAC verification

## Webhook Handling

Webhook signature verification uses the same API key:

```php
// Compute HMAC SHA256
$expectedSignature = hash_hmac('sha256', $rawPayload, $CONFIG['payrexx_api_key']);

// Compare with signature from X-Payrexx-Signature or X-Api-Signature-Sha256 header
$isValid = hash_equals($expectedSignature, $signature);
```

## Checkout Flow

1. **Create Payment FIRST** (before saving order)
   ```php
   $paymentResult = createPayrexxPayment($order);
   ```

2. **Check Result**
   - If `success=false` → Show error, do NOT save order
   - If `success=true` → Save order with `pending_payment` status

3. **Redirect to Payment**
   ```php
   header('Location: ' . $paymentResult['paymentUrl']);
   ```

4. **Webhook Processes Payment**
   - `confirmed`/`authorized`/`paid` → Mark order as paid, decrease stock, send emails
   - `waiting`/`pending` → Keep in `pending_payment`
   - `cancelled`/`declined`/`error` → Mark as cancelled

## Testing Checklist

### Payrexx Dashboard Configuration

1. **Activate Payrexx Pay**
   - Settings → Payment providers → Payrexx Pay → Enable

2. **Enable Payment Methods**
   - TWINT: Enabled ✓
   - Credit/Debit Cards: Enabled ✓

3. **Configure Webhook**
   - Type: JSON ✓
   - URL: `https://nichehome.ch/webhook_payrexx.php`
   - No trailing slash!

### Test Scenarios

#### 1. TWINT Payment
- [ ] Add item to cart
- [ ] Select TWINT at checkout
- [ ] Submit order
- [ ] **Expected:** Redirect to Payrexx with TWINT option
- [ ] Complete payment
- [ ] **Expected:** Order marked as paid, stock decreased

#### 2. Card Payment
- [ ] Add item to cart
- [ ] Select Card at checkout
- [ ] Submit order
- [ ] **Expected:** Redirect to Payrexx showing card options (Visa, Mastercard)
- [ ] Complete payment
- [ ] **Expected:** Order marked as paid, stock decreased

#### 3. Test Webhook
- [ ] Send test webhook from Payrexx dashboard
- [ ] **Expected:** HTTP 200 response
- [ ] Check logs: "Test webhook received"

### Monitor Error Logs

```bash
# Check for Payrexx logs
tail -f /var/log/php/error.log | grep -i payrexx
```

**Look for:**
- `Payrexx: Creating payment for order...` - Payment initiated
- `Payrexx: API URL: https://api.payrexx.com/v1.0/Gateway/?instance=nichehome` - Correct URL
- `Payrexx: Payment created successfully...` - Payment URL received
- `Payrexx: API error...` - Error occurred (check error message)

## Common API Errors and Solutions

| Error Message | Cause | Solution |
|--------------|-------|----------|
| "Invalid API key" | Wrong API key in config | Check `payrexx_api_key` |
| "Invalid instance" | Wrong instance name | Check `payrexx_instance` |
| "Payment method not active" | Method not enabled | Enable in Payrexx dashboard |
| "Payrexx Pay not activated" | Payrexx Pay disabled | Activate in dashboard |
| "Invalid parameter" | Wrong field in request | Check API docs |

## Files Modified

1. **`includes/payrexx.php`**
   - API endpoint: `https://api.payrexx.com/v1.0/Gateway/?instance={instance}`
   - Authentication: `X-API-KEY` header
   - Added customer contact fields
   - Updated error logging

2. **`checkout.php`** (No changes needed)
   - Already implements payment-first logic correctly

3. **`webhook_payrexx.php`** (No changes needed)
   - Already handles all status transitions correctly

## Success Criteria

After deployment:
- ✅ Payment initiation succeeds (no API errors)
- ✅ TWINT payments redirect to Payrexx with TWINT option
- ✅ Card payments show card selection (Visa, Mastercard)
- ✅ Test webhooks return HTTP 200
- ✅ Orders created only after payment initiated
- ✅ Stock decreased only after webhook confirms payment
- ✅ Error logs show actual API responses

## References

- **Official API Documentation:** https://developers.payrexx.com/reference/rest-api
- **Getting Started:** https://developers.payrexx.com/docs/getting-started
- **Payment Methods:** https://developers.payrexx.com/reference/rest-api#payment-methods
- **Authentication:** https://developers.payrexx.com/reference/rest-api#authentication

## Support

If issues persist:
1. Check error logs for exact API error message
2. Verify Payrexx dashboard configuration
3. Ensure API key has correct permissions
4. Contact Payrexx support with API error details

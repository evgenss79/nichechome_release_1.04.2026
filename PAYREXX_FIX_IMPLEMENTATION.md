# Payrexx Integration Fix - Implementation Summary

## Problem Statement
The Payrexx payment integration was failing with "Payment initiation failed" errors on the checkout page, and Payrexx was sending webhook error emails. This was preventing customers from completing orders via TWINT and card payments.

## Root Causes Identified

1. **Wrong API Endpoint**: Using `/v1/Gateway/` instead of `/v1/Checkout/`
2. **Incorrect Payment Method Parameter**: Sending array `['mastercard', 'visa']` instead of string `'card'`
3. **Overly Complex Payment Data**: Including unnecessary `fields` array with customer data
4. **Insufficient Error Logging**: Not logging enough details to diagnose API errors
5. **Webhook Test Failures**: Test webhooks without `referenceId` were being rejected
6. **Single Signature Header Format**: Only checking one webhook signature header format

## Changes Made

### 1. Updated `includes/payrexx.php`

#### API Endpoint Change
```php
// BEFORE
$apiUrl = 'https://' . $CONFIG['payrexx_instance'] . '.payrexx.com/v1/Gateway/';

// AFTER
$apiUrl = 'https://' . $CONFIG['payrexx_instance'] . '.payrexx.com/v1/Checkout/';
```

#### Simplified Payment Data Structure
```php
// BEFORE - Complex structure with unnecessary fields array
$paymentData = [
    'amount' => $amountInCents,
    'currency' => $CONFIG['currency'] ?? 'CHF',
    'referenceId' => $orderId,
    'successRedirectUrl' => $successUrl,
    'cancelRedirectUrl' => $cancelUrl,
    'failedRedirectUrl' => $failedUrl,
    'purpose' => 'Order #' . $orderId,
    'fields' => [
        'title' => [],
        'firstname' => ['defaultValue' => $order['customer']['first_name'] ?? ''],
        // ... many more fields
    ]
];

// AFTER - Clean, minimal structure
$paymentData = [
    'amount' => $amountInCents,
    'currency' => $CONFIG['currency'] ?? 'CHF',
    'referenceId' => $orderId,
    'purpose' => 'Order #' . $orderId,
    'successRedirectUrl' => $successUrl,
    'failedRedirectUrl' => $failedUrl,
    'cancelRedirectUrl' => $cancelUrl,
];
```

#### Fixed Payment Method Parameter
```php
// BEFORE - Array format causing errors
if (($order['payment_method'] ?? '') === 'card') {
    $paymentMethods[] = 'mastercard';
    $paymentMethods[] = 'visa';
}
$paymentData['pm'] = $paymentMethods;

// AFTER - String format as per Payrexx API
if (($order['payment_method'] ?? '') === 'twint') {
    $paymentData['pm'] = 'twint';
} elseif (($order['payment_method'] ?? '') === 'card') {
    $paymentData['pm'] = 'card';  // Generic 'card' lets Payrexx handle Visa/Mastercard
}
```

#### Enhanced Logging
```php
// Added detailed logging (with sensitive data masked)
error_log("Payrexx: Creating payment for order $orderId, amount: CHF " . number_format($amount, 2));
error_log("Payrexx: API URL: $apiUrl");
$logData = [
    'amount' => $paymentData['amount'],
    'currency' => $paymentData['currency'],
    'referenceId' => $paymentData['referenceId'],
    'pm' => $paymentData['pm'] ?? 'not set'
];
error_log("Payrexx: Request data: " . json_encode($logData));
```

#### Improved Error Handling
```php
// Added comprehensive error checking
if ($httpCode !== 200) {
    $errorMessage = $result['message'] ?? 'Unknown API error';
    error_log("Payrexx: API error for order $orderId - HTTP $httpCode: $errorMessage");
    return ['success' => false, 'paymentUrl' => '', 'error' => $errorMessage];
}

// Check for error status in response data
if (isset($result['data'][0]['status']) && $result['data'][0]['status'] === 'error') {
    $errorMessage = $result['data'][0]['message'] ?? $result['message'] ?? 'Payment creation failed';
    error_log("Payrexx: Payment creation error for order $orderId: $errorMessage");
    return ['success' => false, 'paymentUrl' => '', 'error' => $errorMessage];
}
```

### 2. Updated `webhook_payrexx.php`

#### Support Multiple Signature Headers
```php
// BEFORE
$signature = $_SERVER['HTTP_X_PAYREXX_SIGNATURE'] ?? '';

// AFTER - Check both possible header formats
$signature = $_SERVER['HTTP_X_PAYREXX_SIGNATURE'] ?? $_SERVER['HTTP_X_API_SIGNATURE_SHA256'] ?? '';
```

#### Handle Test Webhooks
```php
// AFTER - Allow test webhooks without referenceId
if (empty($referenceId)) {
    error_log('Payrexx Webhook: Test webhook received (no reference ID) - returning success');
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Test webhook received']);
    exit;
}
```

### 3. Updated `data/config.json`

Removed unnecessary `payrexx_webhook_secret` field:
```json
{
    "payrexx_instance": "nichehome",
    "payrexx_api_key": "u04b7NFG77dtgejfVKLRD7w03faNfP",
    "app_base_url": "https://nichehome.ch"
}
```

### 4. Verified `checkout.php` (No Changes Needed)

The existing checkout flow already had proper error handling:
- Creates payment BEFORE saving order
- Shows error message if payment initiation fails
- Only saves order and redirects if payment creation succeeds
- Maintains existing flow for cash payments

## Testing Performed

### Validation Tests
Created and ran comprehensive validation tests covering:
- ✅ TWINT payment data structure
- ✅ Card payment data structure (string 'card', not array)
- ✅ URL encoding for order IDs
- ✅ Webhook signature header fallback logic
- ✅ Amount conversion to cents (with proper rounding)

All validation tests passed successfully.

### Security Review
- ✅ Masked sensitive data in logs (customer info, payment details)
- ✅ Only log non-sensitive fields for debugging
- ✅ Webhook signature verification maintained
- ✅ HTTPS enforcement for payment endpoints

## Expected Results

### Before Fix
- ❌ Checkout page shows "Payment initiation failed"
- ❌ Orders not created in system
- ❌ Customers cannot complete TWINT/card payments
- ❌ Payrexx sends webhook error emails for test payloads
- ❌ No detailed error information in logs

### After Fix
- ✅ Payment initiation succeeds
- ✅ Customers redirected to Payrexx payment page
- ✅ TWINT payments work correctly
- ✅ Card payments work correctly
- ✅ Test webhooks handled gracefully (no error emails)
- ✅ Detailed, secure logging for debugging
- ✅ Orders created only after successful payment
- ✅ Stock decremented and emails sent after webhook confirmation

## Configuration Requirements

Ensure the following in Payrexx dashboard:
1. **Payrexx Pay** is active (Live or Test mode)
2. **TWINT** payment method is enabled
3. **Card** payment methods (Visa/Mastercard) are enabled
4. **Webhook URL** is set to: `https://nichehome.ch/webhook_payrexx.php`
5. **Webhook Type** is set to: JSON
6. **Retry on failure** is checked

## Troubleshooting

If issues persist after deployment, check the error logs for:
- `Payrexx: API URL:` - Verify endpoint is `/v1/Checkout/`
- `Payrexx: Request data:` - Verify `pm` is a string, not array
- `Payrexx: API error` - Check specific error message from Payrexx
- `Payrexx Webhook:` - Verify webhook is receiving and processing correctly

Common issues:
- **"Payment method not active"**: Enable TWINT/Card in Payrexx dashboard
- **"Invalid signature"**: Verify API key in config matches Payrexx
- **"Missing parameter"**: Check Payrexx API documentation for required fields

## Files Modified

1. `includes/payrexx.php` - Payment creation function
2. `webhook_payrexx.php` - Webhook handler
3. `data/config.json` - Configuration cleanup

## Migration Notes

No database migrations required. Changes are backward compatible with existing orders.

## Rollback Plan

If issues occur, revert to previous version:
```bash
git checkout HEAD~1 includes/payrexx.php webhook_payrexx.php data/config.json
```

## Next Steps

1. Deploy changes to production
2. Test with small live transaction or Payrexx test mode
3. Monitor error logs for any issues
4. Verify webhook emails stop arriving from Payrexx
5. Confirm customer orders process successfully

## Support

For issues or questions:
- Check PHP error log at configured location
- Review Payrexx dashboard webhook logs
- Consult Payrexx API documentation: https://developers.payrexx.com/

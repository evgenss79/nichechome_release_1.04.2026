# Payrexx Payment Integration

This document describes the Payrexx payment integration implemented for NicheHome.ch.

## Overview

The e-commerce platform now supports three payment methods:
- **TWINT** - Online payment via TWINT through Payrexx
- **Credit/Debit Card** - Visa and Mastercard via Payrexx
- **Cash at Pickup** - Cash payment when customer picks up order at branch

## Configuration

### Step 1: Configure Payrexx Credentials

Edit `data/config.json` and update the following keys with your actual Payrexx credentials:

```json
{
  "payrexx_instance": "your_instance_name",
  "payrexx_api_key": "your_api_secret_key",
  "payrexx_webhook_secret": "your_api_secret_key",
  "app_base_url": "https://yourdomain.com"
}
```

**Note:** For Payrexx, the `payrexx_webhook_secret` should be set to the same value as `payrexx_api_key`, as Payrexx uses the API key for webhook signature verification (HMAC-SHA256).

**Important:** Never commit real credentials to the repository. Use environment-specific config files or environment variables in production.

### Step 2: Configure Payrexx Webhook

In your Payrexx dashboard:

1. Go to Settings → Webhooks
2. Add a new webhook with the URL: `https://yourdomain.com/webhook.php`
   - **Important:** Use `webhook.php` (not `webhook_payrexx.php`) to avoid HTTP 301 redirects
   - Ensure you use HTTPS (not HTTP) to prevent redirect issues
3. Set webhook type to: **JSON**
4. Select events to notify: **Transaction** (payment status changes)
5. Enable: **Retry on failure**
6. Payrexx uses your API key for webhook signature verification - no separate webhook secret is generated

### Configuration Parameters

- **payrexx_instance**: Your Payrexx instance name (e.g., "mystore" if your URL is mystore.payrexx.com)
- **payrexx_api_key**: API secret key from Payrexx dashboard (also used for webhook signature verification)
- **payrexx_webhook_secret**: Set to the same value as `payrexx_api_key` (Payrexx uses the API key for HMAC verification)
- **app_base_url**: Your website's base URL (without trailing slash)

## Payment Flow

### Online Payments (TWINT/Card)

1. Customer selects TWINT or Card as payment method
2. Order is created with status `pending_payment`
3. Customer is redirected to Payrexx payment page
4. After payment:
   - **Success**: Redirected to `payment_success.php` 
   - **Cancel/Fail**: Redirected to `payment_cancel.php`
5. Webhook receives payment notification from Payrexx
6. Order status updated to `paid` when payment confirmed

### Cash at Pickup

1. Customer checks "Pickup in branch" checkbox
2. Cash payment option becomes available
3. Customer selects branch and cash payment
4. Order is created with status `awaiting_cash_pickup`
5. Success page shown immediately (no payment gateway redirect)
6. Customer pays cash when picking up order

## Order Statuses

The following order statuses are used for payment tracking:

- `pending_payment` - Waiting for online payment (TWINT/Card)
- `awaiting_cash_pickup` - Order confirmed, awaiting cash payment at pickup
- `paid` - Online payment confirmed by Payrexx
- `processing` - Order being prepared
- `shipped` - Order shipped (delivery orders)
- `completed` - Order completed
- `cancelled` - Order cancelled

## Files

### Core Integration Files

- **includes/payrexx.php** - Payrexx API integration functions
  - `createPayrexxPayment()` - Creates payment gateway and returns payment URL
  - `verifyPayrexxWebhook()` - Verifies webhook signature

- **webhook.php** - Webhook entry point (use this URL in Payrexx dashboard)
  - Wrapper script that prevents HTTP 301 redirects
  - Forwards requests to the actual webhook handler
  - **Set this as your webhook URL:** `https://nichehome.ch/webhook.php`

- **webhook_payrexx.php** - Webhook handler implementation
  - Receives payment status updates from Payrexx
  - Updates order status and payment details
  - Responds with HTTP status codes

- **payment_success.php** - Payment success page
  - Displays order confirmation
  - Shows payment status

- **payment_cancel.php** - Payment cancel/failed page
  - Displays cancellation/failure message
  - Provides options to retry or contact support

### Modified Files

- **checkout.php** - Updated to support new payment methods and redirect flow
- **admin/orders.php** - Extended status dropdown with new payment statuses
- **data/config.json** - Added Payrexx configuration keys

## Testing

### Test Mode

Payrexx provides a test environment. To use test mode:

1. Create a test instance in Payrexx
2. Update `config.json` with test credentials
3. Use test credit cards provided by Payrexx documentation

### Webhook Testing

Before setting up webhooks in production, verify the webhook endpoint is accessible without redirects:

1. **Test the webhook URL directly:**
   - Visit `https://nichehome.ch/webhook.php` in your browser
   - You should see a JSON response: `{"status":"error","message":"Empty payload"}`
   - Check the HTTP status code is **200** (not 301 or 302)
   - If you see a redirect or different status code, review the troubleshooting section

2. **Test with Payrexx test webhook:**
   - In Payrexx dashboard, use the "Test webhook" feature
   - Check that Payrexx logs show HTTP 200 response (not 301)
   - Verify no redirect errors in Payrexx webhook logs

### Manual Testing Checklist

- [ ] Verify webhook.php returns HTTP 200 with JSON error (not 301 redirect)
- [ ] Test TWINT payment flow
- [ ] Test Card payment flow
- [ ] Test Cash at Pickup flow
- [ ] Test payment cancellation
- [ ] Test webhook reception and order status update
- [ ] Verify stock decreases after payment confirmation
- [ ] Verify customer and admin emails are sent
- [ ] Test admin order status updates
- [ ] Verify email notifications include correct payment method

## Security Considerations

1. **API Keys**: Never commit real API keys to version control
2. **Webhook Signature**: Always verify webhook signatures to prevent fraudulent updates
3. **HTTPS**: Use HTTPS for all payment-related pages and webhook endpoint
4. **Error Logging**: Sensitive payment details are logged only to error_log, not displayed to users

## Troubleshooting

### Webhook returning HTTP 301 (redirect error)

**Problem:** Payrexx logs show HTTP 301 errors when calling the webhook, preventing order updates.

**Solution:** 
- Ensure you are using `https://nichehome.ch/webhook.php` (not `webhook_payrexx.php`)
- Use HTTPS (not HTTP) in the webhook URL to avoid .htaccess redirect rules
- Verify the webhook URL has no trailing slash
- Test by visiting `https://nichehome.ch/webhook.php` - you should see a JSON error response like `{"status":"error","message":"Empty payload"}` (not a redirect)

### Payment redirect not working

- Check that `app_base_url` in config.json matches your actual domain
- Verify Payrexx credentials are correct
- Check error_log for payment API errors

### Webhook not updating order status

- Verify webhook URL is accessible from internet
- Ensure webhook URL is set to `https://nichehome.ch/webhook.php`
- Check that webhook secret in config.json matches Payrexx API key
- Review webhook logs in Payrexx dashboard for HTTP status codes
- Check PHP error_log for webhook processing errors
- Verify webhook settings: Type=JSON, Events=Transaction, Retry enabled

### Cash payment option not showing

- Ensure "Pickup in branch" checkbox is selected
- Check browser console for JavaScript errors
- Verify pickup branches are configured in admin panel

## Webhook Redirect Fix Summary

### The Problem
Payrexx was unable to deliver webhook events because `webhook_payrexx.php` was returning HTTP 301 (redirect) instead of HTTP 200. This prevented:
- Order status updates from "pending_payment" to "paid"
- Stock reduction after successful payments
- Confirmation emails to customers and admin

### The Solution
A wrapper file `webhook.php` was created to serve as the webhook entry point. This file:
- Contains no redirect logic
- Simply includes `webhook_payrexx.php` for processing
- Returns HTTP 200 on success without server-side redirects

### Implementation
1. **webhook.php** acts as a clean entry point
2. **webhook_payrexx.php** handles all webhook logic
3. Documentation updated to specify `webhook.php` as the webhook URL
4. Must use HTTPS to avoid .htaccess redirect rules

### Validation Steps
After deployment, verify the fix by:
1. Visit `https://nichehome.ch/webhook.php` → should return HTTP 200 with JSON error
2. Update Payrexx webhook URL to `https://nichehome.ch/webhook.php`
3. Run a test transaction
4. Check Payrexx logs → should show HTTP 200 (not 301)
5. Verify order status changes to "paid"
6. Verify stock decreases
7. Verify emails are sent

## Support

For Payrexx API documentation and support:
- Documentation: https://developers.payrexx.com/
- Support: https://www.payrexx.com/en/support

For integration issues, check the PHP error log at `/var/log/php/error.log` or your server's configured error log location.

# Payrexx Integration - Quick Start Guide

## ✅ What Was Fixed

The Payrexx payment integration has been fixed to resolve:
1. ❌ "Payment initiation failed" errors → ✅ Now uses correct Gateway API endpoint
2. ❌ HTTP 301 webhook errors → ✅ Added webhook wrapper and improved handling
3. ❌ Card payments not working → ✅ Fixed payment method parameter logic

## 🚀 Immediate Action Required

### 1. Verify Payrexx Dashboard Settings

Login to your Payrexx dashboard at: https://nichehome.payrexx.com

**Check these settings:**

#### a) Payrexx Pay Activation
- Go to: **Settings → Payment providers → Payrexx Pay**
- Status: Must be **Enabled** ✅
- Mode: Set to **Live** (or **Test** for testing)

#### b) Payment Methods
- Go to: **Settings → Payment methods**
- Enable: **TWINT** ✅
- Enable: **Credit/Debit Cards** (Visa, Mastercard) ✅

#### c) Webhook Configuration
- Go to: **Settings → Webhooks**
- Type: **JSON** (NOT PHP Post) ✅
- URL: `https://nichehome.ch/webhook.php` ✅
  - ⚠️ **Use `webhook.php` to avoid HTTP 301 redirect errors**
  - ⚠️ **No trailing slash!**
  - ⚠️ **Must use HTTPS (not HTTP)**
- Events: **Transaction** ✅
- Retry on failure: **Enabled** ✅

### 2. Test the Integration

#### Test 1: TWINT Payment
1. Add a product to cart
2. Go to checkout
3. Select **TWINT** payment method
4. Submit order
5. **Expected:** Redirects to Payrexx payment page (NOT error page)
6. Complete payment on Payrexx
7. **Expected:** Redirects back to success page
8. Check order in admin panel - should be marked as **paid**

#### Test 2: Card Payment
1. Add a product to cart
2. Go to checkout
3. Select **Credit/Debit Card** payment method
4. Submit order
5. **Expected:** Redirects to Payrexx showing card options (Visa, Mastercard)
6. Complete payment
7. **Expected:** Redirects back to success page
8. Check order in admin panel - should be marked as **paid**

#### Test 3: Verify Webhook Accessibility
1. Visit `https://nichehome.ch/webhook.php` in your browser
2. **Expected:** JSON response `{"status":"error","message":"Empty payload"}` with HTTP 200 ✅
3. **If you see a redirect or different error:** Review webhook configuration

#### Test 4: Test Webhook from Payrexx
1. Go to Payrexx dashboard → Settings → Webhooks
2. Click "Send Test" next to your webhook
3. **Expected:** Response: HTTP 200 OK ✅ (NOT 301)
4. Check error logs: Should show "Test webhook received"

### 3. Monitor Error Logs

Check your PHP error log for Payrexx-related messages:

```bash
# Linux/Unix
tail -f /var/log/php/error.log | grep -i payrexx

# Or check your hosting panel's error log viewer
```

**Look for:**
- `Payrexx: Creating payment for order...` - Payment initiated
- `Payrexx: Payment created successfully...` - Payment URL received
- `Payrexx: API error...` - Something went wrong (see error message)
- `Payrexx Webhook: Processing...` - Webhook received
- `Payrexx Webhook: Order ... marked as paid` - Payment confirmed

### 4. Common Issues & Solutions

#### Issue: "Payment initiation failed"
**Solutions:**
- ✅ Check API key in `data/config.json`
- ✅ Verify Payrexx Pay is activated
- ✅ Check error log for actual API error message

#### Issue: Webhook returns HTTP 301
**This is now FIXED!** 
**Solutions:**
- ✅ Use `https://nichehome.ch/webhook.php` (NOT webhook_payrexx.php)
- ✅ Use HTTPS (not HTTP) to avoid .htaccess redirects
- ✅ Remove trailing slash from webhook URL
- ✅ Test by visiting https://nichehome.ch/webhook.php - should return HTTP 200 with JSON error

#### Issue: Card payments not showing card options
**Solutions:**
- ✅ This is now fixed - don't set `pm` parameter for cards
- ✅ Verify card methods are enabled in Payrexx dashboard

#### Issue: Test webhook fails
**Solutions:**
- ✅ This is now fixed - signature verification is skipped for test webhooks
- ✅ Webhook should return HTTP 200

## 📝 Configuration Reference

Your `data/config.json` should have:
```json
{
  "payrexx_instance": "nichehome",
  "payrexx_api_key": "u04b7NFG77dtgejfVKLRD7w03faNfP",
  "app_base_url": "https://nichehome.ch"
}
```

**Note:** Do NOT add `payrexx_webhook_secret` - it's not needed!

## 🔧 Technical Details

### What Changed?

1. **API Endpoint:** `/v1/Checkout/` → `/v1/Gateway/`
2. **TWINT:** Sets `pm=twint` ✅
3. **Card:** Does NOT set `pm` parameter ✅ (allows all card types)
4. **Webhook:** Handles test webhooks without signatures ✅
5. **Logging:** Full API responses logged for debugging ✅

### Payment Flow

**Before Fix:**
1. Submit checkout → ❌ API error → Order not saved

**After Fix:**
1. Submit checkout → ✅ API success → Order saved
2. Redirect to Payrexx → Complete payment
3. Webhook updates order → Stock decreased → Emails sent

## 📚 Documentation

For detailed technical information, see:
- **`PAYREXX_API_FIX_IMPLEMENTATION.md`** - Complete implementation guide
- **`PAYREXX_INTEGRATION.md`** - Original integration documentation
- **`PAYREXX_FIX_SUMMARY.md`** - Previous fix summary

## 🆘 Need Help?

If payment initiation still fails:

1. Check error log for: `Payrexx: Full API response:`
2. This will show the exact error from Payrexx API
3. Common errors:
   - "Invalid API key" → Check `payrexx_api_key` in config
   - "Invalid instance" → Check `payrexx_instance` in config
   - "Payrexx Pay not activated" → Enable in dashboard
   - "Invalid payment method" → Enable in dashboard

## ✅ Success!

When everything works:
- ✅ TWINT payments redirect to Payrexx
- ✅ Card payments show card selection
- ✅ Payments complete successfully
- ✅ Orders marked as paid
- ✅ Stock decreased automatically
- ✅ Confirmation emails sent
- ✅ Test webhooks return HTTP 200

**Happy selling! 🎉**

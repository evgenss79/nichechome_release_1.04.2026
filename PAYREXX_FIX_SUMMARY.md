# Payrexx Payment Integration Fix - Implementation Summary

## Overview
Fixed the Payrexx payment integration to ensure orders are **not processed or emailed** until payment is successfully initiated. The previous implementation saved orders, decreased stock, and sent emails before calling the payment gateway, which meant orders were confirmed even when payment failed.

## Changes Made

### 1. Configuration (`data/config.json`)
✅ Already configured correctly:
- `payrexx_instance`: "nichehome"
- `payrexx_api_key`: "u04b7NFG77dtgejfVKLRD7w03faNfP"
- `payrexx_webhook_secret`: "u04b7NFG77dtgejfVKLRD7w03faNfP" (same as API key)
- `app_base_url`: "https://nichehome.ch"

### 2. Checkout Flow Refactoring (`checkout.php`)

#### Previous Flow (INCORRECT):
1. Save order → 2. Decrease stock → 3. Send emails → 4. Create payment (too late!)

#### New Flow (CORRECT):

**For Online Payments (TWINT/Card):**
1. **Create Payrexx payment FIRST** (lines 292-308)
2. If payment creation fails → Show error, do NOT save order
3. If payment creation succeeds → Save order with status `pending_payment`
4. Do NOT decrease stock
5. Do NOT send confirmation emails
6. Clear cart and redirect user to Payrexx payment URL (lines 547-557)
7. **Webhook handles stock decrease and emails after payment confirmation**

**For Cash Payments (Pickup):**
1. Save order with status `awaiting_cash_pickup`
2. Decrease stock immediately (lines 338-495)
3. Send confirmation emails immediately (lines 525-538)
4. Show success page

#### Key Code Changes:
- **Lines 289-316**: Payment initiation logic moved BEFORE order save
- **Lines 336-542**: Stock decrease and emails wrapped in `if ($paymentMethod === 'cash' && $isPickup)` condition
- **Lines 547-557**: Redirect to payment URL for online payments

### 3. Webhook Enhancement (`webhook_payrexx.php`)

#### New Functionality (lines 79-140):
When payment status is `confirmed`, `authorized`, or `paid`:
1. **Check if this is the first time being marked as paid** (prevents duplicate processing)
2. **Decrease stock** for all order items (lines 88-122)
   - Handles both global stock and branch stock
   - Handles gift sets (expands to underlying SKUs)
3. **Send confirmation emails** (lines 124-138)
   - Customer confirmation email
   - Admin notification email
4. **Update order status** to `paid`

#### Signature Verification:
✅ Already correct in `includes/payrexx.php` (line 178):
```php
$expectedSignature = hash_hmac('sha256', $rawPayload, $CONFIG['payrexx_api_key']);
```
Uses API key for HMAC verification (no separate webhook secret).

## Payment Flow Diagrams

### TWINT/Card Payment Flow:
```
User submits checkout form
    ↓
Validate form & stock
    ↓
Create Payrexx payment ← [Payment gateway called FIRST]
    ↓
Payment creation succeeds?
    ├─ NO → Show error, order NOT saved
    └─ YES → Save order with status="pending_payment"
                ↓
             Clear cart
                ↓
             Redirect to Payrexx payment page
                ↓
             [User completes payment on Payrexx]
                ↓
             Webhook receives payment confirmation
                ↓
             Decrease stock + Send emails
                ↓
             Update order status to "paid"
```

### Cash Payment Flow:
```
User submits checkout form (with pickup selected)
    ↓
Validate form & stock
    ↓
Save order with status="awaiting_cash_pickup"
    ↓
Decrease stock immediately
    ↓
Send confirmation emails
    ↓
Clear cart
    ↓
Show success page
```

## Testing Checklist

### Before Testing:
- [ ] Ensure Payrexx Pay is activated in Payrexx admin UI
- [ ] Select TWINT and Card payment methods in Payrexx settings
- [ ] Configure webhook in Payrexx:
  - Type: **JSON** (not PHP Post)
  - URL: `https://nichehome.ch/webhook_payrexx.php`

### Test Scenarios:

#### Scenario 1: TWINT Payment (Success)
1. Add items to cart
2. Go to checkout
3. Fill in all required fields
4. Select TWINT payment method
5. Submit order
6. **Expected**: Redirect to Payrexx payment page
7. **Expected**: Order saved with status `pending_payment`
8. **Expected**: Stock NOT yet decreased
9. **Expected**: No confirmation emails sent yet
10. Complete payment on Payrexx
11. **Expected**: Webhook receives confirmation
12. **Expected**: Stock decreased
13. **Expected**: Confirmation emails sent
14. **Expected**: Order status updated to `paid`

#### Scenario 2: Card Payment (Success)
Same as Scenario 1 but select Credit/Debit Card

#### Scenario 3: Payment Initiation Failure
1. Add items to cart
2. Go to checkout
3. Fill in all required fields
4. Select TWINT or Card
5. Submit order
6. If Payrexx API fails (e.g., wrong credentials):
   - **Expected**: Error message shown
   - **Expected**: Order NOT saved
   - **Expected**: Stock NOT decreased
   - **Expected**: User can retry

#### Scenario 4: Cash Payment (Pickup)
1. Add items to cart
2. Go to checkout
3. Check "Pickup in branch" and select a branch
4. Fill in all required fields
5. Select "Cash at Pickup" payment method
6. Submit order
7. **Expected**: Order saved with status `awaiting_cash_pickup`
8. **Expected**: Stock decreased immediately
9. **Expected**: Confirmation emails sent immediately
10. **Expected**: Success page shown

#### Scenario 5: Payment Cancelled by User
1. Complete steps 1-6 from Scenario 1
2. On Payrexx payment page, click "Cancel"
3. **Expected**: Redirect to cancel page
4. **Expected**: Order remains with status `pending_payment`
5. **Expected**: Stock NOT decreased
6. **Expected**: No confirmation emails

## Security Considerations

### Webhook Signature Verification:
- Uses HMAC SHA256 with API key
- Timing-safe comparison (`hash_equals`)
- Rejects webhooks with invalid signatures

### Payment-First Architecture:
- Prevents order confirmation before payment
- Protects against race conditions
- Ensures inventory integrity

## Monitoring & Logs

All operations are logged with the prefix:
- `CHECKOUT:` - Checkout process logs
- `Payrexx Webhook:` - Webhook processing logs
- `STOCK ERROR:` - Stock operation errors

Key log points:
1. Payment initiation success/failure
2. Order save operations
3. Stock decrease operations
4. Email sending success/failure
5. Webhook signature verification
6. Payment status updates

## Rollback Plan

If issues occur, revert to previous checkout flow by:
1. Moving payment creation back to after order save
2. Removing the `if ($paymentMethod === 'cash' && $isPickup)` condition
3. Removing webhook stock/email logic

However, this will revert to the buggy behavior where orders are confirmed before payment.

## Notes for Payrexx Admin Configuration

**CRITICAL**: The code changes alone are not sufficient. You must:

1. **Activate Payrexx Pay** in the Payrexx admin UI:
   - Go to Settings → Payment providers → Payrexx Pay
   - Enable it and save

2. **Select payment methods**:
   - Enable TWINT
   - Enable credit/debit cards

3. **Configure webhook**:
   - Type: **JSON** (NOT PHP Post)
   - URL: `https://nichehome.ch/webhook_payrexx.php`
   - Ensure no HTTP 301 redirects (URL must match exactly)

Without these steps, the API will return errors and `createPayrexxPayment()` will fail.

## Files Modified

1. `checkout.php` - Refactored payment flow
2. `webhook_payrexx.php` - Added stock decrease and email sending
3. `data/config.json` - Already had correct configuration (no changes needed)

No changes needed to:
- `includes/payrexx.php` - Already using API key for signature verification
- Email helper functions - Already work correctly

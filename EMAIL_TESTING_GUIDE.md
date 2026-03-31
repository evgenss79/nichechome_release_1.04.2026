# Email System Testing Guide

This guide provides step-by-step instructions for testing the new email notification system.

## Prerequisites

- Admin access to the NicheHome.ch admin panel
- Access to TutHost SMTP credentials
- Test email address for receiving emails

## Phase 1: Admin UI Testing

### 1.1 Access Admin Email Settings

1. Log in to admin panel: `/admin/login.php`
2. Click on "Email" in the left sidebar
3. Verify you can see all 5 tabs:
   - SMTP Settings
   - Routing
   - Templates
   - Test Email
   - Logs

**Expected Result**: All tabs are visible and clickable.

### 1.2 SMTP Settings Tab

1. Go to SMTP Settings tab
2. Check the "Enable email sending" checkbox
3. Fill in the following:
   - SMTP Host: `smtp.nichehome.ch`
   - SMTP Port: `587`
   - Encryption: `TLS`
   - SMTP Username: `orders@nichehome.ch`
   - SMTP Password: `[Enter actual password]`
   - From Email: `orders@nichehome.ch`
   - From Name: `NicheHome.ch`
4. Click "Save SMTP Settings"

**Expected Result**: 
- Success message appears
- Password field shows "✓ Password is set (encrypted)" after save
- Password is never displayed in plain text

**Security Check**:
- View page source and search for the password - it should NOT appear
- Check `/data/email_settings.json` - password should be in `password_encrypted` field as encrypted string

### 1.3 Routing Tab

1. Go to Routing tab
2. Fill in:
   - Admin Orders Email: `info@nichehome.ch`
   - Support Email: `support@nichehome.ch`
   - Reply-To Email: `support@nichehome.ch`
3. Click "Save Routing Settings"

**Expected Result**: Success message appears

### 1.4 Templates Tab

1. Go to Templates tab
2. Select each template from dropdown:
   - Order Admin Notification
   - Order Customer Confirmation
   - Support Admin Notification
   - Support Customer Auto-Reply
3. For each template, verify:
   - Subject field is populated
   - HTML Body field is populated (large textarea)
   - Plain Text Body field is populated
4. Try editing one template (e.g., change subject)
5. Click "Save Template"

**Expected Result**: 
- All templates are pre-populated with defaults
- Template saves successfully
- Changes persist after save

### 1.5 Test Email Tab

1. Go to Test Email tab
2. Enter your email address in "Test Email Address" field
3. Click "Send Test Email"
4. Check your inbox (and spam folder)

**Expected Result**:
- Success message appears in admin UI
- Test email arrives in inbox within 1-2 minutes
- Email has proper formatting and sender name

**Troubleshooting**:
- If failed, check error message
- Go to Logs tab to see detailed error
- Verify SMTP credentials are correct

### 1.6 Logs Tab

1. Go to Logs tab
2. Verify log entries are visible

**Expected Result**:
- Recent email events are displayed
- Success and failure events are distinguishable (different colors)
- No sensitive data (passwords) in logs

## Phase 2: Order Flow Testing

### 2.1 Place Test Order

1. Open the shop in incognito/private window
2. Add a product to cart (e.g., Aroma Diffusor 125ml Cherry Blossom)
3. Go to checkout
4. Fill in all required fields:
   - Use a real email address you can check
   - Choose delivery or pickup
   - Select payment method
5. Submit order

**Expected Result**:
- Order is placed successfully
- Order ID is displayed
- Cart is cleared

### 2.2 Check Admin Notification Email

Check the admin orders email (`info@nichehome.ch`)

**Expected Result**:
- Email arrives with subject "New Order #[ORDER_ID] — NicheHome.ch"
- Email contains:
  - Customer name, email, phone
  - Order items with quantities and prices
  - Subtotal, shipping, total
  - Payment method
  - Shipping/pickup information
  - Link to admin panel

### 2.3 Check Customer Confirmation Email

Check the email address used in checkout

**Expected Result**:
- Email arrives with subject "Order Confirmation #[ORDER_ID] — NicheHome.ch"
- Email contains:
  - Thank you message
  - Order details (items, prices)
  - Shipping/pickup information
  - Professional formatting

### 2.4 Verify Email Logs

1. Go to `/admin/email.php` → Logs tab
2. Check for two new entries:
   - `[SUCCESS] Event: order_admin | To: info@nichehome.ch`
   - `[SUCCESS] Event: order_customer | To: [customer-email]`

**Expected Result**: Both events logged as SUCCESS

## Phase 3: Support Form Testing

### 3.1 Submit Support Request

1. Go to `/support.php`
2. Fill in all fields:
   - First Name, Last Name
   - Email (use a real address you can check)
   - Phone
   - Subject: "Test Support Request"
   - Message: "This is a test message"
3. Submit form

**Expected Result**:
- Success message displayed
- Form is cleared

### 3.2 Check Admin Support Email

Check the support email (`support@nichehome.ch`)

**Expected Result**:
- Email arrives with subject "New Support Request — [Name]"
- Email contains:
  - Customer name, email, phone
  - Subject and message
  - Date/time
  - Reply-to is set to customer email

### 3.3 Check Customer Auto-Reply

Check the email address used in support form

**Expected Result**:
- Email arrives with subject "We Received Your Request — NicheHome.ch"
- Email contains:
  - Thank you message
  - Quote of their original message
  - Expected response time
  - Professional formatting

### 3.4 Verify Email Logs

Check `/admin/email.php` → Logs tab

**Expected Result**: Two new entries:
- `[SUCCESS] Event: support_admin | To: support@nichehome.ch`
- `[SUCCESS] Event: support_customer | To: [customer-email]`

## Phase 4: Failure Testing

### 4.1 Test with Email Disabled

1. Go to `/admin/email.php` → SMTP Settings
2. Uncheck "Enable email sending"
3. Save settings
4. Place a test order

**Expected Result**:
- Order placement succeeds (not blocked)
- No emails sent
- Logs show: `[FAILED] Event: ... | Error: Email sending is disabled in configuration`

### 4.2 Test with Invalid SMTP Credentials

1. Go to SMTP Settings
2. Enable email sending
3. Change SMTP password to incorrect value
4. Save settings
5. Try to send test email

**Expected Result**:
- Test fails with error message
- Log entry shows authentication error
- Order placement still works (not blocked by email failure)

### 4.3 Re-enable Email

1. Go to SMTP Settings
2. Enter correct SMTP password
3. Save and verify test email works again

## Phase 5: Security Testing

### 5.1 Password Security

1. Go to SMTP Settings tab
2. View page source (Ctrl+U or right-click → View Source)
3. Search for "password" in source

**Expected Result**: 
- SMTP password is NOT visible in plain text
- Only see empty password field or encrypted value in JSON

### 5.2 Admin Authentication

1. Log out of admin panel
2. Try to access `/admin/email.php` directly

**Expected Result**: 
- Redirected to login page
- Cannot access email settings without authentication

### 5.3 Log File Security

1. Check `/logs/email.log` file
2. Search for "password" in log contents

**Expected Result**:
- No passwords or sensitive data in logs
- Only email addresses, event types, success/failure status

## Phase 6: Regression Testing

Verify existing functionality still works:

### 6.1 Cart & Checkout
- [ ] Add products to cart
- [ ] View cart
- [ ] Update quantities
- [ ] Remove items
- [ ] Proceed to checkout
- [ ] Complete order

### 6.2 Account Functions
- [ ] Register new account
- [ ] Login
- [ ] View order history
- [ ] Update profile
- [ ] Manage favorites

### 6.3 Admin Functions
- [ ] View orders
- [ ] Update stock
- [ ] Manage products
- [ ] View branches
- [ ] Configure shipping

**Expected Result**: All existing functionality works without errors

## Test Results Checklist

Use this checklist to track test completion:

### Admin UI
- [ ] All 5 tabs visible and functional
- [ ] SMTP settings save correctly
- [ ] Password encrypted and never displayed
- [ ] Routing settings save correctly
- [ ] Templates load and save correctly
- [ ] Test email sends successfully
- [ ] Logs display correctly

### Order Emails
- [ ] Admin notification received
- [ ] Customer confirmation received
- [ ] Emails have correct content
- [ ] Email formatting is professional
- [ ] Order placement not blocked by email failure

### Support Emails
- [ ] Admin notification received
- [ ] Customer auto-reply received
- [ ] Emails have correct content
- [ ] Reply-to is set correctly
- [ ] Support form not blocked by email failure

### Security
- [ ] Password never displayed in UI
- [ ] Password encrypted in JSON file
- [ ] No sensitive data in logs
- [ ] Admin authentication required
- [ ] No XSS vulnerabilities

### Regression
- [ ] Cart works
- [ ] Checkout works
- [ ] Stock management works
- [ ] Account functions work
- [ ] No new errors in browser console
- [ ] No PHP errors in server logs

## Troubleshooting Common Issues

### "Failed to decrypt SMTP password"
**Solution**: Re-enter the SMTP password in admin settings and save.

### "SMTP settings are incomplete"
**Solution**: Ensure all required fields are filled (host, port, username, password, from email).

### Test email not received
**Checks**:
1. Email sending is enabled
2. SMTP credentials are correct
3. Check spam folder
4. Check logs for error details
5. Verify firewall doesn't block port 587

### Order placed but no email
**Checks**:
1. Email system is enabled
2. Admin/routing emails are configured
3. Check logs for error messages
4. Verify order was actually saved (check `/admin/orders.php`)

### Emails have missing placeholders
**Solution**: Check template in admin panel and ensure placeholders are using correct format: `{placeholder_name}`

## Success Criteria

The email system is considered fully functional when:

1. ✅ All admin UI tests pass
2. ✅ Order emails (admin + customer) are received
3. ✅ Support emails (admin + customer) are received
4. ✅ All security checks pass
5. ✅ No regression in existing functionality
6. ✅ Email failure doesn't block order/support submission
7. ✅ Logs correctly record all email events

## Final Sign-Off

After completing all tests:

- [ ] All test sections completed
- [ ] All issues documented and resolved
- [ ] Production SMTP credentials configured
- [ ] Email system enabled
- [ ] Documentation updated if needed

**Tested by**: _______________  
**Date**: _______________  
**Status**: ⬜ PASS  ⬜ FAIL (see notes)  
**Notes**: _______________

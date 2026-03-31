# Email System Testing & Deployment Guide

## Pre-Deployment Checklist

### 1. Server Requirements
- [ ] PHP 7.4+ with OpenSSL extension enabled
- [ ] Write permissions on `/data/` directory for settings
- [ ] Write permissions on `/logs/` directory for email logs
- [ ] Outbound SMTP connection allowed (port 587 for TLS, or 465 for SSL)
- [ ] PHP `mail()` function not required (using PHPMailer with SMTP)

### 2. Generate Encryption Key (First Time Setup)

**On the server, generate a secure encryption key:**

```bash
# Option 1: Using OpenSSL
openssl rand -base64 32

# Option 2: Using PHP
php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
```

**Edit `/config/email_secret.php` and replace the key:**

```php
<?php
return 'YOUR_GENERATED_KEY_HERE';
```

**Important:** Keep this key secure and never commit it to version control!

## Deployment Steps

### Step 1: Upload Files to Server

Deploy all files to production server using one of these methods:

**Method A: Git Pull (Recommended)**
```bash
cd /path/to/nichehome
git pull origin main
```

**Method B: FTP/SFTP Upload**
Upload these files/directories:
- `/admin/email.php` (updated with error handling)
- `/admin/email-settings.php` (now redirects to email.php)
- `/includes/email/` (all files: mailer.php, templates.php, crypto.php, log.php)
- `/lib/PHPMailer/` (entire directory)
- `/config/email_secret.php` (generate unique key on server)
- `/data/email_settings.json`
- `/data/email_templates.json`
- `/init.php` (updated with EMAIL_DEBUG support)

### Step 2: Set File Permissions

```bash
# Make data directory writable
chmod 755 /path/to/nichehome/data
chmod 644 /path/to/nichehome/data/*.json

# Make logs directory writable
mkdir -p /path/to/nichehome/logs
chmod 755 /path/to/nichehome/logs

# Secure the encryption key
chmod 600 /path/to/nichehome/config/email_secret.php
```

### Step 3: Configure SMTP Settings

1. **Log in to Admin Panel**
   - Navigate to: `https://nichehome.ch/admin/login.php`
   - Log in with admin credentials

2. **Go to Email Settings**
   - Click "Email" in the left sidebar
   - You should see 5 tabs: SMTP Settings, Routing, Templates, Test Email, Logs

3. **Configure SMTP Settings Tab**
   - **Enable email sending:** ✓ (check the box)
   - **SMTP Host:** `mail.nichehome.ch` (or your Tuthost SMTP server)
   - **SMTP Port:** `587`
   - **Encryption:** `TLS`
   - **SMTP Username:** `orders@nichehome.ch` (your SMTP account)
   - **SMTP Password:** Enter your SMTP password (will be encrypted)
   - **From Email:** `orders@nichehome.ch`
   - **From Name:** `NicheHome.ch`
   - Click **Save SMTP Settings**

   **Common SMTP Settings for Tuthost:**
   - Host: Usually `mail.yourdomain.ch` or provided by Tuthost
   - Port: 587 (TLS) or 465 (SSL)
   - Username: Full email address
   - Password: Email account password

4. **Configure Routing Tab**
   - **Admin Orders Email:** `info@nichehome.ch` (where order notifications go)
   - **Support Email:** `support@nichehome.ch` (where contact form submissions go)
   - **Reply-To Email:** `support@nichehome.ch` (default reply-to for customer emails)
   - Click **Save Routing Settings**

### Step 4: Test SMTP Connection

1. **Go to Test Email Tab**
2. **Click "Test Connection"** button (at the bottom)
3. **Expected Result:** ✓ SMTP connection test successful!
4. **If it fails:** Check the error message and verify:
   - SMTP host is correct
   - Port is correct (587 for TLS, 465 for SSL)
   - Username and password are correct
   - Server firewall allows outbound SMTP connections
   - Encryption method matches port (TLS for 587, SSL for 465)

### Step 5: Send Test Email

1. **Still on Test Email Tab**
2. **Enter your personal email address** (Gmail, Outlook, etc.)
3. **Click "Send Test Email"**
4. **Check your inbox** (and spam folder)
5. **Expected:** Test email arrives with subject "Test Email from NicheHome.ch"
6. **Verify:**
   - Email is properly formatted (HTML)
   - From address is correct
   - No encoding issues

### Step 6: Test Order Confirmation Flow

1. **Place a test order on the website:**
   - Add a product to cart
   - Go to checkout
   - Fill in customer details with a real email address you can access
   - Choose a payment method
   - Complete the order

2. **Verify Admin Notification:**
   - Check the **Admin Orders Email** inbox (`info@nichehome.ch`)
   - Should receive: "New Order #[ORDER_ID] — NicheHome.ch"
   - Verify: Order details, customer info, items, totals are correct

3. **Verify Customer Confirmation:**
   - Check the customer email inbox (the one entered during checkout)
   - Should receive: "Order Confirmation #[ORDER_ID] — NicheHome.ch"
   - Verify: Professionally formatted, all order details correct

4. **Check Email Logs:**
   - Go to **Admin → Email → Logs** tab
   - Should see 2 SUCCESS entries:
     - `[SUCCESS] Event: order_admin | To: info@nichehome.ch`
     - `[SUCCESS] Event: order_customer | To: customer@example.com`

### Step 7: Test Support Form

1. **Submit a support request:**
   - Navigate to: `https://nichehome.ch/support.php`
   - Fill in the form with a real email address
   - Submit the request

2. **Verify Support Notification:**
   - Check **Support Email** inbox (`support@nichehome.ch`)
   - Should receive: "New Support Request — [Customer Name]"
   - Verify: Customer details and message are correct
   - Verify: Reply-To is set to customer's email (can click reply)

3. **Verify Customer Auto-Reply:**
   - Check the customer email inbox
   - Should receive: "We Received Your Request — NicheHome.ch"
   - Verify: Professional auto-reply confirming receipt

4. **Check Email Logs:**
   - Go to **Admin → Email → Logs** tab
   - Should see 2 SUCCESS entries for support

### Step 8: Monitor for Failures

1. **Check Logs Regularly:**
   - **Admin → Email → Logs** tab
   - Look for any `[FAILED]` entries
   - Investigate failures and fix configuration

2. **Check Server Logs:**
   ```bash
   tail -f /path/to/nichehome/logs/email.log
   ```

3. **If Emails Fail:**
   - Check SMTP credentials are correct
   - Verify server can connect to SMTP server (firewall)
   - Check that "Enable email sending" is checked
   - Review error messages in Logs tab

## Troubleshooting

### Issue: HTTP 500 Error on /admin/email.php?tab=test

**Cause:** Usually a PHP error or exception not being caught

**Solution:**
1. Enable PHP error logging temporarily:
   ```php
   // In init.php, add:
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```
2. Or check server error logs:
   ```bash
   tail -f /var/log/apache2/error.log
   # or
   tail -f /var/log/nginx/error.log
   ```
3. Enable EMAIL_DEBUG mode:
   ```php
   // In init.php, change:
   define('EMAIL_DEBUG', 1);
   ```
4. This will show detailed error messages in the admin UI

### Issue: "Failed to decrypt SMTP password"

**Cause:** Encryption key has changed or is missing

**Solution:**
1. Verify `/config/email_secret.php` exists and has a key
2. Re-enter SMTP password in admin settings (it will be re-encrypted)
3. Click "Save SMTP Settings"

### Issue: "Connection timeout" or "Could not connect to SMTP server"

**Cause:** Firewall blocking SMTP connection or wrong host/port

**Solution:**
1. Verify SMTP host is correct (usually `mail.yourdomain.ch`)
2. Verify port is correct (587 for TLS, 465 for SSL)
3. Test connection from server:
   ```bash
   telnet mail.nichehome.ch 587
   # Should connect successfully
   ```
4. Check with hosting provider about SMTP restrictions
5. Tuthost may require SPF/DKIM configuration for the domain

### Issue: "Authentication failed"

**Cause:** Wrong username or password

**Solution:**
1. Verify SMTP username (usually full email address)
2. Verify SMTP password (try logging into webmail with same credentials)
3. Check if account requires app-specific password
4. Ensure account is active and not suspended

### Issue: Emails go to spam

**Cause:** SPF/DKIM/DMARC not configured, or "From" domain mismatch

**Solution:**
1. Configure SPF record for domain:
   ```
   v=spf1 include:_spf.tuthost.ch ~all
   ```
2. Configure DKIM (ask Tuthost for DKIM keys)
3. Ensure "From Email" matches the authenticated SMTP domain
4. Use `orders@nichehome.ch` not `noreply@nichehome.ch` if not configured

### Issue: UTF-8 encoding problems (Russian/German characters)

**Cause:** Character encoding misconfiguration

**Solution:**
1. PHPMailer is already configured with `UTF-8` charset
2. Verify database/files are saved as UTF-8
3. Test by sending email with special characters
4. Check email headers show: `Content-Type: text/html; charset=UTF-8`

### Issue: Email system is completely disabled

**Cause:** "Enable email sending" is unchecked

**Solution:**
1. Go to **Admin → Email → SMTP Settings**
2. Check **"Enable email sending"**
3. Click **Save SMTP Settings**

## Production Best Practices

### Security

1. **Never expose the encryption key:**
   - `/config/email_secret.php` should be in `.gitignore`
   - Generate a unique key for each environment (local, staging, production)

2. **Disable EMAIL_DEBUG in production:**
   ```php
   // In init.php:
   define('EMAIL_DEBUG', 0);  // Always 0 in production
   ```

3. **Use strong SMTP password:**
   - Minimum 16 characters
   - Mix of letters, numbers, symbols
   - Unique to this application

4. **Monitor logs for suspicious activity:**
   - Regular log reviews
   - Watch for unauthorized email sending
   - Set up alerts for high volume

### Performance

1. **Email sending is non-blocking:**
   - Order placement succeeds even if email fails
   - Customer sees success message immediately
   - Errors are logged but not shown to customer

2. **Log rotation:**
   ```bash
   # Archive old logs monthly
   mv logs/email.log logs/email.log.$(date +%Y%m)
   ```

3. **Rate limiting:**
   - Most SMTP servers limit emails per hour
   - For high volume, consider queueing system
   - Tuthost typical limit: 500-1000 emails/day

### Monitoring

1. **Set up email monitoring:**
   - Use a monitoring service (Uptime Robot, Pingdom)
   - Test email delivery daily
   - Alert on failures

2. **Regular checks:**
   - Weekly: Review Email Logs tab for failures
   - Monthly: Test all email types
   - Quarterly: Verify SMTP credentials still valid

3. **Backup settings:**
   ```bash
   # Backup email settings
   cp data/email_settings.json data/email_settings.json.backup
   cp data/email_templates.json data/email_templates.json.backup
   ```

## Advanced Configuration

### Multiple SMTP Accounts

Currently, the system uses one SMTP account for all emails. To use multiple accounts:

1. **Current limitation:** One SMTP credential set
2. **Workaround:** Use "From" and "Reply-To" headers:
   - All emails send via `orders@nichehome.ch` SMTP
   - But "From" can vary per email type
   - Requires SMTP server to allow different "From" addresses

3. **Future enhancement:** Modify `sendEmailViaSMTP()` to accept SMTP override per email type

### Custom Templates

Templates support placeholders in `{placeholder}` format:

**Order Templates:**
- `{order_id}`, `{customer_name}`, `{customer_email}`, `{customer_phone}`
- `{order_date}`, `{payment_method}`
- `{items_table}` (HTML table), `{items_list}` (plain text)
- `{subtotal}`, `{shipping}`, `{total}`
- `{pickup_branch}` (pickup location or shipping address)

**Support Templates:**
- `{name}`, `{email}`, `{phone}`
- `{support_subject}`, `{support_message}`, `{date}`

**To customize:**
1. Go to **Admin → Email → Templates** tab
2. Select template to edit
3. Modify subject, HTML, or text
4. Use placeholders as needed
5. Save template

### Email Debugging

To enable detailed debugging when troubleshooting:

1. **Edit `/init.php`:**
   ```php
   define('EMAIL_DEBUG', 1);  // Enable debug mode
   ```

2. **Now error messages will show:**
   - Full SMTP configuration (without password)
   - Detailed error messages
   - Connection details

3. **IMPORTANT:** Disable after troubleshooting:
   ```php
   define('EMAIL_DEBUG', 0);  // Disable in production
   ```

## Support Contact

For issues with:
- **SMTP settings/credentials:** Contact Tuthost support
- **Email delivery/spam:** Check SPF/DKIM with Tuthost
- **System bugs:** Check GitHub issues or repository maintainer

## Success Criteria Checklist

- [ ] Admin can access `/admin/email.php` without HTTP 500 error
- [ ] SMTP settings can be saved and loaded
- [ ] Test email sends successfully
- [ ] Connection test works
- [ ] Order confirmation emails send to customers
- [ ] Order notification emails send to admin
- [ ] Support request emails send to support team
- [ ] Support auto-reply sends to customers
- [ ] Email logs show all sent emails
- [ ] No passwords exposed in UI or logs
- [ ] UTF-8 characters work correctly (German, Russian)
- [ ] Error messages are user-friendly
- [ ] System works on Tuthost shared hosting
- [ ] Both `/admin/email.php` and `/admin/email-settings.php` work (redirect)

## Changelog

- **2025-12-16:** Fixed HTTP 500 errors with comprehensive try-catch blocks
- **2025-12-16:** Added EMAIL_DEBUG support for troubleshooting
- **2025-12-16:** Added connection test feature
- **2025-12-16:** Made `/admin/email-settings.php` redirect to `/admin/email.php`
- **2025-12-16:** Enhanced error messages with debug info
- **2025-12-13:** Initial email system implementation with PHPMailer

# Email System Configuration Guide

## Overview

NicheHome.ch uses PHPMailer for secure SMTP email delivery. This guide explains how to configure, test, and troubleshoot the email system.

## Quick Start

### 1. Access Email Settings

Navigate to: **Admin Panel → Email Settings**  
URL: `https://nichehome.ch/admin/email.php`

### 2. Configure SMTP Settings

Go to the **SMTP Settings** tab and fill in:

| Field | Value | Description |
|-------|-------|-------------|
| **Enable email sending** | ✓ Checked | Master switch for all email functionality |
| **SMTP Host** | `mail.nichehome.ch` | Your SMTP server address |
| **SMTP Port** | `587` | Port for STARTTLS (recommended) |
| **Encryption** | `TLS` | Use TLS for port 587, SSL for port 465 |
| **SMTP Username** | `orders@nichehome.ch` | Your email address |
| **SMTP Password** | Your password | Stored encrypted, never shown in plain text |
| **From Email** | `orders@nichehome.ch` | Email address shown in "From" field |
| **From Name** | `NicheHome.ch` | Name shown in "From" field |

**Note about ports:**
- Port **587** uses **TLS** (STARTTLS) — Recommended
- Port **465** uses **SSL** — Alternative
- Port **25** typically has no encryption — Not recommended

### 3. Configure Email Routing

Go to the **Routing** tab:

| Field | Purpose |
|-------|---------|
| **Admin Orders Email** | Receives notifications when customers place orders |
| **Support Email** | Receives support requests from contact form |
| **Reply-To Email** | Default reply-to address for customer emails |

### 4. Test Your Configuration

Go to the **Test Email** tab:

1. **Test SMTP Connection** — Click to verify server connection and authentication
   - ✓ Green = Connection successful, credentials valid
   - ✗ Red = Shows specific error (authentication, connection, encryption)

2. **Send Test Email** — Enter your email address and click to send a real test email
   - Check your inbox (and spam folder)
   - If it fails, error details will be shown

### 5. Email Templates

Go to the **Templates** tab to customize:

- **Order Admin Notification** — Email sent to admin when order is placed
- **Order Customer Confirmation** — Email sent to customer confirming order
- **Support Admin Notification** — Email sent to admin for support requests
- **Support Customer Auto-Reply** — Acknowledgement sent to customer

Available placeholders:
- Order emails: `{order_id}`, `{customer_name}`, `{customer_email}`, `{total}`, etc.
- Support emails: `{name}`, `{email}`, `{support_subject}`, `{support_message}`, etc.

## Troubleshooting

### Debug Mode

When troubleshooting email issues:

1. Go to **SMTP Settings** tab
2. Enable **"Enable SMTP Debug Mode (temporary)"**
3. Perform test operations
4. Debug information will be shown in test results
5. **Important:** Disable debug mode after troubleshooting

Debug mode shows:
- SMTP handshake details
- STARTTLS negotiation
- Authentication process
- Detailed error messages

### Common Errors

#### "SMTP AUTH failed"
**Cause:** Wrong username or password  
**Solution:** 
- Verify credentials in your email provider
- Check for typos
- Ensure you're using the full email address as username

#### "Could not connect to SMTP server"
**Cause:** Network or firewall issue  
**Solution:**
- Verify SMTP host is correct: `mail.nichehome.ch`
- Check port is open (587 or 465)
- Verify server allows outbound SMTP connections

#### "SMTP connect() failed"
**Cause:** Port or encryption mismatch  
**Solution:**
- Port 587 → Use TLS encryption
- Port 465 → Use SSL encryption
- Check with your hosting provider for correct port

#### "Failed to decrypt password" or "Decryption failed: invalid key or corrupted data"
**Cause:** The encryption key used to secure your SMTP password has changed  
**Solution:** 
1. Go to SMTP Settings tab
2. A red warning will appear at the top indicating password cannot be decrypted
3. Enter your SMTP password again in the password field (highlighted in red)
4. Click "Save SMTP Settings"
5. Test the connection again

**Technical Details:** 
- Passwords are encrypted using AES-256-CBC with a persistent key stored in `config/.email_encryption_key`
- If this key file is deleted or modified, stored passwords cannot be decrypted
- The system detects this automatically and prompts you to re-enter the password
- Re-entering and saving the password encrypts it with the current key

#### "Email sending is disabled"
**Cause:** Master switch is off  
**Solution:** Check "Enable email sending" in SMTP Settings

### Checking Logs

View recent email activity in the **Logs** tab:

- Shows last 100 email events
- Green = Successful delivery
- Red = Failed delivery with error details
- Log file location: `/logs/email.log`

**Log file features:**
- Automatic rotation when file exceeds 5MB
- Keeps last 10 archived log files
- Passwords always masked as `***`
- Limited to last 500 characters of error message

### Manual Log Access

If you have SSH/FTP access:

```bash
# View recent logs
tail -n 50 /path/to/nichehome/logs/email.log

# Search for failures
grep FAILED /path/to/nichehome/logs/email.log

# View logs in real-time
tail -f /path/to/nichehome/logs/email.log
```

## When Emails Are Sent

### Order Emails

When a customer places an order:
1. **Customer confirmation** sent to customer's email address
2. **Admin notification** sent to Admin Orders Email (from Routing settings)

Both emails use templates from the Templates tab.

### Support Form Emails

When someone submits the contact/support form:
1. **Admin notification** sent to Support Email (from Routing settings)
2. **Customer acknowledgement** sent to customer as auto-reply

### Important Notes

- If email sending fails, orders still complete successfully
- Failures are logged but don't block the checkout process
- Test emails use the same code path as real emails

## Security

✓ **Passwords are encrypted** at rest using AES-256-CBC  
✓ **Passwords are masked** in all logs and error messages  
✓ **Debug mode** should only be enabled temporarily  
✓ **No secrets** are exposed in HTML or client-side code  

## Testing Checklist

Before going live, verify:

- [ ] SMTP Settings saved and enabled
- [ ] Test SMTP Connection shows ✓ Success
- [ ] Test Email received in inbox
- [ ] Create test order → Both admin and customer emails received
- [ ] Submit support form → Both admin and customer emails received
- [ ] Check Logs tab shows successful deliveries
- [ ] Debug mode is disabled

## Provider-Specific Notes

### NicheHome.ch SMTP Settings

Verified working configuration:
- **Host:** `mail.nichehome.ch`
- **Port:** `587`
- **Encryption:** `TLS` (STARTTLS)
- **Username:** Your full email address
- **Password:** Your email account password

Alternative configuration:
- **Port:** `465`
- **Encryption:** `SSL`

## Encryption Key Management

### How It Works

SMTP passwords are encrypted at rest using **AES-256-CBC** encryption. The encryption key is:

1. **Generated automatically** on first use
2. **Stored persistently** in `config/.email_encryption_key`
3. **Reused across requests** to ensure passwords can be decrypted

### Key Storage Locations (Priority Order)

The system checks for encryption keys in this order:

1. **File:** `config/.email_encryption_key` (recommended, auto-generated)
2. **Environment Variable:** `EMAIL_ENCRYPTION_KEY` (for containerized deployments)
3. **Fallback:** Default key (insecure, only for testing)

### Important Notes

⚠️ **DO NOT delete or modify** `config/.email_encryption_key` after setup  
⚠️ **Backup this file** when backing up your site  
⚠️ The key file is excluded from Git by `.gitignore`

### If You Lose Your Encryption Key

If the encryption key file is deleted or becomes corrupted:

1. The admin panel will show a **red warning** that the password cannot be decrypted
2. The password field will be **highlighted in red**
3. Simply **re-enter your SMTP password** and save
4. The password will be re-encrypted with the current (or new) key

**No data loss occurs** — you just need to re-enter the password.

### Manual Key Generation (Advanced)

If you want to pre-generate a key:

```bash
# Generate a secure 32-byte base64-encoded key
openssl rand -base64 32 > config/.email_encryption_key

# Or using PHP
php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;" > config/.email_encryption_key

# Set correct permissions (readable only by web server)
chmod 600 config/.email_encryption_key
```

### Environment Variable Method (Docker/Containerized)

For containerized deployments, set the environment variable:

```bash
# In your .env file or container environment
EMAIL_ENCRYPTION_KEY=your-base64-encoded-32-byte-key-here
```

## Getting Help

If emails still don't work after following this guide:

1. Enable Debug Mode
2. Run "Test SMTP Connection"
3. Copy the error message
4. Check the Logs tab for additional details
5. Contact your hosting provider with:
   - The error message
   - SMTP host, port, and encryption type
   - Whether you can test with another SMTP client

## Files Modified/Created

Email system files:
- `/admin/email.php` — Admin UI for configuration
- `/includes/email/mailer.php` — SMTP sending logic
- `/includes/email/templates.php` — Email templates
- `/includes/email/crypto.php` — Password encryption
- `/includes/email/log.php` — Logging functionality
- `/includes/helpers.php` — Email trigger functions
- `/data/email_settings.json` — Configuration storage
- `/data/email_templates.json` — Template storage
- `/data/email_debug.json` — Debug mode toggle
- `/config/email_secret.php` — Encryption key loader (keep secure)
- `/config/.email_encryption_key` — **Persistent encryption key** (auto-generated, excluded from Git)
- `/logs/email.log` — Activity log file

---

**Last Updated:** December 2024  
**System:** NicheHome.ch Email System v2.0

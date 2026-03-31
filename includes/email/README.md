# Email Notification System

Production-ready email notification system using PHPMailer with SMTP.

## Features

- **SMTP Support**: Uses PHPMailer with TLS/SSL encryption
- **Secure Password Storage**: SMTP password encrypted using AES-256-CBC
- **Template System**: HTML + plain text templates with placeholders
- **Email Logging**: Activity logged to `/logs/email.log` (no sensitive data)
- **Admin UI**: Complete management interface at `/admin/email.php`

## Files Structure

```
/includes/email/
├── crypto.php      # Password encryption/decryption
├── log.php         # Email event logging
├── mailer.php      # SMTP email sending via PHPMailer
├── templates.php   # Template loading and rendering
└── README.md       # This file

/lib/PHPMailer/src/  # PHPMailer library
/data/
├── email_settings.json   # SMTP and routing configuration
└── email_templates.json  # Email templates

/config/
└── email_secret.php      # Encryption key (DO NOT COMMIT)

/logs/
└── email.log            # Email activity log
```

## Configuration

### 1. Set Encryption Key

Edit `/config/email_secret.php` and set a strong random key:

```bash
# Generate a secure key
openssl rand -base64 32
```

**IMPORTANT**: This file should NOT be committed to version control.

### 2. Configure SMTP Settings

Go to `/admin/email.php` → SMTP Settings tab and configure:

- SMTP Host (e.g., `smtp.nichehome.ch`)
- SMTP Port (587 for TLS, 465 for SSL)
- Encryption (TLS/SSL/None)
- Username (usually your email)
- Password (will be encrypted automatically)
- From Email & Name

### 3. Configure Routing

Set recipient emails in the Routing tab:

- **Admin Orders Email**: Receives new order notifications
- **Support Email**: Receives support requests
- **Reply-To Email**: Default reply-to for customer emails

### 4. Customize Templates

Edit email templates in the Templates tab. Available templates:

1. **Order Admin** - Notification to admin when order placed
2. **Order Customer** - Confirmation to customer
3. **Support Admin** - Notification when support request submitted
4. **Support Customer** - Auto-reply to customer

#### Template Placeholders

**Order emails:**
- `{order_id}` - Order number
- `{customer_name}` - Customer full name
- `{customer_email}` - Customer email
- `{customer_phone}` - Customer phone
- `{order_date}` - Order date/time
- `{payment_method}` - Payment method
- `{items_table}` - HTML table of order items
- `{items_list}` - Plain text list of items
- `{subtotal}` - Order subtotal
- `{shipping}` - Shipping cost
- `{total}` - Order total
- `{pickup_branch}` - Pickup location (if applicable)

**Support emails:**
- `{name}` - Customer name
- `{email}` - Customer email
- `{phone}` - Customer phone
- `{support_subject}` - Request subject
- `{support_message}` - Request message
- `{date}` - Request date/time

## Testing

### Test SMTP Connection

1. Go to `/admin/email.php` → Test Email tab
2. Enter your email address
3. Click "Send Test Email"
4. Check your inbox/spam folder

### Test in Development

Email sending is disabled by default. To enable:

1. Go to `/admin/email.php` → SMTP Settings
2. Check "Enable email sending"
3. Save settings

## Integration

Email sending is automatically integrated into:

1. **Checkout Flow** (`checkout.php`)
   - Sends order confirmation to customer
   - Sends order notification to admin

2. **Support Form** (`support.php`)
   - Sends support request to admin
   - Sends auto-reply to customer

Both integrations are non-blocking - if email fails, it won't prevent order placement or support request submission.

## Security

- SMTP password is **never** stored in plain text
- Password is **never** displayed in the admin UI
- Password is **never** logged
- All email addresses are validated
- Admin authentication required for all email settings
- XSS protection in admin UI

## Troubleshooting

### Email not sending

1. Check if email is enabled in SMTP Settings
2. Verify SMTP credentials are correct
3. Check `/logs/email.log` for error messages
4. Test SMTP connection in Test Email tab

### "Failed to decrypt SMTP password"

The encryption key in `/config/email_secret.php` has changed. You need to re-enter the SMTP password in the admin UI.

### "SMTP settings are incomplete"

Make sure all required SMTP settings are filled:
- Host
- Port
- Username
- Password
- From Email

## Production Checklist

- [ ] Set a strong encryption key in `/config/email_secret.php`
- [ ] Add `/config/email_secret.php` to `.gitignore`
- [ ] Configure real SMTP credentials
- [ ] Set correct routing email addresses
- [ ] Customize email templates (optional)
- [ ] Test email sending
- [ ] Enable email system
- [ ] Monitor `/logs/email.log` for issues

## Support

For issues or questions, contact the development team.

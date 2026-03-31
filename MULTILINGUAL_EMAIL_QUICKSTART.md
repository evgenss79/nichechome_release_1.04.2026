# Multilingual Email - Quick Start Guide

## Testing the Implementation

### 1. Test Order Confirmation Email

#### English
```
1. Visit: https://nichehome.ch/cart.php?lang=en
2. Add items and checkout
3. Check email - should be in English
```

#### German
```
1. Visit: https://nichehome.ch/cart.php?lang=de
2. Add items and checkout
3. Check email - should be in German
```

#### French
```
1. Visit: https://nichehome.ch/cart.php?lang=fr
2. Add items and checkout
3. Check email - should be in French
```

### 2. Test Support Auto-Reply

#### German Example
```
1. Visit: https://nichehome.ch/support.php?lang=de
2. Fill out and submit form
3. Check email - should be in German
```

## How to Add a New Language

### Step 1: Create Translation File
Create `/includes/i18n/email/xx.php` where `xx` is your language code:

```php
<?php
return [
    'email.order.subject' => 'Your Translation Here',
    'email.order.title' => 'Your Translation Here',
    // ... copy all keys from en.php and translate
];
```

### Step 2: Add to Supported Languages
Update `I18N.php` to include your new language in the supported list:
```php
private static $supportedLanguages = ['en', 'de', 'fr', 'it', 'ru', 'ukr', 'xx'];
```

### Step 3: Test
```
1. Visit site with ?lang=xx
2. Place order or submit support
3. Email should arrive in new language
```

## Debugging

### Check Email Logs
```bash
tail -f /path/to/logs/email.log
```

Look for entries like:
```
[2025-12-17 15:00:00] [SUCCESS] Flow: order_customer | To: customer@example.com | Lang: de
```

### Check Stored Language
```php
// Check order
$orders = loadJSON('orders.json');
echo $orders['ORD-XXX']['language']; // Should show 'de', 'fr', etc.

// Check support request
$requests = loadJSON('support_requests.json');
echo $requests[0]['language']; // Should show 'de', 'fr', etc.
```

## Translation Keys Reference

### Order Emails
- `email.order.subject` - Email subject line
- `email.order.title` - Main title in email
- `email.order.greeting` - Greeting (Dear {customer_name},)
- `email.order.intro` - Introduction text
- `email.order.orderDetails` - "Order Details" heading
- `email.order.orderNumber` - "Order Number" label
- `email.order.orderDate` - "Order Date" label
- `email.order.paymentMethod` - "Payment Method" label
- `email.order.product` - "Product" table header
- `email.order.sku` - "SKU" table header
- `email.order.qty` - "Qty" table header
- `email.order.price` - "Price" table header
- `email.order.total` - "Total" table header
- `email.order.subtotal` - "Subtotal" label
- `email.order.shipping` - "Shipping" label
- `email.order.shippingAddress` - "Shipping Address" heading
- `email.order.pickupBranch` - "Pickup Location" heading
- `email.order.footer.thanks` - Thank you message
- `email.order.footer.questions` - Contact us message

### Payment Methods
- `email.payment.cash` - "Cash (on pickup)"
- `email.payment.twint` - "TWINT"
- `email.payment.card` - "Credit/Debit Card"
- `email.payment.paypal` - "PayPal"

### Delivery Methods
- `email.delivery.delivery` - "Delivery"
- `email.delivery.pickup` - "Pickup"
- `email.delivery.free` - "FREE"
- `email.delivery.pickupInBranch` - "Pickup in branch"

### Support Emails
- `email.support.subject` - Email subject line
- `email.support.title` - Main title in email
- `email.support.greeting` - Greeting (Dear {name},)
- `email.support.intro` - Introduction text
- `email.support.yourRequest` - "Your Request" heading
- `email.support.subject_label` - "Subject" label
- `email.support.message_label` - "Message" label
- `email.support.responseTime` - Response time text
- `email.support.footer.thanks` - Thank you message
- `email.support.footer.urgent` - Urgent contact message

### Common
- `email.footer.auto` - Automated message disclaimer

## Common Issues

### Email in Wrong Language
**Symptom:** Customer receives email in English despite using German site

**Solutions:**
1. Check if language cookie is set: Browser DevTools → Application → Cookies
2. Check order in database: `orders.json` → look for `"language": "xx"`
3. Check email logs: Should show `Lang: xx` not `Lang: en`

### Translation Not Showing
**Symptom:** Translation key showing instead of translated text

**Solutions:**
1. Check translation file exists: `/includes/i18n/email/xx.php`
2. Check key is defined in translation file
3. Clear any PHP opcache if enabled
4. Check for syntax errors in translation file

### Fallback to English Not Working
**Symptom:** Email fails instead of falling back to English

**Solutions:**
1. Check English translation file exists: `/includes/i18n/email/en.php`
2. Check all required keys are present in en.php
3. Check file permissions (should be readable)

## File Locations

```
/includes/
  /i18n/
    /email/
      en.php      # English (fallback)
      de.php      # German
      fr.php      # French
      it.php      # Italian
      ru.php      # Russian
      ukr.php     # Ukrainian
  /email/
    templates.php # email_t() function
    mailer.php    # SMTP (unchanged)
    log.php       # Logging (includes lang)
  helpers.php     # Email generation functions
  I18N.php        # Main i18n system

/logs/
  email.log       # Email logs with language

/data/
  orders.json     # Orders with language field
  support_requests.json  # Support requests with language field
```

## Need Help?

See full documentation: `MULTILINGUAL_EMAIL_IMPLEMENTATION.md`

## Quick Test Commands

```bash
# Test translation loading
php -r "require 'includes/email/templates.php'; var_dump(loadEmailTranslations('de'));"

# Test email_t function
php -r "require 'includes/email/templates.php'; echo email_t('email.order.title', 'de');"

# Check recent email logs
tail -20 logs/email.log
```

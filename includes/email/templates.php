<?php
/**
 * Email Templates Module
 * Handles loading and rendering of email templates
 */

// Constants for product options
define('PRODUCT_VOLUME_STANDARD', 'standard');
define('PRODUCT_FRAGRANCE_NONE', 'none');

/**
 * Load email translations for a specific language
 * @param string $lang Language code (en, de, fr, it, ru, ukr)
 * @return array Translation array
 */
function loadEmailTranslations(string $lang): array {
    $supportedLangs = ['en', 'de', 'fr', 'it', 'ru', 'ukr'];
    
    // Normalize language code
    $lang = strtolower($lang);
    if (!in_array($lang, $supportedLangs)) {
        $lang = 'en'; // Fallback to English
    }
    
    $translationFile = __DIR__ . '/../i18n/email/' . $lang . '.php';
    
    if (file_exists($translationFile)) {
        $translations = require $translationFile;
        if (is_array($translations)) {
            return $translations;
        }
    }
    
    // Fallback to English if translation file not found or invalid
    if ($lang !== 'en') {
        $englishFile = __DIR__ . '/../i18n/email/en.php';
        if (file_exists($englishFile)) {
            $translations = require $englishFile;
            if (is_array($translations)) {
                return $translations;
            }
        }
    }
    
    return [];
}

/**
 * Get email translation by key
 * @param string $key Translation key (e.g., 'email.order.subject')
 * @param string $lang Language code (en, de, fr, it, ru, ukr)
 * @param array $params Parameters to replace in the translation (e.g., ['order_id' => '123'])
 * @return string Translated string with parameters replaced
 */
function email_t(string $key, string $lang = 'en', array $params = []): string {
    static $translationsCache = [];
    
    // Load translations for the language if not cached
    if (!isset($translationsCache[$lang])) {
        $translationsCache[$lang] = loadEmailTranslations($lang);
    }
    
    $translations = $translationsCache[$lang];
    
    // Get translation or use key as fallback
    $translation = $translations[$key] ?? $key;
    
    // Replace parameters in the translation
    foreach ($params as $paramKey => $paramValue) {
        $translation = str_replace('{' . $paramKey . '}', $paramValue, $translation);
    }
    
    return $translation;
}

/**
 * Load email templates from JSON file
 * @return array Email templates
 */
function loadEmailTemplates(): array {
    $templatesFile = __DIR__ . '/../../data/email_templates.json';
    
    if (!file_exists($templatesFile)) {
        return getDefaultEmailTemplates();
    }
    
    $json = file_get_contents($templatesFile);
    if ($json === false) {
        error_log("Failed to read email templates file: $templatesFile");
        return getDefaultEmailTemplates();
    }
    
    $templates = json_decode($json, true);
    
    // Check for JSON parsing errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Failed to parse email templates JSON: " . json_last_error_msg());
        return getDefaultEmailTemplates();
    }
    
    if (!is_array($templates)) {
        error_log("Email templates is not an array");
        return getDefaultEmailTemplates();
    }
    
    return $templates;
}

/**
 * Save email templates to JSON file
 * @param array $templates Email templates
 * @return bool Success status
 */
function saveEmailTemplates(array $templates): bool {
    $templatesFile = __DIR__ . '/../../data/email_templates.json';
    $json = json_encode($templates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if ($json === false) {
        return false;
    }
    
    return file_put_contents($templatesFile, $json, LOCK_EX) !== false;
}

/**
 * Get default email templates
 * @return array Default templates
 */
function getDefaultEmailTemplates(): array {
    return [
        'order_admin' => [
            'subject' => 'New Order #{order_id} — NicheHome.ch',
            'html' => getDefaultOrderAdminHtml(),
            'text' => getDefaultOrderAdminText()
        ],
        'order_customer' => [
            'subject' => 'Order Confirmation #{order_id} — NicheHome.ch',
            'html' => getDefaultOrderCustomerHtml(),
            'text' => getDefaultOrderCustomerText()
        ],
        'support_admin' => [
            'subject' => 'New Support Request — {name}',
            'html' => getDefaultSupportAdminHtml(),
            'text' => getDefaultSupportAdminText()
        ],
        'support_customer' => [
            'subject' => 'We Received Your Request — NicheHome.ch',
            'html' => getDefaultSupportCustomerHtml(),
            'text' => getDefaultSupportCustomerText()
        ]
    ];
}

/**
 * Render a template with variables
 * @param string $templateKey Template key (e.g., 'order_admin', 'order_customer')
 * @param array $vars Variables to replace in the template
 * @return array ['subject' => string, 'html' => string, 'text' => string]
 */
function renderEmailTemplate(string $templateKey, array $vars): array {
    $templates = loadEmailTemplates();
    
    if (!isset($templates[$templateKey])) {
        return [
            'subject' => 'Email from NicheHome.ch',
            'html' => '<p>Content not available</p>',
            'text' => 'Content not available'
        ];
    }
    
    $template = $templates[$templateKey];
    
    // Replace placeholders in subject, html, and text
    $subject = replacePlaceholders($template['subject'] ?? '', $vars);
    $html = replacePlaceholders($template['html'] ?? '', $vars);
    $text = replacePlaceholders($template['text'] ?? '', $vars);
    
    return [
        'subject' => $subject,
        'html' => $html,
        'text' => $text
    ];
}

/**
 * Replace placeholders in a template string
 * @param string $template Template string with placeholders
 * @param array $vars Variables to replace
 * @return string Template with placeholders replaced
 */
function replacePlaceholders(string $template, array $vars): string {
    foreach ($vars as $key => $value) {
        // Support both {key} and #{key} formats
        $template = str_replace(['{' . $key . '}', '#{' . $key . '}'], $value, $template);
    }
    
    return $template;
}

/**
 * Build order items table (HTML)
 * @param array $items Order items
 * @param string $lang Language code for translations (default: 'en')
 * @return string HTML table
 */
function buildOrderItemsTableHtml(array $items, string $lang = 'en'): string {
    $html = '<table class="items-table">';
    $html .= '<thead><tr>';
    $html .= '<th>' . email_t('email.order.product', $lang) . '</th>';
    $html .= '<th style="text-align: center;">' . email_t('email.order.sku', $lang) . '</th>';
    $html .= '<th style="text-align: center;">' . email_t('email.order.qty', $lang) . '</th>';
    $html .= '<th style="text-align: right;">' . email_t('email.order.price', $lang) . '</th>';
    $html .= '<th style="text-align: right;">' . email_t('email.order.total', $lang) . '</th>';
    $html .= '</tr></thead><tbody>';
    
    foreach ($items as $item) {
        $name = htmlspecialchars($item['name'] ?? 'Product');
        $sku = htmlspecialchars($item['sku'] ?? 'N/A');
        $volume = $item['volume'] ?? '';
        $fragrance = $item['fragrance'] ?? '';
        $qty = (int)($item['quantity'] ?? 1);
        $price = (float)($item['price'] ?? 0);
        $total = $price * $qty;
        
        // Build product details with options
        $details = '<strong>' . $name . '</strong>';
        $options = [];
        if ($volume && $volume !== PRODUCT_VOLUME_STANDARD) {
            $options[] = 'Volume: ' . htmlspecialchars($volume);
        }
        if ($fragrance && $fragrance !== PRODUCT_FRAGRANCE_NONE) {
            $options[] = 'Fragrance: ' . htmlspecialchars($fragrance);
        }
        if (!empty($options)) {
            $details .= '<br><small style="color: #666;">' . implode(', ', $options) . '</small>';
        }
        
        $html .= '<tr>';
        $html .= '<td>' . $details . '</td>';
        $html .= '<td style="text-align: center;"><code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">' . $sku . '</code></td>';
        $html .= '<td style="text-align: center;">' . $qty . '</td>';
        $html .= '<td style="text-align: right;">CHF ' . number_format($price, 2) . '</td>';
        $html .= '<td style="text-align: right;"><strong>CHF ' . number_format($total, 2) . '</strong></td>';
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table>';
    
    return $html;
}

/**
 * Build order items list (plain text)
 * @param array $items Order items
 * @param string $lang Language code for translations (default: 'en')
 * @return string Plain text list
 */
function buildOrderItemsListText(array $items, string $lang = 'en'): string {
    $text = "";
    
    foreach ($items as $item) {
        $name = htmlspecialchars($item['name'] ?? 'Product');
        $sku = htmlspecialchars($item['sku'] ?? 'N/A');
        $volume = $item['volume'] ?? '';
        $fragrance = $item['fragrance'] ?? '';
        $qty = (int)($item['quantity'] ?? 1);
        $price = (float)($item['price'] ?? 0);
        $total = $price * $qty;
        
        // Build product line with options
        $productLine = $name;
        $options = [];
        if ($volume && $volume !== PRODUCT_VOLUME_STANDARD) {
            $options[] = htmlspecialchars($volume);
        }
        if ($fragrance && $fragrance !== PRODUCT_FRAGRANCE_NONE) {
            $options[] = htmlspecialchars($fragrance);
        }
        if (!empty($options)) {
            $productLine .= ' (' . implode(', ', $options) . ')';
        }
        
        // Format: Product (options) | SKU | Qty | Price | Total
        $text .= $productLine . "\n";
        $text .= "SKU: " . $sku . " | Qty: " . $qty . " | Price: CHF " . number_format($price, 2) . " | Total: CHF " . number_format($total, 2) . "\n\n";
    }
    
    return rtrim($text);
}

// Default template content functions

function getDefaultOrderAdminHtml(): string {
    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 700px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .info-box { background: white; padding: 20px; margin: 20px 0; border-left: 4px solid #d4af37; }
        .info-label { font-weight: bold; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛍️ New Order Received</h1>
            <p>Order #{order_id}</p>
        </div>
        <div class="content">
            <div class="info-box">
                <p><span class="info-label">Customer:</span> {customer_name}</p>
                <p><span class="info-label">Email:</span> {customer_email}</p>
                <p><span class="info-label">Phone:</span> {customer_phone}</p>
                <p><span class="info-label">Order Date:</span> {order_date}</p>
                <p><span class="info-label">Payment Method:</span> {payment_method}</p>
            </div>
            
            {items_table}
            
            <div class="info-box">
                <p><span class="info-label">Subtotal:</span> CHF {subtotal}</p>
                <p><span class="info-label">Shipping:</span> CHF {shipping}</p>
                <h3 style="margin-top: 20px; border-top: 2px solid #333; padding-top: 10px;">Total: CHF {total}</h3>
            </div>
            
            {pickup_branch}
            
            <p style="text-align: center; margin-top: 30px;">
                <a href="https://nichehome.ch/admin/orders.php" style="display: inline-block; background: #d4af37; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px;">View in Admin</a>
            </p>
        </div>
    </div>
</body>
</html>';
}

function getDefaultOrderAdminText(): string {
    return 'NEW ORDER RECEIVED
Order: #{order_id}

Customer Information:
- Name: {customer_name}
- Email: {customer_email}
- Phone: {customer_phone}
- Order Date: {order_date}
- Payment Method: {payment_method}

{items_list}

Order Summary:
- Subtotal: CHF {subtotal}
- Shipping: CHF {shipping}
- Total: CHF {total}

{shipping_address_block}

View order in admin: https://nichehome.ch/admin/orders.php

---
NicheHome.ch Admin Notification';
}

function getDefaultOrderCustomerHtml(): string {
    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 700px; margin: 0 auto; padding: 20px; }
        .header { background: #d4af37; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .info-box { background: white; padding: 20px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Thank You for Your Order!</h1>
            <p>Order #{order_id}</p>
        </div>
        <div class="content">
            <p>Dear {customer_name},</p>
            <p>We have received your order and it is being processed. Thank you for shopping with NicheHome.ch!</p>
            
            <div class="info-box">
                <h3>Order Details</h3>
                <p><strong>Order Number:</strong> {order_id}</p>
                <p><strong>Order Date:</strong> {order_date}</p>
            </div>
            
            {items_table}
            
            <div class="info-box">
                <p><strong>Subtotal:</strong> CHF {subtotal}</p>
                <p><strong>Shipping:</strong> CHF {shipping}</p>
                <h3 style="border-top: 2px solid #333; padding-top: 10px;">Total: CHF {total}</h3>
            </div>
            
            {pickup_branch}
        </div>
        <div class="footer">
            <p>Thank you for shopping with NicheHome.ch!</p>
            <p>If you have any questions, please contact us at support@nichehome.ch</p>
        </div>
    </div>
</body>
</html>';
}

function getDefaultOrderCustomerText(): string {
    return 'THANK YOU FOR YOUR ORDER!
Order: #{order_id}

Dear {customer_name},

We have received your order and it is being processed. Thank you for shopping with NicheHome.ch!

Order Details:
- Order Number: {order_id}
- Order Date: {order_date}

{items_list}

Order Summary:
- Subtotal: CHF {subtotal}
- Shipping: CHF {shipping}
- Total: CHF {total}

{shipping_address_block}

If you have any questions, please contact us at support@nichehome.ch

---
NicheHome.ch';
}

function getDefaultSupportAdminHtml(): string {
    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 700px; margin: 0 auto; padding: 20px; }
        .header { background: #e74c3c; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .info-box { background: white; padding: 20px; margin: 20px 0; border-left: 4px solid #e74c3c; }
        .message-box { background: #f5f5f5; padding: 20px; margin: 20px 0; border: 1px solid #ddd; }
        .meta-info { font-size: 0.9em; color: #666; padding: 10px; background: #f9f9f9; margin-top: 20px; border-top: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 New Support Request</h1>
            <p>From {name}</p>
        </div>
        <div class="content">
            <div class="info-box">
                <p><strong>Name:</strong> {name}</p>
                <p><strong>Email:</strong> {email}</p>
                <p><strong>Phone:</strong> {phone}</p>
                <p><strong>Subject:</strong> {support_subject}</p>
                <p><strong>Date:</strong> {date}</p>
            </div>
            
            <div class="message-box">
                <h3>Message:</h3>
                <p>{support_message}</p>
            </div>
            
            <div class="meta-info">
                <p><strong>Request ID:</strong> {request_id}</p>
                <p><strong>Page URL:</strong> {page_url}</p>
                <p><strong>Language:</strong> {language}</p>
            </div>
        </div>
    </div>
</body>
</html>';
}

function getDefaultSupportAdminText(): string {
    return 'NEW SUPPORT REQUEST

From: {name}
Email: {email}
Phone: {phone}
Subject: {support_subject}
Date: {date}

Message:
{support_message}

---
Request ID: {request_id}
Page URL: {page_url}
Language: {language}

---
NicheHome.ch Support Notification';
}

function getDefaultSupportCustomerHtml(): string {
    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 700px; margin: 0 auto; padding: 20px; }
        .header { background: #27ae60; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .info-box { background: white; padding: 20px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✓ We Received Your Request</h1>
        </div>
        <div class="content">
            <p>Dear {name},</p>
            <p>Thank you for contacting NicheHome.ch support. We have received your request and will respond as soon as possible.</p>
            
            <div class="info-box">
                <h3>Your Request:</h3>
                <p><strong>Subject:</strong> {support_subject}</p>
                <p><strong>Message:</strong></p>
                <p style="background: #f5f5f5; padding: 15px; border-left: 3px solid #27ae60;">{support_message}</p>
            </div>
            
            <p>Our support team typically responds within 24-48 hours during business days.</p>
        </div>
        <div class="footer">
            <p>Thank you for choosing NicheHome.ch</p>
            <p>For urgent matters, please call us directly</p>
        </div>
    </div>
</body>
</html>';
}

function getDefaultSupportCustomerText(): string {
    return 'WE RECEIVED YOUR REQUEST

Dear {name},

Thank you for contacting NicheHome.ch support. We have received your request and will respond as soon as possible.

Your Request:
- Subject: {support_subject}
- Message: {support_message}

Our support team typically responds within 24-48 hours during business days.

Thank you for choosing NicheHome.ch
For urgent matters, please call us directly

---
NicheHome.ch Support';
}

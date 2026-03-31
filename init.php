<?php
/**
 * Global Bootstrap for NICHEHOME.CH
 * Initialize session, language, I18N and configuration
 */

// Start session
session_start();

// Determine and set language
$lang = $_GET['lang'] ?? $_COOKIE['lang'] ?? 'en';
$allowed_langs = ['en', 'de', 'fr', 'it', 'ru', 'ukr'];

if (!in_array($lang, $allowed_langs)) {
    $lang = 'en';
}

// Set language cookie for 30 days
setcookie('lang', $lang, time() + 3600 * 24 * 30, '/');

// Load I18N class and set language
require_once __DIR__ . '/includes/I18N.php';
I18N::setLanguage($lang);

// Load configuration
$config_file = __DIR__ . '/data/config.json';
if (file_exists($config_file)) {
    $CONFIG = json_decode(file_get_contents($config_file), true);
} else {
    $CONFIG = [
        'free_shipping_threshold' => 80,
        'admin_email' => 'admin@nichehome.ch',
        'currency' => 'CHF',
        'discount_gift_set' => 0.05,
        'site_name' => 'NicheHome.ch',
        'brand_name' => 'By Velcheva'
    ];
}

// Load helpers
require_once __DIR__ . '/includes/helpers.php';

// Load fragrance descriptions from fragrance.txt
require_once __DIR__ . '/includes/fragrance_data.php';
$FRAGRANCE_DESCRIPTIONS = loadFragranceDescriptions();

// Email debug mode (for troubleshooting email issues)
// Can be toggled from admin email settings UI
// When enabled, shows detailed SMTP errors and increases log verbosity
if (!defined('EMAIL_DEBUG')) {
    // Check if debug mode is enabled in settings
    $emailDebugFile = __DIR__ . '/data/email_debug.json';
    $emailDebugEnabled = false;
    
    if (file_exists($emailDebugFile)) {
        $debugData = json_decode(file_get_contents($emailDebugFile), true);
        $emailDebugEnabled = !empty($debugData['debug_enabled']);
    }
    
    define('EMAIL_DEBUG', $emailDebugEnabled ? 1 : 0);
}
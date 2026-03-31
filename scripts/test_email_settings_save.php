<?php
/**
 * Email Settings Save Test Script
 * Tests the save and load functionality of email settings
 */

// Include required files
require_once __DIR__ . '/../includes/email/mailer.php';
require_once __DIR__ . '/../includes/email/crypto.php';
require_once __DIR__ . '/../includes/email/templates.php';

echo "=== Email Settings Save Test ===\n\n";

// Test 1: Load current settings
echo "Test 1: Loading current settings...\n";
$settings = loadEmailSettings();
echo "✓ Current settings loaded\n";
echo "  - Enabled: " . ($settings['enabled'] ? 'Yes' : 'No') . "\n";
echo "  - SMTP Host: " . ($settings['smtp']['host'] ?? 'Not set') . "\n";
echo "  - SMTP Port: " . ($settings['smtp']['port'] ?? 'Not set') . "\n";
if (!empty($settings['last_saved_at'])) {
    echo "  - Last Saved: " . $settings['last_saved_at'] . "\n";
}
echo "\n";

// Test 2: Test SMTP settings save
echo "Test 2: Testing SMTP settings save...\n";
$testSettings = $settings;
$testSettings['smtp']['host'] = 'smtp.test-' . time() . '.com'; // Use test hostname
$testSettings['smtp']['port'] = 587;

$saveResult = saveEmailSettings($testSettings);
if ($saveResult) {
    echo "✓ SMTP settings saved successfully\n";
    
    // Verify by loading again
    $reloadedSettings = loadEmailSettings();
    if ($reloadedSettings['smtp']['host'] === $testSettings['smtp']['host']) {
        echo "✓ SMTP settings persisted correctly\n";
        echo "  - Host: " . $reloadedSettings['smtp']['host'] . "\n";
        echo "  - Last saved at: " . ($reloadedSettings['last_saved_at'] ?? 'Not set') . "\n";
    } else {
        echo "✗ ERROR: SMTP settings did not persist!\n";
        echo "  - Expected: " . $testSettings['smtp']['host'] . "\n";
        echo "  - Got: " . $reloadedSettings['smtp']['host'] . "\n";
    }
} else {
    echo "✗ ERROR: Failed to save SMTP settings\n";
}
echo "\n";

// Test 3: Test routing settings save
echo "Test 3: Testing routing settings save...\n";
$testSettings = $settings;
$testSettings['routing']['admin_orders_email'] = 'admin@test-' . time() . '.com';
$testSettings['routing']['support_email'] = 'support@test.com';

$saveResult = saveEmailSettings($testSettings);
if ($saveResult) {
    echo "✓ Routing settings saved successfully\n";
    
    // Verify by loading again
    $reloadedSettings = loadEmailSettings();
    if ($reloadedSettings['routing']['admin_orders_email'] === $testSettings['routing']['admin_orders_email']) {
        echo "✓ Routing settings persisted correctly\n";
        echo "  - Admin Email: " . $reloadedSettings['routing']['admin_orders_email'] . "\n";
        echo "  - Support Email: " . $reloadedSettings['routing']['support_email'] . "\n";
    } else {
        echo "✗ ERROR: Routing settings did not persist!\n";
    }
} else {
    echo "✗ ERROR: Failed to save routing settings\n";
}
echo "\n";

// Test 4: Test templates save
echo "Test 4: Testing templates save...\n";
$templates = loadEmailTemplates();
$templates['order_admin']['subject'] = 'Test Subject - ' . time();

$saveResult = saveEmailTemplates($templates);
if ($saveResult) {
    echo "✓ Templates saved successfully\n";
    
    // Verify by loading again
    $reloadedTemplates = loadEmailTemplates();
    if ($reloadedTemplates['order_admin']['subject'] === $templates['order_admin']['subject']) {
        echo "✓ Templates persisted correctly\n";
        echo "  - Subject: " . $reloadedTemplates['order_admin']['subject'] . "\n";
    } else {
        echo "✗ ERROR: Templates did not persist!\n";
    }
} else {
    echo "✗ ERROR: Failed to save templates\n";
}
echo "\n";

// Test 5: Restore original settings
echo "Test 5: Restoring original settings...\n";
if (saveEmailSettings($settings)) {
    echo "✓ Original settings restored\n";
} else {
    echo "✗ ERROR: Failed to restore settings\n";
}
echo "\n";

// Test 6: Check file permissions
echo "Test 6: Checking file permissions...\n";
$settingsFile = __DIR__ . '/../data/email_settings.json';
if (file_exists($settingsFile)) {
    if (is_writable($settingsFile)) {
        echo "✓ Settings file is writable\n";
        echo "  - Path: $settingsFile\n";
        echo "  - Permissions: " . substr(sprintf('%o', fileperms($settingsFile)), -4) . "\n";
    } else {
        echo "✗ ERROR: Settings file is not writable!\n";
        echo "  - Path: $settingsFile\n";
        echo "  - Permissions: " . substr(sprintf('%o', fileperms($settingsFile)), -4) . "\n";
    }
} else {
    echo "✗ ERROR: Settings file does not exist!\n";
    echo "  - Expected path: $settingsFile\n";
}
echo "\n";

// Test 7: Check directory permissions
echo "Test 7: Checking data directory permissions...\n";
$dataDir = __DIR__ . '/../data';
if (is_dir($dataDir)) {
    if (is_writable($dataDir)) {
        echo "✓ Data directory is writable\n";
        echo "  - Path: $dataDir\n";
    } else {
        echo "✗ ERROR: Data directory is not writable!\n";
        echo "  - Path: $dataDir\n";
    }
} else {
    echo "✗ ERROR: Data directory does not exist!\n";
    echo "  - Expected path: $dataDir\n";
}
echo "\n";

// Test 8: Verify timestamp is being added
echo "Test 8: Verifying timestamp functionality...\n";
sleep(1); // Wait to ensure timestamp will be different
$timestampTest = $settings;
$timestampTest['smtp']['host'] = 'timestamp-test.com';
saveEmailSettings($timestampTest);
$timestampVerify = loadEmailSettings();
if (!empty($timestampVerify['last_saved_at'])) {
    echo "✓ Timestamp is being added\n";
    echo "  - Last saved at: " . $timestampVerify['last_saved_at'] . "\n";
} else {
    echo "✗ ERROR: Timestamp not found in saved settings\n";
}
// Restore original
saveEmailSettings($settings);
echo "\n";

echo "=== Test Complete ===\n";

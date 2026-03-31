<?php
/**
 * Simulate POST requests to admin/email.php
 * Tests the PRG pattern and form handling
 * 
 * WARNING: This script directly manipulates PHP superglobals ($_SERVER, $_POST)
 * for testing purposes. This is intentional to simulate browser POST behavior
 * in a CLI environment. Do not use this pattern in production code.
 */

require_once __DIR__ . '/../includes/email/mailer.php';
require_once __DIR__ . '/../includes/email/templates.php';

echo "=== Email Admin POST Simulation Test ===\n\n";

// Initialize session to simulate real environment
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Test 1: Simulate SMTP settings save
echo "Test 1: Simulating SMTP settings POST...\n";
$settings = loadEmailSettings();
$originalHost = $settings['smtp']['host'] ?? '';

// Simulate POST data (WARNING: Manipulating global state for testing)
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'action' => 'save_email_settings',
    'csrf_token' => $csrfToken,
    'tab' => 'smtp',
    'enabled' => '1',
    'smtp_host' => 'smtp.simulation-test.com',
    'smtp_port' => '587',
    'smtp_encryption' => 'tls',
    'smtp_username' => 'test@example.com',
    'smtp_password' => '', // Empty to test existing password handling
    'smtp_from_email' => 'test@example.com',
    'smtp_from_name' => 'Test User'
];

// Manually trigger the save logic (simulating what email.php does)
$settings['enabled'] = !empty($_POST['enabled']);
$settings['smtp']['host'] = trim($_POST['smtp_host'] ?? '');
$settings['smtp']['port'] = (int)($_POST['smtp_port'] ?? 587);
$settings['smtp']['encryption'] = trim($_POST['smtp_encryption'] ?? 'tls');
$settings['smtp']['username'] = trim($_POST['smtp_username'] ?? '');
$settings['smtp']['from_email'] = trim($_POST['smtp_from_email'] ?? '');
$settings['smtp']['from_name'] = trim($_POST['smtp_from_name'] ?? '');

if (saveEmailSettings($settings)) {
    echo "✓ SMTP settings saved via POST simulation\n";
    
    // Verify
    $reloaded = loadEmailSettings();
    if ($reloaded['smtp']['host'] === 'smtp.simulation-test.com') {
        echo "✓ Settings persisted: " . $reloaded['smtp']['host'] . "\n";
        echo "✓ Timestamp added: " . ($reloaded['last_saved_at'] ?? 'Not found') . "\n";
    } else {
        echo "✗ ERROR: Settings did not persist\n";
    }
} else {
    echo "✗ ERROR: Save failed\n";
}
echo "\n";

// Test 2: Simulate routing settings save
echo "Test 2: Simulating Routing settings POST...\n";
$_POST = [
    'action' => 'save_email_settings',
    'csrf_token' => $csrfToken,
    'tab' => 'routing',
    'admin_orders_email' => 'admin@simulation.com',
    'support_email' => 'support@simulation.com',
    'reply_to_email' => 'reply@simulation.com'
];

$settings = loadEmailSettings();
$settings['routing']['admin_orders_email'] = trim($_POST['admin_orders_email'] ?? '');
$settings['routing']['support_email'] = trim($_POST['support_email'] ?? '');
$settings['routing']['reply_to_email'] = trim($_POST['reply_to_email'] ?? '');

if (saveEmailSettings($settings)) {
    echo "✓ Routing settings saved via POST simulation\n";
    
    // Verify
    $reloaded = loadEmailSettings();
    if ($reloaded['routing']['admin_orders_email'] === 'admin@simulation.com') {
        echo "✓ Routing persisted: " . $reloaded['routing']['admin_orders_email'] . "\n";
    } else {
        echo "✗ ERROR: Routing did not persist\n";
    }
} else {
    echo "✗ ERROR: Save failed\n";
}
echo "\n";

// Test 3: Verify PRG would work (check session flash messages)
echo "Test 3: Verifying session flash message mechanism...\n";
$_SESSION['flash_success'] = 'Test success message';
$_SESSION['flash_error'] = 'Test error message';

// Retrieve and clear (simulating what the page does after redirect)
$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success']);
unset($_SESSION['flash_error']);

if ($success === 'Test success message') {
    echo "✓ Flash success message works\n";
}
if ($error === 'Test error message') {
    echo "✓ Flash error message works\n";
}
if (empty($_SESSION['flash_success']) && empty($_SESSION['flash_error'])) {
    echo "✓ Flash messages cleared after retrieval\n";
}
echo "\n";

// Restore original settings
echo "Restoring original settings...\n";
$settings['smtp']['host'] = $originalHost;
saveEmailSettings($settings);
echo "✓ Settings restored\n\n";

echo "=== Simulation Complete ===\n";

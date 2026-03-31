<?php
/**
 * Email Settings - Admin page for configuring SMTP, templates, and testing
 */

// Configure PHP error logging to dedicated file
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Never display errors in production

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Log the fatal error
        $logFile = __DIR__ . '/../logs/php_errors.log';
        $logDir = dirname($logFile);
        
        // Create logs directory if missing
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $message = "[$timestamp] FATAL ERROR: {$error['message']} in {$error['file']} on line {$error['line']}\n";
        @file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);
        
        // Show friendly error page
        if (!headers_sent()) {
            http_response_code(500);
        }
        
        echo <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Internal Error</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .error-box { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 20px; border-radius: 4px; }
        h1 { color: #721c24; }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>⚠️ Internal Error</h1>
        <p>A critical error occurred while loading the email settings page.</p>
        <p><strong>What to do:</strong></p>
        <ul>
            <li>Check the <strong>Email → Logs</strong> tab for details (if accessible)</li>
            <li>Review <code>/logs/php_errors.log</code> on the server</li>
            <li>Contact your system administrator</li>
        </ul>
        <p><a href="index.php">← Return to Dashboard</a></p>
    </div>
</body>
</html>
HTML;
        exit;
    }
});

// Protected include function
function safeRequire($file, $description = 'file') {
    if (!file_exists($file)) {
        $error = "Missing required file: $file ($description)";
        error_log($error);
        
        $fileEscaped = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
        $descEscaped = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        
        echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Missing File</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .error-box { background: #fff3cd; color: #856404; border: 1px solid #ffc107; padding: 20px; border-radius: 4px; }
        h1 { color: #856404; }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>⚠️ Missing Required File</h1>
        <p><strong>File:</strong> <code>$fileEscaped</code></p>
        <p><strong>Description:</strong> $descEscaped</p>
        <p>This file is required for the email settings page to function properly.</p>
        <p><a href="index.php">← Return to Dashboard</a></p>
    </div>
</body>
</html>
HTML;
        exit;
    }
    require_once $file;
}

// Safe includes with validation
safeRequire(__DIR__ . '/../init.php', 'Application initialization');
safeRequire(__DIR__ . '/../includes/email/mailer.php', 'Email mailer module');
safeRequire(__DIR__ . '/../includes/email/templates.php', 'Email templates module');
safeRequire(__DIR__ . '/../includes/email/crypto.php', 'Email encryption module');
safeRequire(__DIR__ . '/../includes/email/log.php', 'Email logging module');

// Check authentication
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Constants for common messages
define('PASSWORD_DECRYPT_WARNING', 'Existing SMTP password cannot be decrypted. Please enter a new password to restore email functionality.');

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Determine active tab BEFORE any POST handling (so we know where to redirect)
$activeTab = $_GET['tab'] ?? 'smtp';
$validTabs = ['smtp', 'routing', 'templates', 'test', 'logs'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'smtp';
}

// POST-REDIRECT-GET Pattern: Handle ALL POST requests first, then redirect
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // CSRF validation (required for all POST actions)
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash_error'] = 'Session expired. Please refresh the page and try again.';
        header('Location: email.php?tab=' . urlencode($activeTab));
        exit;
    }
    
    try {
        // Load current settings
        $settings = loadEmailSettings();
        $templates = loadEmailTemplates();
        
        switch ($action) {
            case 'save_email_settings': // Unified save action for SMTP/Routing
            case 'save_smtp':
            case 'save_routing':
                // Determine which settings to update based on form fields
                if (isset($_POST['smtp_host'])) {
                    // SMTP settings
                    $settings['enabled'] = !empty($_POST['enabled']);
                    $settings['smtp']['host'] = trim($_POST['smtp_host'] ?? '');
                    $settings['smtp']['port'] = (int)($_POST['smtp_port'] ?? 587);
                    $settings['smtp']['encryption'] = trim($_POST['smtp_encryption'] ?? 'tls');
                    $settings['smtp']['username'] = trim($_POST['smtp_username'] ?? '');
                    $settings['smtp']['from_email'] = trim($_POST['smtp_from_email'] ?? '');
                    $settings['smtp']['from_name'] = trim($_POST['smtp_from_name'] ?? '');
                    
                    // Handle password - only update if new password provided
                    $newPassword = $_POST['smtp_password'] ?? '';
                    $passwordWasUpdated = false;
                    
                    if ($newPassword !== '') {
                        try {
                            $encryptedPassword = encryptEmailPassword($newPassword);
                            $settings['smtp']['password_encrypted'] = $encryptedPassword;
                            $passwordWasUpdated = true;
                        } catch (Exception $e) {
                            logEmailEvent('smtp_save', 'admin-action', false, 'Password encryption failed: ' . $e->getMessage());
                            throw new Exception('Failed to encrypt password: ' . $e->getMessage());
                        }
                    }
                    
                    // Validate SMTP settings
                    $validationErrors = [];
                    if (empty($settings['smtp']['host'])) {
                        $validationErrors[] = 'SMTP host is required';
                    }
                    if (empty($settings['smtp']['username'])) {
                        $validationErrors[] = 'SMTP username is required';
                    }
                    if (empty($settings['smtp']['from_email']) || !filter_var($settings['smtp']['from_email'], FILTER_VALIDATE_EMAIL)) {
                        $validationErrors[] = 'Valid from email is required';
                    }
                    
                    // Check password - allow save even if decrypt fails, but require new password or valid existing one
                    if ($newPassword === '') {
                        if (empty($settings['smtp']['password_encrypted'])) {
                            // Show warning but allow save
                            $_SESSION['flash_warning'] = 'SMTP password is not set. Email sending will not work until password is configured.';
                        } else {
                            // Try to decrypt existing password - if fails, show warning but ALLOW save
                            try {
                                if (!canDecryptEmailPassword($settings['smtp']['password_encrypted'])) {
                                    $_SESSION['flash_warning'] = PASSWORD_DECRYPT_WARNING;
                                }
                            } catch (Exception $e) {
                                $_SESSION['flash_warning'] = PASSWORD_DECRYPT_WARNING;
                            }
                        }
                    }
                    
                    if (!empty($validationErrors)) {
                        $_SESSION['flash_error'] = implode('; ', $validationErrors);
                        header('Location: email.php?tab=smtp');
                        exit;
                    }
                } elseif (isset($_POST['admin_orders_email']) || isset($_POST['support_email']) || isset($_POST['reply_to_email'])) {
                    // Routing settings
                    $settings['routing']['admin_orders_email'] = trim($_POST['admin_orders_email'] ?? '');
                    $settings['routing']['support_email'] = trim($_POST['support_email'] ?? '');
                    $settings['routing']['reply_to_email'] = trim($_POST['reply_to_email'] ?? '');
                    
                    // Validate
                    $validationErrors = [];
                    if (!empty($settings['routing']['admin_orders_email']) && !filter_var($settings['routing']['admin_orders_email'], FILTER_VALIDATE_EMAIL)) {
                        $validationErrors[] = 'Invalid admin orders email';
                    }
                    if (!empty($settings['routing']['support_email']) && !filter_var($settings['routing']['support_email'], FILTER_VALIDATE_EMAIL)) {
                        $validationErrors[] = 'Invalid support email';
                    }
                    if (!empty($settings['routing']['reply_to_email']) && !filter_var($settings['routing']['reply_to_email'], FILTER_VALIDATE_EMAIL)) {
                        $validationErrors[] = 'Invalid reply-to email';
                    }
                    
                    if (!empty($validationErrors)) {
                        $_SESSION['flash_error'] = implode('; ', $validationErrors);
                        header('Location: email.php?tab=routing');
                        exit;
                    }
                }
                
                // Save settings
                if (saveEmailSettings($settings)) {
                    logEmailEvent('settings_save', 'admin-action', true, 'Settings saved successfully');
                    $_SESSION['flash_success'] = 'Settings saved successfully and persisted.';
                } else {
                    logEmailEvent('settings_save', 'admin-action', false, 'Failed to save settings file');
                    $_SESSION['flash_error'] = 'Failed to save settings. Please check file permissions on data/email_settings.json';
                }
                
                // Use POST tab for redirect to ensure correct destination
                $redirectTab = $_POST['tab'] ?? $activeTab;
                header('Location: email.php?tab=' . urlencode($redirectTab));
                exit;
                
            case 'save_template':
                $templateKey = $_POST['template_key'] ?? '';
                $validKeys = ['order_admin', 'order_customer', 'support_admin', 'support_customer'];
                
                if (!in_array($templateKey, $validKeys)) {
                    $_SESSION['flash_error'] = 'Invalid template key';
                } else {
                    $templates[$templateKey] = [
                        'subject' => trim($_POST['template_subject'] ?? ''),
                        'html' => trim($_POST['template_html'] ?? ''),
                        'text' => trim($_POST['template_text'] ?? '')
                    ];
                    
                    if (saveEmailTemplates($templates)) {
                        $_SESSION['flash_success'] = 'Template saved successfully';
                    } else {
                        $_SESSION['flash_error'] = 'Failed to save template';
                    }
                }
                
                $redirectTemplate = $_GET['template'] ?? $templateKey;
                header('Location: email.php?tab=templates&template=' . urlencode($redirectTemplate));
                exit;
                
            case 'toggle_debug':
                $debugEnabled = !empty($_POST['debug_enabled']);
                $debugFile = __DIR__ . '/../data/email_debug.json';
                $debugData = ['debug_enabled' => $debugEnabled];
                
                if (file_put_contents($debugFile, json_encode($debugData, JSON_PRETTY_PRINT), LOCK_EX) !== false) {
                    $_SESSION['flash_success'] = 'Debug mode ' . ($debugEnabled ? 'enabled' : 'disabled') . ' successfully.';
                } else {
                    $_SESSION['flash_error'] = 'Failed to save debug mode setting';
                }
                
                header('Location: email.php?tab=smtp');
                exit;
                
            case 'reset_password':
                $settings['smtp']['password_encrypted'] = '';
                
                if (saveEmailSettings($settings)) {
                    logEmailEvent('password_reset', 'admin-action', true, 'SMTP password reset by administrator');
                    $_SESSION['flash_success'] = 'Stored SMTP password cleared. Please enter a new password and save.';
                } else {
                    $_SESSION['flash_error'] = 'Failed to reset password. Please check file permissions.';
                }
                
                header('Location: email.php?tab=smtp');
                exit;
                
            case 'send_test':
                $testEmail = trim($_POST['test_email'] ?? '');
                
                if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                    $_SESSION['flash_error'] = 'Valid test email address is required';
                } else {
                    $subject = 'Test Email from NicheHome.ch';
                    $html = '<h1>Test Email</h1><p>This is a test email from NicheHome.ch email system.</p><p>If you received this, your SMTP configuration is working correctly!</p>';
                    $text = "Test Email\n\nThis is a test email from NicheHome.ch email system.\n\nIf you received this, your SMTP configuration is working correctly!";
                    
                    $result = sendEmailViaSMTP($testEmail, $subject, $html, $text, null, 'test');
                    
                    if ($result['success']) {
                        $_SESSION['flash_success'] = 'Test email sent successfully! Check your inbox (and spam folder).';
                    } else {
                        $_SESSION['flash_error'] = 'Failed to send test email: ' . $result['error'];
                    }
                }
                
                header('Location: email.php?tab=test');
                exit;
                
            case 'test_connection':
                $connectionResult = testSMTPConnection();
                
                if ($connectionResult['success']) {
                    $message = '✓ SMTP Connection Test: SUCCESSFUL. Your SMTP configuration is working correctly!';
                    $_SESSION['flash_success'] = $message;
                } else {
                    $message = '✗ SMTP Connection Test: FAILED. Error: ' . $connectionResult['error'];
                    $_SESSION['flash_error'] = $message;
                }
                
                header('Location: email.php?tab=test');
                exit;
                
            default:
                $_SESSION['flash_error'] = 'Invalid action';
                header('Location: email.php?tab=' . urlencode($activeTab));
                exit;
        }
    } catch (Exception $e) {
        error_log("Email settings POST error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        $_SESSION['flash_error'] = 'An unexpected error occurred: ' . $e->getMessage();
        header('Location: email.php?tab=' . urlencode($activeTab));
        exit;
    }
}

// Retrieve and clear flash messages from session
$success = '';
$errors = [];
$testResult = [];

if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

if (isset($_SESSION['flash_error'])) {
    $errors[] = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

if (isset($_SESSION['flash_warning'])) {
    $errors[] = $_SESSION['flash_warning'];
    unset($_SESSION['flash_warning']);
}

// Wrap everything in try-catch to prevent 500 errors
try {
    // Load current settings and templates
    $settings = loadEmailSettings();
    $templates = loadEmailTemplates();
    
    // Check if legacy encryption key file exists
    $hasLegacyKey = hasLegacyEmailEncryptionKey();
    if ($hasLegacyKey) {
        $errors[] = '⚠️ Legacy encryption key file detected (config/.email_encryption_key). The system now uses config/email_secret.php as the single source of truth for the encryption key. The legacy file is no longer used, but you may want to delete it for security reasons.';
    }
    
    // Check password status: empty, valid, or corrupt
    $passwordNeedsReset = false;
    $passwordStatus = 'empty'; // empty, valid, or corrupt
    
    if (!empty($settings['smtp']['password_encrypted'])) {
        try {
            if (canDecryptEmailPassword($settings['smtp']['password_encrypted'])) {
                $passwordStatus = 'valid';
            } else {
                $passwordStatus = 'corrupt';
                $passwordNeedsReset = true;
                $errors[] = '⚠️ The stored SMTP password cannot be decrypted (encryption key may have changed). Please use "Reset Stored SMTP Password" button below or re-enter your SMTP password and save.';
            }
        } catch (Exception $e) {
            $passwordStatus = 'corrupt';
            $passwordNeedsReset = true;
            $errors[] = '⚠️ The stored SMTP password cannot be decrypted (encryption key may have changed). Please use "Reset Stored SMTP Password" button below or re-enter your SMTP password and save.';
            error_log("Password decryption check failed: " . $e->getMessage());
        }
    }
} catch (Exception $e) {
    error_log("Email settings/templates loading error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    $errors[] = 'Failed to load email configuration. Please check server logs.';
    
    // Set defaults to prevent further errors
    $settings = [
        'enabled' => false,
        'smtp' => ['host' => '', 'port' => 587, 'encryption' => 'tls', 'username' => '', 'password_encrypted' => '', 'from_email' => '', 'from_name' => ''],
        'routing' => ['admin_orders_email' => '', 'support_email' => '', 'reply_to_email' => '']
    ];
    $templates = [];
    $passwordNeedsReset = false;
}

// Get current template for editing (if on templates tab)
$currentTemplate = $_GET['template'] ?? 'order_admin';
if (!isset($templates[$currentTemplate])) {
    $currentTemplate = 'order_admin';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Settings - NicheHome.ch Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .tabs {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid #ddd;
            margin-bottom: 30px;
        }
        .tab {
            padding: 12px 24px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-bottom: none;
            cursor: pointer;
            text-decoration: none;
            color: #333;
            border-radius: 4px 4px 0 0;
        }
        .tab.active {
            background: white;
            border-bottom: 2px solid white;
            margin-bottom: -2px;
            font-weight: bold;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group textarea {
            min-height: 200px;
            font-family: monospace;
        }
        .form-group textarea.large {
            min-height: 400px;
        }
        .form-group .help-text {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert--success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert--error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }
        .btn--primary {
            background: #d4af37;
            color: white;
        }
        .btn--primary:hover {
            background: #c19b2a;
        }
        .btn--secondary {
            background: #6c757d;
            color: white;
        }
        .template-placeholders {
            background: #f9f9f9;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 15px 0;
        }
        .template-placeholders h4 {
            margin-top: 0;
        }
        .template-placeholders code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.9em;
        }
        .log-entry {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-family: monospace;
            font-size: 0.9em;
        }
        .log-entry.success {
            background: #f0f9ff;
        }
        .log-entry.failed {
            background: #fff5f5;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 12px;
            border-radius: 4px;
            margin-top: 10px;
            display: none;
        }
        .warning-box.show {
            display: block;
        }
    </style>
    <script>
        // Check for port/encryption mismatch
        function checkPortEncryptionMismatch() {
            const portInput = document.querySelector('input[name="smtp_port"]');
            const encryptionSelect = document.querySelector('select[name="smtp_encryption"]');
            const warningBox = document.getElementById('port-encryption-warning');
            
            if (!portInput || !encryptionSelect || !warningBox) return;
            
            const port = parseInt(portInput.value);
            const encryption = encryptionSelect.value;
            
            let warning = '';
            
            if (port === 587 && encryption !== 'tls') {
                warning = '⚠ Port 587 typically uses TLS encryption. Consider changing encryption to TLS.';
            } else if (port === 465 && encryption !== 'ssl') {
                warning = '⚠ Port 465 typically uses SSL encryption. Consider changing encryption to SSL.';
            } else if (port === 25 && encryption !== 'none') {
                warning = '⚠ Port 25 typically uses no encryption. Consider changing encryption to None.';
            }
            
            if (warning) {
                warningBox.textContent = warning;
                warningBox.classList.add('show');
            } else {
                warningBox.classList.remove('show');
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const portInput = document.querySelector('input[name="smtp_port"]');
            const encryptionSelect = document.querySelector('select[name="smtp_encryption"]');
            
            if (portInput && encryptionSelect) {
                portInput.addEventListener('input', checkPortEncryptionMismatch);
                encryptionSelect.addEventListener('change', checkPortEncryptionMismatch);
                checkPortEncryptionMismatch(); // Check on page load
            }
        });
    </script>
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-sidebar__logo">NicheHome Admin</div>
                        <nav class="admin-sidebar__nav">
                <a href="index.php" class="admin-sidebar__link">Dashboard</a>
                <a href="products.php" class="admin-sidebar__link">Products</a>
                <a href="admin_products.php" class="admin-sidebar__link">Products (Enhanced)</a>
                <a href="accessories.php" class="admin-sidebar__link">Accessories</a>
                <a href="fragrances.php" class="admin-sidebar__link">Fragrances</a>
                <a href="categories.php" class="admin-sidebar__link">Categories</a>
                <a href="stock.php" class="admin-sidebar__link">Stock</a>
                <a href="stock_import.php" class="admin-sidebar__link">Stock Import</a>
                <a href="sku_audit.php" class="admin-sidebar__link">SKU Audit</a>
                <a href="orders.php" class="admin-sidebar__link">Orders</a>
                <a href="admin_orders.php" class="admin-sidebar__link">Orders (Enhanced)</a>
                <a href="shipping.php" class="admin-sidebar__link">Shipping</a>
                <a href="branches.php" class="admin-sidebar__link">Branches</a>
                <a href="admin_users.php" class="admin-sidebar__link">User Management</a>
                <?php if (canManageUsers()): // MENU FIX: Only full_access role can manage admins ?>
                <a href="manage_admins.php" class="admin-sidebar__link">Admin Management</a>
                <?php endif; ?>
                <a href="diagnostics.php" class="admin-sidebar__link">Diagnostics</a>
                <a href="notifications.php" class="admin-sidebar__link">Notifications</a>
                <a href="email.php" class="admin-sidebar__link active">Email Settings</a>
                <a href="logout.php" class="admin-sidebar__link">Logout</a>
            </nav>
        </aside>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Email Settings</h1>
                <p>Configure SMTP, templates, and test email sending</p>
                <?php if (!empty($settings['last_saved_at'])): ?>
                    <p style="font-size: 0.9em; color: #666; margin-top: 5px;">
                        ✓ Last saved: <strong><?php echo htmlspecialchars($settings['last_saved_at']); ?></strong>
                    </p>
                <?php endif; ?>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert--success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert--error">
                    <strong>Errors:</strong>
                    <ul style="margin: 10px 0 0 20px; padding: 0;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($testResult)): ?>
                <div class="alert <?php echo $testResult['success'] ? 'alert--success' : 'alert--error'; ?>">
                    <?php 
                    // Safe: htmlspecialchars() sanitizes first, then nl2br() adds <br> tags
                    echo nl2br(htmlspecialchars($testResult['message'])); 
                    ?>
                </div>
            <?php endif; ?>
            
            <div class="tabs">
                <a href="?tab=smtp" class="tab <?php echo $activeTab === 'smtp' ? 'active' : ''; ?>">SMTP Settings</a>
                <a href="?tab=routing" class="tab <?php echo $activeTab === 'routing' ? 'active' : ''; ?>">Routing</a>
                <a href="?tab=templates" class="tab <?php echo $activeTab === 'templates' ? 'active' : ''; ?>">Templates</a>
                <a href="?tab=test" class="tab <?php echo $activeTab === 'test' ? 'active' : ''; ?>">Test Email</a>
                <a href="?tab=logs" class="tab <?php echo $activeTab === 'logs' ? 'active' : ''; ?>">Logs</a>
            </div>
            
            <!-- SMTP Settings Tab -->
            <div class="tab-content <?php echo $activeTab === 'smtp' ? 'active' : ''; ?>">
                <div class="admin-card">
                    <h2>SMTP Configuration</h2>
                    
                    <!-- Debug Mode Toggle -->
                    <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                        <h3 style="margin-top: 0; color: #856404;">🔍 Debug Mode</h3>
                        <form method="post" action="?tab=smtp" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_debug">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <label style="display: inline-block; margin-right: 15px;">
                                <input type="checkbox" name="debug_enabled" value="1" <?php echo (defined('EMAIL_DEBUG') && EMAIL_DEBUG) ? 'checked' : ''; ?>>
                                <strong>Enable SMTP Debug Mode (temporary)</strong>
                            </label>
                            <button type="submit" class="btn btn--secondary" style="display: inline-block; padding: 8px 16px;">
                                <?php echo (defined('EMAIL_DEBUG') && EMAIL_DEBUG) ? 'Disable' : 'Enable'; ?> Debug
                            </button>
                        </form>
                        <p style="margin: 10px 0 0 0; font-size: 0.9em; color: #856404;">
                            When enabled, detailed SMTP connection logs and error messages will be shown. 
                            Disable this in production after troubleshooting. <strong>Current status: <?php echo (defined('EMAIL_DEBUG') && EMAIL_DEBUG) ? '✓ Enabled' : '✗ Disabled'; ?></strong>
                        </p>
                    </div>
                    
                    <form method="post" action="?tab=smtp">
                        <input type="hidden" name="action" value="save_email_settings">
                        <input type="hidden" name="tab" value="smtp">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php echo !empty($settings['enabled']) ? 'checked' : ''; ?>>
                                Enable email sending
                            </label>
                            <p class="help-text">Master switch for all email functionality</p>
                        </div>
                        
                        <div class="form-group">
                            <label>SMTP Host *</label>
                            <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp']['host'] ?? ''); ?>" required>
                            <p class="help-text">Example: smtp.nichehome.ch</p>
                        </div>
                        
                        <div class="form-group">
                            <label>SMTP Port *</label>
                            <input type="number" name="smtp_port" value="<?php echo (int)($settings['smtp']['port'] ?? 587); ?>" required>
                            <p class="help-text">Common ports: 587 (TLS), 465 (SSL), 25 (none)</p>
                        </div>
                        
                        <div class="form-group">
                            <label>Encryption *</label>
                            <select name="smtp_encryption" required>
                                <option value="tls" <?php echo ($settings['smtp']['encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                <option value="ssl" <?php echo ($settings['smtp']['encryption'] ?? 'tls') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="none" <?php echo ($settings['smtp']['encryption'] ?? 'tls') === 'none' ? 'selected' : ''; ?>>None</option>
                            </select>
                            <div id="port-encryption-warning" class="warning-box"></div>
                        </div>
                        
                        <div class="form-group">
                            <label>SMTP Username *</label>
                            <input type="text" name="smtp_username" value="<?php echo htmlspecialchars($settings['smtp']['username'] ?? ''); ?>" required>
                            <p class="help-text">Usually your email address</p>
                        </div>
                        
                        <div class="form-group">
                            <label>SMTP Password</label>
                            <?php
                            // Determine password field styling based on state
                            $passwordFieldClass = '';
                            $passwordPlaceholder = 'Enter new password to change';
                            
                            if (isset($passwordStatus) && $passwordStatus === 'corrupt') {
                                $passwordFieldClass = 'border: 2px solid #dc3545;';
                                $passwordPlaceholder = 'Password cannot be decrypted - enter new password';
                            } elseif (isset($passwordStatus) && $passwordStatus === 'empty') {
                                $passwordPlaceholder = 'Enter SMTP password';
                            }
                            ?>
                            <input type="password" name="smtp_password" value="" placeholder="<?php echo htmlspecialchars($passwordPlaceholder, ENT_QUOTES, 'UTF-8'); ?>" <?php echo !empty($passwordFieldClass) ? 'style="' . htmlspecialchars($passwordFieldClass, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
                            <p class="help-text">
                                <?php if (isset($passwordStatus) && $passwordStatus === 'corrupt'): ?>
                                    <strong style="color: red;">⚠️ Password cannot be decrypted</strong> — Enter password to restore email functionality
                                <?php elseif (isset($passwordStatus) && $passwordStatus === 'valid'): ?>
                                    <strong style="color: green;">✓ Password is set (encrypted)</strong> — Leave blank to keep current password
                                <?php else: ?>
                                    <strong style="color: #856404;">⚠ No password set</strong> — Enter password to enable email
                                <?php endif; ?>
                            </p>
                            
                            <?php if (isset($passwordStatus) && ($passwordStatus === 'corrupt' || $passwordStatus === 'valid')): ?>
                            <!-- Reset Password Button -->
                            <div style="margin-top: 15px; padding: 12px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">
                                <button type="button" class="btn btn--secondary" style="padding: 8px 16px; font-size: 13px;" onclick="if(confirm('Are you sure you want to reset the stored SMTP password? You will need to re-enter it.')) { document.getElementById('reset-password-form').submit(); }">
                                    🔄 Reset Stored SMTP Password
                                </button>
                                <span style="margin-left: 10px; font-size: 0.9em; color: #666;">
                                    Clears the stored encrypted password (useful if password cannot be decrypted)
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label>From Email *</label>
                            <input type="email" name="smtp_from_email" value="<?php echo htmlspecialchars($settings['smtp']['from_email'] ?? ''); ?>" required>
                            <p class="help-text">Email address that appears in the "From" field</p>
                            
                            <?php
                            // Check if From email differs from SMTP username
                            $fromEmail = $settings['smtp']['from_email'] ?? '';
                            $username = $settings['smtp']['username'] ?? '';
                            if (!empty($fromEmail) && !empty($username) && $fromEmail !== $username):
                            ?>
                                <div style="background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 10px; margin-top: 10px; border-radius: 4px; font-size: 0.9em;">
                                    <strong>⚠️ Warning:</strong> From email differs from SMTP username.<br>
                                    Many email providers (including tuthost) require the "From" email to match the authenticated SMTP username, or they will reject the email.<br>
                                    <strong>Recommendation:</strong> Set From Email to match your SMTP username (<code><?php echo htmlspecialchars($username); ?></code>), or use Reply-To for a different response address.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label>From Name *</label>
                            <input type="text" name="smtp_from_name" value="<?php echo htmlspecialchars($settings['smtp']['from_name'] ?? ''); ?>" required>
                            <p class="help-text">Name that appears in the "From" field</p>
                        </div>
                        
                        <button type="submit" class="btn btn--primary" name="submit_smtp" value="1">Save Settings</button>
                    </form>
                    
                    <!-- Separate form for password reset (cannot be nested) -->
                    <form id="reset-password-form" method="post" action="?tab=smtp" style="display: none;">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    </form>
                </div>
            </div>
            
            <!-- Routing Tab -->
            <div class="tab-content <?php echo $activeTab === 'routing' ? 'active' : ''; ?>">
                <div class="admin-card">
                    <h2>Email Routing</h2>
                    <p>Configure where different types of emails are sent</p>
                    
                    <form method="post" action="?tab=routing">
                        <input type="hidden" name="action" value="save_email_settings">
                        <input type="hidden" name="tab" value="routing">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        
                        <div class="form-group">
                            <label>Admin Orders Email</label>
                            <input type="email" name="admin_orders_email" value="<?php echo htmlspecialchars($settings['routing']['admin_orders_email'] ?? ''); ?>">
                            <p class="help-text">Receives notifications when new orders are placed</p>
                        </div>
                        
                        <div class="form-group">
                            <label>Support Email</label>
                            <input type="email" name="support_email" value="<?php echo htmlspecialchars($settings['routing']['support_email'] ?? ''); ?>">
                            <p class="help-text">Receives support requests from contact form</p>
                        </div>
                        
                        <div class="form-group">
                            <label>Reply-To Email</label>
                            <input type="email" name="reply_to_email" value="<?php echo htmlspecialchars($settings['routing']['reply_to_email'] ?? ''); ?>">
                            <p class="help-text">Default reply-to address for customer emails</p>
                        </div>
                        
                        <button type="submit" class="btn btn--primary" name="submit_routing" value="1">Save Settings</button>
                    </form>
                </div>
            </div>
            
            <!-- Templates Tab -->
            <div class="tab-content <?php echo $activeTab === 'templates' ? 'active' : ''; ?>">
                <div class="admin-card">
                    <h2>Email Templates</h2>
                    
                    <div class="form-group">
                        <label>Select Template to Edit</label>
                        <select onchange="window.location.href='?tab=templates&template=' + this.value">
                            <option value="order_admin" <?php echo $currentTemplate === 'order_admin' ? 'selected' : ''; ?>>Order Admin Notification</option>
                            <option value="order_customer" <?php echo $currentTemplate === 'order_customer' ? 'selected' : ''; ?>>Order Customer Confirmation</option>
                            <option value="support_admin" <?php echo $currentTemplate === 'support_admin' ? 'selected' : ''; ?>>Support Admin Notification</option>
                            <option value="support_customer" <?php echo $currentTemplate === 'support_customer' ? 'selected' : ''; ?>>Support Customer Auto-Reply</option>
                        </select>
                    </div>
                    
                    <div class="template-placeholders">
                        <h4>Available Placeholders:</h4>
                        <p><strong>Order templates:</strong> 
                            <code>{order_id}</code>
                            <code>{customer_name}</code>
                            <code>{customer_email}</code>
                            <code>{customer_phone}</code>
                            <code>{order_date}</code>
                            <code>{payment_method}</code>
                            <code>{items_table}</code>
                            <code>{items_list}</code>
                            <code>{subtotal}</code>
                            <code>{shipping}</code>
                            <code>{total}</code>
                            <code>{pickup_branch}</code>
                        </p>
                        <p><strong>Support templates:</strong> 
                            <code>{name}</code>
                            <code>{email}</code>
                            <code>{phone}</code>
                            <code>{support_subject}</code>
                            <code>{support_message}</code>
                            <code>{date}</code>
                        </p>
                    </div>
                    
                    <form method="post" action="?tab=templates&template=<?php echo urlencode($currentTemplate); ?>">
                        <input type="hidden" name="action" value="save_template">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="template_key" value="<?php echo htmlspecialchars($currentTemplate); ?>">
                        
                        <div class="form-group">
                            <label>Subject</label>
                            <input type="text" name="template_subject" value="<?php echo htmlspecialchars($templates[$currentTemplate]['subject'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>HTML Body</label>
                            <textarea name="template_html" class="large" required><?php echo htmlspecialchars($templates[$currentTemplate]['html'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Plain Text Body</label>
                            <textarea name="template_text" required><?php echo htmlspecialchars($templates[$currentTemplate]['text'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn--primary" name="submit_template" value="1">Save Settings</button>
                    </form>
                </div>
            </div>
            
            <!-- Test Email Tab -->
            <div class="tab-content <?php echo $activeTab === 'test' ? 'active' : ''; ?>">
                <div class="admin-card">
                    <h2>Send Test Email</h2>
                    <p>Send a test email to verify your SMTP configuration is working correctly</p>
                    
                    <form method="post" action="?tab=test">
                        <input type="hidden" name="action" value="send_test">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        
                        <div class="form-group">
                            <label>Test Email Address *</label>
                            <input type="email" name="test_email" value="" placeholder="your@email.com" required>
                            <p class="help-text">Enter your email address to receive a test message</p>
                        </div>
                        
                        <button type="submit" class="btn btn--primary">Send Test Email</button>
                    </form>
                    
                    <hr style="margin: 40px 0;">
                    
                    <h3>Test SMTP Connection</h3>
                    <p>Test the SMTP connection without sending an email</p>
                    
                    <form method="post" action="?tab=test">
                        <input type="hidden" name="action" value="test_connection">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <button type="submit" class="btn btn--secondary">Test Connection</button>
                    </form>
                    
                    <hr style="margin: 40px 0;">
                    
                    <h3>Current Configuration Status</h3>
                    <ul>
                        <li>Email Enabled: <strong><?php echo !empty($settings['enabled']) ? '✓ Yes' : '✗ No'; ?></strong></li>
                        <li>SMTP Host: <strong><?php echo htmlspecialchars($settings['smtp']['host'] ?? 'Not set'); ?></strong></li>
                        <li>SMTP Port: <strong><?php echo (int)($settings['smtp']['port'] ?? 0); ?></strong></li>
                        <li>Encryption: <strong><?php echo htmlspecialchars(strtoupper($settings['smtp']['encryption'] ?? 'Not set')); ?></strong></li>
                        <li>Username: <strong><?php echo htmlspecialchars($settings['smtp']['username'] ?? 'Not set'); ?></strong></li>
                        <li>Password: <strong><?php echo !empty($settings['smtp']['password_encrypted']) ? '✓ Set' : '✗ Not set'; ?></strong></li>
                        <li>From Email: <strong><?php echo htmlspecialchars($settings['smtp']['from_email'] ?? 'Not set'); ?></strong></li>
                    </ul>
                </div>
            </div>
            
            <!-- Logs Tab -->
            <div class="tab-content <?php echo $activeTab === 'logs' ? 'active' : ''; ?>">
                <?php
                // Check logs directory status
                $logsDir = __DIR__ . '/../logs';
                $logsDirExists = is_dir($logsDir);
                $logsDirWritable = $logsDirExists && is_writable($logsDir);
                $emailLogFile = $logsDir . '/email.log';
                $phpErrorLogFile = $logsDir . '/php_errors.log';
                ?>
                
                <!-- Directory Status Warning -->
                <?php if (!$logsDirExists): ?>
                    <div class="alert alert--error">
                        <strong>⚠️ Logs directory does not exist!</strong><br>
                        Directory: <code><?php echo htmlspecialchars($logsDir); ?></code><br>
                        Please create this directory with write permissions.
                    </div>
                <?php elseif (!$logsDirWritable): ?>
                    <div class="alert alert--error">
                        <strong>⚠️ Logs directory is not writable!</strong><br>
                        Directory: <code><?php echo htmlspecialchars($logsDir); ?></code><br>
                        Please set permissions to allow web server to write log files (e.g., chmod 755).
                    </div>
                <?php else: ?>
                    <div class="alert alert--success">
                        ✓ Logs directory is properly configured and writable.
                    </div>
                <?php endif; ?>
                
                <!-- Storage Debug Panel -->
                <div class="admin-card" style="background: #f8f9fa; border: 1px solid #dee2e6; margin-bottom: 20px;">
                    <h2>🔧 Storage Debug Information</h2>
                    <div style="font-family: monospace; font-size: 0.9em;">
                        <?php
                        $settingsFile = __DIR__ . '/../data/email_settings.json';
                        $templatesFile = __DIR__ . '/../data/email_templates.json';
                        $dataDir = __DIR__ . '/../data';
                        ?>
                        <p><strong>Settings Storage:</strong></p>
                        <ul style="margin: 10px 0 0 20px;">
                            <li>File: <code><?php echo htmlspecialchars($settingsFile); ?></code></li>
                            <li>Exists: <strong><?php echo file_exists($settingsFile) ? '✓ Yes' : '✗ No'; ?></strong></li>
                            <li>Writable: <strong><?php echo (file_exists($settingsFile) && is_writable($settingsFile)) ? '✓ Yes' : '✗ No'; ?></strong></li>
                            <?php if (file_exists($settingsFile)): ?>
                                <li>Size: <?php echo filesize($settingsFile); ?> bytes</li>
                                <li>Last Modified: <?php echo date('Y-m-d H:i:s', filemtime($settingsFile)); ?></li>
                                <li>Permissions: <?php echo substr(sprintf('%o', fileperms($settingsFile)), -4); ?></li>
                            <?php endif; ?>
                        </ul>
                        
                        <p style="margin-top: 15px;"><strong>Data Directory:</strong></p>
                        <ul style="margin: 10px 0 0 20px;">
                            <li>Path: <code><?php echo htmlspecialchars($dataDir); ?></code></li>
                            <li>Exists: <strong><?php echo is_dir($dataDir) ? '✓ Yes' : '✗ No'; ?></strong></li>
                            <li>Writable: <strong><?php echo (is_dir($dataDir) && is_writable($dataDir)) ? '✓ Yes' : '✗ No'; ?></strong></li>
                            <?php if (is_dir($dataDir)): ?>
                                <li>Permissions: <?php echo substr(sprintf('%o', fileperms($dataDir)), -4); ?></li>
                            <?php endif; ?>
                        </ul>
                        
                        <p style="margin-top: 15px;"><strong>Templates Storage:</strong></p>
                        <ul style="margin: 10px 0 0 20px;">
                            <li>File: <code><?php echo htmlspecialchars($templatesFile); ?></code></li>
                            <li>Exists: <strong><?php echo file_exists($templatesFile) ? '✓ Yes' : '✗ No'; ?></strong></li>
                            <li>Writable: <strong><?php echo (file_exists($templatesFile) && is_writable($templatesFile)) ? '✓ Yes' : '✗ No'; ?></strong></li>
                            <?php if (file_exists($templatesFile)): ?>
                                <li>Size: <?php echo filesize($templatesFile); ?> bytes</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <!-- Email Logs -->
                <div class="admin-card">
                    <h2>📧 Email Logs</h2>
                    <p>Recent email sending activity (last 100 entries)</p>
                    <p style="font-size: 0.9em; color: #666;">
                        Log file: <code><?php echo htmlspecialchars($emailLogFile); ?></code>
                        <?php if (file_exists($emailLogFile)): ?>
                            | Size: <?php echo round(filesize($emailLogFile) / 1024, 2); ?> KB
                            | Last modified: <?php echo date('Y-m-d H:i:s', filemtime($emailLogFile)); ?>
                        <?php else: ?>
                            | <em>File does not exist yet (no emails sent)</em>
                        <?php endif; ?>
                    </p>
                    
                    <div style="max-height: 600px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; margin-top: 15px;">
                        <?php
                        $logs = getRecentEmailLogs(100);
                        if (empty($logs)):
                        ?>
                            <p style="padding: 20px; text-align: center; color: #666;">No email logs yet</p>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                $class = 'log-entry';
                                if (strpos($log, '[SUCCESS]') !== false) {
                                    $class .= ' success';
                                } elseif (strpos($log, '[FAILED]') !== false) {
                                    $class .= ' failed';
                                }
                                ?>
                                <div class="<?php echo $class; ?>"><?php echo htmlspecialchars($log); ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- PHP Error Logs -->
                <div class="admin-card" style="margin-top: 30px;">
                    <h2>🐞 PHP Error Logs</h2>
                    <p>Recent PHP errors, warnings, and fatal errors (last 100 lines)</p>
                    <p style="font-size: 0.9em; color: #666;">
                        Log file: <code><?php echo htmlspecialchars($phpErrorLogFile); ?></code>
                        <?php if (file_exists($phpErrorLogFile)): ?>
                            | Size: <?php echo round(filesize($phpErrorLogFile) / 1024, 2); ?> KB
                            | Last modified: <?php echo date('Y-m-d H:i:s', filemtime($phpErrorLogFile)); ?>
                        <?php else: ?>
                            | <em>File does not exist yet (no errors logged)</em>
                        <?php endif; ?>
                    </p>
                    
                    <div style="max-height: 600px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; margin-top: 15px; background: #f9f9f9;">
                        <?php
                        if (!file_exists($phpErrorLogFile)):
                        ?>
                            <p style="padding: 20px; text-align: center; color: #666;">No PHP errors logged yet</p>
                        <?php else:
                            $phpErrors = file($phpErrorLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                            if ($phpErrors === false || empty($phpErrors)):
                        ?>
                            <p style="padding: 20px; text-align: center; color: #666;">No PHP errors logged yet</p>
                        <?php else:
                            // Get last 100 lines
                            $phpErrors = array_slice($phpErrors, -100);
                            // Reverse to show most recent first
                            $phpErrors = array_reverse($phpErrors);
                            foreach ($phpErrors as $errorLine):
                                // Determine error severity for styling
                                $errorClass = 'log-entry';
                                if (stripos($errorLine, 'FATAL') !== false || stripos($errorLine, 'ERROR') !== false) {
                                    $errorClass .= ' failed';
                                } elseif (stripos($errorLine, 'WARNING') !== false || stripos($errorLine, 'WARN') !== false) {
                                    $errorClass = 'log-entry'; // neutral
                                }
                        ?>
                            <div class="<?php echo $errorClass; ?>"><?php echo htmlspecialchars($errorLine); ?></div>
                        <?php 
                            endforeach;
                        endif;
                        endif;
                        ?>
                    </div>
                </div>
            </div>
            
        </main>
    </div>
</body>
</html>

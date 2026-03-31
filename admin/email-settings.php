<?php
/**
 * Email Settings - Legacy redirect to new email.php interface
 * This file is kept for backward compatibility
 */

require_once __DIR__ . '/../init.php';

// Check authentication
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Redirect to new unified email interface
header('Location: email.php');
exit;

$success = false;
$errors = [];

// Load current configuration
$config = loadJSON('email_config.json');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config['enabled'] = !empty($_POST['enabled']);
    $config['from_email'] = trim($_POST['from_email'] ?? '');
    $config['from_name'] = trim($_POST['from_name'] ?? '');
    $config['order_confirmation_enabled'] = !empty($_POST['order_confirmation_enabled']);
    $config['order_confirmation_subject'] = trim($_POST['order_confirmation_subject'] ?? '');
    
    // Validate
    if (empty($config['from_email']) || !isValidEmail($config['from_email'])) {
        $errors[] = 'Valid from email is required';
    }
    
    if (empty($config['from_name'])) {
        $errors[] = 'From name is required';
    }
    
    if (empty($errors)) {
        if (saveJSON('email_config.json', $config)) {
            $success = true;
        } else {
            $errors[] = 'Could not save configuration';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Settings - NicheHome.ch Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-sidebar__logo">NicheHome Admin</div>
            <nav class="admin-sidebar__nav">
                <a href="index.php" class="admin-sidebar__link">Dashboard</a>
                <a href="products.php" class="admin-sidebar__link">Products</a>
                <a href="orders.php" class="admin-sidebar__link">Orders</a>
                <a href="email.php" class="admin-sidebar__link active">Email Settings</a>
                <a href="notifications.php" class="admin-sidebar__link">Notifications</a>
                <a href="logout.php" class="admin-sidebar__link">Logout</a>
            </nav>
        </aside>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Email Settings</h1>
                <p>Configure order confirmation emails</p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert--success" style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 4px; margin-bottom: 2rem;">
                    Email settings saved successfully!
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert--error" style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 4px; margin-bottom: 2rem;">
                    <ul style="list-style: disc; padding-left: 1.5rem; margin: 0;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="admin-card">
                <form method="post" action="">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="enabled" value="1" <?php echo !empty($config['enabled']) ? 'checked' : ''; ?>>
                            Enable email sending
                        </label>
                        <p style="font-size: 0.9rem; color: #666;">Master switch for all email functionality</p>
                    </div>
                    
                    <div class="form-group">
                        <label>From Email *</label>
                        <input type="email" name="from_email" value="<?php echo htmlspecialchars($config['from_email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>From Name *</label>
                        <input type="text" name="from_name" value="<?php echo htmlspecialchars($config['from_name'] ?? ''); ?>" required>
                    </div>
                    
                    <hr style="margin: 2rem 0;">
                    
                    <h3>Order Confirmation Email</h3>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="order_confirmation_enabled" value="1" <?php echo !empty($config['order_confirmation_enabled']) ? 'checked' : ''; ?>>
                            Enable order confirmation emails
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>Subject Template</label>
                        <input type="text" name="order_confirmation_subject" value="<?php echo htmlspecialchars($config['order_confirmation_subject'] ?? ''); ?>">
                        <p style="font-size: 0.9rem; color: #666;">Use {order_id} as placeholder for order number</p>
                    </div>
                    
                    <div class="form-actions" style="margin-top: 2rem;">
                        <button type="submit" class="btn btn--gold">Save Settings</button>
                    </div>
                </form>
            </div>
            
            <div class="admin-card" style="margin-top: 2rem; background: #f9f9f9;">
                <h3>Note</h3>
                <p>
                    Email functionality uses PHP's built-in <code>mail()</code> function. 
                    For production use, configure your server's SMTP settings or integrate a service like SendGrid, Mailgun, etc.
                </p>
                <p style="margin-top: 1rem;">
                    To test email functionality, enable email sending and order confirmation, then place a test order.
                </p>
            </div>
        </main>
    </div>
</body>
</html>

<?php
/**
 * Notifications Settings - Admin page for configuring email notifications
 */

require_once __DIR__ . '/../init.php';

// Check authentication
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

$success = false;
$errors = [];

// Load current configuration
$config = loadJSON('notification_config.json');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // New order notification
    $config['new_order']['enabled'] = !empty($_POST['new_order_enabled']);
    $newOrderRecipients = trim($_POST['new_order_recipients'] ?? '');
    $config['new_order']['recipients'] = array_filter(array_map('trim', explode(',', $newOrderRecipients)));
    
    // New support request notification
    $config['new_support_request']['enabled'] = !empty($_POST['new_support_enabled']);
    $newSupportRecipients = trim($_POST['new_support_recipients'] ?? '');
    $config['new_support_request']['recipients'] = array_filter(array_map('trim', explode(',', $newSupportRecipients)));
    
    // Validate email addresses
    foreach ($config['new_order']['recipients'] as $email) {
        if (!isValidEmail($email)) {
            $errors[] = "Invalid email address for order notifications: {$email}";
        }
    }
    
    foreach ($config['new_support_request']['recipients'] as $email) {
        if (!isValidEmail($email)) {
            $errors[] = "Invalid email address for support notifications: {$email}";
        }
    }
    
    if (empty($errors)) {
        if (saveJSON('notification_config.json', $config)) {
            $success = true;
        } else {
            $errors[] = 'Could not save configuration';
        }
    }
}

// Prepare recipients for display
$newOrderRecipientsStr = implode(', ', $config['new_order']['recipients'] ?? []);
$newSupportRecipientsStr = implode(', ', $config['new_support_request']['recipients'] ?? []);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Settings - NicheHome.ch Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
                <a href="notifications.php" class="admin-sidebar__link active">Notifications</a>
                <a href="email.php" class="admin-sidebar__link">Email Settings</a>
                <a href="logout.php" class="admin-sidebar__link">Logout</a>
            </nav>
        </aside>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Notification Settings</h1>
                <p>Configure admin email notifications</p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert--success" style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 4px; margin-bottom: 2rem;">
                    Notification settings saved successfully!
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
                    <h3>New Order Notification</h3>
                    <p style="color: #666; margin-bottom: 1.5rem;">
                        Receive an email notification when a new order is placed.
                    </p>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="new_order_enabled" value="1" <?php echo !empty($config['new_order']['enabled']) ? 'checked' : ''; ?>>
                            Enable new order notifications
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>Recipient Email(s)</label>
                        <input type="text" name="new_order_recipients" value="<?php echo htmlspecialchars($newOrderRecipientsStr); ?>" placeholder="admin@nichehome.ch, manager@nichehome.ch">
                        <p style="font-size: 0.9rem; color: #666;">Separate multiple emails with commas</p>
                    </div>
                    
                    <hr style="margin: 2rem 0;">
                    
                    <h3>New Support Request Notification</h3>
                    <p style="color: #666; margin-bottom: 1.5rem;">
                        Receive an email notification when a new support request is submitted.
                    </p>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="new_support_enabled" value="1" <?php echo !empty($config['new_support_request']['enabled']) ? 'checked' : ''; ?>>
                            Enable new support request notifications
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>Recipient Email(s)</label>
                        <input type="text" name="new_support_recipients" value="<?php echo htmlspecialchars($newSupportRecipientsStr); ?>" placeholder="support@nichehome.ch">
                        <p style="font-size: 0.9rem; color: #666;">Separate multiple emails with commas</p>
                    </div>
                    
                    <div class="form-actions" style="margin-top: 2rem;">
                        <button type="submit" class="btn btn--gold">Save Settings</button>
                    </div>
                </form>
            </div>
            
            <div class="admin-card" style="margin-top: 2rem; background: #f9f9f9;">
                <h3>Note</h3>
                <p>
                    Notifications require email sending to be enabled. Go to <a href="email.php">Email Settings</a> to enable it.
                </p>
                <p style="margin-top: 1rem;">
                    Notification emails are sent automatically when:
                </p>
                <ul style="margin-top: 0.5rem;">
                    <li>A customer places a new order</li>
                    <li>A customer submits a support request</li>
                </ul>
            </div>
        </main>
    </div>
</body>
</html>

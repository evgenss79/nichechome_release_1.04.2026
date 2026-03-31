<?php
/**
 * Admin - Shipping Rules Management
 */

require_once __DIR__ . '/../init.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

$rules = loadShippingRules();
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'save_rules') {
            $newRules = [];
            if (isset($_POST['rules']) && is_array($_POST['rules'])) {
                foreach ($_POST['rules'] as $rule) {
                    $minTotal = floatval($rule['minTotal'] ?? 0);
                    $maxTotal = floatval($rule['maxTotal'] ?? 999999);
                    $shippingCost = floatval($rule['shippingCost'] ?? 0);
                    
                    if ($maxTotal > $minTotal) {
                        $newRules[] = [
                            'minTotal' => $minTotal,
                            'maxTotal' => $maxTotal,
                            'shippingCost' => $shippingCost
                        ];
                    }
                }
            }
            
            // Sort rules by minTotal
            usort($newRules, function($a, $b) {
                return $a['minTotal'] <=> $b['minTotal'];
            });
            
            if (saveShippingRules($newRules)) {
                $rules = $newRules;
                $success = 'Shipping rules saved successfully.';
            } else {
                $error = 'Failed to save shipping rules.';
            }
        } elseif ($_POST['action'] === 'add_rule') {
            $rules[] = [
                'minTotal' => 0,
                'maxTotal' => 999999,
                'shippingCost' => 0
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Rules - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Manrope:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .shipping-rules-table input[type="number"] {
            width: 100px;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .rule-row {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1rem;
            padding: 1rem;
            background: var(--color-sand);
            border-radius: 8px;
        }
        .rule-row .form-group {
            margin-bottom: 0;
        }
        .rule-row label {
            display: block;
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 0.25rem;
        }
        .btn-remove-rule {
            background: var(--color-error);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-add-rule {
            background: var(--color-charcoal);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 1rem;
        }
    </style>
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
                <a href="shipping.php" class="admin-sidebar__link active">Shipping</a>
                <a href="branches.php" class="admin-sidebar__link">Branches</a>
                <a href="admin_users.php" class="admin-sidebar__link">User Management</a>
                <?php if (canManageUsers()): // MENU FIX: Only full_access role can manage admins ?>
                <a href="manage_admins.php" class="admin-sidebar__link">Admin Management</a>
                <?php endif; ?>
                <a href="diagnostics.php" class="admin-sidebar__link">Diagnostics</a>
                <a href="notifications.php" class="admin-sidebar__link">Notifications</a>
                <a href="email.php" class="admin-sidebar__link">Email Settings</a>
                <a href="logout.php" class="admin-sidebar__link">Logout</a>
            </nav>
        </aside>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Shipping Rules</h1>
                <p style="color: #666; margin-top: 0.5rem;">Configure shipping costs based on order total</p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert--success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="admin-card">
                <form method="post" action="">
                    <input type="hidden" name="action" value="save_rules">
                    
                    <div id="rules-container">
                        <?php foreach ($rules as $index => $rule): ?>
                            <div class="rule-row">
                                <div class="form-group">
                                    <label>Min Total (CHF)</label>
                                    <input type="number" 
                                           name="rules[<?php echo $index; ?>][minTotal]" 
                                           value="<?php echo htmlspecialchars($rule['minTotal'] ?? 0); ?>" 
                                           step="0.01" 
                                           min="0">
                                </div>
                                <div class="form-group">
                                    <label>Max Total (CHF)</label>
                                    <input type="number" 
                                           name="rules[<?php echo $index; ?>][maxTotal]" 
                                           value="<?php echo htmlspecialchars($rule['maxTotal'] ?? 999999); ?>" 
                                           step="0.01" 
                                           min="0">
                                </div>
                                <div class="form-group">
                                    <label>Shipping Cost (CHF)</label>
                                    <input type="number" 
                                           name="rules[<?php echo $index; ?>][shippingCost]" 
                                           value="<?php echo htmlspecialchars($rule['shippingCost'] ?? 0); ?>" 
                                           step="0.01" 
                                           min="0">
                                </div>
                                <button type="button" class="btn-remove-rule" onclick="this.parentElement.remove()">Remove</button>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($rules)): ?>
                            <div class="rule-row">
                                <div class="form-group">
                                    <label>Min Total (CHF)</label>
                                    <input type="number" name="rules[0][minTotal]" value="0" step="0.01" min="0">
                                </div>
                                <div class="form-group">
                                    <label>Max Total (CHF)</label>
                                    <input type="number" name="rules[0][maxTotal]" value="80" step="0.01" min="0">
                                </div>
                                <div class="form-group">
                                    <label>Shipping Cost (CHF)</label>
                                    <input type="number" name="rules[0][shippingCost]" value="10" step="0.01" min="0">
                                </div>
                                <button type="button" class="btn-remove-rule" onclick="this.parentElement.remove()">Remove</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" class="btn-add-rule" onclick="addRule()">+ Add Rule</button>
                    
                    <div style="margin-top: 2rem;">
                        <button type="submit" class="btn btn--gold">Save Shipping Rules</button>
                    </div>
                </form>
            </div>
            
            <div class="admin-card" style="margin-top: 2rem;">
                <h3>How It Works</h3>
                <p style="margin-top: 1rem; color: #666;">
                    Shipping rules are applied based on the order subtotal. Create ranges with min/max totals 
                    and set the shipping cost for each range. For example:
                </p>
                <ul style="margin-top: 1rem; color: #666; padding-left: 1.5rem;">
                    <li>Orders from CHF 0 to CHF 79.99 → Shipping cost CHF 10</li>
                    <li>Orders from CHF 80 and above → Free shipping (CHF 0)</li>
                </ul>
                <p style="margin-top: 1rem; color: #666;">
                    <strong>Note:</strong> If a customer selects "Pickup in branch", shipping cost will be CHF 0 regardless of these rules.
                </p>
            </div>
        </main>
    </div>
    
    <script>
        let ruleIndex = <?php echo count($rules); ?>;
        
        function addRule() {
            const container = document.getElementById('rules-container');
            const ruleHtml = `
                <div class="rule-row">
                    <div class="form-group">
                        <label>Min Total (CHF)</label>
                        <input type="number" name="rules[${ruleIndex}][minTotal]" value="0" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Max Total (CHF)</label>
                        <input type="number" name="rules[${ruleIndex}][maxTotal]" value="999999" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Shipping Cost (CHF)</label>
                        <input type="number" name="rules[${ruleIndex}][shippingCost]" value="0" step="0.01" min="0">
                    </div>
                    <button type="button" class="btn-remove-rule" onclick="this.parentElement.remove()">Remove</button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', ruleHtml);
            ruleIndex++;
        }
    </script>
</body>
</html>

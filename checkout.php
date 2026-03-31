<?php
/**
 * Checkout - Checkout form page
 */

require_once __DIR__ . '/init.php';

$currentLang = I18N::getLanguage();
$cart = getCart();
$cartTotal = getCartTotal();

// Redirect if cart is empty
if (empty($cart)) {
    header('Location: cart.php?lang=' . $currentLang);
    exit;
}

$success = false;
$orderId = '';
$errors = [];
$fieldErrors = []; // Track which specific fields have errors

// Get logged-in customer data for auto-fill
$customer = getCurrentCustomer();
$isLoggedIn = isCustomerLoggedIn();

// Get active branches for pickup option
$activeBranches = getActiveBranches();

// Prepare default values from customer profile if logged in and not POST
$firstName = '';
$lastName = '';
$email = '';
$phone = '';
$street = '';
$house = '';
$zip = '';
$city = '';
$country = 'Switzerland';
$isPickup = false;
$pickupBranchId = '';

if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Auto-fill from customer profile
    $firstName = $customer['first_name'] ?? '';
    $lastName = $customer['last_name'] ?? '';
    $email = $customer['email'] ?? '';
    $phone = $customer['phone'] ?? '';
    
    $shippingAddr = $customer['shipping_address'] ?? [];
    $street = $shippingAddr['street'] ?? '';
    $house = $shippingAddr['house_number'] ?? '';
    $zip = $shippingAddr['zip'] ?? '';
    $city = $shippingAddr['city'] ?? '';
    $country = $shippingAddr['country'] ?? 'Switzerland';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get values from POST
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $street = trim($_POST['street'] ?? '');
    $house = trim($_POST['house'] ?? '');
    $zip = trim($_POST['zip'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $isPickup = !empty($_POST['pickup_in_branch']);
    $pickupBranchId = $isPickup ? trim($_POST['pickup_branch_id'] ?? '') : '';
    $registerAccount = !empty($_POST['register_account']) && !$isLoggedIn;
    $password = trim($_POST['password'] ?? '');
    
    // Determine required fields based on shipping method and registration choice
    $requiredFields = [
        'first_name' => I18N::t('page.checkout.firstName', 'First name'),
        'last_name' => I18N::t('page.checkout.lastName', 'Last name'),
        'phone' => I18N::t('page.checkout.phone', 'Phone'),
        'payment' => I18N::t('page.checkout.payment', 'Payment method')
    ];
    
    // For guest + pickup + no registration: only name and phone required
    // For all other cases: add email and address fields
    if (!$isPickup || $registerAccount) {
        $requiredFields['email'] = I18N::t('page.checkout.email', 'Email');
        $requiredFields['street'] = I18N::t('page.checkout.street', 'Street');
        $requiredFields['house'] = I18N::t('page.checkout.houseNumber', 'House number');
        $requiredFields['zip'] = I18N::t('page.checkout.zip', 'ZIP code');
        $requiredFields['city'] = I18N::t('page.checkout.city', 'City');
        $requiredFields['country'] = I18N::t('page.checkout.country', 'Country');
    }
    
    $requiredSuffix = I18N::t('page.checkout.requiredField', 'is required');
    
    foreach ($requiredFields as $field => $label) {
        $value = trim($_POST[$field] ?? '');
        if ($value === '') {
            $errors[] = $label . ' ' . $requiredSuffix . '.';
            $fieldErrors[$field] = $label . ' ' . $requiredSuffix . '.';
        }
    }
    
    // Validate email if provided
    if (!empty($email) && !isValidEmail($email)) {
        $errors[] = 'Please enter a valid email address.';
        $fieldErrors['email'] = 'Please enter a valid email address.';
    }
    
    // Additional validation for account registration
    if ($registerAccount) {
        // Email is required for registration
        if (empty($email)) {
            $errors[] = I18N::t('page.checkout.email', 'Email') . ' ' . $requiredSuffix . '.';
            $fieldErrors['email'] = I18N::t('page.checkout.email', 'Email') . ' ' . $requiredSuffix . '.';
        } elseif (!isValidEmail($email)) {
            $errors[] = 'Please enter a valid email address.';
            $fieldErrors['email'] = 'Please enter a valid email address.';
        } else {
            // Check if email already exists
            $customers = getCustomers();
            if (isset($customers[$email])) {
                $errors[] = I18N::t('page.checkout.emailExists', 'An account with this email already exists');
                $fieldErrors['email'] = I18N::t('page.checkout.emailExists', 'An account with this email already exists');
            }
        }
        
        // Password validation
        if (empty($password)) {
            $errors[] = I18N::t('page.checkout.password', 'Password') . ' ' . $requiredSuffix . '.';
            $fieldErrors['password'] = I18N::t('page.checkout.password', 'Password') . ' ' . $requiredSuffix . '.';
        } elseif (strlen($password) < 8) {
            $errors[] = I18N::t('page.checkout.passwordMinLength', 'Password must be at least 8 characters');
            $fieldErrors['password'] = I18N::t('page.checkout.passwordMinLength', 'Password must be at least 8 characters');
        }
    }
    
    // Validate consent checkbox (TASK 6 - MANDATORY)
    if (empty($_POST['consent_checkbox'])) {
        $errors[] = I18N::t('page.checkout.consentError', 'You must accept the Terms and Conditions and Privacy Policy to place an order.');
        $fieldErrors['consent_checkbox'] = I18N::t('page.checkout.consentError', 'You must accept the Terms and Conditions and Privacy Policy to place an order.');
    }
    
    // Validate pickup branch if pickup is selected
    if ($isPickup) {
        if (empty($pickupBranchId) || !isset($activeBranches[$pickupBranchId])) {
            $errors[] = I18N::t('page.checkout.selectBranch', 'Select branch for pickup');
        } else {
            // Check branch stock for all items
            $branchStockErrors = checkBranchStockForCart($pickupBranchId, $cart);
            if (!empty($branchStockErrors)) {
                // FIX: TASK 2 - Get branch name for detailed error messages
                $branchName = $activeBranches[$pickupBranchId]['name'] ?? 'selected branch';
                $errors[] = I18N::t('page.checkout.branchStockError', 'Some items are not available for pickup at this branch');
                
                // FIX: TASK 2 - Detailed error messages for each missing item
                foreach ($branchStockErrors as $stockError) {
                    if (!empty($stockError['is_gift_set_component'])) {
                        // FIX: TASK 2 UPDATE - Gift set component error - show product name instead of SKU
                        $componentName = $stockError['component_name'] ?? $stockError['sku'];
                        $errors[] = sprintf(
                            I18N::t('page.checkout.giftSetComponentMissing', '%s - Component: %s: required %d, available %d at %s'),
                            htmlspecialchars($stockError['name']),
                            htmlspecialchars($componentName),
                            (int)$stockError['requested'],
                            (int)$stockError['available'],
                            htmlspecialchars($branchName)
                        );
                    } else {
                        // FIX: TASK 2 UPDATE - Regular product error - use detailed product name
                        $displayName = $stockError['detailed_name'] ?? $stockError['name'];
                        $errors[] = sprintf(
                            I18N::t('page.checkout.branchStockItemError', '%s: required %d, available %d at %s'),
                            htmlspecialchars($displayName),
                            (int)$stockError['requested'],
                            (int)$stockError['available'],
                            htmlspecialchars($branchName)
                        );
                    }
                }
            }
        }
    }
    
    // Check stock for all items (global stock for non-pickup orders)
    if (!$isPickup) {
        $stock = loadJSON('stock.json');
        $stockErrors = [];
        foreach ($cart as $item) {
            $sku = $item['sku'] ?? '';
            $qty = $item['quantity'] ?? 1;
            $productName = $item['name'] ?? $sku;
            $category = $item['category'] ?? '';
            
            // Special handling for gift sets
            if ($category === 'gift_sets') {
                // Get gift set items from meta or items field
                $giftSetItems = $item['meta']['gift_set_items'] ?? $item['items'] ?? [];
                
                if (!empty($giftSetItems)) {
                    // Evaluate gift set stock
                    $giftSetStock = evaluateGiftSetStock($giftSetItems);
                    
                    if (!$giftSetStock['ok']) {
                        // FIX: TASK 2 UPDATE - Add error for gift set with component product name
                        foreach ($giftSetStock['missing'] as $missingSku => $missingQty) {
                            $requiredQty = $giftSetStock['required'][$missingSku] ?? 0;
                            $availableQty = $giftSetStock['available'][$missingSku] ?? 0;
                            $componentName = $giftSetStock['product_names'][$missingSku] ?? $missingSku;
                            
                            $stockErrors[] = [
                                'name' => $productName,
                                'sku' => $missingSku,
                                'component_name' => $componentName, // FIX: TASK 2 UPDATE - Add component product name
                                'requested' => $requiredQty,
                                'available' => $availableQty,
                                'error' => 'gift_set_insufficient'
                            ];
                        }
                    }
                } else {
                    // Gift set without items data - treat as error
                    $stockErrors[] = [
                        'name' => $productName,
                        'requested' => $qty,
                        'available' => 0,
                        'error' => 'gift_set_no_data'
                    ];
                }
            } else {
                // Normal product stock check
                // FIX: TASK 2 UPDATE - Build detailed product description for all products
                $detailedProductName = buildProductDescription($sku, $stock[$sku] ?? null);
                
                // Check if SKU exists in stock
                if (!isset($stock[$sku])) {
                    $stockErrors[] = [
                        'name' => $productName,
                        'detailed_name' => $detailedProductName,
                        'requested' => $qty,
                        'available' => 0,
                        'error' => 'not_found'
                    ];
                    error_log("CHECKOUT VALIDATION: SKU '$sku' not found in stock.json for item: $productName");
                } elseif ($stock[$sku]['quantity'] < $qty) {
                    $stockErrors[] = [
                        'name' => $productName,
                        'detailed_name' => $detailedProductName,
                        'requested' => $qty,
                        'available' => $stock[$sku]['quantity'],
                        'error' => 'insufficient'
                    ];
                }
            }
        }
        
        // Add stock error messages
        if (!empty($stockErrors)) {
            $errors[] = I18N::t('page.checkout.stockError', 'Some items are not available in requested quantity');
            foreach ($stockErrors as $stockError) {
                if ($stockError['error'] === 'not_found') {
                    // FIX: TASK 2 UPDATE - Use detailed product name for all products
                    $displayName = $stockError['detailed_name'] ?? $stockError['name'];
                    $errors[] = sprintf(
                        I18N::t('page.checkout.stockNotFound', '%s: not available'),
                        htmlspecialchars($displayName)
                    );
                } elseif ($stockError['error'] === 'gift_set_insufficient') {
                    // FIX: TASK 2 UPDATE - Detailed error message with product name for gift set components
                    $componentName = $stockError['component_name'] ?? $stockError['sku'];
                    $errors[] = sprintf(
                        I18N::t('page.checkout.giftSetComponentMissingDelivery', '%s - Component: %s: required %d, available %d for delivery'),
                        htmlspecialchars($stockError['name']),
                        htmlspecialchars($componentName),
                        $stockError['requested'],
                        $stockError['available']
                    );
                } elseif ($stockError['error'] === 'gift_set_no_data') {
                    $errors[] = sprintf(
                        I18N::t('page.checkout.stockNotFound', '%s: not available'),
                        htmlspecialchars($stockError['name'])
                    );
                } else {
                    // FIX: TASK 2 UPDATE - Use detailed product name for all products
                    $displayName = $stockError['detailed_name'] ?? $stockError['name'];
                    $errors[] = sprintf(
                        I18N::t('page.checkout.stockInsufficient', '%s: %d requested, %d available'),
                        htmlspecialchars($displayName),
                        $stockError['requested'],
                        $stockError['available']
                    );
                }
            }
        }
    }
    
    if (empty($errors)) {
        // Handle account registration if requested
        if ($registerAccount && !$isLoggedIn) {
            // FIX: TASK 4 - Get newsletter opt-in value
            $newsletterOptIn = !empty($_POST['newsletter_opt_in']);
            
            // Create new customer account
            $customers = getCustomers();
            $customerId = uniqid('cust_', true);
            
            $customers[$email] = [
                'id' => $customerId,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'salutation' => '',
                'first_name' => sanitize($firstName),
                'last_name' => sanitize($lastName),
                'phone' => sanitize($phone),
                'shipping_address' => [
                    'street' => sanitize($street),
                    'house_number' => sanitize($house),
                    'zip' => sanitize($zip),
                    'city' => sanitize($city),
                    'country' => sanitize($country)
                ],
                'billing_address' => [
                    'street' => '',
                    'house_number' => '',
                    'zip' => '',
                    'city' => '',
                    'country' => ''
                ],
                // FIX: TASK 4 - Add newsletter opt-in fields
                'newsletter_opt_in' => $newsletterOptIn ? 1 : 0,
                'newsletter_opt_in_at' => $newsletterOptIn ? date('c') : null,
                'created_at' => date('c')
            ];
            
            if (saveCustomers($customers)) {
                // Auto-login after registration
                $_SESSION['customer'] = $customers[$email];
                $isLoggedIn = true;
                error_log("CHECKOUT: Account created and logged in for customer ID: $customerId");
            } else {
                $errors[] = I18N::t('account.error.registrationFailed', 'Registration failed. Please try again.');
                error_log("CHECKOUT: Failed to save customer account");
            }
        }
        
        if (empty($errors)) {
            // Calculate shipping with proper rounding
            $shippingCost = 0.0;
            if (!$isPickup) {
                $shippingCost = round(calculateShippingForTotal($cartTotal), 2);
            }
            // Ensure all values are properly rounded to 2 decimals
            $subtotal = round($cartTotal, 2);
            $orderTotal = round($subtotal + $shippingCost, 2);
            
            // Create order
            $orderId = generateOrderId();
            
            // Get customer ID if logged in (could be newly registered or already logged in)
            $customerId = isCustomerLoggedIn() ? getCurrentCustomerId() : null;
        
        // Determine payment method
        $paymentMethod = sanitize($_POST['payment']);
        
        // Build order array
        $order = [
            'id' => $orderId,
            'customer_id' => $customerId,
            'date' => date('Y-m-d H:i:s'),
            'created_at' => date('c'),
            'status' => 'pending',
            'language' => $currentLang,
            'customer' => [
                'first_name' => sanitize($firstName),
                'last_name' => sanitize($lastName),
                'email' => sanitize($email),
                'phone' => sanitize($phone)
            ],
            'shipping' => [
                'street' => sanitize($street),
                'house' => sanitize($house),
                'zip' => sanitize($zip),
                'city' => sanitize($city),
                'country' => sanitize($country)
            ],
            'billing' => [],
            'comment' => sanitize($_POST['comment'] ?? ''),
            'payment_method' => $paymentMethod,
            'items' => $cart,
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'total' => $orderTotal,
            'pickup_in_branch' => $isPickup,
            'pickup_branch_id' => $pickupBranchId
        ];
        
        // Handle billing address
        if (empty($_POST['same_as_shipping'])) {
            $order['billing'] = [
                'street' => sanitize($_POST['billing_street'] ?? ''),
                'house' => sanitize($_POST['billing_house'] ?? ''),
                'zip' => sanitize($_POST['billing_zip'] ?? ''),
                'city' => sanitize($_POST['billing_city'] ?? ''),
                'country' => sanitize($_POST['billing_country'] ?? '')
            ];
        } else {
            $order['billing'] = $order['shipping'];
        }
        
        // Handle payment method-specific logic
        $paymentUrl = null;
        
        if ($paymentMethod === 'twint' || $paymentMethod === 'card') {
            // For online payments, initiate payment BEFORE saving order
            require_once __DIR__ . '/includes/payrexx.php';
            $paymentResult = createPayrexxPayment($order);
            
            if (!$paymentResult['success']) {
                // Payment initiation failed - show error and do not save order
                $errors[] = I18N::t('page.checkout.paymentInitFailed', 'Payment initiation failed. Please try again or contact support.');
                if (!empty($paymentResult['error'])) {
                    error_log("Payment initiation failed for order $orderId: " . $paymentResult['error']);
                }
            } else {
                // Payment initiation succeeded
                $paymentUrl = $paymentResult['paymentUrl'];
                $order['status'] = 'pending_payment';
                $order['payment_status'] = 'pending';
            }
        } elseif ($paymentMethod === 'cash' && $isPickup) {
            // Cash payment at pickup
            $order['status'] = 'awaiting_cash_pickup';
            $order['payment_status'] = 'unpaid';
        } else {
            // Invalid payment method
            error_log("CHECKOUT: Invalid payment method selected: " . $paymentMethod);
            $errors[] = I18N::t('page.checkout.invalidPayment', 'Invalid payment method selected.');
        }
        
        // Only proceed if there are no errors
        if (empty($errors)) {
            // Save order
            $orders = loadJSON('orders.json');
            if (!is_array($orders)) {
                $orders = [];
            }
            $orders[$orderId] = $order;
            
            if (!saveJSON('orders.json', $orders)) {
                $errors[] = 'Could not save your order. Please try again.';
            }
        }
        
        // Process order if successfully saved
        if (empty($errors)) {
            // For cash orders, decrease stock and send confirmation emails immediately
            // For online payments, these will be handled by the webhook after payment confirmation
            if ($paymentMethod === 'cash' && $isPickup) {
                // Decrease stock
                $stockErrors = [];
                // Debug logging for Limited Edition stock issue investigation
                // NOTE: This logging can be removed once the issue is confirmed resolved
                error_log("CHECKOUT: Starting stock deduction for order $orderId with " . count($cart) . " items");
            
            foreach ($cart as $item) {
                $sku = $item['sku'] ?? '';
                $qty = $item['quantity'] ?? 1;
                $productId = $item['productId'] ?? '';
                $productName = $item['name'] ?? $sku;  // Use SKU as fallback for better debugging
                
                // Debug logging - can be removed after issue investigation
                error_log("CHECKOUT: Processing item - ProductID: $productId, SKU: $sku, Name: $productName, Qty: $qty");
                
                $stockDecreaseSuccess = false;
                $category = $item['category'] ?? '';
                
                // Special handling for gift sets - expand to underlying SKUs
                if ($category === 'gift_sets') {
                    error_log("CHECKOUT: Gift set detected - expanding to underlying SKUs");
                    $giftSetItems = $item['meta']['gift_set_items'] ?? $item['items'] ?? [];
                    
                    if (!empty($giftSetItems)) {
                        $skuMap = expandGiftSetToSkuMap($giftSetItems);
                        error_log("CHECKOUT: Gift set expanded to " . count($skuMap) . " unique SKUs: " . implode(', ', array_keys($skuMap)));
                        
                        // Deduct stock for each SKU in the gift set
                        foreach ($skuMap as $giftSku => $giftQty) {
                            $giftStockDecreaseSuccess = false;
                            
                            if ($isPickup && $pickupBranchId) {
                                // Decrease branch stock for gift set items
                                error_log("CHECKOUT: Gift set - decreasing branch stock - Branch: $pickupBranchId, SKU: $giftSku, Qty: $giftQty");
                                $giftStockDecreaseSuccess = decreaseBranchStock($pickupBranchId, $giftSku, $giftQty);
                                error_log("CHECKOUT: Gift set branch stock decrease result for $giftSku: " . ($giftStockDecreaseSuccess ? 'SUCCESS' : 'FAILED'));
                                
                                if (!$giftStockDecreaseSuccess) {
                                    $errorMsg = "Failed to decrease branch stock for gift set in order $orderId - Branch: $pickupBranchId, SKU: $giftSku, Product: $productName, Qty: $giftQty";
                                    error_log("STOCK ERROR: " . $errorMsg);
                                    $stockErrors[] = $errorMsg;
                                }
                            } else {
                                // Decrease global stock for gift set items
                                error_log("CHECKOUT: Gift set - decreasing global stock - SKU: $giftSku, Qty: $giftQty");
                                $giftStockDecreaseSuccess = decreaseStock($giftSku, $giftQty);
                                error_log("CHECKOUT: Gift set global stock decrease result for $giftSku: " . ($giftStockDecreaseSuccess ? 'SUCCESS' : 'FAILED'));
                                
                                if (!$giftStockDecreaseSuccess) {
                                    $errorMsg = "Failed to decrease global stock for gift set in order $orderId - SKU: $giftSku, Product: $productName, Qty: $giftQty";
                                    error_log("STOCK ERROR: " . $errorMsg);
                                    $stockErrors[] = $errorMsg;
                                }
                            }
                        }
                        
                        // Mark as success if we processed the gift set (errors already logged)
                        $stockDecreaseSuccess = true;
                    } else {
                        error_log("CHECKOUT WARNING: Gift set has no items data - cannot deduct stock");
                        $stockErrors[] = "Gift set '$productName' has no items data - stock not deducted";
                    }
                    
                    // Skip normal product processing for gift sets
                    continue;
                }
                
                if ($isPickup && $pickupBranchId) {
                    // Decrease branch stock for pickup orders
                    // Debug logging - can be removed after issue investigation
                    error_log("CHECKOUT: Attempting to decrease branch stock - Branch: $pickupBranchId, SKU: $sku, Qty: $qty");
                    
                    // DIAGNOSTIC: Read quantity BEFORE decrease
                    $branchStockFilePath = __DIR__ . '/data/branch_stock.json';
                    $rawBeforeContent = file_get_contents($branchStockFilePath);
                    $qtyBeforeDecrease = 'READ_ERROR';
                    if ($rawBeforeContent !== false) {
                        $branchStockBeforeData = json_decode($rawBeforeContent, true);
                        if ($branchStockBeforeData !== null) {
                            $qtyBeforeDecrease = ($branchStockBeforeData[$pickupBranchId][$sku]['quantity'] ?? null) ?? 'NOT_FOUND';
                        }
                    }
                    error_log("CHECKOUT DIAGNOSTIC: BEFORE decreaseBranchStock() - Branch '$pickupBranchId', SKU '$sku' quantity in file: $qtyBeforeDecrease");
                    
                    $stockDecreaseSuccess = decreaseBranchStock($pickupBranchId, $sku, $qty);
                    error_log("CHECKOUT: Branch stock decrease result for $sku: " . ($stockDecreaseSuccess ? 'SUCCESS' : 'FAILED'));
                    
                    // DIAGNOSTIC: Read quantity IMMEDIATELY AFTER decrease - bypass any caching
                    clearstatcache(true, $branchStockFilePath);
                    $rawAfterContent = file_get_contents($branchStockFilePath);
                    $qtyAfterDecrease = 'READ_ERROR';
                    if ($rawAfterContent !== false) {
                        $branchStockAfterData = json_decode($rawAfterContent, true);
                        if ($branchStockAfterData !== null) {
                            $qtyAfterDecrease = ($branchStockAfterData[$pickupBranchId][$sku]['quantity'] ?? null) ?? 'NOT_FOUND';
                        }
                    }
                    $expectedQty = ($qtyBeforeDecrease !== 'NOT_FOUND' && $qtyBeforeDecrease !== 'READ_ERROR') ? ($qtyBeforeDecrease - $qty) : 'UNKNOWN';
                    error_log("CHECKOUT DIAGNOSTIC: AFTER decreaseBranchStock() - Branch '$pickupBranchId', SKU '$sku' quantity in file: $qtyAfterDecrease (expected: $expectedQty)");
                    
                    if ($qtyAfterDecrease !== $expectedQty && $qtyBeforeDecrease !== 'NOT_FOUND' && $qtyBeforeDecrease !== 'READ_ERROR' && $qtyAfterDecrease !== 'READ_ERROR') {
                        error_log("CHECKOUT DIAGNOSTIC ERROR: Quantity mismatch! Before: $qtyBeforeDecrease, After: $qtyAfterDecrease, Expected: $expectedQty");
                        error_log("CHECKOUT DIAGNOSTIC: File path: $branchStockFilePath");
                        $mtime = filemtime($branchStockFilePath);
                        error_log("CHECKOUT DIAGNOSTIC: File modification time: " . ($mtime !== false ? date('Y-m-d H:i:s', $mtime) : 'UNKNOWN'));
                    }
                    
                    if (!$stockDecreaseSuccess) {
                        $errorMsg = "Failed to decrease branch stock for order $orderId - Branch: $pickupBranchId, SKU: $sku, Product: $productName, Qty: $qty";
                        error_log("STOCK ERROR: " . $errorMsg);
                        $stockErrors[] = $errorMsg;
                    }
                } else {
                    // Decrease global stock for delivery orders
                    // Debug logging - can be removed after issue investigation
                    error_log("CHECKOUT: Attempting to decrease global stock - SKU: $sku, Qty: $qty");
                    
                    // DIAGNOSTIC: Read quantity BEFORE decrease
                    $stockFilePath = __DIR__ . '/data/stock.json';
                    $rawBeforeContent = file_get_contents($stockFilePath);
                    $qtyBeforeDecrease = 'READ_ERROR';
                    if ($rawBeforeContent !== false) {
                        $stockBeforeData = json_decode($rawBeforeContent, true);
                        if ($stockBeforeData !== null) {
                            $qtyBeforeDecrease = ($stockBeforeData[$sku]['quantity'] ?? null) ?? 'NOT_FOUND';
                        }
                    }
                    error_log("CHECKOUT DIAGNOSTIC: BEFORE decreaseStock() - SKU '$sku' quantity in file: $qtyBeforeDecrease");
                    
                    $stockDecreaseSuccess = decreaseStock($sku, $qty);
                    error_log("CHECKOUT: Global stock decrease result for $sku: " . ($stockDecreaseSuccess ? 'SUCCESS' : 'FAILED'));
                    
                    // DIAGNOSTIC: Read quantity IMMEDIATELY AFTER decrease - bypass any caching
                    clearstatcache(true, $stockFilePath);
                    $rawAfterContent = file_get_contents($stockFilePath);
                    $qtyAfterDecrease = 'READ_ERROR';
                    if ($rawAfterContent !== false) {
                        $stockAfterData = json_decode($rawAfterContent, true);
                        if ($stockAfterData !== null) {
                            $qtyAfterDecrease = ($stockAfterData[$sku]['quantity'] ?? null) ?? 'NOT_FOUND';
                        }
                    }
                    $expectedQty = ($qtyBeforeDecrease !== 'NOT_FOUND' && $qtyBeforeDecrease !== 'READ_ERROR') ? ($qtyBeforeDecrease - $qty) : 'UNKNOWN';
                    error_log("CHECKOUT DIAGNOSTIC: AFTER decreaseStock() - SKU '$sku' quantity in file: $qtyAfterDecrease (expected: $expectedQty)");
                    
                    if ($qtyAfterDecrease !== $expectedQty && $qtyBeforeDecrease !== 'NOT_FOUND' && $qtyBeforeDecrease !== 'READ_ERROR' && $qtyAfterDecrease !== 'READ_ERROR') {
                        error_log("CHECKOUT DIAGNOSTIC ERROR: Quantity mismatch! Before: $qtyBeforeDecrease, After: $qtyAfterDecrease, Expected: $expectedQty");
                        error_log("CHECKOUT DIAGNOSTIC: File path: $stockFilePath");
                        $mtime = filemtime($stockFilePath);
                        error_log("CHECKOUT DIAGNOSTIC: File modification time: " . ($mtime !== false ? date('Y-m-d H:i:s', $mtime) : 'UNKNOWN'));
                    }
                    
                    if (!$stockDecreaseSuccess) {
                        $errorMsg = "Failed to decrease global stock for order $orderId - SKU: $sku, Product: $productName, Qty: $qty";
                        error_log("STOCK ERROR: " . $errorMsg);
                        $stockErrors[] = $errorMsg;
                    }
                }
            }
            
            // Log stock errors summary if any occurred
            if (!empty($stockErrors)) {
                error_log("STOCK DEDUCTION SUMMARY for order $orderId: " . count($stockErrors) . " items failed stock deduction");
            }
            
            // POST-CHECKOUT VERIFICATION: Read back stock.json to verify quantities were persisted
            error_log("=== POST-CHECKOUT VERIFICATION START ===");
            error_log("POST-CHECKOUT: Order $orderId completed, verifying stock quantities were saved...");
            $verifyStock = loadJSON('stock.json');
            foreach ($cart as $item) {
                $sku = $item['sku'] ?? '';
                if ($sku && !$isPickup) { // Only verify global stock for delivery orders
                    $currentQty = $verifyStock[$sku]['quantity'] ?? 'NOT_FOUND';
                    error_log("POST-CHECKOUT: SKU '$sku' quantity in stock.json: $currentQty");
                }
            }
            if ($isPickup && $pickupBranchId) {
                $verifyBranchStock = loadJSON('branch_stock.json');
                foreach ($cart as $item) {
                    $sku = $item['sku'] ?? '';
                    if ($sku) {
                        $currentQty = $verifyBranchStock[$pickupBranchId][$sku]['quantity'] ?? 'NOT_FOUND';
                        error_log("POST-CHECKOUT: Branch '$pickupBranchId', SKU '$sku' quantity in branch_stock.json: $currentQty");
                    }
                }
            }
            error_log("=== POST-CHECKOUT VERIFICATION END ===");
                
                // Send confirmation emails for cash orders (don't block order completion if emails fail)
                try {
                    // Send order confirmation email
                    sendOrderConfirmationEmail($order);
                } catch (Exception $e) {
                    error_log("Failed to send order confirmation email for order $orderId: " . $e->getMessage());
                }
                
                try {
                    // Send new order notification to admin
                    sendNewOrderNotification($order);
                } catch (Exception $e) {
                    error_log("Failed to send new order notification for order $orderId: " . $e->getMessage());
                }
                
                // Show success page for cash orders
                $success = true;
            }
            
            // Clear cart for all successful orders
            clearCart();
            
            // Handle payment redirect for online payments
            if ($paymentMethod === 'twint' || $paymentMethod === 'card') {
                if (!empty($paymentUrl)) {
                    // Redirect to Payrexx payment page
                    header('Location: ' . $paymentUrl);
                    exit;
                } else {
                    // This should never happen - indicates a logic error in the flow
                    $criticalError = "CRITICAL: Payment URL missing for order $orderId after successful payment initiation";
                    error_log($criticalError);
                    $errors[] = I18N::t('page.checkout.paymentInitFailed', 'Payment initiation failed. Please try again or contact support.');
                    // In production, this should trigger an alert/notification to developers
                }
            }
        }
        }
    }
}

// Calculate shipping cost for display
$displayShippingCost = calculateShippingForTotal($cartTotal);
$displayTotal = $cartTotal + $displayShippingCost;

include __DIR__ . '/includes/header.php';
?>

<section class="checkout-section">
    <div class="container">
        <?php if ($success): ?>
            <div class="text-center" style="max-width: 600px; margin: 0 auto; padding: 4rem 2rem;">
                <h1><?php echo I18N::t('page.checkout.orderSuccess', 'Thank you for your order!'); ?></h1>
                <p style="font-size: 1.2rem; margin: 2rem 0;">
                    <?php echo str_replace('{orderId}', $orderId, I18N::t('page.checkout.orderConfirmation', 'Order #{orderId} has been placed successfully. You will receive a confirmation email shortly.')); ?>
                </p>
                
                <?php if (isset($order) && ($order['payment_method'] ?? '') === 'cash'): ?>
                    <div style="background: #fff8e1; border: 1px solid #ff9800; padding: 1.5rem; border-radius: 8px; margin: 2rem 0;">
                        <p style="margin: 0; color: #e65100; font-weight: 600;">
                            <?php echo I18N::t('page.checkout.cashPaymentReminder', 'Please bring cash payment when you pick up your order at the selected branch.'); ?>
                        </p>
                    </div>
                <?php endif; ?>
                
                <a href="catalog.php?lang=<?php echo $currentLang; ?>" class="btn btn--gold">
                    <?php echo I18N::t('common.continueShopping', 'Continue shopping'); ?>
                </a>
            </div>
            <script>
                // Clear localStorage cart immediately after successful order
                localStorage.setItem('nichehome_cart', JSON.stringify([]));
                // Sync empty cart to server
                fetch('add_to_cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'sync', cart: [] })
                });
                // Update cart count display
                document.querySelectorAll('[data-cart-count]').forEach(el => {
                    el.textContent = '0';
                    el.style.display = 'none';
                });
            </script>
        <?php else: ?>
            <h1 class="text-center mb-4"><?php echo I18N::t('page.checkout.title', 'Checkout'); ?></h1>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert--error">
                    <ul style="list-style: disc; padding-left: 1.5rem;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="checkout-grid">
                <form method="post" action="" class="checkout-form" data-checkout-form>
                    <!-- Pickup Option -->
                    <?php if (!empty($activeBranches)): ?>
                    <div class="form-section" style="background: var(--color-sand); padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <div class="form-checkbox">
                            <input type="checkbox" 
                                   name="pickup_in_branch" 
                                   id="pickup_in_branch" 
                                   value="1"
                                   <?php echo $isPickup ? 'checked' : ''; ?>
                                   onchange="togglePickupSection()">
                            <label for="pickup_in_branch" style="font-weight: 600;">
                                <?php echo I18N::t('page.checkout.pickupInBranch', 'Pickup in branch'); ?>
                            </label>
                        </div>
                        
                        <div id="pickup-branch-section" style="<?php echo $isPickup ? '' : 'display: none;'; ?> margin-top: 1rem;">
                            <label><?php echo I18N::t('page.checkout.selectBranch', 'Select branch for pickup'); ?></label>
                            <select name="pickup_branch_id" id="pickup_branch_id" style="width: 100%; padding: 0.5rem; margin-top: 0.5rem;">
                                <option value="">-- <?php echo I18N::t('page.checkout.selectBranch', 'Select branch'); ?> --</option>
                                <?php foreach ($activeBranches as $branchId => $branch): ?>
                                    <option value="<?php echo htmlspecialchars($branchId); ?>" <?php echo $pickupBranchId === $branchId ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($branch['name'] ?? $branchId); ?> - <?php echo htmlspecialchars($branch['address'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">
                                <?php echo I18N::t('page.checkout.pickupNote', 'When you select pickup, shipping is free'); ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Shipping Address -->
                    <div class="form-section">
                        <h3 class="form-section__title"><?php echo I18N::t('page.checkout.shipping', 'Shipping Address'); ?></h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><?php echo I18N::t('page.checkout.firstName', 'First name'); ?> *</label>
                                <input type="text" 
                                       name="first_name" 
                                       required 
                                       class="<?php echo isset($fieldErrors['first_name']) ? 'form-control--error' : ''; ?>"
                                       value="<?php echo htmlspecialchars($firstName); ?>">
                                <?php if (isset($fieldErrors['first_name'])): ?>
                                    <div class="form-error"><?php echo htmlspecialchars($fieldErrors['first_name']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label><?php echo I18N::t('page.checkout.lastName', 'Last name'); ?> *</label>
                                <input type="text" 
                                       name="last_name" 
                                       required 
                                       class="<?php echo isset($fieldErrors['last_name']) ? 'form-control--error' : ''; ?>"
                                       value="<?php echo htmlspecialchars($lastName); ?>">
                                <?php if (isset($fieldErrors['last_name'])): ?>
                                    <div class="form-error"><?php echo htmlspecialchars($fieldErrors['last_name']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>
                                    <?php echo I18N::t('page.checkout.email', 'Email'); ?>
                                    <span class="required-asterisk" data-field="email"></span>
                                </label>
                                <input type="email" 
                                       name="email" 
                                       class="<?php echo isset($fieldErrors['email']) ? 'form-control--error' : ''; ?>"
                                       value="<?php echo htmlspecialchars($email); ?>">
                                <?php if (isset($fieldErrors['email'])): ?>
                                    <div class="form-error"><?php echo htmlspecialchars($fieldErrors['email']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label><?php echo I18N::t('page.checkout.phone', 'Phone'); ?> *</label>
                                <input type="tel" 
                                       name="phone" 
                                       required 
                                       class="<?php echo isset($fieldErrors['phone']) ? 'form-control--error' : ''; ?>"
                                       value="<?php echo htmlspecialchars($phone); ?>">
                                <?php if (isset($fieldErrors['phone'])): ?>
                                    <div class="form-error"><?php echo htmlspecialchars($fieldErrors['phone']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>
                                    <?php echo I18N::t('page.checkout.street', 'Street'); ?>
                                    <span class="required-asterisk" data-field="street"></span>
                                </label>
                                <input type="text" 
                                       name="street" 
                                       class="<?php echo isset($fieldErrors['street']) ? 'form-control--error' : ''; ?>"
                                       value="<?php echo htmlspecialchars($street); ?>">
                                <?php if (isset($fieldErrors['street'])): ?>
                                    <div class="form-error"><?php echo htmlspecialchars($fieldErrors['street']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label>
                                    <?php echo I18N::t('page.checkout.houseNumber', 'House number'); ?>
                                    <span class="required-asterisk" data-field="house"></span>
                                </label>
                                <input type="text" 
                                       name="house" 
                                       class="<?php echo isset($fieldErrors['house']) ? 'form-control--error' : ''; ?>"
                                       value="<?php echo htmlspecialchars($house); ?>">
                                <?php if (isset($fieldErrors['house'])): ?>
                                    <div class="form-error"><?php echo htmlspecialchars($fieldErrors['house']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>
                                    <?php echo I18N::t('page.checkout.zip', 'ZIP code'); ?>
                                    <span class="required-asterisk" data-field="zip"></span>
                                </label>
                                <input type="text" 
                                       name="zip" 
                                       class="<?php echo isset($fieldErrors['zip']) ? 'form-control--error' : ''; ?>"
                                       value="<?php echo htmlspecialchars($zip); ?>">
                                <?php if (isset($fieldErrors['zip'])): ?>
                                    <div class="form-error"><?php echo htmlspecialchars($fieldErrors['zip']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label>
                                    <?php echo I18N::t('page.checkout.city', 'City'); ?>
                                    <span class="required-asterisk" data-field="city"></span>
                                </label>
                                <input type="text" 
                                       name="city" 
                                       class="<?php echo isset($fieldErrors['city']) ? 'form-control--error' : ''; ?>"
                                       value="<?php echo htmlspecialchars($city); ?>">
                                <?php if (isset($fieldErrors['city'])): ?>
                                    <div class="form-error"><?php echo htmlspecialchars($fieldErrors['city']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <?php echo I18N::t('page.checkout.country', 'Country'); ?>
                                <span class="required-asterisk" data-field="country"></span>
                            </label>
                            <select name="country" 
                                    class="<?php echo isset($fieldErrors['country']) ? 'form-control--error' : ''; ?>">
                                <option value="Switzerland" <?php echo $country === 'Switzerland' ? 'selected' : ''; ?>>Switzerland</option>
                                <option value="Germany" <?php echo $country === 'Germany' ? 'selected' : ''; ?>>Germany</option>
                                <option value="Austria" <?php echo $country === 'Austria' ? 'selected' : ''; ?>>Austria</option>
                                <option value="France" <?php echo $country === 'France' ? 'selected' : ''; ?>>France</option>
                                <option value="Italy" <?php echo $country === 'Italy' ? 'selected' : ''; ?>>Italy</option>
                            </select>
                            <?php if (isset($fieldErrors['country'])): ?>
                                <div class="form-error"><?php echo htmlspecialchars($fieldErrors['country']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Billing Address -->
                    <div class="form-section">
                        <div class="form-checkbox">
                            <input type="checkbox" name="same_as_shipping" id="same_as_shipping" checked data-same-as-shipping>
                            <label for="same_as_shipping"><?php echo I18N::t('page.checkout.sameAsShipping', 'Billing address same as shipping'); ?></label>
                        </div>
                        
                        <div data-billing-section style="display: none;">
                            <h3 class="form-section__title"><?php echo I18N::t('page.checkout.billing', 'Billing Address'); ?></h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label><?php echo I18N::t('page.checkout.street', 'Street'); ?></label>
                                    <input type="text" name="billing_street">
                                </div>
                                <div class="form-group">
                                    <label><?php echo I18N::t('page.checkout.houseNumber', 'House number'); ?></label>
                                    <input type="text" name="billing_house">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label><?php echo I18N::t('page.checkout.zip', 'ZIP code'); ?></label>
                                    <input type="text" name="billing_zip">
                                </div>
                                <div class="form-group">
                                    <label><?php echo I18N::t('page.checkout.city', 'City'); ?></label>
                                    <input type="text" name="billing_city">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label><?php echo I18N::t('page.checkout.country', 'Country'); ?></label>
                                <select name="billing_country">
                                    <option value="Switzerland">Switzerland</option>
                                    <option value="Germany">Germany</option>
                                    <option value="Austria">Austria</option>
                                    <option value="France">France</option>
                                    <option value="Italy">Italy</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Comment -->
                    <div class="form-section">
                        <div class="form-group">
                            <label><?php echo I18N::t('page.checkout.comment', 'Order comment (optional)'); ?></label>
                            <textarea name="comment" rows="3"><?php echo htmlspecialchars($_POST['comment'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Payment Method -->
                    <div class="form-section">
                        <h3 class="form-section__title"><?php echo I18N::t('page.checkout.payment', 'Payment Method'); ?></h3>
                        
                        <div class="payment-methods">
                            <label class="payment-option">
                                <input type="radio" name="payment" value="twint" required checked>
                                <span class="payment-option__content">
                                    <img src="img/onlinepayment.jpg" alt="Credit cards, Apple Pay, Google Pay, Samsung Pay, and TWINT logos">
                                </span>
                            </label>
                            
                            <label class="payment-option payment-option--cash payment-option--disabled" id="cash-payment-option">
                                <input type="radio" name="payment" value="cash" id="cash-payment-radio" disabled>
                                <span class="payment-option__content payment-option__content--column">
                                    <span><?php echo I18N::t('page.checkout.paymentCash', 'Cash payment (Pickup only)'); ?></span>
                                    <small class="payment-option__helper"><?php echo I18N::t('page.checkout.cashPaymentHelper', 'Available only for pickup'); ?></small>
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Register Account Checkbox (Optional, Guest Only) -->
                    <?php if (!$isLoggedIn): ?>
                    <div class="form-section" style="background: var(--color-sand); padding: 1.5rem; border-radius: 8px; margin-top: 1.5rem;">
                        <div class="form-checkbox">
                            <input type="checkbox" 
                                   name="register_account" 
                                   id="register_account" 
                                   value="1"
                                   <?php echo !empty($_POST['register_account']) ? 'checked' : ''; ?>
                                   onchange="togglePasswordField()">
                            <label for="register_account" style="font-weight: 600;">
                                <?php echo I18N::t('page.checkout.registerAccount', 'Register an account automatically'); ?>
                            </label>
                        </div>
                        
                        <div id="password-field-section" style="<?php echo !empty($_POST['register_account']) ? '' : 'display: none;'; ?> margin-top: 1rem;">
                            <div class="form-group">
                                <label><?php echo I18N::t('page.checkout.password', 'Password'); ?> * <small>(<?php echo I18N::t('page.checkout.passwordMinLength', 'at least 8 characters'); ?>)</small></label>
                                <input type="password" 
                                       name="password" 
                                       id="password" 
                                       class="<?php echo isset($fieldErrors['password']) ? 'form-control--error' : ''; ?>"
                                       value="">
                                <?php if (isset($fieldErrors['password'])): ?>
                                    <div class="form-error"><?php echo htmlspecialchars($fieldErrors['password']); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- FIX: TASK 4 - Newsletter opt-in checkbox (only when registering account) -->
                            <div class="form-checkbox" style="margin-top: 1rem;">
                                <input type="checkbox" 
                                       name="newsletter_opt_in" 
                                       id="checkout_newsletter_opt_in" 
                                       value="1"
                                       <?php echo !empty($_POST['newsletter_opt_in']) ? 'checked' : ''; ?>>
                                <label for="checkout_newsletter_opt_in" style="font-size: 0.95rem;">
                                    <?php echo I18N::t('page.checkout.newsletterOptIn', 'I agree to receive newsletters and promotional emails'); ?>
                                </label>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Consent Checkbox (TASK 6 - MANDATORY) -->
                    <div class="form-section" style="background: #f9f9f9; padding: 1.5rem; border-radius: 8px; margin-top: 1.5rem;">
                        <div class="form-checkbox">
                            <input type="checkbox" 
                                   name="consent_checkbox" 
                                   id="consent_checkbox" 
                                   value="1"
                                   class="<?php echo isset($fieldErrors['consent_checkbox']) ? 'form-control--error' : ''; ?>"
                                   <?php echo !empty($_POST['consent_checkbox']) ? 'checked' : ''; ?>>
                            <label for="consent_checkbox" style="font-size: 0.95rem; line-height: 1.6;">
                                <?php
                                $termsLink = '<a href="terms-and-conditions.php?lang=' . $currentLang . '" target="_blank" style="color: var(--color-gold); text-decoration: underline;">' . I18N::t('page.checkout.consentTermsLink', 'Terms and Conditions') . '</a>';
                                $privacyLink = '<a href="privacy-policy.php?lang=' . $currentLang . '" target="_blank" style="color: var(--color-gold); text-decoration: underline;">' . I18N::t('page.checkout.consentPrivacyLink', 'Privacy Policy') . '</a>';
                                
                                $consentText = I18N::t('page.checkout.consentLabel', 'I have read and agree to the {terms} and {privacy}, and consent to the processing of my personal data for order processing purposes.');
                                $consentText = str_replace('{terms}', $termsLink, $consentText);
                                $consentText = str_replace('{privacy}', $privacyLink, $consentText);
                                
                                echo $consentText;
                                ?>
                            </label>
                        </div>
                        <?php if (isset($fieldErrors['consent_checkbox'])): ?>
                            <div class="form-error" style="margin-top: 0.5rem; color: var(--color-error); font-size: 0.9rem;">
                                <?php echo htmlspecialchars($fieldErrors['consent_checkbox']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn btn--gold" style="width: 100%;">
                        <?php echo I18N::t('page.checkout.placeOrder', 'Place Order'); ?>
                    </button>
                </form>
                
                <!-- Order Summary -->
                <div class="order-summary">
                    <h3 class="order-summary__title"><?php echo I18N::t('page.checkout.orderSummary', 'Order Summary'); ?></h3>
                    
                    <?php foreach ($cart as $item): ?>
                        <?php
                        $fragranceName = '';
                        if (!empty($item['fragrance']) && $item['fragrance'] !== 'none') {
                            $fragranceName = I18N::t('fragrance.' . $item['fragrance'] . '.name', ucfirst(str_replace('_', ' ', $item['fragrance'])));
                        }
                        $isGiftSet = ($item['category'] ?? '') === 'gift_sets';
                        ?>
                        <div class="order-summary__item">
                            <div>
                                <div class="order-summary__item-name"><?php echo htmlspecialchars($item['name'] ?? 'Product'); ?> × <?php echo (int)($item['quantity'] ?? 1); ?></div>
                                <?php if ($isGiftSet): ?>
                                    <?php 
                                    // Display gift set contents breakdown
                                    $giftSetItems = $item['meta']['gift_set_items'] ?? $item['items'] ?? [];
                                    if (!empty($giftSetItems)) {
                                        $contentsText = formatGiftSetContents($giftSetItems, $currentLang);
                                        if ($contentsText) {
                                            echo '<div class="order-summary__item-details giftset-contents">' . htmlspecialchars($contentsText) . '</div>';
                                        }
                                    }
                                    ?>
                                <?php else: ?>
                                    <div class="order-summary__item-details">
                                        <?php if (!empty($item['volume']) && $item['volume'] !== 'standard'): ?>
                                            <?php echo htmlspecialchars($item['volume']); ?>
                                        <?php endif; ?>
                                        <?php if ($fragranceName): ?>
                                            • <?php echo htmlspecialchars($fragranceName); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span>CHF <?php echo number_format(($item['price'] ?? 0) * ($item['quantity'] ?? 1), 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="order-summary__item" style="border-top: 1px solid #ddd; margin-top: 1rem; padding-top: 1rem;">
                        <span><?php echo I18N::t('page.checkout.subtotal', 'Subtotal'); ?></span>
                        <span>CHF <?php echo number_format($cartTotal, 2); ?></span>
                    </div>
                    
                    <div class="order-summary__item" id="shipping-row">
                        <span><?php echo I18N::t('page.checkout.shippingCost', 'Shipping'); ?></span>
                        <span id="shipping-cost">
                            <?php if ($displayShippingCost > 0): ?>
                                CHF <?php echo number_format($displayShippingCost, 2); ?>
                            <?php else: ?>
                                <?php echo I18N::t('common.freeShipping', 'Free'); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <div class="order-summary__item" style="font-weight: 700; border-top: 2px solid var(--color-charcoal); margin-top: 1rem; padding-top: 1rem;">
                        <span><?php echo I18N::t('page.checkout.total', 'Total'); ?></span>
                        <span id="order-total">CHF <?php echo number_format($displayTotal, 2); ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
function togglePickupSection() {
    const pickupCheckbox = document.getElementById('pickup_in_branch');
    const pickupSection = document.getElementById('pickup-branch-section');
    const shippingCostEl = document.getElementById('shipping-cost');
    const orderTotalEl = document.getElementById('order-total');
    const cashPaymentOption = document.getElementById('cash-payment-option');
    const cashPaymentRadio = document.getElementById('cash-payment-radio');
    
    const subtotal = <?php echo json_encode($cartTotal); ?>;
    const regularShipping = <?php echo json_encode($displayShippingCost); ?>;
    const freeText = <?php echo json_encode(I18N::t('common.freeShipping', 'Free')); ?>;
    
    if (pickupCheckbox && pickupCheckbox.checked) {
        if (pickupSection) pickupSection.style.display = 'block';
        if (shippingCostEl) shippingCostEl.textContent = freeText;
        if (orderTotalEl) orderTotalEl.textContent = 'CHF ' + subtotal.toFixed(2);
        
        // Enable cash payment option when pickup is selected
        if (cashPaymentOption) {
            cashPaymentOption.classList.remove('payment-option--disabled');
        }
        if (cashPaymentRadio) {
            cashPaymentRadio.disabled = false;
        }
    } else {
        if (pickupSection) pickupSection.style.display = 'none';
        if (shippingCostEl) {
            if (regularShipping > 0) {
                shippingCostEl.textContent = 'CHF ' + regularShipping.toFixed(2);
            } else {
                shippingCostEl.textContent = freeText;
            }
        }
        if (orderTotalEl) orderTotalEl.textContent = 'CHF ' + (subtotal + regularShipping).toFixed(2);
        
        // Disable cash payment option and deselect it when pickup is not selected
        if (cashPaymentOption) {
            cashPaymentOption.classList.add('payment-option--disabled');
        }
        if (cashPaymentRadio) {
            cashPaymentRadio.disabled = true;
            if (cashPaymentRadio.checked) {
                // Select online payment as default if cash was selected
                const onlineRadio = document.querySelector('input[name="payment"][value="twint"]');
                if (onlineRadio) onlineRadio.checked = true;
            }
        }
    }
}

function togglePasswordField() {
    const registerCheckbox = document.getElementById('register_account');
    const passwordSection = document.getElementById('password-field-section');
    const passwordField = document.getElementById('password');
    const pickupCheckbox = document.getElementById('pickup_in_branch');
    
    if (registerCheckbox && registerCheckbox.checked) {
        if (passwordSection) passwordSection.style.display = 'block';
        if (passwordField) passwordField.required = true;
        updateFieldRequirements();
    } else {
        if (passwordSection) passwordSection.style.display = 'none';
        if (passwordField) {
            passwordField.required = false;
            passwordField.value = ''; // Clear password when unchecked
        }
        updateFieldRequirements();
    }
}

function updateFieldRequirements() {
    const pickupCheckbox = document.getElementById('pickup_in_branch');
    const registerCheckbox = document.getElementById('register_account');
    const isPickup = pickupCheckbox && pickupCheckbox.checked;
    const isRegister = registerCheckbox && registerCheckbox.checked;
    
    // Fields that may be conditionally required
    const emailField = document.querySelector('input[name="email"]');
    const streetField = document.querySelector('input[name="street"]');
    const houseField = document.querySelector('input[name="house"]');
    const zipField = document.querySelector('input[name="zip"]');
    const cityField = document.querySelector('input[name="city"]');
    const countryField = document.querySelector('select[name="country"]');
    
    // For guest + pickup + no registration: only name and phone required
    // For all other cases: email and address required
    const addressRequired = !isPickup || isRegister;
    
    if (emailField) emailField.required = addressRequired;
    if (streetField) streetField.required = addressRequired;
    if (houseField) houseField.required = addressRequired;
    if (zipField) zipField.required = addressRequired;
    if (cityField) cityField.required = addressRequired;
    if (countryField) countryField.required = addressRequired;
    
    // Update asterisks dynamically
    const fieldNamesToUpdate = ['email', 'street', 'house', 'zip', 'city', 'country'];
    fieldNamesToUpdate.forEach(fieldName => {
        const asterisk = document.querySelector(`span.required-asterisk[data-field="${fieldName}"]`);
        if (asterisk) {
            asterisk.textContent = addressRequired ? ' *' : '';
        }
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    togglePickupSection();
    updateFieldRequirements();
    
    // Add event listener for pickup checkbox
    const pickupCheckbox = document.getElementById('pickup_in_branch');
    if (pickupCheckbox) {
        pickupCheckbox.addEventListener('change', updateFieldRequirements);
    }
    
    // Set custom validation message for consent checkbox
    const consentCheckbox = document.getElementById('consent_checkbox');
    if (consentCheckbox) {
        const validationMessage = <?php echo json_encode(I18N::t('validation.acceptTermsTooltip', 'To continue, please check this checkbox.')); ?>;
        
        consentCheckbox.addEventListener('invalid', function() {
            this.setCustomValidity(validationMessage);
        });
        
        consentCheckbox.addEventListener('change', function() {
            this.setCustomValidity('');
        });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

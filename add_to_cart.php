<?php
/**
 * Add to Cart AJAX Endpoint
 * Syncs JavaScript cart to PHP session
 */

require_once __DIR__ . '/init.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$action = $input['action'] ?? 'add';

header('Content-Type: application/json');

switch ($action) {
    case 'add':
        if (!isset($input['item']) || !is_array($input['item'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing item data']);
            exit;
        }
        
        $item = $input['item'];
        
        // Validate required fields
        if (empty($item['sku']) || empty($item['name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        
        // Get requested quantity (stock check moved to checkout)
        $sku = sanitize($item['sku']);
        $requestedQty = intval($item['quantity'] ?? 1);
        $category = sanitize($item['category'] ?? '');
        
        // For gift sets, validate and recalculate price server-side; for other products, get price from products.json
        if ($category === 'gift_sets') {
            // Gift set: validate structure and recalculate price
            $giftSetItems = $item['gift_set_items'] ?? $item['items'] ?? [];
            
            // Enforce 3-item rule for gift sets
            if (empty($giftSetItems) || !is_array($giftSetItems) || count($giftSetItems) !== 3) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'GIFTSET_INCOMPLETE',
                    'code' => 'GIFTSET_INCOMPLETE',
                    'message' => 'Gift Set requires 3 fully configured items.'
                ]);
                exit;
            }
            
            // Validate and recalculate price for each slot
            $calculatedTotal = 0;
            $validationErrors = [];
            
            // Load catalogs for validation
            $products = loadJSON('products.json');
            $accessories = loadJSON('accessories.json');
            
            foreach ($giftSetItems as $slotIndex => $slotItem) {
                $slotNumber = $slotItem['slot'] ?? ($slotIndex + 1);
                
                // Validate required fields
                if (empty($slotItem['productId'])) {
                    $validationErrors[] = [
                        'slot' => $slotNumber,
                        'field' => 'product',
                        'message' => 'Product not selected'
                    ];
                    continue;
                }
                
                $productId = sanitize($slotItem['productId']);
                $slotCategory = sanitize($slotItem['category'] ?? '');
                $variant = sanitize($slotItem['variant'] ?? 'standard');
                $fragrance = sanitize($slotItem['fragrance'] ?? 'NA');
                
                // Validate variant if needed
                if (empty($variant)) {
                    $validationErrors[] = [
                        'slot' => $slotNumber,
                        'field' => 'variant',
                        'message' => 'Variant not selected'
                    ];
                    continue;
                }
                
                // Validate fragrance requirement
                // Check if product requires fragrance and validate it
                $productData = null;
                if ($slotCategory === 'accessories' && isset($accessories[$productId])) {
                    $productData = $accessories[$productId];
                } elseif (isset($products[$productId])) {
                    $productData = $products[$productId];
                }
                
                if ($productData) {
                    $allowedFragrances = $productData['allowed_fragrances'] ?? [];
                    $requiresFragrance = !empty($allowedFragrances);
                    
                    if ($requiresFragrance) {
                        // Fragrance is required - must be valid
                        if (empty($fragrance) || $fragrance === 'NA' || $fragrance === 'none') {
                            $validationErrors[] = [
                                'slot' => $slotNumber,
                                'field' => 'fragrance',
                                'message' => 'Fragrance is required for this product'
                            ];
                            continue;
                        }
                        
                        // Validate fragrance is in allowed list
                        if (!in_array($fragrance, $allowedFragrances)) {
                            $validationErrors[] = [
                                'slot' => $slotNumber,
                                'field' => 'fragrance',
                                'message' => 'Invalid fragrance for this product'
                            ];
                            continue;
                        }
                    }
                }
                
                // Get price from products.json using canonical price function
                $slotPrice = getProductPrice($productId, $variant);
                
                if ($slotPrice <= 0) {
                    $validationErrors[] = [
                        'slot' => $slotNumber,
                        'field' => 'product',
                        'message' => 'Invalid product or variant'
                    ];
                    continue;
                }
                
                $calculatedTotal += $slotPrice;
            }
            
            // If validation errors exist, return structured error
            if (!empty($validationErrors)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Gift set validation failed',
                    'validationErrors' => $validationErrors
                ]);
                exit;
            }
            
            // Apply 5% discount only when all 3 items are valid
            $discount = $calculatedTotal * 0.05;
            $finalPrice = $calculatedTotal - $discount;
            
            // Verify calculated price matches client price (within small tolerance for rounding)
            $clientPrice = isset($item['price']) && is_numeric($item['price']) ? floatval($item['price']) : 0;
            if (abs($finalPrice - $clientPrice) > 0.01) {
                error_log("GIFTSET PRICE MISMATCH: Server calculated $finalPrice, client sent $clientPrice");
                // Use server-calculated price as authoritative
            }
            
            // Generate unique SKU based on gift set configuration
            // This ensures different configurations don't merge, while identical ones do
            $uniqueSku = generateGiftSetConfigKey($giftSetItems);
            
            // Generate readable breakdown for display
            $currentLang = I18N::getLanguage();
            $breakdown = formatGiftSetContents($giftSetItems, $currentLang);
            
            $cartItem = [
                'sku' => $uniqueSku, // Use unique configuration-based SKU
                'productId' => sanitize($item['productId'] ?? 'gift_set'),
                'name' => sanitize($item['name']),
                'category' => $category,
                'volume' => 'standard',
                'fragrance' => 'none',
                'price' => $finalPrice, // Use server-calculated price
                'quantity' => $requestedQty,
                'isGiftSet' => true,
                'items' => $giftSetItems,
                'breakdown' => $breakdown, // Store readable breakdown
                'meta' => [
                    'gift_set_items' => $giftSetItems,
                    'breakdown' => $breakdown
                ]
            ];
        } else {
            // Regular product: get price from products.json
            $productId = sanitize($item['productId'] ?? '');
            $normalizedSelection = normalizeCartSelection(
                $productId,
                sanitize($item['volume'] ?? 'standard'),
                sanitize($item['fragrance'] ?? 'none')
            );
            $sku = $normalizedSelection['sku'];
            $volume = $normalizedSelection['volume'];
            $fragrance = $normalizedSelection['fragrance'];
            $price = getProductPrice($productId, $volume);
            
            // Debug logging for Limited Edition stock issue investigation
            // NOTE: This logging can be removed once the issue is confirmed resolved
            error_log("ADD_TO_CART: Adding item - ProductID: $productId, SKU: $sku, Volume: $volume, Fragrance: $fragrance, Qty: $requestedQty");
            
            $cartItem = [
                'sku' => $sku,
                'productId' => $productId,
                'name' => sanitize($item['name']),
                'category' => $category,
                'volume' => $volume,
                'fragrance' => $fragrance,
                'price' => $price,
                'quantity' => $requestedQty
            ];
        }
        
        addToCart($cartItem);
        
        echo json_encode([
            'success' => true,
            'cartCount' => getCartCount(),
            'cartTotal' => getCartTotal()
        ]);
        break;
        
    case 'update':
        if (empty($input['sku']) || !isset($input['quantity'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing sku or quantity']);
            exit;
        }
        
        $sku = sanitize($input['sku']);
        $quantity = intval($input['quantity']);
        
        // Stock check moved to checkout
        updateCartQuantity($sku, $quantity);
        
        echo json_encode([
            'success' => true,
            'cartCount' => getCartCount(),
            'cartTotal' => getCartTotal()
        ]);
        break;
        
    case 'remove':
        if (empty($input['sku'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing sku']);
            exit;
        }
        
        $sku = sanitize($input['sku']);
        removeFromCart($sku);
        
        echo json_encode([
            'success' => true,
            'cartCount' => getCartCount(),
            'cartTotal' => getCartTotal()
        ]);
        break;
        
    case 'sync':
        // Sync entire cart from JavaScript
        if (!isset($input['cart']) || !is_array($input['cart'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing cart data']);
            exit;
        }
        
        // Clear existing cart and rebuild from JS data
        clearCart();
        
        foreach ($input['cart'] as $item) {
            if (!empty($item['sku']) && !empty($item['name'])) {
                $sku = sanitize($item['sku']);
                $category = sanitize($item['category'] ?? '');
                $requestedQty = intval($item['quantity'] ?? 1);
                
                // For gift sets, use incoming price; for other products, get price from products.json
                if ($category === 'gift_sets' && isset($item['price']) && is_numeric($item['price'])) {
                    // Gift set: use incoming price and preserve items metadata
                    $giftSetItems = $item['gift_set_items'] ?? $item['items'] ?? [];
                    
                    $cartItem = [
                        'sku' => $sku,
                        'productId' => sanitize($item['productId'] ?? ''),
                        'name' => sanitize($item['name']),
                        'category' => $category,
                        'volume' => 'standard',
                        'fragrance' => 'none',
                        'price' => floatval($item['price']),
                        'quantity' => $requestedQty,
                        'isGiftSet' => true,
                        'items' => $giftSetItems,
                        'meta' => [
                            'gift_set_items' => $giftSetItems
                        ]
                    ];
                } else {
                    // Regular product: get price from products.json
                    $productId = sanitize($item['productId'] ?? '');
                    $normalizedSelection = normalizeCartSelection(
                        $productId,
                        sanitize($item['volume'] ?? 'standard'),
                        sanitize($item['fragrance'] ?? 'none')
                    );
                    $sku = $normalizedSelection['sku'];
                    $volume = $normalizedSelection['volume'];
                    $fragrance = $normalizedSelection['fragrance'];
                    $price = getProductPrice($productId, $volume);
                    
                    $cartItem = [
                        'sku' => $sku,
                        'productId' => $productId,
                        'name' => sanitize($item['name']),
                        'category' => $category,
                        'volume' => $volume,
                        'fragrance' => $fragrance,
                        'price' => $price,
                        'quantity' => $requestedQty
                    ];
                }
                addToCart($cartItem);
            }
        }
        
        echo json_encode([
            'success' => true,
            'cart' => getCart(),
            'cartCount' => getCartCount(),
            'cartTotal' => getCartTotal()
        ]);
        break;
        
    case 'get':
        // Return current session cart
        echo json_encode([
            'success' => true,
            'cart' => getCart(),
            'cartCount' => getCartCount(),
            'cartTotal' => getCartTotal()
        ]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}

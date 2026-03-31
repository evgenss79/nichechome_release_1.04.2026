<?php
/**
 * AJAX endpoint to fetch product data by category for gift set builder
 * Returns products with their variants, prices, and fragrance requirements
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$category = $_GET['category'] ?? '';

if (empty($category)) {
    http_response_code(400);
    echo json_encode(['error' => 'Category parameter required']);
    exit;
}

// Load products or accessories based on category
$categoryProducts = [];

if ($category === 'accessories') {
    // Load accessories from accessories.json
    $accessories = loadJSON('accessories.json');
    
    foreach ($accessories as $accessoryId => $accessory) {
        if (empty($accessory['active'])) {
            continue;
        }
        
        $variants = [];
        
        // Check if has volume selector/variants
        if (!empty($accessory['has_volume_selector']) && !empty($accessory['volumes'])) {
            // Build variants from volumes
            foreach ($accessory['volumes'] as $vol) {
                $price = (float)($accessory['volume_prices'][$vol] ?? $accessory['priceCHF'] ?? 0.0);
                $variants[] = [
                    'volume' => $vol,
                    'price' => $price
                ];
            }
        } else {
            // Single variant with standard volume
            $variants[] = [
                'volume' => 'standard',
                'price' => (float)($accessory['priceCHF'] ?? 0.0)
            ];
        }
        
        // Determine if accessory requires fragrance selection based on allowed_fragrances
        $allowedFragranceCodes = $accessory['allowed_fragrances'] ?? [];
        $requiresFragrance = !empty($allowedFragranceCodes);
        
        // Get allowed fragrances with localized names
        $allowedFrags = [];
        if ($requiresFragrance) {
            foreach ($allowedFragranceCodes as $fragCode) {
                $allowedFrags[] = [
                    'code' => $fragCode,
                    'name' => I18N::t('fragrance.' . $fragCode . '.name', ucfirst(str_replace('_', ' ', $fragCode)))
                ];
            }
        }
        
        // Get localized product name
        $nameKey = $accessory['name_key'] ?? '';
        $productName = !empty($nameKey) ? I18N::t($nameKey, ucfirst(str_replace('_', ' ', $accessoryId))) : ucfirst(str_replace('_', ' ', $accessoryId));
        
        $categoryProducts[] = [
            'id' => $accessoryId,
            'name' => $productName,
            'variants' => $variants,
            'requiresFragrance' => $requiresFragrance,
            'allowedFragrances' => $allowedFrags
        ];
    }
} else {
    // Load regular products from products.json
    $products = loadJSON('products.json');
    
    foreach ($products as $productId => $product) {
        if (isset($product['category']) && $product['category'] === $category && !empty($product['active'])) {
            $variants = [];
            
            // Build variants array with prices from products.json
            foreach ($product['variants'] ?? [] as $variant) {
                $volume = $variant['volume'] ?? 'standard';
                $price = (float)($variant['priceCHF'] ?? 0.0);
                
                $variants[] = [
                    'volume' => $volume,
                    'price' => $price
                ];
            }
            
            // Determine if product requires fragrance selection
            // Check both category-based rules and product-specific allowed_fragrances
            $allowedFragranceCodes = $product['allowed_fragrances'] ?? [];
            $requiresFragrance = !empty($allowedFragranceCodes) || in_array($category, ['aroma_diffusers', 'scented_candles', 'home_perfume', 'car_perfume', 'textile_perfume']);
            
            // Get allowed fragrances with localized names
            $allowedFrags = [];
            if ($requiresFragrance) {
                // Use product-specific fragrances if defined, otherwise use category fragrances
                if (!empty($allowedFragranceCodes)) {
                    $fragranceCodes = $allowedFragranceCodes;
                } else {
                    $fragranceCodes = allowedFragrances($category);
                }
                
                foreach ($fragranceCodes as $fragCode) {
                    $allowedFrags[] = [
                        'code' => $fragCode,
                        'name' => I18N::t('fragrance.' . $fragCode . '.name', ucfirst(str_replace('_', ' ', $fragCode)))
                    ];
                }
            }
            
            // Get localized product name
            $nameKey = $product['name_key'] ?? '';
            $productName = !empty($nameKey) ? I18N::t($nameKey, ucfirst(str_replace('_', ' ', $productId))) : ucfirst(str_replace('_', ' ', $productId));
            
            $categoryProducts[] = [
                'id' => $productId,
                'name' => $productName,
                'variants' => $variants,
                'requiresFragrance' => $requiresFragrance,
                'allowedFragrances' => $allowedFrags
            ];
        }
    }
}

echo json_encode([
    'success' => true,
    'products' => $categoryProducts,
    'category' => $category
]);

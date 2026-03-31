# Accessories Volume-Based Pricing System

## Overview
This document describes the volume-based pricing system implemented for Accessories category products. This system allows accessories to have different prices for different volumes/formats (e.g., "5 guggul + 5 louban" vs "10 guggul + 10 louban").

## Key Principle
**ONLY `variants[]` array contains pricing information for accessories. The `priceCHF` field is NOT used when volume selector is enabled.**

## Data Structure

### accessories.json
```json
{
  "sticks": {
    "id": "sticks",
    "priceCHF": 0,  // Set to 0 when volume selector is enabled
    "has_volume_selector": true,
    "volumes": [
      "5 guggul + 5 louban",
      "10 guggul + 10 louban"
    ],
    "volume_prices": {
      "5 guggul + 5 louban": 2.99,
      "10 guggul + 10 louban": 3.99
    }
  }
}
```

### products.json
```json
{
  "sticks": {
    "id": "sticks",
    "category": "accessories",
    "variants": [
      { "volume": "5 guggul + 5 louban", "priceCHF": 2.99 },
      { "volume": "10 guggul + 10 louban", "priceCHF": 3.99 }
    ]
  }
}
```

**Important**: `priceCHF` field should NOT exist at the product level in products.json for accessories. ALL pricing is in variants[].

## Admin Form (admin/accessories.php)

### Form Behavior

#### When Volume Selector is DISABLED (has_volume_selector = false):
- ✅ Show and require "Price (CHF)" field
- ❌ Hide "Volume Prices" section
- Creates: `variants: [{ volume: "standard", priceCHF: <base_price> }]`

#### When Volume Selector is ENABLED (has_volume_selector = true):
- ❌ Disable "Price (CHF)" field (set opacity: 0.5)
- ✅ Show and require "Volume Prices" section
- ✅ Validate that all selected volumes have prices > 0
- Creates: `variants: [{ volume: <vol>, priceCHF: <price> }, ...]` for each volume

### JavaScript Logic
```javascript
function updateVolumePricesFields() {
  const isEnabled = enableVolumeCheckbox.checked;
  const selectedVolumes = Array.from(volumesSelector.selectedOptions);
  
  if (isEnabled && selectedVolumes.length > 0) {
    // Disable Price (CHF), show volume prices
    priceChfInput.disabled = true;
    priceChfInput.removeAttribute('required');
    volumePricesContainer.style.display = '';
  } else {
    // Enable Price (CHF), hide volume prices
    priceChfInput.disabled = false;
    priceChfInput.setAttribute('required', 'required');
    volumePricesContainer.style.display = 'none';
  }
}
```

### Save Logic
```php
// When volume selector is enabled, priceCHF is set to 0
$accessories[$id] = [
    'priceCHF' => $hasVolumeSelector ? 0 : $priceCHF,
    'has_volume_selector' => $hasVolumeSelector,
    'volumes' => $hasVolumeSelector ? $volumes : [],
    'volume_prices' => $hasVolumeSelector ? $volumePrices : []
];
```

### syncAccessoryToProducts()
```php
if ($hasVolumeSelector && !empty($volumes)) {
    // Multi-volume: create variant for each volume with its price
    foreach ($volumes as $vol) {
        $variants[] = [
            'volume' => $vol,
            'priceCHF' => (float)$volumePrices[$vol]
        ];
    }
} else {
    // Single volume: standard variant
    $variants[] = [
        'volume' => 'standard',
        'priceCHF' => (float)$priceCHF
    ];
}

// DO NOT save priceCHF at product level - only variants[]
$products[$slug] = [
    'variants' => $variants
];
```

## Frontend Display (product.php)

### Price Initialization
```php
// Get default price from first variant
$defaultPrice = 0;
if (!empty($productVariants)) {
    $defaultPrice = $productVariants[0]['priceCHF'] ?? 0;
}
```

### Passing PRICES to JavaScript
```php
window.PRICES = <?php 
if (!empty($productVariants)) {
    if (count($productVariants) > 1) {
        // Multiple variants: create volume => price mapping
        $pricesData = [];
        foreach ($productVariants as $variant) {
            $vol = $variant['volume'] ?? 'standard';
            $pricesData[$vol] = (float)($variant['priceCHF'] ?? 0);
        }
        echo json_encode([$categorySlug => $pricesData]);
    } else {
        // Single variant
        $singlePrice = (float)($productVariants[0]['priceCHF'] ?? 0);
        echo json_encode([$categorySlug => $singlePrice]);
    }
}
?>;
```

**Result for sticks**:
```javascript
window.PRICES = {
  "accessories": {
    "5 guggul + 5 louban": 2.99,
    "10 guggul + 10 louban": 3.99
  }
}
```

## JavaScript Price Updates (assets/js/app.js)

### Critical Fix: Merging window.PRICES
```javascript
// Default prices for all categories
const DEFAULT_PRICES = {
    aroma_diffusers: { '125ml': 20.90, '250ml': 29.90, '500ml': 50.90 },
    scented_candles: { '160ml': 24.90, '500ml': 59.90 },
    home_perfume: { '10ml': 9.90, '50ml': 19.90 },
    car_perfume: 14.90,
    textile_perfume: 19.90,
    limited_edition: 39.90,
    accessories: { 'standard': 11.90 }
};

// Merge window.PRICES (from product.php) with defaults
// window.PRICES takes precedence for product-specific pricing
const PRICES = window.PRICES ? 
    Object.assign({}, DEFAULT_PRICES, window.PRICES) : 
    DEFAULT_PRICES;
```

### updatePrice Function
```javascript
function updatePrice(card, category) {
    const volumeSelect = card.querySelector('[data-volume-select]');
    const priceDisplay = card.querySelector('[data-price-display]');
    
    let price = 0;
    
    if (volumeSelect && PRICES[category]) {
        const volume = volumeSelect.value;
        if (typeof PRICES[category] === 'object') {
            price = PRICES[category][volume] || 0;
        } else {
            price = PRICES[category];
        }
    }
    
    priceDisplay.textContent = 'CHF ' + price.toFixed(2);
}
```

## Backend Pricing (includes/helpers.php)

### getProductPrice()
```php
function getProductPrice(string $productId, string $volume = 'standard'): float {
    $products = loadJSON('products.json');
    $product = $products[$productId] ?? null;
    
    $variants = $product['variants'] ?? [];
    
    // Always use variants for pricing
    foreach ($variants as $variant) {
        if ($variant['volume'] === $volume) {
            return (float)($variant['priceCHF'] ?? 0.0);
        }
    }
    
    // No matching variant found - return 0
    // NO fallback to priceCHF
    return 0.0;
}
```

**Important**: This function intentionally returns 0 instead of falling back to `priceCHF` to ensure pricing errors are caught rather than silently using incorrect prices.

## Testing

### Test Cases

1. **Regular Accessory (no volume selector)**
   - Product: aroma_sashe
   - Volume: standard
   - Expected: CHF 11.90 ✅

2. **Multi-Volume Accessory - Volume 1**
   - Product: sticks
   - Volume: "5 guggul + 5 louban"
   - Expected: CHF 2.99 ✅

3. **Multi-Volume Accessory - Volume 2**
   - Product: sticks
   - Volume: "10 guggul + 10 louban"
   - Expected: CHF 3.99 ✅

4. **Invalid Volume**
   - Any product with invalid volume
   - Expected: CHF 0.00 ✅

### Running Tests
```bash
php /tmp/test_cart_pricing.php
```

## Backward Compatibility

Existing accessories without `has_volume_selector` continue to work:
- They use `priceCHF` field
- `syncAccessoryToProducts()` creates: `variants: [{ volume: "standard", priceCHF: <price> }]`
- Frontend and backend correctly handle "standard" volume

## Common Issues and Solutions

### Issue: Price shows CHF 0.00 when switching volumes

**Cause**: `window.PRICES` not merged with `DEFAULT_PRICES` in app.js

**Solution**: Use `Object.assign({}, DEFAULT_PRICES, window.PRICES)` to merge

### Issue: Admin form shows price field even with volume selector enabled

**Cause**: JavaScript not properly toggling field visibility

**Solution**: Check `updateVolumePricesFields()` is called on checkbox change

### Issue: Cart shows wrong price

**Cause**: `getProductPrice()` falling back to `priceCHF`

**Solution**: Remove fallback, return 0 for missing variants

## Files Modified

1. `admin/accessories.php` - Form logic and data sync
2. `product.php` - Price display and window.PRICES
3. `assets/js/app.js` - PRICES merge and updatePrice
4. `includes/helpers.php` - getProductPrice function
5. `data/accessories.json` - Data structure
6. `data/products.json` - Variants structure

## Date Implemented
December 11, 2025

## Author
GitHub Copilot with evgenss79

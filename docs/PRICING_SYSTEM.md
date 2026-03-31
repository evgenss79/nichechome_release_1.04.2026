# Dynamic Pricing System Documentation

## Overview

The NicheHome.ch e-commerce system uses a **fully dynamic pricing system** that allows administrators to change product prices through the admin panel, with changes immediately reflected across all pages.

## Architecture

### 1. Data Source: `data/products.json`

This is the **single source of truth** for all product prices. Each product has a `variants` array:

```json
{
  "textile_spray": {
    "id": "textile_spray",
    "category": "textile_perfume",
    "name_key": "product.textile_spray.name",
    "variants": [
      {
        "volume": "standard",
        "priceCHF": 19.90
      }
    ]
  }
}
```

Products can have multiple variants with different volumes and prices:

```json
{
  "diffuser_classic": {
    "variants": [
      { "volume": "125ml", "priceCHF": 20.90 },
      { "volume": "250ml", "priceCHF": 29.90 },
      { "volume": "500ml", "priceCHF": 50.90 }
    ]
  }
}
```

### 2. Server-Side Pricing: `includes/helpers.php`

The `getProductPrice()` function retrieves prices from `products.json`:

```php
function getProductPrice(string $productId, string $volume = 'standard'): float {
    $products = loadJSON('products.json');
    if (!isset($products[$productId])) {
        return 0.0;
    }
    $product = $products[$productId];
    $variants = $product['variants'] ?? [];
    
    foreach ($variants as $variant) {
        $variantVolume = $variant['volume'] ?? '';
        if ($variantVolume === $volume) {
            return (float)($variant['priceCHF'] ?? 0.0);
        }
    }
    
    return 0.0;
}
```

**Important:** This function is used by:
- `add_to_cart.php` - When adding products to cart
- Admin pages - For displaying current prices
- Any price calculation logic

### 3. Client-Side Pricing: JavaScript

#### Default Prices (Fallback Only)

In `assets/js/app.js`, there is a `DEFAULT_PRICES` object that serves **only as a fallback** when `window.PRICES` is not set:

```javascript
const DEFAULT_PRICES = {
    aroma_diffusers: {
        '125ml': 20.90,
        '250ml': 29.90,
        '500ml': 50.90
    },
    textile_perfume: 19.90,
    // ...
};

// window.PRICES takes precedence
const PRICES = window.PRICES ? 
    Object.assign({}, DEFAULT_PRICES, window.PRICES) : 
    DEFAULT_PRICES;
```

#### Dynamic Price Passing

Each PHP page that displays products must pass prices to JavaScript via `window.PRICES`:

**product.php:**
```php
window.PRICES = <?php 
if (!empty($productVariants) && count($productVariants) > 1) {
    // Multiple variants: volume => price mapping
    foreach ($productVariants as $variant) {
        $vol = $variant['volume'] ?? 'standard';
        $pricesData[$vol] = (float)($variant['priceCHF'] ?? 0);
    }
    echo json_encode([$categorySlug => $pricesData]);
} else {
    // Single variant: direct price
    echo json_encode([$categorySlug => $singlePrice]);
}
?>;
```

**category.php:**
```php
window.PRICES = <?php 
$pricesData = [];
foreach ($categoryProducts as $productId => $product) {
    $variants = $product['variants'] ?? [];
    if (!empty($variants)) {
        // Build price mapping for each product
        $pricesData[$productId] = /* price data */;
    }
}
echo json_encode($pricesData);
?>;
```

## Admin Price Management

### Editing Prices

Administrators can edit prices through `admin/product-edit.php`:

1. Select product to edit
2. Modify prices in the "Price Variants" section
3. Click "Save Changes"
4. Changes are immediately saved to `products.json`

### Price Change Flow

When an admin changes a price:

1. **Admin Panel** (`admin/product-edit.php`)
   - Form submission with new price
   - Updates `products.json` via `saveJSON()`

2. **Backend Immediately Reflects Change**
   - `getProductPrice()` reads from updated `products.json`
   - Any new cart additions use the new price

3. **Frontend Pages** (on next page load)
   - `product.php` passes new price via `window.PRICES`
   - `category.php` passes new price via `window.PRICES`
   - JavaScript uses new price for calculations

## Cart and Checkout

### Adding to Cart (`add_to_cart.php`)

When a product is added to cart, the price is fetched using `getProductPrice()`:

```php
$productId = sanitize($item['productId'] ?? '');
$volume = sanitize($item['volume'] ?? 'standard');
$price = getProductPrice($productId, $volume);

$cartItem = [
    'sku' => $sku,
    'productId' => $productId,
    'name' => sanitize($item['name']),
    'category' => $category,
    'volume' => $volume,
    'fragrance' => sanitize($item['fragrance'] ?? 'none'),
    'price' => $price,
    'quantity' => $requestedQty
];
```

**Critical:** The cart stores a snapshot of the price at the time of adding. If the admin changes the price later, items already in the cart retain their original price.

### Cart Display (`cart.php`)

Cart totals are calculated from stored prices:

```php
function getCartTotal(): float {
    $total = 0;
    foreach (getCart() as $item) {
        $total += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
    }
    return $total;
}
```

### Checkout (`checkout.php`)

Checkout uses the same cart prices and validates stock:

```php
$subtotal = round($cartTotal, 2);
$orderTotal = round($subtotal + $shippingCost, 2);
```

## Special Cases

### Textile Perfume Spray

- **Product ID:** `textile_spray`
- **Category:** `textile_perfume`
- **Volume:** `standard` (single variant)
- **Default Price:** CHF 19.90

The textile perfume uses the same pricing system as all other products. There are **no special cases or hardcoded values** for this product.

### Accessories

Accessories are stored in both `data/accessories.json` and `data/products.json`:

- `accessories.json` - Contains metadata (images, descriptions, allowed fragrances)
- `products.json` - Contains pricing via variants (source of truth for prices)

**Always use `products.json` for pricing**, not `accessories.json`.

### Gift Sets

Gift sets calculate price dynamically based on their contents. The price is computed client-side and passed to the cart.

## Testing

### Unit Tests

**tests/test_textile_perfume_pricing.php**
Tests basic pricing functionality:
- Price retrieval from products.json
- getProductPrice() function
- Cart calculations
- Multiple quantities
- Different fragrances

### Integration Tests

**tests/test_admin_price_change.php**
Tests the complete price change flow:
- Admin changes price in products.json
- getProductPrice() returns new price
- Cart uses new price
- Checkout calculations correct
- Price restoration

### Running Tests

```bash
# Unit test
php tests/test_textile_perfume_pricing.php

# Integration test
php tests/test_admin_price_change.php
```

## Troubleshooting

### Issue: Price Changes Don't Appear

**Symptoms:** Admin changes price but old price still shows on frontend

**Causes:**
1. Browser cache - Hard refresh (Ctrl+F5) or clear cache
2. PHP opcache - Restart PHP-FPM or web server
3. JSON file not writable - Check file permissions on `data/products.json`

**Debug Steps:**
1. Check `data/products.json` directly - is the new price there?
2. Test `getProductPrice()` - does it return the new price?
3. Check browser console - is `window.PRICES` set correctly?

### Issue: Cart Has Wrong Price

**Symptoms:** Item in cart has different price than product page

**Causes:**
1. Item was added before price change - cart stores snapshot
2. Different product/volume selected

**Solution:** Remove item from cart and re-add to get current price

### Issue: JavaScript Uses Wrong Price

**Symptoms:** Add to cart button shows wrong price calculation

**Causes:**
1. `window.PRICES` not set on the page
2. JavaScript falling back to DEFAULT_PRICES

**Solution:** Ensure the PHP page sets `window.PRICES` before loading `app.js`

## Best Practices

### For Developers

1. **Never hardcode prices** in PHP or JavaScript
2. **Always use `getProductPrice()`** in server-side code
3. **Always pass prices via `window.PRICES`** in client-side pages
4. **Test price changes** after modifying pricing logic
5. **Document price-related code** clearly

### For Administrators

1. **Use the admin panel** to change prices (don't edit JSON directly)
2. **Test changes** on the frontend after saving
3. **Clear caches** if changes don't appear immediately
4. **Document price changes** for business records

## Related Files

- `data/products.json` - Price data source
- `includes/helpers.php` - `getProductPrice()` and cart functions
- `admin/product-edit.php` - Price editing interface
- `add_to_cart.php` - Cart management
- `product.php` - Product detail page
- `category.php` - Category listing page
- `cart.php` - Shopping cart page
- `checkout.php` - Checkout process
- `assets/js/app.js` - Client-side cart and pricing logic

## Version History

- **v1.0** - Initial implementation with dynamic pricing
- **v1.1** - Added category.php price passing
- **v1.2** - Added comprehensive test suite
- **Current** - Fully dynamic pricing system operational

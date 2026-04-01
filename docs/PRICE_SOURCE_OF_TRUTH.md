# Price Source of Truth Documentation

## Overview

This document describes the pricing architecture for NicheHome.ch, ensuring all prices come from a single source of truth and are consistently displayed across storefront, cart, and checkout.

## Single Source of Truth

**File:** `data/products.json`

This is the **only** authoritative source for product pricing. All price reads must ultimately come from this file.

### Data Structure

```json
{
  "product_id": {
    "id": "product_id",
    "category": "category_slug",
    "variants": [
      {
        "volume": "125ml",
        "priceCHF": 20.90
      },
      {
        "volume": "250ml",
        "priceCHF": 29.90
      }
    ]
  }
}
```

## Pricing Read Paths

### 1. Admin Write Path

**File:** `admin/product-edit.php`

When admin changes a product price:

1. Admin submits form with new price in `admin/product-edit.php`
2. Server processes POST request (line 28-53)
3. Updates `$products[$productId]['variants']` array
4. Calls `saveJSON('products.json', $products)` (line 47)
5. Writes to `data/products.json` via `includes/helpers.php::saveJSON()`

**Path:** Admin UI → POST → product-edit.php → saveJSON() → data/products.json

### 2. Storefront Read Path

**Files:** `product.php`, `category.php`

When displaying product prices on storefront:

1. Page loads and calls `loadJSON('products.json')` 
2. Server reads from `data/products.json`
3. Extracts `variants[]` array for the product
4. Passes prices to JavaScript via `window.PRICES` object (embedded in HTML)
5. JavaScript uses merged `PRICES` object for dynamic updates

**Path:** data/products.json → loadJSON() → $product['variants'] → window.PRICES → JavaScript → Frontend Display

**Key Files:**
- `product.php` lines 12, 383-411: Loads products and passes window.PRICES
- `category.php` lines 23, 612-640: Loads products and passes window.PRICES
- `assets/js/app.js` lines 104-106: Merges window.PRICES with defaults
- `assets/js/app.js` lines 146-166: updatePrice() uses PRICES object

### 3. Cart Read Path

**File:** `add_to_cart.php`

When adding product to cart or syncing the browser cart back into the PHP session:

1. Client sends AJAX request with product info
2. Server normalizes the selection with `normalizeCartSelection($productId, $volume, $fragrance)`
3. Server calls `getProductPrice($productId, $volume, $fragrance)`
3. `getProductPrice()` in `includes/helpers.php` (line 391-411):
   - Loads `data/products.json`
   - Finds matching variants by volume + fragrance when variants are fragrance-specific
   - Falls back to the volume-only price only for products intentionally modeled without fragrance-specific prices
   - Returns `priceCHF` value
4. Price stored in session cart

**Path:** data/products.json → loadJSON() → normalizeCartSelection() → getProductPrice() → Cart Session → Checkout

**Key Files:**
- `add_to_cart.php` add + sync actions: call getProductPrice() with normalized volume + fragrance
- `includes/helpers.php` lines 391-411: getProductPrice() implementation
- `cart.php`: Displays cart from session
- `checkout.php`: Calculates totals from cart session

### 4. CSV Export Path (if applicable)

Currently no CSV export includes pricing. If added in future, must use:
- `getProductPrice()` function
- OR directly read from `$products[$productId]['variants']`

## Price Resolver Functions

### Primary Function: getProductPrice()

**Location:** `includes/helpers.php` lines 391-411

```php
function getProductPrice(string $productId, string $volume = 'standard', string $fragrance = ''): float
```

**Purpose:** Get price for a specific product and volume/fragrance selection

**Used by:**
- `add_to_cart.php` - When adding items to cart
- Any server-side price calculation

**Returns:** Float price or 0.0 if not found. `add_to_cart.php` now rejects unresolved zero-price sellable variants so they do not persist into cart or checkout.

### Helper Functions (Added)

**Location:** `includes/helpers.php`

```php
function getVariantPrice(string $productId, string $volume, string $fragrance = 'none'): float
```

**Purpose:** Get price considering volume and fragrance (alias for getProductPrice for clarity)

```php
function getDefaultDisplayedPrice(string $productId): float
```

**Purpose:** Get the default (first variant) price for product card initial display

## Catalog Version Tracking

To ensure browser caches are properly invalidated when prices change:

**Version File:** `data/catalog_version.json`

```json
{
  "version": 1234567890,
  "updated_at": "2024-01-15 10:30:45"
}
```

### Version Update Flow

1. Admin saves product in `admin/product-edit.php`
2. After `saveJSON('products.json', ...)` succeeds
3. Call `updateCatalogVersion()` helper
4. Increments version number (timestamp)
5. Saves to `data/catalog_version.json`

### Version Usage

**In product pages:**
```php
$catalogVersion = getCatalogVersion();
```

```html
<script src="assets/js/app.js?v=<?php echo $catalogVersion; ?>"></script>
```

**Cache control headers added to:**
- `product.php`
- `category.php`
- Any JSON API endpoints

```php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
```

## Data File Structure

### Single Products.json Location

**Path:** `data/products.json`

**Verified:** No duplicate copies exist in:
- ❌ `public_html/data/products.json` - Does not exist
- ❌ `www/data/products.json` - Does not exist  
- ❌ `htdocs/data/products.json` - Does not exist
- ✅ `data/products.json` - Only copy (canonical)

### Accessories Dual Storage

Accessories exist in TWO files for different purposes:

1. **`data/products.json`** - Contains PRICING via variants[] (source of truth)
2. **`data/accessories.json`** - Contains metadata (images, allowed_fragrances, volume selectors)

**Rule:** Always read prices from `products.json`, never from `accessories.json`

## The Fix Applied

### Problem Identified

After admin changes price in `products.json`, storefront showed old price but cart used new price.

**Root Cause:**
1. JavaScript had hardcoded `DEFAULT_PRICES` fallback that could override `window.PRICES`
2. Object.assign() shallow merge could fail for nested price objects
3. No cache-busting mechanism when prices changed
4. Browser could cache old `window.PRICES` data

### Solution Implemented

1. **Enhanced Price Resolvers** (includes/helpers.php / add_to_cart.php)
    - Verified getProductPrice() is robust
    - Added getDefaultDisplayedPrice() helper
    - Added getVariantPrice() alias for clarity
    - Cart sync now passes the normalized fragrance to the server-side price lookup
    - Zero-price sellable variants are rejected instead of being persisted into cart/checkout

2. **Catalog Versioning** (includes/helpers.php)
   - Added getCatalogVersion() function
   - Added updateCatalogVersion() function
   - Reads/writes data/catalog_version.json

3. **Cache Control Headers** 
   - Added to product.php
   - Added to category.php
   - Prevents browser caching of price data

4. **Admin UI Feedback**
   - Display catalog version after save in admin/product-edit.php
   - Shows "Prices updated. Catalog version: X"

5. **Verification Tool** (tools/verify_pricing_consistency.php)
   - CLI tool to verify pricing consistency
   - Tests 3 products with variants
   - Confirms storefront and cart prices match

## Verification Commands

```bash
# Verify pricing consistency
php tools/verify_pricing_consistency.php
php tools/verify_storefront_vs_cart_prices.php

# Test existing pricing tests
php tests/test_textile_perfume_pricing.php
php tests/test_interior_perfume_pricing.php
php tests/test_e2e_price_change.php
php tests/test_cart_sync_variant_price.php

# Validate catalog integrity
php tools/validate_catalog_consistency.php
```

## Testing Checklist

- [ ] Admin changes price in product-edit.php
- [ ] Verify data/products.json contains new price
- [ ] Verify catalog version incremented
- [ ] Hard refresh storefront product page (Ctrl+F5)
- [ ] Verify product card shows new price
- [ ] Select different volume variant
- [ ] Verify price updates correctly
- [ ] Add to cart
- [ ] Verify cart shows correct price
- [ ] Reload and verify server cart sync still shows the same non-zero price
- [ ] Proceed to checkout
- [ ] Verify checkout total is correct
- [ ] All prices match across flow

## Key Files Reference

### Data Files
- `data/products.json` - Single source of truth for pricing
- `data/accessories.json` - Accessory metadata only (not pricing)
- `data/catalog_version.json` - Version tracking for cache busting

### Server-Side Price Functions
- `includes/helpers.php` - getProductPrice(), getDefaultDisplayedPrice(), getVariantPrice()
- `includes/helpers.php` - getCatalogVersion(), updateCatalogVersion()

### Admin Price Management
- `admin/product-edit.php` - Edit product prices
- `admin/accessories.php` - Edit accessories (syncs to products.json)

### Storefront Price Display
- `product.php` - Product detail page
- `category.php` - Category/catalog listing
- `assets/js/app.js` - Client-side price updates

### Cart & Checkout
- `add_to_cart.php` - Add items (uses getProductPrice())
- `cart.php` - Display cart
- `checkout.php` - Calculate totals

### Verification
- `tools/verify_pricing_consistency.php` - Price consistency checker
- `tests/test_textile_perfume_pricing.php` - Pricing unit tests
- `tests/test_admin_price_change.php` - Integration tests

## Important Notes

1. **Never hardcode prices** in JavaScript, PHP, or anywhere else
2. **Always use getProductPrice()** for server-side price retrieval
3. **Always pass window.PRICES** from PHP to JavaScript on product pages
4. **Always increment catalog version** after saving products.json
5. **No duplicate products.json files** - only one canonical copy
6. **Accessories pricing** - read from products.json, not accessories.json

## Troubleshooting

### Storefront shows old price after admin change

1. Check `data/products.json` - is new price saved?
2. Check catalog version - did it increment?
3. Hard refresh browser (Ctrl+F5)
4. Check browser console for `window.PRICES` object
5. Check for JavaScript errors

### Cart has different price than storefront

1. Verify both use same data/products.json
2. Run `php tools/verify_pricing_consistency.php`
3. Check getProductPrice() is called in add_to_cart.php
4. Clear PHP opcache if enabled

### Price doesn't update when selecting variant

1. Check window.PRICES is properly set
2. Check JavaScript console for errors
3. Verify PRICES object structure matches category
4. Check updatePrice() function in app.js

## Conclusion

With this architecture, all prices flow from a single source (`data/products.json`) through well-defined functions (`getProductPrice()`) to both storefront display and cart calculations, ensuring consistency across the entire purchase flow.

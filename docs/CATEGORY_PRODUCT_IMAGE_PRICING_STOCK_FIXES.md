# Category / Product Image, Pricing, and Stock Fixes

## Scope

This document records the minimal architecture-consistent fixes for:

- product image rendering from admin-managed product galleries
- category multi-image persistence and category hero slider rendering
- non-zero price propagation from product selection to cart and checkout
- stock decrement on successful order/payment completion
- stock preservation on payment cancellation/failure

## Architecture Rules Preserved

- `data/categories.json`, `data/products.json`, `data/stock.json`, and `data/branch_stock.json` remain the canonical data sources.
- Category records keep `image` as the primary hero image and may now persist `images[]` for slider rendering.
- Product records keep `image` as the primary product image and `images[]` as the canonical gallery list.
- `add_to_cart.php` still resolves prices server-side from `products.json`; it now does so with normalized volume + fragrance for both add and sync actions.
- Checkout totals still come from the session cart snapshot.
- Online-payment stock changes still happen through the confirmed payment webhook; cash pickup keeps its immediate stock-decrement path.

## Exact Code Changes

### 1. Product images

- `includes/helpers.php`
  - added `normalizeImageFilenameList()` for canonical image-list normalization
  - added `getCategoryImageList()`
  - updated `getProductImageList()` and `getCategoryImage()` to work with canonical image arrays
- `product.php`
  - product hero gallery now uses absolute `/img/...` paths for every gallery image
  - product detail card now defaults to the admin-managed primary product image when an explicit product gallery exists
- `category.php`
  - category product cards now default to the admin-managed primary product image when an explicit product gallery exists
- `assets/js/app.js`
  - fragrance-driven image swaps now keep the admin-managed product image for products that explicitly define a gallery list

### 2. Category multi-image slider

- `admin/categories.php`
  - added multiline/comma-separated slider image input
  - persists `images[]` while keeping `image` as the primary entry
- `category.php`
  - renders multiple category images as the category hero slider
- `assets/css/style.css`
  - added category-gallery sizing overrides so the slider keeps the native category hero proportions

### 3. Cart / checkout price propagation

- `add_to_cart.php`
  - add action now rejects unresolved zero-price sellable variants
  - sync action now resolves price with normalized volume + fragrance and skips invalid zero-price items
- `tests/test_cart_sync_variant_price.php`
  - covers the browser-cart sync path for fragrance-sensitive pricing all the way into cart/checkout output
- `tests/test_textile_perfume_pricing.php`
  - now uses the canonical `products.json` price as the source of truth

### 4. Stock decrement and payment cancellation

- `includes/helpers.php`
  - added `decreaseOrderStock()` to apply the canonical order SKU list to delivery or pickup stock paths
- `webhook_payrexx.php`
  - confirmed-payment webhook now marks the order `paid` only after stock decrement succeeds
  - failed stock decrement keeps the order out of `paid` state and records the stock error
- `payment_cancel.php`
  - cancel/failure redirects now mark unpaid orders as `cancelled` / `failed`
  - cancel/failure redirects do not decrement stock
- `tests/test_payment_stock_paths.php`
  - covers confirmed webhook stock decrement and cancellation-without-decrement behavior

## Invariants Checked

- category creation and catalog visibility remain intact
- product creation and selector rendering remain intact
- multilingual category/product content remains intact
- SKU initialization remains intact
- branch-stock aggregate alignment remains intact
- cart and checkout never show `0.00` for valid sellable variants

## Commands Executed

```bash
php -l includes/helpers.php
php -l admin/categories.php
php -l category.php
php -l product.php
php -l add_to_cart.php
php -l webhook_payrexx.php
php -l payment_cancel.php

php tests/test_admin_catalog_management.php
php tests/test_cart_sync_variant_price.php
php tests/test_payment_stock_paths.php
php tests/test_textile_perfume_pricing.php
php tests/test_interior_perfume_pricing.php
php tests/test_e2e_price_change.php
php tests/test_sku_generation.php
php tests/test_stock_single_source_of_truth.php
php tests/test_branch_stock_pickup_alignment.php
php tools/sku_universe_selftest.php
php tools/validate_catalog_consistency.php
php tools/verify_pricing_consistency.php
php tools/verify_storefront_vs_cart_prices.php
```

## Browser Verification Summary

Local verification used the built-in PHP server and the existing demo admin account flow.

- Admin created a category with `Dubai.jpg` + `Palermo.jpg`; storefront category hero rendered a slider and advanced from `/img/Dubai.jpg` to `/img/Palermo.jpg`.
- Admin created a product with `Etna.jpg` + `Bellini.jpg`; storefront product hero rendered `/img/Etna.jpg` and the product/category cards kept `/img/Etna.jpg` as the admin-managed image.
- Product page price changed from `CHF 17.50` to `CHF 22.50` when switching from `100ml` to `200ml`.
- Cart showed `CHF 17.50` for the selected SKU and checkout summary showed `CHF 17.50` subtotal / total for pickup.
- Cash pickup order completion decremented `BRO-100-BEL` from `5` to `4` in both `data/stock.json` aggregate and `data/branch_stock.json` for `branch_1`.

## Compatibility Notes

- Legacy categories without `images[]` continue to use the existing mapped single hero image until an admin explicitly enables `use_custom_image`.
- Legacy products without explicit gallery lists still keep the previous fragrance-image behavior.
- The stock-warning output from `tools/validate_catalog_consistency.php` for `STI-5GU-CHE` / `STI-10G-CHE` remains a pre-existing warning and was not changed by this fix set.

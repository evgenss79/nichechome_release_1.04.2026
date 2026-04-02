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
- Product records keep `image` as the primary product image and `images[]` as the canonical gallery list when explicit product images exist.
- Product creation/editing must not require product image upload; fragrance-based products without explicit product images must use fragrance imagery as the storefront fallback.
- `add_to_cart.php` still resolves prices server-side from `products.json`; it now does so with normalized volume + fragrance for both add and sync actions.
- Checkout totals still come from the session cart snapshot.
- Online-payment stock changes still happen through the confirmed payment webhook; cash pickup keeps its immediate stock-decrement path.

## Exact Code Changes

### 1. Product images

- `includes/helpers.php`
  - added `normalizeImageFilenameList()` for canonical image-list normalization
  - added canonical single-image normalization and `/img/...` URL generation helpers
  - added `getCategoryImageList()`
  - updated `getProductImageList()`, `getCategoryImage()`, and `getFragranceImage()` to normalize legacy local paths to canonical `img` filenames and render only `/img/...` URLs
  - added legacy compatibility helpers so pre-existing products can treat stored `fragrance` as a default selector value when no explicit fixed-fragrance flag exists
  - changed legacy category resolution so a valid stored `categories.json:image` wins even when `use_custom_image=false`, with the old hard-coded map kept only as the final fallback
- `product.php`
  - product hero gallery now uses absolute `/img/...` paths for every gallery image
  - product detail card now defaults to the admin-managed primary product image when an explicit product gallery exists
  - no-image fragrance products now fall back directly to fragrance images on the product page and recommended product cards
  - selector-driven fragrance image swaps now update both the product hero image and the product detail card image for no-image products
- `category.php`
  - category product cards now default to the admin-managed primary product image when an explicit product gallery exists
  - no-image fragrance products now fall back directly to fragrance images on category cards and recommendations
  - category hero slider, product cards, and recommendations now resolve through canonical helper output only
- `admin/product-edit.php`
  - removed the required product-image validation, clarified that product images are optional in admin, and now rejects unsupported/missing non-`img` image paths on save
  - defaults new products to `category_default` fragrance mode
  - hides the standalone product-image textarea for fragrance-driven product classes so fragrance images remain the canonical visual model
  - preserves legacy stored product galleries on edit when the standalone image field is hidden for compatibility
- `admin/categories.php`
  - category primary and slider images now normalize to filename-only `img` storage and reject unsupported/missing non-`img` image paths on save
- `admin/accessories.php`
  - accessory image lists now normalize to filename-only `img` storage and reject unsupported/missing non-`img` image paths on save
- `admin/products.php` and `admin/admin_products.php`
  - single-image product edits now normalize to filename-only `img` storage and keep the primary gallery image aligned
- `admin/fragrances.php`
  - fragrance previews now render through the canonical `/img/...` helper instead of `assets/img/fragrances/...`
- `account.php`
  - favorites image rendering now consumes canonical helper URLs directly
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

### 5. Full-suite fixed-fragrance regression

- `includes/helpers.php`
  - `getProductPrice()` now applies the product’s fixed/default fragrance when the caller omits an explicit fragrance
  - `getVariantPrice()` now forwards the fragrance argument to the canonical resolver
- Why this was included:
  - `php tools/verify_storefront_vs_cart_prices.php` must pass as part of the documented verification suite
  - the failing case was a fixed-fragrance product and was addressed with a localized resolver fix only

### 6. Legacy selector runtime regression

- `includes/header.php`
  - renamed the catalog-menu loop variables so the shared header no longer overwrites page-local `$slug` / `$category` variables
- `includes/footer.php`
  - renamed the footer catalog loop variables for the same isolation rule
- Why this was included:
  - `category.php` computes selector rules after including the shared header
  - the shared include was leaking the last navigation category slug into the page scope, so legacy category cards could inherit the wrong fragrance rule set
  - the fix is localized to shared template variable names and preserves selector, image, price, SKU, and stock behavior

## Invariants Checked

- category creation and catalog visibility remain intact
- product creation and selector rendering remain intact
- multilingual category/product content remains intact
- pre-existing categories/products now keep their stored canonical image/default-fragrance behavior
- legacy category pages keep their own category slug during selector inference instead of inheriting the last navigation entry
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
php tests/test_admin_product_image_optional.php
php tests/test_legacy_catalog_compatibility.php
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
- Pre-existing `aroma_diffusers` catalog/category pages rendered `/img/Mikado-category.jpg` from the stored category record instead of falling back to a broken/non-canonical path.
- Pre-existing `diffuser_classic` product page rendered `/img/Mikado-category.jpg` and kept a working fragrance selector.
- Admin-created smoke product `smoke_selector_product_20260401` saved without any product image upload, rendered `/img/Bellini.jpg`, and switched to `/img/Eden.jpg` when the fragrance selector changed.
- Product page price changed from `CHF 17.50` to `CHF 22.50` when switching from `100ml` to `200ml`.
- Cart showed `CHF 17.50` for the selected SKU and checkout summary showed `CHF 17.50` subtotal / total for pickup.
- Cash pickup order completion decremented `BRO-100-BEL` from `5` to `4` in both `data/stock.json` aggregate and `data/branch_stock.json` for `branch_1`.

## Compatibility Notes

- Legacy categories without `images[]` now prefer their stored canonical `image` value first; the old mapped single hero image remains only as a fallback for unresolved legacy data.
- Legacy products without explicit gallery lists still keep the previous fragrance-image behavior.
- Legacy products that stored only `fragrance` now keep that value as the default selected fragrance while still rendering selectors when canonical category/product rules provide multiple fragrances.
- Legacy local image inputs are normalized to filename-only `img` storage when the corresponding file exists in `img/`.
- Unsupported or missing image references are rejected on save instead of being silently persisted.
- The stock-warning output from `tools/validate_catalog_consistency.php` for `STI-5GU-CHE` / `STI-10G-CHE` remains a pre-existing warning and was not changed by this fix set.

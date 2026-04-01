# Admin Category & Product Management

## Scope

This flow extends the existing JSON-based catalog architecture without introducing a parallel schema.

## Canonical Data Sources

- Categories: `data/categories.json`
- Products: `data/products.json`
- Product/category translations: `data/i18n/ui_*.json`
- SKU universe: `includes/stock/sku_universe.php`
- Main stock: `data/stock.json`
- Branch stock: `data/branch_stock.json`

## Category Admin Workflow

Location: `admin/categories.php`

The category form now supports:

- create category
- edit category
- category image filename
- category slider image list (multiline / comma separated)
- multilingual name, short description, and long description
- default volume options
- default fragrance support and allowed fragrance list
- visibility flags for catalog, mega menu, and footer catalog block

Saved category records keep the existing keys:

- `name_key`
- `short_key`
- `long_key`

Additional category flags:

- `active`
- `show_in_catalog`
- `show_in_navigation`
- `show_in_footer`
- `use_custom_image`
- `images`

`use_custom_image=true` activates the category image(s) stored in `categories.json`. Legacy categories keep the previous storefront image mapping until an admin explicitly switches them to a custom image. When `images[]` is present, `image` remains the primary/first hero image and the full list is rendered as the category hero slider.

## Product Admin Workflow

Location: `admin/product-edit.php` (linked from `admin/products.php` and `admin/admin_products.php`)

The product form now supports:

- create product
- edit product
- assign product to an existing category
- multiline product image list
- multilingual name and description
- multiple sellable variants
- fragrance mode:
  - category default
  - selectable fragrances
  - fixed fragrance
  - no fragrance

Each sellable row is stored in `products.json` as a variant with:

- `volume`
- optional `fragrance`
- `priceCHF`

This keeps existing volume-only products compatible while also supporting price-per-volume and price-per-volume+fragrance combinations.

`image` remains the primary product image and `images[]` remains the canonical storefront gallery list. Product/category cards keep using the product-managed image when a product has an explicit gallery, while legacy fragrance-image swaps stay available for older products that do not define a gallery list.

## Storefront Rendering Rules

`category.php` and `product.php` now derive selectors from the product record first:

1. explicit variant rows
2. explicit product fragrance configuration
3. category defaults in `categories.json`
4. legacy helper fallbacks

Rendering invariants:

- multi-volume products show a volume selector
- no-fragrance products submit `none` and generate `NA` SKUs
- fixed-fragrance products render hidden fragrance state
- dynamic prices are keyed by product ID, not category slug, so multiple products can safely coexist inside the same category
- product detail heroes render `images[]` as the canonical gallery when present
- category heroes render category `images[]` as the canonical slider when present
- category/product cards default to the primary product image when an explicit product gallery exists

## SKU + Stock Initialization

Saving a product triggers:

1. write to `data/products.json`
2. write translations to `data/i18n/ui_*.json`
3. `initializeMissingSkuKeys(false)`
4. `updateCatalogVersion()`

`generateCatalogSkus()` now supports:

- fixed-fragrance products outside the limited edition category
- explicit fragrance-per-variant rows
- non-fragrance products using `NA`

Every newly generated SKU is inserted into:

- `data/stock.json`
- every branch in `data/branch_stock.json`

Initial quantity remains `0` everywhere.

## Compatibility Rules Preserved

- JSON files remain the single source of truth
- cart and checkout still use canonical SKU generation
- `add_to_cart.php` resolves price with volume + fragrance for both add and sync flows
- existing accessory management remains separate and compatible
- stock decreases use the canonical order SKU list for cash pickup and confirmed online-payment flows

## Verification Commands

Executed during implementation:

```bash
php -l includes/helpers.php
php -l includes/stock/sku_universe.php
php -l category.php
php -l product.php
php -l add_to_cart.php
php -l admin/categories.php
php -l admin/product-edit.php
php -l admin/products.php
php -l admin/admin_products.php
php -l catalog.php
php -l includes/header.php
php -l includes/footer.php

php tests/test_admin_catalog_management.php
php tests/test_cart_sync_variant_price.php
php tests/test_payment_stock_paths.php
php tests/test_sku_generation.php
php tests/test_textile_perfume_pricing.php
php tests/test_branch_stock_pickup_alignment.php
php tests/test_stock_single_source_of_truth.php
php tools/sku_universe_selftest.php
php tools/validate_catalog_consistency.php
php tests/test_interior_perfume_pricing.php
php tests/test_e2e_price_change.php
php tools/verify_pricing_consistency.php
php tools/verify_storefront_vs_cart_prices.php
```

## Regression Coverage Added

`tests/test_admin_catalog_management.php` verifies:

- category persistence
- product persistence
- multilingual content persistence
- category catalog visibility
- category page rendering
- category gallery slider rendering
- product page rendering
- product gallery rendering
- product selector rendering
- exact variant pricing
- cart/checkout non-zero price propagation through sync
- SKU generation
- stock initialization in main and branch stock

`tests/test_cart_sync_variant_price.php` verifies that fragrance-sensitive cart sync keeps a non-zero server-resolved price all the way into cart/checkout output.

`tests/test_payment_stock_paths.php` verifies:

- confirmed payment webhook decrements the correct SKU and persists `paid`
- cancellation marks the order failed/cancelled without decrementing stock

## Known Existing Warnings

`php tools/validate_catalog_consistency.php` still reports pre-existing warning candidates for two incense stick SKUs (`STI-5GU-CHE`, `STI-10G-CHE`). This change does not introduce new SKU warnings and keeps the current universe consistent.

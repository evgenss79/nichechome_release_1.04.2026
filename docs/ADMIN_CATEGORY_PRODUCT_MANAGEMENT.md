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

`use_custom_image=true` activates the category image stored in `categories.json`. Legacy categories keep the previous storefront image mapping until an admin explicitly switches them to a custom image.

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
- `add_to_cart.php` now resolves price with volume + fragrance when needed
- existing accessory management remains separate and compatible
- checkout/payment stock handling is unchanged

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
php tests/test_sku_generation.php
php tests/test_branch_stock_pickup_alignment.php
php tests/test_stock_single_source_of_truth.php
php tools/sku_universe_selftest.php
php tools/validate_catalog_consistency.php
php tests/test_admin_price_change.php
php tests/test_interior_perfume_pricing.php
php tests/test_e2e_price_change.php
```

## Regression Coverage Added

`tests/test_admin_catalog_management.php` verifies:

- category persistence
- product persistence
- multilingual content persistence
- category catalog visibility
- category page rendering
- product page rendering
- product selector rendering
- exact variant pricing
- SKU generation
- stock initialization in main and branch stock

## Known Existing Warnings

`php tools/validate_catalog_consistency.php` still reports pre-existing warning candidates for two incense stick SKUs (`STI-5GU-CHE`, `STI-10G-CHE`). This change does not introduce new SKU warnings and keeps the current universe consistent.

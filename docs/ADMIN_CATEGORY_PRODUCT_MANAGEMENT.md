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
- delete category from the existing-category table
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

`use_custom_image=true` activates the category image(s) stored in `categories.json`. For backward compatibility, legacy categories now always prefer a valid stored `image` filename from `categories.json` first; the old storefront mapping is used only when the stored legacy value no longer resolves in `img/`. When `images[]` is present, `image` remains the primary/first hero image and the full list is rendered as the category hero slider.

Canonical image rule:

- admin category image inputs normalize supported legacy local references to filename-only storage
- saved `image` / `images[]` values must resolve to files in `img/`
- remote URLs and missing local files are rejected explicitly

### Category Delete Rule

- Delete is available from the category list in `admin/categories.php`
- Delete always requires an explicit confirmation dialog
- Server-side deletion uses `deleteCategory()` in `includes/helpers.php`
- **Canonical safety rule:** a category cannot be deleted while any product in `data/products.json` still references that category slug
- To delete a populated category, the admin must first reassign or delete the dependent products

### Category Delete Persistence

Deleting an empty category removes:

- the category record from `data/categories.json`
- category translations from `data/i18n/ui_*.json`
- legacy category translations from `data/i18n/categories_*.json`
- the category from catalog, navigation, footer, and the category route (the page redirects to catalog because the slug no longer exists)

Deleting a non-empty category does **not** change products, stock, SKUs, cart data, or checkout data because the operation is blocked before any write occurs.

## Product Admin Workflow

Location: `admin/product-edit.php` (linked from `admin/products.php` and `admin/admin_products.php`)

The product form now supports:

- create product
- edit product
- assign product to an existing category
- optional multiline product image list only for non-fragrance visual products
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

`image` remains the primary product image and `images[]` remains the canonical storefront gallery list when explicit product images are supplied. Product image upload is not required for creation or editing. For fragrance-based products without explicit product images, product/category cards and the product page must fall back to the fragrance image instead of forcing a product-level upload or showing a placeholder first.

Admin visual-model rule:

- new create forms default to `category_default` fragrance mode instead of `no_fragrance`
- fragrance-driven product classes (`category_default`, `selectable_fragrances`, `fixed_fragrance`) no longer expose the standalone product-image textarea in `admin/product-edit.php`
- the admin form now explains that fragrance images are the canonical storefront visual model for those classes
- existing stored `image` / `images[]` values are preserved on edit when the field is hidden, so legacy products keep working without making standalone product images authoritative for new saves

Canonical image rule:

- product/admin image values are stored as filename-only references for the `img/` folder
- `/img/...`, `img/...`, and other local legacy paths are normalized to filenames when the image exists in `img/`
- unsupported or missing image paths are rejected on save

## Storefront Rendering Rules

`category.php` and `product.php` now derive selectors from the product record first:

1. explicit variant rows
2. explicit product fragrance configuration
3. category defaults in `categories.json`
4. legacy helper fallbacks

Legacy fragrance compatibility rule:

- if `has_fragrance_selector` is explicitly stored, that explicit value still wins
- if a legacy product stores only `fragrance` but the category/product rules still expose multiple fragrances, the stored `fragrance` is treated as the default preselected fragrance rather than as a forced fixed-fragrance lock
- fixed-fragrance products still stay fixed when they explicitly store `has_fragrance_selector=false` or belong to canonical fixed-fragrance flows such as `limited_edition`

Rendering invariants:

- multi-volume products show a volume selector
- no-fragrance products submit `none` and generate `NA` SKUs
- fixed-fragrance products render hidden fragrance state
- dynamic prices are keyed by product ID, not category slug, so multiple products can safely coexist inside the same category
- product detail heroes render `images[]` as the canonical gallery when present
- product detail single-image heroes without explicit galleries stay selector-aware and now follow canonical fragrance-image swaps from `/img/...`
- category heroes render category `images[]` as the canonical slider when present
- category/product cards default to the primary product image when an explicit product gallery exists
- fragrance admin previews and storefront fragrance fallbacks render from absolute `/img/...` paths only
- shared includes must not overwrite page-local category variables before selector inference or rendering, otherwise legacy category/product selector rules can silently switch to the wrong category context

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
php -l admin/categories.php

php tests/test_admin_catalog_management.php
php tests/test_admin_product_image_optional.php
php tests/test_legacy_catalog_compatibility.php
php tests/test_category_deletion.php
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

`tests/test_admin_product_image_optional.php` verifies:

- product create/edit does not require a product image
- no-image products save successfully
- no-image products fall back to fragrance imagery on product/category pages

`tests/test_legacy_catalog_compatibility.php` verifies:

- legacy non-custom category records still render their stored canonical image
- legacy products with only a stored default fragrance still render selectors when category rules require them
- selector-driven legacy/new fragrance fallbacks continue to resolve from `/img/...`

`tests/test_legacy_selector_runtime_regression.php` verifies:

- the real legacy `aroma_diffusers` category page still renders the full selector options for `diffuser_classic`
- the real legacy `limited_edition` product page still does not render a false fragrance selector
- the admin-created fixed-fragrance regression product still stays selector-free

`tests/test_category_deletion.php` verifies:

- admin categories page renders a delete control and confirmation text
- deletion succeeds for an empty category
- deletion is blocked when products still belong to the category
- deleted category translations are removed from both `ui_*.json` and `categories_*.json`
- deleted categories disappear from catalog/header/footer output

`tests/test_cart_sync_variant_price.php` verifies that fragrance-sensitive cart sync keeps a non-zero server-resolved price all the way into cart/checkout output.

`tests/test_payment_stock_paths.php` verifies:

- confirmed payment webhook decrements the correct SKU and persists `paid`
- cancellation marks the order failed/cancelled without decrementing stock

## Known Existing Warnings

`php tools/validate_catalog_consistency.php` still reports pre-existing warning candidates for two incense stick SKUs (`STI-5GU-CHE`, `STI-10G-CHE`). This change does not introduce new SKU warnings and keeps the current universe consistent.

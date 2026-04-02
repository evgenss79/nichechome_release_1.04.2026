# Canonical `img` Folder Rule

## Decision

All category, product, accessory, fragrance, favorites, recommendation, and admin-preview images must resolve from the repository `img/` folder only.

## Canonical Storage Format

- JSON/admin-managed image values are stored as **plain filenames only**.
- Canonical examples:
  - `Dubai.jpg`
  - `Bellini.jpg`
  - `placeholder.svg`
- Non-canonical stored formats are not allowed:
  - `/img/Dubai.jpg`
  - `img/Dubai.jpg`
  - `../assets/img/fragrances/Bellini.jpg`
  - `https://example.com/image.jpg`

## Normalization Rule

Admin save flows normalize supported legacy local references to the canonical filename-only format when the basename exists in `img/`.

Accepted and normalized on save:

- `Dubai.jpg` → `Dubai.jpg`
- `/img/Dubai.jpg` → `Dubai.jpg`
- `img/Dubai.jpg` → `Dubai.jpg`
- `../assets/img/fragrances/Bellini.jpg` → `Bellini.jpg`

Rejected on save:

- remote URLs
- non-image files
- any image reference whose basename does not exist in `img/`

## Rendering Rule

- All runtime image URLs are emitted as absolute `/img/...` paths.
- Storefront/admin rendering must not emit `assets/img/fragrances/...` or other local image folders.
- Helpers normalize legacy local references before rendering and fall back safely to `/img/placeholder.svg` when canonical resolution is impossible at runtime.
- Legacy categories now prefer a valid stored `categories.json:image` filename even when `use_custom_image=false`; the hard-coded category map is only the final fallback for records whose stored image no longer resolves in `img/`.
- Legacy no-image products and legacy products whose stored `fragrance` was only a default selection continue to render and select canonical fragrance imagery from `/img/...`.
- `img/placeholder.svg` is a required repository asset and is validated by `php tools/check_assets.php`.

## Affected Flows

- `admin/categories.php`
  - main category image and category slider images normalize to filename-only storage
- `admin/product-edit.php`
  - product gallery images normalize to filename-only storage
- `admin/accessories.php`
  - accessory images normalize to filename-only storage
- `admin/products.php`
  - single-image edits normalize to filename-only storage and keep product gallery primary image aligned
- `admin/admin_products.php`
  - single-image edits normalize to filename-only storage and keep product gallery primary image aligned
- `admin/fragrances.php`
  - fragrance previews render from `/img/...`
- `category.php`
  - category hero, category slider, category cards, and recommendation cards render from `/img/...`
- `product.php`
  - product hero, product card, fragrance fallback, selector-driven hero/card swaps, and recommendation cards render from `/img/...`
- `account.php`
  - favorites images render from canonical helper output
- `includes/helpers.php`
  - canonical normalization, validation, and `/img/...` URL generation are centralized

## Compatibility Rules Preserved

- `data/categories.json`, `data/products.json`, `data/accessories.json`, and `data/fragrances.json` remain the canonical sources.
- Category/product logic stays separate.
- Fragrance-driven product visuals still work.
- Legacy category image mappings still work as a last-resort fallback, but valid stored category images now win for both old and new records.
- Legacy products that stored `fragrance` as a default selection (without `has_fragrance_selector`) still expose selectors when category/product rules provide multiple fragrances.
- Fixed-fragrance products still resolve the correct storefront/cart price after the localized resolver fix required by the full verification suite.

## Commands Executed

```bash
php -l includes/helpers.php
php -l category.php
php -l product.php
php -l account.php
php -l admin/categories.php
php -l admin/product-edit.php
php -l admin/accessories.php
php -l admin/products.php
php -l admin/admin_products.php
php -l admin/fragrances.php
php -l tests/test_canonical_image_paths.php

php tests/test_canonical_image_paths.php
php tests/test_admin_catalog_management.php
php tests/test_category_deletion.php
php tests/test_admin_product_image_optional.php
php tests/test_legacy_catalog_compatibility.php
php tests/test_cart_sync_variant_price.php
php tests/test_payment_stock_paths.php
php tests/test_sku_generation.php
php tests/test_textile_perfume_pricing.php
php tests/test_branch_stock_pickup_alignment.php
php tests/test_stock_single_source_of_truth.php
php tests/test_interior_perfume_pricing.php
php tests/test_e2e_price_change.php
php tools/sku_universe_selftest.php
php tools/validate_catalog_consistency.php
php tools/verify_pricing_consistency.php
php tools/verify_storefront_vs_cart_prices.php
php tools/check_assets.php
php -S 127.0.0.1:8000 -t .
```

## Verification Evidence

### Automated

- `tests/test_canonical_image_paths.php`
  - verifies canonical helper normalization
  - verifies category/product rendering from `/img/...`
  - verifies admin category/product saves normalize legacy local paths to filename-only storage
  - verifies remote image URLs are rejected
- `tests/test_legacy_catalog_compatibility.php`
  - verifies a legacy non-custom category still renders its stored canonical image from `/img/...`
  - verifies a legacy product with only a stored default fragrance still renders a selector and canonical fragrance-image fallback
- existing catalog/admin image regressions still pass:
  - `tests/test_admin_catalog_management.php`
  - `tests/test_admin_product_image_optional.php`

### Runtime

Browser verification on the local PHP server confirmed:

- catalog card image src: `/img/Aroma%20diffusers_category.jpg`
- pre-existing category card image src: `/img/Mikado-category.jpg`
- pre-existing category hero image src: `/img/Mikado-category.jpg`
- pre-existing product hero/card image src: `/img/Mikado-category.jpg`
- newly created/selectable product hero/card image src changed from `/img/Bellini.jpg` to `/img/Eden.jpg` when the fragrance selector changed
- recommendation card image src examples:
  - `/img/smart_1.jpg`
  - `/img/Recarga.jpg`

No verified storefront/admin runtime path used a local folder outside `img/`.

## Known Validation Notes

- `php tools/validate_catalog_consistency.php` still reports the pre-existing incense-stick `NA` warning candidates (`STI-5GU-CHE`, `STI-10G-CHE`).
- Those warnings are unrelated to canonical image-path enforcement and remained unchanged.

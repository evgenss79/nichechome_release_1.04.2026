# Branch Stock / Pickup SKU Alignment

## Root cause

The consolidated stock page already used the SKU universe (`getConsolidatedStockViewFromUniverse()`), but the branch stock page rebuilt its own SKU list from `products.json`. That parallel path dropped valid branch-stock rows whenever a product had no fragrance selector or existed only as a legacy/orphan SKU in stock files. At the same time, the storefront cart generator could create non-canonical SKUs such as `SMA-STA-NON` for no-fragrance items, while stock files stored the canonical `...-NA` form.

## What changed

- `admin/branches.php` now renders branch stock rows from `getBranchStockItemsFromUniverse()` instead of regenerating SKUs locally.
- `includes/helpers.php` now exposes:
  - `getBranchStockItemsFromUniverse()` for the branch stock UI
  - `normalizeCartSelection()` so server-side cart ingestion always derives the canonical SKU with PHP's `generateSKU()`
- `add_to_cart.php` now normalizes regular product selections before they are stored in the PHP session cart.
- `assets/js/app.js` now matches the documented SKU rules for no-fragrance items and known prefix/suffix mappings.

## Invariant now enforced

Every branch stock UI and pickup validation path must use the same canonical SKU contract:

- branch stock visibility comes from the SKU universe
- session cart SKUs come from PHP `generateSKU()`
- no-fragrance items resolve to `...-NA`, never `...-NON`

## How to verify

Run:

```bash
php tests/test_branch_stock_pickup_alignment.php
php tools/sku_universe_selftest.php
```

Manual spot-check:

1. Open **Admin → Stock** and confirm `SMA-STA-NA` is present with branch quantity.
2. Open **Admin → Branches → branch_1** and confirm the same SKU is visible there.
3. Add **Aroma Smart** to cart, choose branch pickup for `branch_1`, and confirm checkout no longer reports false out-of-stock.

## Compatibility

- Existing stock data files are unchanged.
- Legacy/orphan SKUs remain visible in the branch stock UI if they still exist in stock sources.
- Checkout/cart display still keeps no-fragrance products visually fragrance-free while storing canonical `...-NA` SKUs for stock validation.

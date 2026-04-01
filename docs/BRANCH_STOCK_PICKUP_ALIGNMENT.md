# Stock Source of Truth / Branch Pickup Alignment

## Architecture now enforced

- `data/stock.json` is the **sole authoritative source of inventory quantities**.
- Inventory quantities are **manually editable only on `admin/stock.php`**.
- `admin/branches.php` is **read-only** for stock quantities in both UI and server behavior.
- Pickup validation and checkout use the **same quantity source** as delivery: `stock.json`.
- `data/branch_stock.json` remains **compatibility-only**. It is refreshed from `stock.json` and is no longer an independent source of truth.

## File-level evidence of branch write paths removed or neutralized

- `includes/helpers.php`
  - `getBranchStockQuantity()` now returns the authoritative quantity from `stock.json`.
  - `decreaseBranchStock()` now validates the branch ID and delegates the stock decrease to `decreaseStock()`, so pickup orders reduce the same stock record as delivery orders.
  - `loadBranchStock()` now builds a derived compatibility snapshot from `stock.json` instead of reading independent branch quantities.
  - `saveBranchStock()` now only refreshes the compatibility mirror; it no longer accepts independent branch quantities as authoritative input.
  - `updateConsolidatedStock()` now updates only `stock.json` and then refreshes the compatibility mirror.
- `admin/branches.php`
  - The former `action=update_stock` write path is neutralized and now returns a read-only error.
  - The branch stock table no longer renders editable quantity inputs or Save buttons.
- `admin/stock.php`
  - STOCK saves a single authoritative quantity per SKU.
  - Branch columns are rendered as read-only mirrors of the authoritative STOCK quantity.
- `admin/stock_import.php`
  - Import now accepts only the authoritative STOCK quantity column.
  - Independent branch-level import quantities are no longer parsed or written.
- `admin/export_stock_csv.php`
  - Export now produces a single authoritative quantity column instead of branch-level writable data.

## What changed functionally

- Branch stock visibility still comes from the SKU universe, but the displayed quantity now mirrors `stock.json`.
- Canonical SKU handling is still preserved:
  - session cart SKUs come from PHP `generateSKU()`
  - no-fragrance items resolve to `...-NA`, never `...-NON`
- The branch stock compatibility file is kept only so existing exports/backups/tools can continue to operate without becoming an authoritative inventory database.

## Direct proof

Run:

```bash
php tests/test_branch_stock_pickup_alignment.php
php tests/test_stock_single_source_of_truth.php
php tools/sku_universe_selftest.php
```

The new direct-proof test performs the requested verification flow:

1. edit quantity on STOCK
2. save
3. verify BRANCH/STOCK immediately reflects the same value
4. verify pickup validation uses that same value
5. verify the unavailable case still blocks correctly

## Manual spot-check

1. Open **Admin → Stock** and edit any SKU quantity.
2. Save the row.
3. Open **Admin → Branches → any branch** and confirm the quantity shown for the same SKU matches STOCK exactly.
4. Try checkout with pickup for a quantity within stock and confirm it passes.
5. Try checkout with pickup for a quantity above stock and confirm it is blocked.

## Compatibility

- `branch_stock.json` may remain in the repository and backup/export flows, but it is **non-authoritative**.
- Any quantity shown on branch pages is a derived mirror of `stock.json`.
- Checkout/cart display still keeps no-fragrance products visually fragrance-free while storing canonical `...-NA` SKUs for stock validation.

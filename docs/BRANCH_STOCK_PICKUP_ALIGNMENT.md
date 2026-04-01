# Branch Stock / Pickup Alignment

## Architecture enforced

- `data/branch_stock.json` stores the authoritative **per-branch quantities** shown on **Admin → STOCK**.
- `data/stock.json` keeps the SKU-level aggregate used by legacy/global stock consumers and must stay aligned to the **sum of all branch quantities** for a SKU.
- `admin/stock.php` is the writable admin surface for branch quantities.
- `TOTAL` on `admin/stock.php` is **derived/read-only** and always equals `sum(branch columns)`.
- Pickup validation and pickup stock decrease use the selected branch quantity from `branch_stock.json`.
- Delivery/global stock validation continues to read `stock.json`, which is refreshed to the branch sum whenever STOCK or branch-aware import updates a SKU.

## File-level behavior

- `includes/helpers.php`
  - `loadBranchStock()` loads the persisted branch stock file and normalizes missing SKU keys without flattening existing branch quantities.
  - `saveBranchStock()` preserves the submitted branch quantities instead of mirroring one shared total into every branch.
  - `getBranchStockQuantity()` returns the real quantity for the requested `branchId + SKU`.
  - `decreaseBranchStock()` decrements the selected branch and then realigns `stock.json.quantity` / `stock.json.total_qty` to the new branch sum.
  - `getConsolidatedStockViewFromUniverse()` builds STOCK rows from real branch quantities and computes `TOTAL` from the branch sum.
  - `updateConsolidatedStock()` accepts branch quantities, persists them branch-by-branch, and updates `stock.json` totals to the exact aggregate.
- `admin/stock.php`
  - Renders editable inputs for each branch column.
  - Renders `TOTAL` as read-only/computed.
  - Saves branch quantities only; `TOTAL` is not posted back as an authoritative writer.
- `admin/stock_import.php`
  - Imports per-branch quantity columns plus a `total` column.
  - Rejects rows where `total !== sum(branch columns)`.
- `admin/export_stock_csv.php`
  - Exports per-branch quantity columns and a computed `total` column for round-tripping with stock import.

## Invariants

1. Branch quantities remain independently editable and independently persisted.
2. Editing one branch must not overwrite the other branch columns.
3. `TOTAL` must equal the exact sum of branch quantities on render, save, reload, and import.
4. `TOTAL` is display-only; it must never be the authoritative writer for branch stock.
5. Pickup validation must pass/fail against the selected branch quantity.
6. Saving/importing branch quantities must keep `stock.json.quantity` and `stock.json.total_qty` aligned to the branch sum for that SKU.

## Verification commands

Run:

```bash
php tests/test_stock_single_source_of_truth.php
php tests/test_branch_stock_pickup_alignment.php
php tools/sku_universe_selftest.php
```

## Manual verification

1. Open **Admin → STOCK**.
2. Choose a SKU and enter different values in at least 3 branch columns.
3. Confirm `TOTAL` updates to the sum immediately.
4. Save the row and reload the page.
5. Confirm the same branch values remain and `TOTAL` still matches the sum.
6. Import a branch-aware CSV row and confirm the branch values reappear exactly on STOCK.
7. At checkout, verify pickup for an available branch quantity passes and pickup above that branch quantity is blocked.

## Compatibility notes

- `stock.json` is retained for legacy/global stock consumers, but for STOCK-page editing it is an aggregate projection of branch quantities, not an independent branch writer.
- Existing branch IDs continue to come from `branches.json`, and missing SKU keys are still initialized with `0`.
- `TOTAL` remains visible in the UI for operators, but it is no longer editable or submitted as a stock authority.

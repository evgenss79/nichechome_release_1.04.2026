# SKU Universe System - Implementation Summary

## Overview

This system ensures all SKUs follow a consistent 3-part format: `PREFIX-VOLUME-FRAGRANCE`

For products without fragrance selection, the fragrance code is `NA` (No Applicable).

## Key Features

### 1. SKU Format Enforcement

All SKUs are guaranteed to be 3-part:
- **Format**: `PREFIX-VOLUME-FRAGRANCE`
- **Example with fragrance**: `DF-125-BEL` (Diffuser 125ml Bellini)
- **Example without fragrance**: `ARO-STA-NA` (Aroma Sachét Standard No fragrance)

### 2. SKU Universe

The SKU Universe is the single source of truth for all valid SKUs.

**Location**: `includes/stock/sku_universe.php`

**Function**: `loadSkuUniverse()`

**Sources**:
- Products from `data/products.json`
- Accessories from `data/accessories.json`
- Existing stock from `data/stock.json`
- Branch stock from `data/branch_stock.json`

### 3. Debug Panel

**URL**: `admin/stock.php?debug=1`

**Features**:
- Universe SKU count
- Stock file SKU counts
- Missing SKUs (need initialization)
- Extra SKUs (orphaned)
- Format violations (non 3-part)
- NA fragrance SKUs list
- Pass/Fail status

### 4. Auto-Sync

**Manual Trigger**: Admin Stock page → "Sync SKU Universe" button

**Automatic Trigger**: When saving new accessories in admin panel

**What it does**:
- Adds missing SKUs to `stock.json` with qty=0
- Adds missing SKUs to `branch_stock.json` with qty=0 for each branch
- Creates timestamped backups before writing
- Logs all changes
- **Never modifies existing quantities**

### 5. Dynamic CSV Export

**URL**: `admin/export_stock_csv.php`

**Columns**:
- `sku` - SKU code
- `product_name` - Human-readable product name
- `category` - Product category
- `volume` - Volume/size
- `fragrance_key` - Fragrance code (e.g., "cherry_blossom" or "NA")
- `fragrance_label` - Human-readable fragrance name ("No fragrance / Device" for NA)
- `[branch_id]` - Quantity for each branch
- `total_qty` - Sum of all branches

**Features**:
- Generated on-the-fly from current Universe
- UTF-8 with BOM for Excel compatibility
- Includes current stock quantities
- Always up-to-date with catalog

### 6. CLI Testing Tools

#### SKU Universe Self-Test

```bash
php tools/sku_universe_selftest.php
```

**Checks**:
- All SKUs are 3-part format
- Format violations
- Missing SKUs in stock files
- Overall system consistency

#### Stock Sync Dry Run

```bash
php tools/stock_sync_dry_run.php
```

**Shows**:
- What SKUs would be added to stock.json
- What SKUs would be added to branch_stock.json
- Does NOT modify any files (safe to run)

#### NA SKU Generation Test

```bash
php tools/test_na_sku_generation.php
```

**Tests**:
- Empty fragrance → NA
- 'null' fragrance → NA
- 'none' fragrance → NA
- Actual fragrance → correct code
- Lists existing NA SKUs

## Usage Guide

### For Admins

#### Adding New Accessories

1. Go to **Admin → Accessories**
2. Fill in the form:
   - **ID (slug)**: Use lowercase with underscores (e.g., `test_product`)
   - **Has fragrance selector**: Check if product has fragrance options
   - **Allowed fragrances**: Select fragrances (if fragrance selector enabled)
   - **Volumes**: Select if multiple volumes exist
3. Click **Save Accessory**
4. System automatically:
   - Generates SKUs (with NA if no fragrance)
   - Adds SKUs to stock files with qty=0
   - Product appears in Universe and CSV export

#### Synchronizing Stock Files

1. Go to **Admin → Stock**
2. Click **View SKU Universe Diagnostics** to check status
3. If missing SKUs found, click **Sync SKU Universe**
4. Confirm the action
5. System adds missing SKUs with qty=0
6. Backups created in `data/backups/`

#### Exporting Stock Data

1. Go to **Admin → Stock**
2. Click **Download CSV Template**
3. CSV downloads with all current SKUs and quantities
4. Open in Excel or Google Sheets
5. Edit quantities as needed
6. Upload via **Upload Stock File**

### For Developers

#### Generating SKUs in Code

```php
require_once 'includes/helpers.php';

// Product with fragrance
$sku = generateSKU('diffuser_classic', '125ml', 'cherry_blossom');
// Result: DF-125-CHE

// Product without fragrance
$sku = generateSKU('test_product', 'standard', '');
// Result: TES-STA-NA

// Always returns 3-part format
```

#### Loading Universe

```php
require_once 'includes/stock/sku_universe.php';

$universe = loadSkuUniverse();
// Returns: ['SKU' => ['sku', 'productId', 'product_name', 'category', 'volume', 'fragrance', ...]]

$diagnostics = getSkuUniverseDiagnostics();
// Returns: ['universe_count', 'missing_in_stock_json', 'format_violations', ...]
```

#### Initializing Missing SKUs

```php
require_once 'includes/stock/sku_universe.php';

// Dry run (no changes)
$result = initializeMissingSkuKeys(true);

// Actual sync (creates backups, adds SKUs)
$result = initializeMissingSkuKeys(false);

if ($result['success']) {
    $addedToStock = $result['added_to_stock'];
    $addedToBranches = $result['added_to_branches'];
    // Process results
}
```

## File Structure

```
includes/
  helpers.php                 # generateSKU() - core SKU generation
  stock/
    sku_universe.php          # Universe loader and diagnostics

admin/
  stock.php                   # Stock management (with ?debug=1)
  accessories.php             # Accessory management (auto-sync)
  export_stock_csv.php        # Dynamic CSV export

tools/
  sku_universe_selftest.php   # Validation tests
  stock_sync_dry_run.php      # Preview sync changes
  test_na_sku_generation.php  # Test NA generation

data/
  stock.json                  # Global stock quantities
  branch_stock.json           # Per-branch stock quantities
  products.json               # Product catalog
  accessories.json            # Accessory catalog
  backups/                    # Timestamped backups
```

## Safety Features

### No Data Loss

- Sync only ADDS new SKUs with qty=0
- Existing quantities are NEVER modified by sync
- Backups created before ANY write operation
- All changes logged to `logs/stock.log`

### SKU Collision Prevention

When creating new accessories in admin:
- System checks if SKU would collide with existing catalog SKU
- If collision detected, error shown with details
- Suggests using different product ID
- Prevents accidental overwrite of catalog products

### Validation

- All SKUs validated as 3-part format
- Quantities validated as non-negative integers
- Total must match sum of branch quantities
- CSRF tokens protect admin actions

## Common Tasks

### Check System Health

```bash
cd /home/runner/work/BV_alter/BV_alter
php tools/sku_universe_selftest.php
```

### Preview What Would Be Synced

```bash
php tools/stock_sync_dry_run.php
```

### Generate Updated CSV Template

1. Visit `admin/stock.php`
2. Click "Download CSV Template"
3. CSV generated dynamically from current Universe

### Add New Product Without Fragrance

1. Admin → Accessories
2. Create product with ID `my_product`
3. Leave "Enable fragrance selector" UNCHECKED
4. Save
5. System generates: `MY-STA-NA` (or appropriate prefix)
6. SKU appears in Universe, stock files, CSV export

## Troubleshooting

### "Missing SKUs" shown in diagnostics

**Solution**: Run Sync SKU Universe from admin/stock.php

### New accessory not showing in stock list

**Solution**: 
1. Check Universe diagnostics
2. Run sync if needed
3. Verify accessory saved to accessories.json

### CSV export missing products

**Issue**: CSV generated from old template file

**Solution**: Use "Download CSV Template" button (now generates dynamically)

### Format violations detected

**Solution**: Check diagnostics for specific SKUs, ensure all follow PREFIX-VOLUME-FRAGRANCE

## Testing Checklist

- [x] All SKUs are 3-part format
- [x] NA fragrance works for no-fragrance products
- [x] Universe includes all catalog products
- [x] Universe includes all accessories
- [x] Debug panel shows accurate counts
- [x] Sync adds missing SKUs with qty=0
- [x] Sync creates backups
- [x] Sync never modifies existing quantities
- [x] CSV export is dynamic from Universe
- [x] CSV includes all columns
- [x] NA fragrances labeled correctly in CSV
- [x] New accessories auto-sync
- [x] SKU collision prevention works
- [x] CLI tools run successfully

## Maintenance

### Regular Tasks

1. **Weekly**: Review debug panel for orphaned SKUs
2. **After catalog changes**: Run selftest
3. **Before bulk updates**: Export CSV backup
4. **After adding products**: Verify in Universe diagnostics

### Backup Location

All backups stored in: `data/backups/`

Format: `[filename].[YYYYMMDD-HHMMSS].json`

Example: `stock.20231215-143022.json`

## Support

For issues or questions:
1. Check debug panel: `admin/stock.php?debug=1`
2. Run selftest: `php tools/sku_universe_selftest.php`
3. Check logs: `logs/stock.log`
4. Review this documentation

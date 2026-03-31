# Admin Data Sources and Deletion Guide

## Overview

This document describes the data sources used by each admin page, the deletion cascades for products and branches, backup behavior, and validation procedures.

## Menu Item → File → Data Sources Mapping

| Menu Item | Admin Page File | Primary Data Sources | Secondary Data Sources |
|-----------|----------------|---------------------|----------------------|
| Products | `admin/products.php` | `data/products.json` | `data/i18n/ui_*.json` |
| Accessories | `admin/accessories.php` | `data/products.json` (filtered by category="accessories") | `data/accessories.json`, `data/i18n/ui_*.json` |
| Stock | `admin/stock.php` | SKU Universe (`includes/stock/sku_universe.php`) | `data/stock.json`, `data/branch_stock.json`, `data/products.json`, `data/accessories.json` |
| Branches | `admin/branches.php` | `data/branches.json`, `data/branch_stock.json` | - |
| CSV Export | `admin/export_stock_csv.php` | SKU Universe (`includes/stock/sku_universe.php`) | All of the above |

## Data Sources in Detail

### Products List (`admin/products.php`)
- **Reads**: `data/products.json` directly
- **Displays**: All products regardless of category
- **Filter**: Category dropdown filters display
- **i18n**: Product names/descriptions from `data/i18n/ui_*.json` using `product.{productId}.name` keys

### Accessories List (`admin/accessories.php`)
**IMPORTANT: Fixed in Phase 1**
- **Canonical Source**: `data/products.json` filtered by `category: "accessories"`
- **Configuration**: `data/accessories.json` contains fragrance/volume selectors and pricing details
- **Join Logic**: 
  - Lists all products with `category="accessories"` from products.json
  - Merges with accessories.json config if exists
  - Shows "Orphan" indicator if no accessories.json config
  - Provides "Create Config" button for orphans
- **Why**: Ensures accessories visible in Stock/CSV are also manageable in Accessories admin

### Stock Management (`admin/stock.php`)
- **Canonical Source**: SKU Universe (`includes/stock/sku_universe.php`)
- **Universe Generation**:
  1. Generates all possible SKUs from products.json + accessories.json
  2. Merges with stock.json for quantities
  3. Merges with branch_stock.json for branch-specific data
  4. Returns complete list sorted by category/name/volume/fragrance
- **Why**: Ensures Stock UI always shows complete SKU list from catalog

### Branches Management (`admin/branches.php`)
- **Branch List**: `data/branches.json`
- **Stock Data**: `data/branch_stock.json` (keyed by branchId → SKU → quantity)
- **Display**: Shows all branches with ability to manage stock per branch

### CSV Export (`admin/export_stock_csv.php`)
- **Source**: SKU Universe (same as Stock page)
- **Columns**: SKU, product name, category, volume, fragrance, branch quantities, total
- **Branch Columns**: Dynamically generated from canonical branch list in `data/branches.json`

## SKU Format Rules

**Non-negotiable**: All SKUs must be 3-part format: `PREFIX-VOLUME-FRAGRANCE`

### Examples:
- `diffuser_classic-125ml-bellini` (standard product)
- `aroma_sashe-standard-cherry_blossom` (accessory with fragrance)
- `aroma_smart-standard-NA` (non-fragrance device)

### Fragrance Rules:
1. **Products with fragrance selector disabled**: Use `NA` as fragrance
2. **Products with fragrance selector enabled**: Use only saved `allowed_fragrances` list
3. **Never use implicit defaults**: No cherry_blossom or other defaults for non-fragrance items

### Collision Detection:
- **Phase 2 Fix**: Collision warnings now use SKU Universe validation
- **Distinguish**:
  - **ProductId conflict**: Product ID already exists (different issue)
  - **SKU collision**: Generated SKU already exists for another productId
- **Name resolution**: Always by productId using i18n lookup, never by parsing SKU

## Deletion Cascades

### Delete Product (permanent)

**Accessible from**:
- Products page: Delete button next to each product
- Accessories page: Delete button next to each accessory

**Implementation**: Shared function `deleteProduct($productId)` in `includes/helpers.php`

**Cascade steps**:
1. Creates timestamped backups of:
   - `products.json`
   - `accessories.json` (if product has accessory config)
   - `stock.json`
   - `branch_stock.json`
   - All `i18n/ui_*.json` files
2. Removes product from `data/products.json`
3. Removes product from `data/accessories.json` (if exists)
4. Identifies all SKUs for this productId using SKU Universe
5. Removes all identified SKUs from `data/stock.json`
6. Removes all identified SKUs from `data/branch_stock.json` (across all branches)
7. Removes i18n keys `product.{productId}.*` from all language files
8. Logs deletion to `logs/stock.log`

**What is NOT deleted**:
- Images (only references are removed, files remain in repository)

**Confirmation**: JavaScript confirm dialog warns about permanent deletion and lists what will be removed

**Example output**:
```
Product 'aroma_smart' deleted successfully! 
Removed from products.json, accessories.json, 
3 SKUs from stock.json, 9 entries from branch_stock.json, 
and 6 i18n keys.
```

### Delete Branch (permanent)

**Accessible from**:
- Branches page: Delete button next to each branch

**Implementation**: Shared function `deleteBranch($branchId)` in `includes/helpers.php`

**Cascade steps**:
1. Creates timestamped backups of:
   - `branches.json`
   - `branch_stock.json`
2. Removes branch from `data/branches.json`
3. Removes all stock entries for this branch from `data/branch_stock.json`
4. Logs deletion to `logs/stock.log`

**Impact on CSV Export**: 
- Deleted branches will NOT appear as columns in CSV export
- CSV export reads from canonical branch list in branches.json

**Confirmation**: JavaScript confirm dialog warns about permanent deletion

**Example output**:
```
Branch 'branch_invalid' deleted successfully! 
Removed 147 stock entries from branch_stock.json.
```

## Backup Behavior

### Automatic Backups
All destructive operations (product delete, branch delete, stock updates) create timestamped backups before writing.

**Backup location**: `data/backups/`

**Backup filename format**: `{original_filename}.{YYYYMMDD-HHMMSS}.{extension}`

**Example**:
```
data/backups/products.20241222-153045.json
data/backups/stock.20241222-153045.json
data/backups/branch_stock.20241222-153045.json
```

**Function**: `createStockBackup($filename)` in `includes/helpers.php`

**Failure handling**: If backup creation fails, the operation is aborted and no changes are made.

### Manual Backups
Backups are NOT created for:
- Viewing/reading operations
- GET requests
- Form displays

### Backup Retention
Backups are never automatically deleted. Manual cleanup may be needed periodically.

## Validation

### CLI Validation Tool

**Location**: `tools/validate_catalog_consistency.php`

**Usage**:
```bash
php tools/validate_catalog_consistency.php
```

**What it validates**:
1. **SKU Format**: Verifies all SKUs follow 3-part format (PREFIX-VOLUME-FRAGRANCE)
2. **Fragrance Rules**: Checks non-fragrance items use fragrance=NA
3. **Accessories Visibility**: Ensures accessories in products.json are manageable
4. **Orphan Reporting**: Lists products and accessories without proper configuration
5. **Branch Consistency**: Reports branches in branch_stock.json but not in branches.json
6. **Universe Consistency**: Checks SKU Universe matches stock files

**Exit codes**:
- `0`: All tests passed (with possible warnings)
- `1`: Tests failed with errors

**Sample output**:
```
╔════════════════════════════════════════════════════════════════╗
║  NicheHome Catalog Consistency Validation Tool                ║
╚════════════════════════════════════════════════════════════════╝

📁 Loading data files...
   ✓ products.json: 13 products
   ✓ accessories.json: 4 accessories
   ✓ stock.json: 215 SKUs
   ✓ branches.json: 3 branches
   ✓ SKU Universe: 220 total SKUs

🔍 Test 1: Validating SKU format (3-part: PREFIX-VOLUME-FRAGRANCE)...
   ✅ PASS: All SKUs follow 3-part format

🔍 Test 2: Validating non-fragrance items use fragrance=NA...
   ✅ PASS: Non-fragrance items correctly use fragrance=NA

...

╔════════════════════════════════════════════════════════════════╗
║  VALIDATION SUMMARY                                            ║
╚════════════════════════════════════════════════════════════════╝

✅ ALL TESTS PASSED! Catalog is consistent.
```

### Admin UI Diagnostics

**Location**: Stock page → "🔍 View SKU Universe Diagnostics" link

**URL**: `admin/stock.php?debug=1`

**Shows**:
- Universe SKU count
- Stock.json SKU count
- Branch stock SKU count
- Missing SKUs in stock.json
- Missing SKUs in branch_stock.json
- Extra (orphaned) SKUs in both files
- Format violations
- List of NA fragrance SKUs

**Usage**: Real-time diagnostic for identifying sync issues

## Common Operations

### After importing CSV with invalid branches
1. Go to Branches page
2. Identify invalid/test branches
3. Click Delete for each invalid branch
4. Confirm deletion
5. Run CLI validation: `php tools/validate_catalog_consistency.php`

### After creating new accessory in products.json
1. Go to Accessories page
2. Find the new accessory (marked as "Orphan")
3. Click "Create Config" to create accessories.json entry
4. Edit the accessory to configure fragrances/volumes
5. Save changes

### When SKU appears in Stock/CSV but not in Accessories list
**This issue is now fixed in Phase 1**
- The Accessories page now reads from products.json as canonical source
- All accessories with `category="accessories"` will appear
- Orphan indicators show which need configuration

### Sync SKU Universe (initialize missing SKUs)
**Location**: Stock page → "🔄 Sync SKU Universe" button

**What it does**:
- Adds missing SKUs from catalog to stock.json with qty=0
- Adds missing SKUs to branch_stock.json with qty=0
- Does NOT modify existing quantities
- Creates backups before changes

**When to use**:
- After adding new products
- After validation shows missing SKUs
- After imports that may have incomplete data

## Logging

All stock changes are logged to `logs/stock.log`

**Format**:
```
[YYYY-MM-DD HH:MM:SS] MESSAGE
```

**Example entries**:
```
[2024-12-22 15:30:45] PRODUCT DELETED: aroma_smart | SKUs removed: 3 | Stock SKUs: 3 | Branch entries: 9
[2024-12-22 15:31:12] BRANCH DELETED: branch_invalid | Stock entries removed: 147
[2024-12-22 15:32:05] SKU Universe Initialization: Added 5 SKUs to stock.json, 3 SKUs to branches
```

## Best Practices

1. **Always run validation** after bulk imports or manual JSON edits
2. **Review diagnostics** before and after major changes
3. **Keep backups** directory size reasonable (cleanup old backups periodically)
4. **Use admin UI** for deletes instead of manual JSON editing
5. **Sync Universe** after adding products to ensure stock files are up-to-date
6. **Check logs** if unexpected behavior occurs

## Troubleshooting

### Product visible in Stock but not in Accessories admin
- **Cause**: Product has `category="accessories"` in products.json but no accessories.json config
- **Solution**: Click "Create Config" button in Accessories admin (appears after Phase 1 fix)

### SKU collision warning for non-fragrance device
- **Cause**: Old logic used cherry_blossom default instead of NA
- **Solution**: Phase 2 fix ensures NA is used. Re-save the accessory.

### Branch appears in branch_stock.json but deleted
- **Cause**: Branch was deleted manually or inconsistently
- **Solution**: Use Branches admin Delete button to properly remove

### Validation fails with format violations
- **Cause**: Some SKUs don't follow 3-part format
- **Solution**: Identify SKUs in validation output, fix or remove them

### Changes not appearing in CSV export
- **Cause**: CSV export uses Universe, may need sync
- **Solution**: Run "Sync SKU Universe" from Stock page

## File References

### Implementation Files
- **Delete helpers**: `/includes/helpers.php` (lines ~2350-2650)
  - `deleteProduct($productId)` - Product deletion with full cascade
  - `deleteBranch($branchId)` - Branch deletion with stock cleanup
  - `createStockBackup($filename)` - Timestamped backup creation

- **SKU Universe**: `/includes/stock/sku_universe.php`
  - `loadSkuUniverse()` - Load complete SKU list
  - `generateCatalogSkus()` - Generate from products + accessories
  - `validateAdminProductSku()` - Collision validation
  - `getSkuUniverseDiagnostics()` - Diagnostics data

- **Products admin**: `/admin/products.php`
  - Reads: products.json
  - Delete action: calls deleteProduct()

- **Accessories admin**: `/admin/accessories.php`
  - Reads: products.json (filtered by category) + accessories.json
  - Shows: Orphan indicators, create config button
  - Collision check: Uses validateAdminProductSku() with NA for non-fragrance

- **Branches admin**: `/admin/branches.php`
  - Reads: branches.json, branch_stock.json
  - Delete action: calls deleteBranch()

- **Stock admin**: `/admin/stock.php`
  - Uses: SKU Universe for display
  - Sync Universe: initializeMissingSkuKeys()

- **CSV Export**: `/admin/export_stock_csv.php`
  - Uses: SKU Universe
  - Branch columns: from branches.json canonical list

### Data Files
- `/data/products.json` - Canonical product catalog
- `/data/accessories.json` - Accessory configuration (fragrance/volume)
- `/data/stock.json` - Global stock quantities
- `/data/branch_stock.json` - Per-branch stock quantities
- `/data/branches.json` - Branch definitions
- `/data/i18n/ui_*.json` - Translations (en, de, fr, it, ru, ukr)
- `/data/backups/` - Timestamped backup files

### Tools
- `/tools/validate_catalog_consistency.php` - CLI validation tool

## Version History

- **v1.0 (2024-12-22)**: Initial implementation
  - Phase 1: Fixed Products vs Accessories inconsistency
  - Phase 2: Unified collision detection with SKU Universe rules
  - Phase 3: Added permanent delete product functionality
  - Phase 4: Enhanced delete branch functionality
  - Phase 5: Created CLI validation tool and documentation

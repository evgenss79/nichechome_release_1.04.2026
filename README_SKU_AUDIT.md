# SKU Universe and Audit System

## Overview

The SKU Universe is the **single source of truth** for all SKUs in the NICHEHOME.CH system. It ensures that every SKU from all sources is visible and manageable across the entire platform.

## Problem Statement

Previously, the system had incomplete SKU visibility:
- **Stock management page** only showed SKUs present in `stock.json`
- **CSV export templates** only included SKUs from `stock.json`
- **Admin-added products** (especially Accessories like "Refill") were missing from global lists
- **Fragrance combinations** like "Salty Water" existed in some places but not others

This led to:
- Missing products in export templates
- Inability to manage stock for valid catalog items
- Data inconsistencies between sources

## Solution: SKU Universe

The SKU Universe aggregates ALL SKUs from ALL sources:

### SKU Sources

1. **Catalog-derived SKUs** (`products.json` + `accessories.json`)
   - All products with their variants (volumes)
   - All allowed fragrances per category
   - All accessories with their configurations
   - Generates: ~191 SKUs from active catalog

2. **Global Stock** (`data/stock.json`)
   - SKUs that have stock quantities tracked
   - May include legacy or discontinued products
   - Current: ~216 SKUs

3. **Branch Stock** (`data/branch_stock.json`)
   - SKUs with quantities at specific branches
   - Subset of global stock (usually)
   - Current: ~10 SKUs actively used

4. **Admin-added Products** (via `admin/accessories.php`)
   - Synced to both `accessories.json` and `products.json`
   - Participate in SKU Universe generation

### SKU Universe Structure

Each SKU in the universe contains:
```php
[
  'sku' => 'DF-125-BEL',
  'productId' => 'diffuser_classic',
  'product_name' => 'Classic Diffuser',
  'category' => 'aroma_diffusers',
  'volume' => '125ml',
  'fragrance' => 'bellini',
  'in_catalog' => true,      // Derivable from catalog
  'in_stock_json' => true,   // Present in stock.json
  'in_any_branch_json' => false,  // Present in any branch
  'stock_data' => [...]      // Original stock.json data if exists
]
```

## Implementation

### Core Module

**File:** `includes/stock/sku_universe.php`

**Key Functions:**

1. `loadSkuUniverse()`: Returns complete SKU universe
   - Merges all sources
   - Normalizes data
   - Sorts by category, product, volume, fragrance, SKU

2. `generateCatalogSkus()`: Generates all possible catalog SKUs
   - Processes products.json
   - Processes accessories.json (including fragrance selectors)
   - Applies category-specific fragrance rules

3. `getSkuAuditReport()`: Analyzes discrepancies
   - Counts SKUs per source
   - Identifies missing SKUs in each direction
   - Returns full universe for export

4. `initializeMissingSkuKeys()`: Safe initialization helper
   - Adds missing SKUs with quantity=0
   - **NEVER modifies existing quantities**
   - Supports dry-run mode
   - Creates backups before writing

### Updated Components

**1. Stock Management (`admin/stock.php`)**
- Changed from `getConsolidatedStockView()` to `getConsolidatedStockViewFromUniverse()`
- Now shows ALL SKUs, even if not in stock.json
- Missing quantities display as 0

**2. Template Generation (`admin/generate_templates.php`)**
- Uses `loadSkuUniverse()` instead of `stock.json`
- CSV/Excel exports now include ALL catalog SKUs
- Ensures "Salty Water" and "Refill" variants appear

**3. New Helper Function (`includes/helpers.php`)**
- Added `getConsolidatedStockViewFromUniverse()`
- Deprecated old `getConsolidatedStockView()` (backward compatibility)

## SKU Audit Dashboard

**Location:** `admin/sku_audit.php`

**Features:**

### Statistics
- Total SKUs in Universe
- Total in Catalog
- Total in stock.json
- Total in Branches

### Discrepancy Analysis

1. **In Catalog but NOT in stock.json**
   - Products you can sell but have no stock record
   - Safe to initialize to 0

2. **In Catalog but NOT in ANY branch**
   - Common for new products
   - Normal to have many here

3. **In stock.json but NOT in Catalog**
   - Legacy or discontinued products
   - Should be investigated but safe to keep

4. **In Branches but NOT in Catalog**
   - Data integrity issue
   - Should be rare - investigate immediately

### Actions

**Export Reports:**
- CSV format: All SKUs with metadata
- JSON format: Complete audit data structure

**Initialize Missing SKUs:**
- Preview (dry run): See what would be added
- Execute: Add missing SKUs with quantity=0
- Creates backups before modifying
- Logs all changes to `logs/stock_sku_audit.log`

## How to Use

### For Regular Operations

1. **Manage Stock:**
   - Go to `admin/stock.php`
   - All catalog SKUs now appear automatically
   - Edit quantities as needed
   - System handles missing keys gracefully

2. **Export Templates:**
   - Click "Download CSV Template" in Stock page
   - All SKUs are now included
   - Use for bulk updates

### For Auditing

1. **Run Audit:**
   ```
   Go to: admin/sku_audit.php
   ```

2. **Review Discrepancies:**
   - Check each section
   - Understand what's missing where

3. **Initialize Missing Keys (Optional):**
   - Click "Preview Initialization"
   - Review what will be added
   - Click "Execute Initialization" if approved
   - All changes are logged and backed up

### For Adding New Products

1. **Add Product/Accessory:**
   - Use `admin/accessories.php` or `admin/products.php`
   - Product is immediately added to SKU Universe

2. **Verify Visibility:**
   - Check `admin/stock.php` - should appear
   - Check CSV export - should be included
   - Check `admin/sku_audit.php` - should show in catalog

3. **Initialize Stock (if needed):**
   - Go to `admin/sku_audit.php`
   - Run initialization to add to stock.json/branches
   - Or manually set quantities in Stock page

## Safety Features

### No Data Loss

- **Backups:** All modifications create timestamped backups in `data/backups/`
- **Read-only Operations:** Audit and Universe loading never modify data
- **Explicit Confirmation:** Initialization requires preview + confirmation
- **Logging:** All changes logged to `logs/stock_sku_audit.log`

### Validation

- SKU normalization (trim, consistent format)
- Quantity validation (non-negative integers)
- File locking during writes
- CSRF protection on all forms

### Backward Compatibility

- Old `getConsolidatedStockView()` still works (deprecated)
- Existing JSON formats unchanged
- Checkout and order processing untouched

## Technical Details

### SKU Generation Rules

SKUs follow the pattern: `PREFIX-VOLUME-FRAGRANCE`

**Prefixes:**
- `DF`: Diffusers (diffuser_classic)
- `CD`: Candles (candle_classic)
- `HP`: Home Perfume (home_spray)
- `CP`: Car Perfume (car_clip)
- `TP`: Textile Perfume (textile_spray)
- `LE`: Limited Edition (all three cities)
- `ARO`: Aroma Sashe (aroma_sashe)
- `CHR`: Christmas Toy (christ_toy)

**Volume:**
- Numeric: `125`, `250`, `500`, `160`, etc.
- Special: `STA` (standard for accessories)

**Fragrance:**
- 3-letter codes from `fragrances.json`
- Custom codes: `SW` (salty_water), `SC` (salted_caramel)
- Override via `sku_suffix` in fragrances.json

**Examples:**
- `DF-125-BEL`: Diffuser 125ml Bellini
- `ARO-STA-SW`: Aroma Sashe with Salty Water
- `LE-270-NEW`: Limited Edition New York 270ml

### Fragrance Rules by Category

Defined in `includes/helpers.php::allowedFragrances()`:

- **Scented Candles:** Exclude etna, valencia, limited edition fragrances
- **Textile Perfume:** Exclude salted_caramel, cherry_blossom, dubai, salty_water, rosso, christmas_tree
- **Limited Edition:** ONLY new_york, abu_dhabi, palermo
- **Other categories:** All except limited edition fragrances

### Accessories with Fragrance Selectors

Accessories in `accessories.json` can have:
```json
{
  "has_fragrance_selector": true,
  "allowed_fragrances": ["bamboo", "blanc", "carolina", "dubai", "africa"]
}
```

This generates SKUs for each fragrance, e.g., "Refill" product:
- `REF-STA-BAM` (Bamboo)
- `REF-STA-BLA` (Blanc)
- `REF-STA-CAR` (Carolina)
- etc.

## Maintenance

### Regular Tasks

1. **After adding new products:**
   - Run SKU Audit to verify visibility
   - Initialize missing keys if needed

2. **Monthly:**
   - Review "In stock but not catalog" items
   - Archive discontinued products

3. **Before inventory counts:**
   - Export CSV template
   - Verify all active SKUs present

### Troubleshooting

**Q: New product doesn't appear in stock list**
- Check `admin/sku_audit.php` - is it in catalog?
- Verify product is active in products.json
- Check fragrance is allowed for category

**Q: Template missing some SKUs**
- Regenerate template: `php admin/generate_templates.php`
- Check if SKU Universe includes them (audit page)

**Q: Can't set stock for valid product**
- Initialize missing keys via audit page
- Or manually add to stock.json (not recommended)

## File Structure

```
includes/
  stock/
    sku_universe.php      # Core SKU Universe module
  helpers.php             # Updated with getConsolidatedStockViewFromUniverse()

admin/
  stock.php               # Uses SKU Universe for display
  sku_audit.php           # Audit dashboard (NEW)
  generate_templates.php  # Uses SKU Universe for export

data/
  stock.json              # Global stock quantities
  branch_stock.json       # Per-branch quantities
  products.json           # Product catalog
  accessories.json        # Accessories catalog

logs/
  stock_sku_audit.log     # Audit and initialization log
```

## Future Enhancements

Potential improvements:
- Auto-initialize new catalog SKUs on product creation
- Scheduled audit reports via email
- SKU lifecycle tracking (active/discontinued/archived)
- Bulk SKU operations (mark as discontinued, etc.)
- Integration with order history for "never sold" detection

## Verification Checklist

After implementation, verify:

- [ ] Stock page (`admin/stock.php`) shows ALL catalog SKUs
- [ ] CSV template includes "Salty Water" SKU (ARO-STA-SW)
- [ ] CSV template includes "Refill" SKUs (all fragrances)
- [ ] Audit page shows correct counts for all sources
- [ ] Initialization preview works (dry run)
- [ ] Initialization execution adds keys without data loss
- [ ] Existing stock quantities unchanged
- [ ] New accessory appears immediately after creation
- [ ] Backups created in data/backups/
- [ ] Changes logged to logs/stock_sku_audit.log

## Contact

For questions or issues with SKU Universe:
- Review this document
- Check `admin/sku_audit.php` for discrepancies
- Examine logs in `logs/stock_sku_audit.log`

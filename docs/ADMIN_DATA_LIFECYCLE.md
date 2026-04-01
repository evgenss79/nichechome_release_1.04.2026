# Admin Data Lifecycle Documentation

## Overview

This document describes the complete lifecycle of data management in the NicheHome admin system, including creation, synchronization, and deletion of products, accessories, and branches.

## Table of Contents

1. [Creating Data](#creating-data)
2. [Synchronizing Data](#synchronizing-data)
3. [Deleting Data](#deleting-data)
4. [Diagnostics & Reconciliation](#diagnostics--reconciliation)
5. [CLI Validation](#cli-validation)
6. [Best Practices](#best-practices)

---

## Creating Data

For the current canonical category/product CRUD flow, see `docs/ADMIN_CATEGORY_PRODUCT_MANAGEMENT.md`.

### Creating Products

**Location**: `admin/product-edit.php` or `admin/products.php`

**Process**:
1. Navigate to Products page
2. Click "Add New Product" or edit existing
3. Fill in product details:
   - Product ID (slug): lowercase, alphanumeric with underscores only
   - Category: Select from available categories
   - Variants: Add volume/price combinations
   - Images: Upload or specify image filenames
4. Save product

**What happens**:
- Product is added to `data/products.json`
- i18n entries can be added in language files (`data/i18n/ui_*.json`)
- SKUs are generated automatically based on:
  - Product ID (prefix)
  - Volumes from variants
  - Fragrances based on category rules

**SKU Generation Rules**:
- All SKUs follow 3-part format: `PREFIX-VOLUME-FRAGRANCE`
- For products with fragrance selector: Use allowed fragrances list
- For products without fragrance selector: Use `NA` as fragrance
- Example: `diffuser_classic-125ml-bellini` or `aroma_smart-standard-NA`

### Creating Accessories

**Location**: `admin/accessories.php`

**Process**:
1. Navigate to Accessories page
2. Click "Add New Accessory" or edit existing
3. Fill in accessory details:
   - Product ID (slug)
   - Names and descriptions in all languages
   - Images
   - Base price (if no volume selector)
   - Fragrance selector settings:
     - Enable/disable fragrance selector
     - Select allowed fragrances if enabled
   - Volume selector settings:
     - Enable/disable volume selector
     - Select volumes and set individual prices if enabled
4. Save accessory

**What happens**:
- Product is added to `data/products.json` with `category: "accessories"`
- Accessory config is added to `data/accessories.json` with selector settings
- i18n entries are created in all language files
- SKUs are generated and initialized in stock files
- Collision detection runs to prevent duplicate SKUs

**Important Notes**:
- If fragrance selector is disabled, SKUs will use `fragrance=NA`
- If volume selector is enabled, base price is ignored; use volume prices instead
- SKU collision check prevents creating accessories that would generate existing SKUs

### Creating Branches

**Location**: `admin/branches.php`

**Process**:
1. Navigate to Branches page
2. Fill in branch form:
   - Branch ID: lowercase, alphanumeric with underscores (e.g., `branch_zurich`)
   - Branch Name: Display name
   - Address: Physical address
   - Active: Enable/disable for pickup availability
3. Click "Save Branch"

**What happens**:
- Branch is added to `data/branches.json`
- Empty branch stock entry is created in `data/branch_stock.json`
- Branch becomes available in stock management
- Branch appears as column in CSV exports

---

## Synchronizing Data

### SKU Universe Synchronization

**Location**: `admin/stock.php` → "🔄 Sync SKU Universe" button

**Purpose**: Ensures all SKUs from catalog are present in stock files with default quantities

**Process**:
1. Navigate to Stock page
2. Click "Sync SKU Universe" button
3. Review sync results

**What happens**:
- Loads complete SKU Universe from catalog (products + accessories)
- Adds missing SKUs to `data/stock.json` with `quantity: 0`
- Adds missing SKUs to `data/branch_stock.json` for all branches with `quantity: 0`
- Creates timestamped backups before modifications
- Does NOT modify existing quantities
- Returns count of added SKUs

**When to use**:
- After creating new products or accessories
- After imports that may have incomplete stock data
- When diagnostics show missing SKUs
- Before CSV exports to ensure completeness

### Orphan Accessory Config Creation

**Location**: `admin/accessories.php` or `admin/diagnostics.php`

**Purpose**: Create configuration for products with `category="accessories"` that lack `accessories.json` entries

**Process**:
1. Identify orphan accessories (marked with yellow "⚠️ Orphan" badge)
2. Click "Create Config" or "➕ Create Config" button
3. Minimal config is created automatically

**What happens**:
- Entry is added to `data/accessories.json` with default settings:
  - Fragrance selector: disabled
  - Volume selector: disabled
  - Price from first variant in products.json
  - Images from products.json
- Accessory becomes editable in Accessories admin page
- No longer appears as orphan

**Post-creation**:
- Edit the accessory to configure:
  - Fragrance selector and allowed fragrances
  - Volume selector and volume prices
  - Multilingual names and descriptions

---

## Deleting Data

### Deleting Categories (Permanent, empty categories only)

**Location**:
- `admin/categories.php` → Delete button next to each category

**Implementation**: Shared function `deleteCategory($categoryId)` in `includes/helpers.php`

**Canonical safety rule**:
- A category can be deleted only when no product in `data/products.json` still references that category slug
- The system blocks deletion before any write if dependent products remain
- Admin must reassign or delete those products first

**Delete Process**:

1. **Validation**
   - Checks if category exists in `categories.json`
   - Checks whether any products still reference the category
   - Aborts with a clear error if products are still assigned

2. **Backup Creation**
   - Creates timestamped backups of:
     - `data/categories.json`
     - All `data/i18n/ui_*.json` files
     - All `data/i18n/categories_*.json` files
   - If backup fails, deletion is aborted (no changes made)

3. **Data Removal**
   - Removes category from `data/categories.json`
   - Removes `category.{categoryId}.*` translation groups from both translation file sets

4. **Catalog Refresh**
   - Updates catalog version so storefront/admin consumers see the deletion immediately

5. **Logging**
   - Logs deletion to `logs/stock.log`

**Resulting storefront behavior**:
- deleted category disappears from catalog cards
- deleted category disappears from navigation and footer lists
- `category.php?slug={categoryId}` redirects back to catalog because the category no longer exists

### Deleting Products (Permanent)

**Location**: 
- `admin/products.php` → Delete button next to each product
- `admin/diagnostics.php` → Delete button for orphan products

**Implementation**: Shared function `deleteProduct($productId)` in `includes/helpers.php`

**Cascade Process**:

1. **Validation**
   - Checks if product exists in products.json
   - Aborts if product not found

2. **Backup Creation**
   - Creates timestamped backups of all files to be modified:
     - `data/products.json`
     - `data/accessories.json` (if product has accessory config)
     - `data/stock.json`
     - `data/branch_stock.json`
     - All `data/i18n/ui_*.json` files
   - Backup format: `filename.YYYYMMDD-HHMMSS.extension`
   - Stored in: `data/backups/`
   - If backup fails, deletion is aborted (no changes made)

3. **SKU Identification**
   - Loads SKU Universe to find all SKUs for this productId
   - Identifies SKUs in both stock.json and branch_stock.json
   - Ensures complete removal (no orphan SKUs left)

4. **Data Removal**
   - Removes product from `data/products.json`
   - Removes product from `data/accessories.json` (if exists)
   - Removes all identified SKUs from `data/stock.json`
   - Removes all identified SKUs from `data/branch_stock.json` (all branches)
   - Removes i18n keys (`product.{productId}.*`) from all language files

5. **Logging**
   - Logs deletion to `logs/stock.log` with details:
     - Product ID deleted
     - Number of SKUs removed
     - Number of stock entries removed
     - Number of branch stock entries removed

6. **Confirmation**
   - Shows success message with removal counts
   - Example: "Product 'aroma_smart' deleted successfully! Removed from products.json, accessories.json, 3 SKUs from stock.json, 9 entries from branch_stock.json, and 6 i18n keys."

**What is NOT deleted**:
- Image files (only references are removed)
- Order history (past orders remain intact)
- Backup files (kept permanently unless manually cleaned)

**Confirmation Dialog**:
```
⚠️ PERMANENT DELETION

This will delete:
- Product from products.json
- Accessory config from accessories.json (if exists)
- All SKUs from stock.json
- All branch stock entries
- All i18n translations

Backups will be created.

Are you sure you want to delete {productId}?
```

### Deleting Accessories (Permanent)

**Location**: 
- `admin/accessories.php` → Delete button next to each accessory
- `admin/diagnostics.php` → Delete button for orphan accessories

**Process**: Same as deleting products (uses same `deleteProduct()` function)

**Notes**:
- Accessories are products with `category="accessories"`
- Deletion removes from both products.json and accessories.json
- All SKU cascades apply

### Deleting Branches (Permanent)

**Location**: 
- `admin/branches.php` → Delete button next to each branch
- `admin/diagnostics.php` → Delete button for orphan branches

**Implementation**: Shared function `deleteBranch($branchId)` in `includes/helpers.php`

**Cascade Process**:

1. **Validation**
   - Checks if branch exists in branches.json
   - Aborts if branch not found

2. **Backup Creation**
   - Creates timestamped backups of:
     - `data/branches.json`
     - `data/branch_stock.json`
   - If backup fails, deletion is aborted

3. **Data Removal**
   - Removes branch from `data/branches.json`
   - Removes all stock entries for this branch from `data/branch_stock.json`
   - Counts removed entries for confirmation

4. **Logging**
   - Logs deletion to `logs/stock.log`

5. **Impact on Other Systems**
   - Branch no longer appears in branch list dropdown
   - Branch column is excluded from CSV exports
   - Branch stock entries are permanently removed

**Confirmation Dialog**:
```
⚠️ PERMANENT DELETION

Delete branch {branchId} and remove all {count} stock entries?

Backups will be created.

Continue?
```

### Idempotent Deletion

All delete operations are idempotent:
- Deleting an already-missing item shows a warning, not an error
- No data is corrupted if deletion is attempted twice
- Backup creation prevents accidental data loss

---

## Diagnostics & Reconciliation

### Diagnostics Page

**Location**: `admin/diagnostics.php`

**Purpose**: Identify and fix data inconsistencies across the system

**What it shows**:

1. **Summary Cards**
   - Total issues count
   - Orphan accessories count
   - Unknown branches count
   - SKU format violations count
   - Orphan SKUs count

2. **Orphan Accessories**
   - Products with `category="accessories"` but no `accessories.json` config
   - Actions: "Create Config" or "Delete"
   - These appear in Stock/CSV but not in Accessories admin page

3. **Unknown Branches**
   - Branch IDs in `branch_stock.json` not defined in `branches.json`
   - May be from old imports or improperly deleted branches
   - Action: "Remove Branch" (permanent deletion)

4. **SKU Format Violations**
   - SKUs not following 3-part format
   - Lists SKU, part count, and productId
   - Critical errors that need manual fix

5. **Orphan SKUs**
   - SKUs in stock files but not generated from current catalog
   - May be from deleted products
   - Information only (no automatic fix)

**Actions Available**:
- **Create Config**: Creates minimal accessories.json entry for orphan accessories
- **Delete**: Permanently deletes product/accessory with full cascade
- **Remove Branch**: Permanently deletes branch and all stock entries

**When to use**:
- After CSV imports
- After manual JSON edits
- When products appear in stock but not in admin lists
- When branches show in CSV but shouldn't exist
- Regular data health checks

### SKU Universe Diagnostics

**Location**: `admin/stock.php?debug=1`

**Purpose**: Real-time diagnostic view of SKU consistency

**What it shows**:
- Universe SKU count vs stock file counts
- Missing SKUs in stock.json
- Missing SKUs in branch_stock.json
- Extra (orphan) SKUs in stock files
- SKU format violations
- List of NA fragrance SKUs

**Use case**: Quick technical diagnostic during development or troubleshooting

---

## CLI Validation

### validate_integrity.php

**Location**: `tools/validate_integrity.php`

**Usage**:
```bash
php tools/validate_integrity.php
```

**What it validates**:

1. **SKU Format**: All SKUs follow 3-part format (PREFIX-VOLUME-FRAGRANCE)
2. **Product References**: All productIds in stock files exist in products.json
3. **Branch References**: All branchIds in branch_stock.json exist in branches.json
4. **Fragrance Rules**: Non-fragrance items use `fragrance=NA`
5. **Orphan SKUs**: SKUs in stock files but not in catalog

**Exit Codes**:
- `0`: All validations passed (may have warnings)
- `1`: One or more critical validations failed

**Output**:
- Color-coded terminal output
- ✅ Green for passed tests
- ❌ Red for failed tests
- ⚠️  Yellow for warnings
- Detailed error listings with examples

**When to run**:
- After bulk imports
- After manual JSON edits
- Before production deployments
- After deletions to confirm cleanup
- As part of CI/CD pipeline

**Example output**:
```
╔════════════════════════════════════════════════════════╗
║ NicheHome Data Integrity Validation Tool              ║
╚════════════════════════════════════════════════════════╝

📁 Loading data files...
   ✓ products.json: 13 products
   ✓ stock.json: 215 SKUs
   ✓ branch_stock.json: 3 branches
   ✓ branches.json: 3 branches
   ✓ SKU Universe: 220 total SKUs

🔍 Test: Validate SKU format (3-part: PREFIX-VOLUME-FRAGRANCE)
   ✅ PASS: All 220 SKUs follow 3-part format

🔍 Test: Validate all productIds in stock.json exist in products.json
   ✅ PASS: All productIds in stock.json exist in products.json

...

╔════════════════════════════════════════════════════════╗
║ VALIDATION SUMMARY                                     ║
╚════════════════════════════════════════════════════════╝

✅ ALL VALIDATIONS PASSED
Your data is consistent and properly structured.
```

### validate_catalog_consistency.php

**Location**: `tools/validate_catalog_consistency.php`

**Purpose**: More comprehensive validation including product configuration

**Differences from validate_integrity.php**:
- More detailed product/accessory config checks
- Validates i18n completeness
- Checks variant definitions
- Broader scope, longer runtime

**When to use**: Periodic comprehensive audits, not for quick checks

---

## Best Practices

### General Guidelines

1. **Always use admin UI for deletions**
   - Never manually edit JSON files to remove items
   - Use Products/Accessories/Branches admin pages
   - Ensures proper cascading and backups

2. **Run validation after bulk operations**
   - After CSV imports: `php tools/validate_integrity.php`
   - After manual edits: Check diagnostics page
   - Before deployments: Run full validation

3. **Review diagnostics regularly**
   - Check `admin/diagnostics.php` weekly
   - Fix orphan items promptly
   - Keep branch list clean

4. **Sync Universe after catalog changes**
   - After adding products/accessories
   - Before CSV exports
   - After diagnostics show missing SKUs

5. **Backup retention**
   - Backups are created automatically
   - Never automatically deleted
   - Manually clean old backups periodically (keep last month)
   - Backups location: `data/backups/`

6. **Understand SKU format**
   - Always 3-part: PREFIX-VOLUME-FRAGRANCE
   - Non-fragrance items use NA
   - Never manually create SKUs

### Workflow: Adding New Accessory

1. Navigate to Accessories page
2. Fill in all required fields
3. Configure fragrance/volume selectors
4. Save accessory
5. System automatically:
   - Creates products.json entry
   - Creates accessories.json config
   - Creates i18n entries
   - Initializes SKUs in stock files
6. Verify in Stock page that SKUs appear
7. Set initial stock quantities if needed

### Workflow: Removing Old Product

1. Navigate to Products or Accessories page
2. Click Delete next to product
3. Confirm deletion in dialog
4. System automatically:
   - Creates backups
   - Removes from all data files
   - Removes all SKUs
   - Removes branch stock
   - Removes i18n entries
   - Logs deletion
5. Verify in diagnostics that no orphan SKUs remain
6. Run CLI validation: `php tools/validate_integrity.php`

### Workflow: Cleaning Up After Import

1. Import CSV with new data
2. Navigate to Diagnostics page
3. Review unknown branches
4. Delete invalid/test branches
5. Review orphan accessories
6. Either create configs or delete
7. Run Stock → Sync Universe
8. Run CLI validation
9. Check CSV export to verify

### Troubleshooting

**Problem**: Product appears in Stock but not in Accessories admin
- **Cause**: Product has `category="accessories"` but no accessories.json config
- **Solution**: Go to Diagnostics → Click "Create Config" or use Accessories page

**Problem**: Branch appears in CSV export but was deleted
- **Cause**: Branch exists in branch_stock.json but not in branches.json
- **Solution**: Go to Diagnostics → Click "Remove Branch"

**Problem**: SKU format violations in validation
- **Cause**: Old SKUs or manual edits not following 3-part format
- **Solution**: Identify violating SKUs, fix source product, or remove manually

**Problem**: Validation shows orphan SKUs
- **Cause**: SKUs from deleted products still in stock files
- **Solution**: Delete parent product again, or manually remove from stock files

**Problem**: CSV export missing columns
- **Cause**: Branches may have been manually deleted
- **Solution**: Check branches.json, use admin to properly delete

---

## File References

### Implementation Files

- `/includes/helpers.php`: Delete functions (`deleteProduct`, `deleteBranch`)
- `/includes/stock/sku_universe.php`: SKU generation and validation
- `/admin/products.php`: Product management
- `/admin/accessories.php`: Accessory management
- `/admin/branches.php`: Branch management
- `/admin/diagnostics.php`: Data diagnostics and reconciliation
- `/admin/stock.php`: Stock management and universe sync

### Data Files

- `/data/products.json`: Product catalog (canonical)
- `/data/accessories.json`: Accessory configurations
- `/data/stock.json`: Global stock quantities
- `/data/branch_stock.json`: Per-branch stock quantities
- `/data/branches.json`: Branch definitions (canonical)
- `/data/i18n/ui_*.json`: Translations for all languages
- `/data/backups/`: Timestamped backup files

### Tools

- `/tools/validate_integrity.php`: Quick data integrity validation
- `/tools/validate_catalog_consistency.php`: Comprehensive catalog validation

### Documentation

- `/docs/ADMIN_DATA_SOURCES_AND_DELETION.md`: Technical deletion documentation
- `/docs/ADMIN_DATA_LIFECYCLE.md`: This document (lifecycle guide)

---

## Version History

- **v1.0 (2024-12-22)**: Initial documentation
  - Comprehensive lifecycle documentation
  - Delete cascades and backups
  - Diagnostics page
  - CLI validation tool

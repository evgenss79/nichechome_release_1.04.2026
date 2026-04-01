# Stock Import Implementation Guide

## Overview

This implementation provides a consolidated stock management system for NicheHome with:
- Consolidated stock view showing all branches in one table
- Excel/CSV import functionality
- Automatic validation and conflict resolution
- Timestamped backups before all changes
- Comprehensive logging

## Data Sources

### Primary Files

#### `data/stock.json`
- **Purpose**: SKU catalog with product metadata and aggregate totals
- **Structure**:
```json
{
  "SKU": {
    "productId": "string",
    "volume": "string",
    "fragrance": "string",
    "quantity": number,
    "lowStockThreshold": number,
    "total_qty": number
  }
}
```
- **Keys**:
  - `SKU`: Unique product identifier (e.g., "DF-125-BEL")
  - `productId`: Product type (e.g., "diffuser_classic")
  - `volume`: Size/volume (e.g., "125ml", "standard")
  - `fragrance`: Fragrance code (e.g., "bellini", "cherry_blossom")
  - `quantity`: Aggregate quantity aligned to the sum of all branch quantities for the SKU
  - `total_qty`: Sum of all branch quantities
  - `lowStockThreshold`: Alert threshold

#### `data/branch_stock.json`
- **Purpose**: Per-branch stock quantities shown and edited on Admin → STOCK
- **Structure**:
```json
{
  "branch_id": {
    "SKU": {
      "quantity": number
    }
  }
}
```
- **Keys**:
  - `branch_id`: Branch identifier (e.g., "branch_1", "branch_central", "branch_zurich")
  - `SKU`: Product SKU
  - `quantity`: Stock quantity at this branch

#### `data/branches.json`
- **Purpose**: Branch metadata
- **Structure**: Array of branch objects
- **Note**: Branch IDs in `branch_stock.json` are canonical. This file provides display names only.

## Branch Identifiers

Current branches (from `branch_stock.json`):
- `branch_1`
- `branch_2`
- `branch_3`

## Implementation Changes

### New Files
1. `admin/stock.php` - Consolidated stock view (replaces old version)
2. `admin/stock_import.php` - Excel/CSV import interface
3. `admin/generate_templates.php` - Template generator script
4. `admin/templates/stock_import_template.xlsx` - Excel template
5. `admin/templates/stock_import_template.csv` - CSV template
6. `README_STOCK_IMPORT.md` - This documentation

### Modified Files
1. `includes/helpers.php` - Added helper functions:
   - `createStockBackup($filename)` - Creates timestamped backups
   - `logStockChange($message)` - Logs to `logs/stock.log`
   - `validateStockQuantity($value)` - Validates quantities
   - `getAllBranches()` - Gets branch IDs and names
   - `getConsolidatedStockView()` - Builds consolidated view
   - `updateConsolidatedStock($sku, $branchQuantities)` - Updates stock with validation

2. `composer.json` - Added for PhpSpreadsheet dependency
3. `.gitignore` - Updated to exclude vendor/ and backups/

### New Directories
- `data/backups/` - Timestamped JSON backups
- `admin/templates/` - Import templates
- `logs/` - Stock change logs (if not existing)

## Features

### 1. Consolidated Stock View (`admin/stock.php`)

**UI Elements**:
- Table with columns: SKU, Product, Volume, Fragrance, [Branch Columns], TOTAL, Actions
- Each row allows editing branch quantities independently
- TOTAL updates automatically from the branch inputs
- TOTAL is read-only / computed
- "Save" button per row

**Validation Rules**:
- TOTAL must equal sum of all branch quantities
- All branch quantities must be non-negative integers
- TOTAL is never used as an authoritative writer

**Features**:
- Search/filter by product name or SKU
- Sort by total (ascending/descending) or SKU
- Visual feedback for validation errors
- CSRF protection
- Automatic backup before save
- Comprehensive logging

### 2. Excel/CSV Import (`admin/stock_import.php`)

**Import Process**:
1. Download template (XLSX or CSV) from stock page
2. Fill in quantities for each SKU and branch
3. Upload file
4. If CheckControl enabled: Resolve conflicts for SKUs with existing stock
5. Review import summary

**Template Format**:
```
| sku         | product_name | category | volume | fragrance_key | fragrance_label | branch_1 | branch_2 | branch_3 | total |
|-------------|--------------|----------|--------|----------------|-----------------|----------|----------|----------|-------|
| DF-125-BEL  | Diffuser     | aroma_diffusers | 125ml | bellini | Bellini | 5 | 10 | 0 | 15 |
```

**Validation**:
- All SKUs must exist in system
- All branch quantities must be non-negative integers
- TOTAL must equal sum of branches
- File size limit: 10 MB

**CheckControl Feature**:
- When enabled: Prompts for each SKU with existing stock
- Options per SKU:
  - **Replace**: Overwrite current branch quantities
  - **Add**: Add import quantities to the current branch quantities
  - **Skip**: Don't import this SKU
- When disabled: All imports replace existing stock

**Import Summary**:
- Total SKUs processed
- Updated count
- Replaced count (CheckControl: replace)
- Added count (CheckControl: add)
- Skipped count
- Failed count with error details

### 3. Security & Safety

**Security Measures**:
- CSRF token validation on all forms
- Admin authentication required
- File upload restrictions (type, size)
- Uploaded files deleted immediately after parsing

**Data Safety**:
- Timestamped backups before all writes:
  - `data/backups/stock.YYYYMMDD-HHMMSS.json`
  - `data/backups/branch_stock.YYYYMMDD-HHMMSS.json`
- Comprehensive logging to `logs/stock.log`:
  - Timestamp
  - SKU
  - Branch quantities (before/after)
  - Total (before/after)
  - Admin user (if available)

**Validation**:
- Client-side validation for immediate feedback
- Server-side validation for security
- No silent failures - all errors shown in UI and logged

## Manual Test Plan

### Test 1: View Consolidated Stock
**Steps**:
1. Navigate to Admin → Stock
2. Observe table with SKU rows and branch columns

**Expected**:
- Table displays all SKUs
- Branch columns show current quantities
- TOTAL column shows sum of branches
- Search and sort controls work

**Status**: [ ]

---

### Test 2: Edit Branch Quantity - Valid
**Steps**:
1. Find a SKU row
2. Change one or more branch quantities
3. Confirm TOTAL updates automatically to the new sum
4. Click Save

**Expected**:
- Row saves successfully
- Success message appears
- Backup created in `data/backups/`
- Change logged to `logs/stock.log`
- Page reload shows updated values

**Status**: [ ]

---

### Test 3: TOTAL Is Derived / Read-Only
**Steps**:
1. Find a SKU row
2. Confirm TOTAL is visible but read-only
3. Change one branch quantity
4. Confirm TOTAL updates automatically

**Expected**:
- TOTAL cannot be submitted as an independent writer
- Branch quantities remain the only editable inputs
- Save persists the edited branch quantities only

**Status**: [ ]

---

### Test 4: Download Templates
**Steps**:
1. Navigate to Admin → Stock
2. Click "Download CSV Template"

**Expected**:
- CSV file downloads successfully
- File contains all current SKUs
- Files have columns: sku, product_name, category, volume, fragrance_key, fragrance_label, [all branches], total
- Current quantities pre-filled

**Status**: [ ]

---

### Test 7: Import Replace (No Conflicts)
**Steps**:
1. Download Excel template
2. Change quantities for several SKUs (use SKUs with zero stock)
3. Ensure TOTAL equals sum of branches
4. Navigate to Admin → Stock → Upload Stock File
5. Upload file with CheckControl enabled
6. No conflicts should appear → import proceeds automatically

**Expected**:
- Import summary shows updated count
- Stock page reflects new quantities
- Backup created
- Changes logged

**Status**: [ ]

---

### Test 8: Import Replace (With Conflicts)
**Steps**:
1. Download Excel template
2. Change quantities for SKUs that have existing stock
3. Ensure TOTAL equals sum
4. Upload file with CheckControl enabled
5. Conflict resolution dialog appears
6. Select "Replace" for all conflicts
7. Confirm import

**Expected**:
- Conflicts listed correctly
- After confirmation, quantities replaced exactly
- Import summary shows replaced count
- Backup created
- Changes logged

**Status**: [ ]

---

### Test 9: Import Add (With Conflicts)
**Steps**:
1. Note current quantities for a SKU (e.g., branch_1: 10, branch_2: 15, total: 25)
2. In template, set quantities (e.g., branch_1: 5, branch_2: 10, total: 15)
3. Upload with CheckControl enabled
4. Select "Add" for conflicts
5. Confirm import

**Expected**:
- New quantities = old + import
- Example result: branch_1: 15, branch_2: 25, total: 40
- Import summary shows added count
- Backup created
- Changes logged

**Status**: [ ]

---

### Test 10: Import Skip Conflicts
**Steps**:
1. Prepare template with mix of new and existing SKUs
2. Upload with CheckControl enabled
3. Select "Skip" for conflicting SKUs
4. Confirm import

**Expected**:
- Skipped SKUs unchanged
- Non-conflicting SKUs updated
- Import summary shows skipped count

**Status**: [ ]

---

### Test 11: Import Without CheckControl
**Steps**:
1. Prepare template with quantities
2. Uncheck "Enable CheckControl"
3. Upload file

**Expected**:
- No conflict resolution dialog
- All SKUs replaced automatically (default action)
- Import summary shows replaced count

**Status**: [ ]

---

### Test 12: Import Validation - Unknown SKU
**Steps**:
1. Edit template, add row with non-existent SKU (e.g., "FAKE-SKU-123")
2. Upload file

**Expected**:
- Error: "Unknown SKU 'FAKE-SKU-123'"
- Import rejected
- No changes made

**Status**: [ ]

---

### Test 13: Import Validation - Negative Quantity
**Steps**:
1. Edit template, set a branch quantity to negative number (e.g., -5)
2. Upload file

**Expected**:
- Error: "Quantity cannot be negative"
- Import rejected
- No changes made

**Status**: [ ]

---

### Test 14: Import Validation - Total Mismatch
**Steps**:
1. Edit template: branch_1: 10, branch_2: 20, total: 50 (should be 30)
2. Upload file

**Expected**:
- Error: "TOTAL must equal sum of branch quantities"
- Import rejected
- No changes made

**Status**: [ ]

---

### Test 15: Import CSV Format
**Steps**:
1. Download CSV template
2. Edit in Excel or text editor
3. Upload CSV file

**Expected**:
- CSV parsed successfully
- Import proceeds same as Excel
- All validations apply

**Status**: [ ]

---

### Test 16: Backup Files Created
**Steps**:
1. Make any stock change via UI or import
2. Check `data/backups/` directory

**Expected**:
- Files named `stock.YYYYMMDD-HHMMSS.json` and `branch_stock.YYYYMMDD-HHMMSS.json`
- Files contain state before change
- Timestamp matches change time

**Status**: [ ]

---

### Test 17: Logging
**Steps**:
1. Make several stock changes (UI and import)
2. Check `logs/stock.log`

**Expected**:
- Each change logged with:
  - Timestamp
  - SKU
  - Branch quantities
  - Old and new totals
- Log entries are chronological and complete

**Status**: [ ]

---

### Test 18: No Side Effects on Orders
**Steps**:
1. Check existing orders in system
2. Make stock changes
3. Re-check orders

**Expected**:
- No orders modified
- Order data unchanged
- No impact on order processing

**Status**: [ ]

---

### Test 19: No Side Effects on Products
**Steps**:
1. Check product list
2. Make stock changes
3. Re-check products

**Expected**:
- No products modified
- Product data unchanged
- No new products created

**Status**: [ ]

---

### Test 20: No Side Effects on Customers
**Steps**:
1. Check customer list
2. Make stock changes
3. Re-check customers

**Expected**:
- No customers modified
- Customer data unchanged

**Status**: [ ]

---

## Troubleshooting

### Issue: Branches not showing
**Cause**: `branch_stock.json` structure mismatch
**Solution**: Verify branch IDs in `branch_stock.json` match expected format

### Issue: Import fails silently
**Cause**: PHP error or exception not caught
**Solution**: Check PHP error logs, enable error display in development

### Issue: Templates have zero quantities
**Cause**: `branch_stock.json` not initialized for all SKUs
**Solution**: This is normal for new SKUs. Fill in desired quantities.

### Issue: CSRF token error
**Cause**: Session expired or page cached
**Solution**: Refresh page to get new token

### Issue: File upload fails
**Cause**: File too large or wrong format
**Solution**: Verify file size < 10MB and format is .csv

## Regenerating Templates

After adding new products or branches, regenerate templates:

```bash
cd /home/runner/work/BV_alter/BV_alter/admin
php generate_templates.php
```

This updates both XLSX and CSV templates with current SKUs and branches.

## Maintenance Notes

### Adding New Branches
1. Add branch to `branches.json`
2. Create matching branch key in `branch_stock.json`
3. Regenerate templates
4. New branch will appear in stock UI automatically

### Adding New Products
1. Add product to `products.json`
2. Add SKU to `stock.json` with metadata
3. Regenerate templates
4. SKU will appear in consolidated view

### Backup Retention
Consider implementing automated cleanup of old backups in `data/backups/`:
- Keep last 30 days
- Or keep last 100 files
- Compress older backups

### Log Rotation
Consider rotating `logs/stock.log`:
- Daily or weekly rotation
- Keep last 90 days
- Compress older logs

## Architecture Decisions

### Why JSON, Not Database?
- Requirement: "Production already contains real data... Do not modify DB schema"
- JSON provides flexibility and version control friendly format
- Backups are simple file copies

### Verification commands used in this implementation

```bash
php tests/test_stock_single_source_of_truth.php
php tests/test_branch_stock_pickup_alignment.php
php tools/sku_universe_selftest.php
```

### Why Separate Import Page?
- Keeps main stock view clean and fast
- Import is less frequent operation
- Allows multi-step process with conflict resolution
- Better UX for complex operations

### Why CheckControl?
- Prevents accidental data loss
- Allows flexible import scenarios (add vs replace)
- Aligns with requirement: "no silent failures"

## Future Enhancements (Out of Scope)

- Bulk edit multiple rows at once
- Import history/audit trail UI
- Rollback to previous backup from UI
- Email notifications on low stock
- Stock forecasting based on order history
- API endpoints for programmatic access
- Multi-user concurrent editing with conflict resolution
- Mobile-responsive UI improvements

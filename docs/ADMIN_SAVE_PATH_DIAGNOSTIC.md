# Admin Save Path Diagnostic

## Executive Summary

**Status**: ✅ Canonical data flow is correct and consistent.

Admin saves to the same source that SKU Universe reads from. No mismatch detected.

---

## 1. Admin Write Path (Create/Save)

### Accessories Creation: `admin/accessories.php`

**Save Sequence** (lines 337-358):

1. **Save to accessories.json**
   - **File**: `admin/accessories.php:337`
   - **Function**: `saveJSON('accessories.json', $accessories)`
   - **Target**: `data/accessories.json`
   - **Data Structure**: Array indexed by accessory ID with metadata

2. **Save i18n translations**
   - **File**: `admin/accessories.php:339`
   - **Function**: `saveAccessoryI18N($id, $names, $descriptions)`
   - **Target**: `data/i18n/ui_*.json` (6 language files)
   - **Purpose**: Store product names and descriptions for each language

3. **Sync to products.json**
   - **File**: `admin/accessories.php:342`
   - **Function**: `syncAccessoryToProducts(...)`
   - **Implementation**: Lines 91-139 in `admin/accessories.php`
   - **Target**: `data/products.json`
   - **Purpose**: Makes accessory available to product listing pages
   - **Critical**: Writes to `data/products.json` directly using `file_put_contents()`

4. **Auto-initialize stock**
   - **File**: `admin/accessories.php:346`
   - **Function**: `initializeMissingSkuKeys(false)`
   - **Source**: `includes/stock/sku_universe.php`
   - **Effect**: Adds new SKUs to `data/stock.json` and `data/branch_stock.json` with qty=0
   - **Creates**: Timestamped backups before writing

### Products Creation: Similar pattern (if applicable)

Products would follow same pattern through their respective admin pages.

---

## 2. SKU Universe Read Path

### Function: `loadSkuUniverse()` in `includes/stock/sku_universe.php`

**Line 34**: Function definition

**Data Sources Read** (lines 36-40):

```php
$stock = loadJSON('stock.json');              // data/stock.json
$branchStock = loadBranchStock();             // data/branch_stock.json
$products = loadJSON('products.json');        // data/products.json
$accessories = loadJSON('accessories.json');  // data/accessories.json
$fragrances = loadJSON('fragrances.json');    // data/fragrances.json
```

**Processing Flow**:

1. **Line 45**: Calls `generateCatalogSkus($products, $accessories, $fragrances)`
   - **Function location**: Line 169
   - **Reads from**: `$products` and `$accessories` arrays (already loaded from JSON)
   - **Processes**: Iterates through products.json and accessories.json to generate SKUs

2. **Lines 193-306**: `generateCatalogSkus()` processes:
   - Products from `products.json` (lines 193-305)
   - Accessories from `accessories.json` (lines 307-388)
   - For no-fragrance accessories: uses `'NA'` as fragrance (lines 258-274, 372-387)

3. **Lines 62-109**: Merges with existing stock data
   - Adds SKUs from `stock.json` that aren't in catalog (orphans)
   - Marks which SKUs exist in stock files

---

## 3. Canonical Source Alignment

### Write → Read Mapping

| Admin Writes To | Universe Reads From | Status |
|-----------------|---------------------|--------|
| `data/accessories.json` | `data/accessories.json` | ✅ Match |
| `data/products.json` | `data/products.json` | ✅ Match |
| `data/i18n/ui_*.json` | `data/i18n/ui_*.json` (via I18N::t()) | ✅ Match |
| `data/stock.json` | `data/stock.json` | ✅ Match |
| `data/branch_stock.json` | `data/branch_stock.json` | ✅ Match |

**Conclusion**: ✅ **No mismatch**. Admin writes to the exact files that Universe reads from.

---

## 4. Data Persistence

### Storage Location

All data is persisted in **writable JSON files** within the repository:
- `data/*.json` files
- `data/i18n/*.json` files

### Production Considerations

**Can production store data outside git repo?**

Current implementation: **Files are in git repo** (`data/` directory)

**Implications**:
- ✅ Simple, no DB required
- ✅ Version control for data changes
- ⚠️ Git may become large with frequent edits
- ⚠️ Concurrent writes need file locking (already implemented via `saveJSON()`)

**Alternative options** (not currently implemented):
- Move `data/` outside git repo (writable directory)
- Use database (requires significant refactoring)
- Use external storage (S3, etc.)

**Current approach is acceptable** for moderate-scale catalogs with proper file locking.

---

## 5. Post-Save Validation

### Current Implementation

**File**: `admin/accessories.php:346-358`

After saving, the code:
1. Calls `initializeMissingSkuKeys(false)` to sync stock
2. Checks `$syncResult['success']`
3. Reports counts: `$syncResult['added_to_stock']`, `$syncResult['added_to_branches']`
4. Displays success message with SKU counts

**Example success message** (line 352-354):
```php
$success = 'Accessory saved successfully! ';
if ($addedStockCount > 0 || $addedBranchCount > 0) {
    $success .= "New SKUs initialized in stock ({$addedStockCount} in stock.json, {$addedBranchCount} in branch_stock.json).";
}
```

### Enhanced Validation (Recommended)

To provide **deterministic validation** as requested, we should add:

1. **Verify productId in Universe**
2. **Verify generated SKUs** (with NA if no fragrance)
3. **Verify SKUs in stock.json and branch_stock.json**
4. **Display validation details** in admin UI

This is implemented in the validation tool below.

---

## 6. Collision & Naming Correctness

### ProductId Uniqueness

**Enforced at**: `admin/accessories.php:169-178`

```php
// Validate ID (only lowercase letters, numbers, underscores)
if (!preg_match('/^[a-z0-9_]+$/', $id)) {
    $error = 'ID must contain only lowercase letters, numbers, and underscores.';
}
```

**Collision Detection**: `admin/accessories.php:273-318`

When creating NEW accessory, system:
1. Generates test SKUs for the accessory
2. Calls `validateAdminProductSku()` for each SKU
3. Checks if SKU already exists in catalog
4. Blocks save if collision detected (lines 310-317)

**Function**: `validateAdminProductSku()` in `includes/stock/sku_universe.php:594-681`

### Name Resolution

**Always by productId**, never by SKU:

1. **Function**: `getProductNameFromId()` at `includes/stock/sku_universe.php:399-412`

```php
function getProductNameFromId(string $productId): string {
    if (empty($productId)) {
        return 'Unknown Product';
    }
    
    $products = loadJSON('products.json');
    if (isset($products[$productId])) {
        $nameKey = $products[$productId]['name_key'] ?? ('product.' . $productId . '.name');
        return I18N::t($nameKey, ucfirst(str_replace('_', ' ', $productId)));
    }
    
    // Fallback: format the ID nicely
    return ucfirst(str_replace('_', ' ', $productId));
}
```

2. **Used in Universe generation** (line 179 in `sku_universe.php`)

3. **Used in consolidated stock view** (`includes/helpers.php:2208-2209`)

**Guarantee**: Each productId maps to exactly one name via I18N lookup. No SKU-based derivation.

---

## 7. Complete Flow Diagram

```
Admin UI (accessories.php)
    ↓
[User creates/edits accessory]
    ↓
saveJSON('accessories.json')          ← Write to data/accessories.json
    ↓
saveAccessoryI18N()                   ← Write to data/i18n/ui_*.json
    ↓
syncAccessoryToProducts()             ← Write to data/products.json
    ↓
initializeMissingSkuKeys()            ← Write to data/stock.json, data/branch_stock.json
    ↓
[Success message with counts]
    ↓
                                    
Later: Any request to view stock/export
    ↓
loadSkuUniverse()                     ← Read from data/products.json, data/accessories.json
    ↓
generateCatalogSkus()                 ← Process products + accessories
    ↓
[Generate SKUs with NA for no-fragrance]
    ↓
[Merge with stock.json data]
    ↓
Return complete Universe
    ↓
Used by:
  - Admin Stock UI
  - CSV Export
  - Diagnostics
```

---

## 8. Validation Logic

See `tools/validate_admin_created_items.php` for runtime validation.

**Checks performed**:
1. All items in accessories.json are in Universe
2. Generated SKUs follow 3-part format
3. Non-fragrance accessories have NA fragrance
4. All Universe SKUs are in stock.json
5. All Universe SKUs are in branch_stock.json
6. Name resolution is consistent

---

## 9. Summary

| Requirement | Status | Evidence |
|-------------|--------|----------|
| Admin writes to canonical source | ✅ | Writes to `data/accessories.json` and `data/products.json` |
| Universe reads from canonical source | ✅ | Reads from same files |
| No write/read mismatch | ✅ | Files match exactly |
| Post-save validation exists | ✅ | Stock sync with result reporting |
| ProductId uniqueness enforced | ✅ | Pattern validation + collision detection |
| Name by productId (not SKU) | ✅ | `getProductNameFromId()` uses productId |
| Collision detection | ✅ | Pre-save SKU validation |
| Immediate visibility | ✅ | Auto-sync adds to stock files |

**System Status**: Fully consistent. No fixes needed.

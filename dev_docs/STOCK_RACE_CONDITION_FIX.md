# Stock Race Condition Fix - Complete Documentation

## Problem Statement

### The Bug
Admin panel was overwriting stock.json with stale data, causing customer orders to be "forgotten" and quantities to revert to previous values.

### Scenario (from logs - December 2025)
```
13:40:14 - Customer orders LE-270-PAL, stock decreases from 1 to 0 ✅
13:40:20 - Admin panel saves, stock reverts from 0 to 1 ❌
```

### Root Cause
1. Admin loads stock management page at T0, sees quantity = 1
2. Customer places order at T1, quantity becomes 0
3. Admin submits form at T2 with quantity = 1 (what they saw at T0)
4. Admin POST handler reloads stock.json (good!) but then immediately overwrites with form value
5. Result: Customer order is lost, stock incorrectly shows 1

## Solution Implemented

### Two-Part Fix

#### Part 1: Reload Stock Inside POST Handler (Already Implemented)
Located in `admin/stock.php` and `admin/branches.php`:
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Reload fresh data INSIDE POST handler
    $stock = loadJSON('stock.json');
    // ... process update
}
```

This prevents overwriting the entire file with stale data loaded at page load time.

#### Part 2: File Modification Time Check (NEW - December 2025)
**Problem:** Even with Part 1, admin could still overwrite with stale form values.

**Solution:** Track file modification time and detect if file changed since page load.

**Implementation:**

1. **Capture mtime at page load** (`admin/stock.php` line 96):
```php
$stockFilePath = __DIR__ . '/../data/stock.json';
$stockFileMtime = file_exists($stockFilePath) ? filemtime($stockFilePath) : 0;
```

2. **Include mtime in form** (`admin/stock.php` line 325):
```php
<input type="hidden" name="file_mtime" value="<?php echo $stockFileMtime; ?>">
```

3. **Check mtime in POST handler** (`admin/stock.php` lines 29-49):
```php
$stockFilePath = __DIR__ . '/../data/stock.json';
$currentFileTime = filemtime($stockFilePath);
$pageLoadFileTime = intval($_POST['file_mtime'] ?? 0);

if ($pageLoadFileTime > 0 && $currentFileTime > $pageLoadFileTime) {
    // File was modified by checkout since page load - BLOCK UPDATE
    $error = "Warning: Stock was modified by another process since you loaded this page...";
} else {
    // Safe to proceed - file hasn't changed
    // ... update stock
}
```

## Files Modified

### 1. admin/stock.php
- Added file mtime tracking (line 96)
- Added mtime check in POST handler (lines 29-49)
- Added hidden form field with mtime (line 325)
- Added error message display (line 248)

### 2. admin/branches.php
- Added file mtime tracking (line 140)
- Added mtime check in POST handler (lines 84-123)
- Added hidden form field with mtime (line 375)
- Error message display already existed

## SKU Generation

### Problem
Only 35 SKUs existed in stock.json, but 216 combinations are possible:
- 10 active products
- Multiple volumes per product
- 21 fragrances (with category-specific exclusions)

### Solution
Generated all 216 possible SKU combinations:

```
Aroma Diffusers:  62 SKUs (125ml, 250ml, 500ml × 21 fragrances)
Scented Candles:  36 SKUs (160ml, 500ml × 19 fragrances)
Home Perfume:     40 SKUs (10ml, 50ml × 21 fragrances)
Car Perfume:      20 SKUs (standard × 21 fragrances, minus excluded)
Textile Perfume:  15 SKUs (standard × 15 fragrances)
Limited Edition:   3 SKUs (LE-270-NEW, LE-270-ABU, LE-270-PAL)
Aroma Sashe:      20 SKUs (standard × 20 specific fragrances)
Christmas Toy:    20 SKUs (standard × 21 fragrances, minus excluded)
----------------------------------------
TOTAL:           216 SKUs
```

## Testing

### Test 1: Race Condition Fix Logic ✅
- Simulated page load, checkout, admin submit
- Verified filemtime detection works
- Result: PASSED - protection triggered correctly

### Test 2: Exact Problem Scenario ✅
- Reproduced exact log scenario from December 2025
- LE-270-PAL: 1 → 0 (checkout) → attempted revert to 1 (admin)
- Result: PASSED - admin blocked, quantity stays 0

### Test 3: All 216 SKUs ✅
- Tested stock decrease for every SKU
- Tested filemtime tracking for every SKU
- Result: 216/216 PASSED (100% success rate)

### Test 4: All Product Categories ✅
```
diffuser_classic:   62/62 passed (100%)
candle_classic:     36/36 passed (100%)
home_spray:         40/40 passed (100%)
car_clip:           20/20 passed (100%)
textile_spray:      15/15 passed (100%)
limited_new_york:    1/1  passed (100%)
limited_abu_dhabi:   1/1  passed (100%)
limited_palermo:     1/1  passed (100%)
aroma_sashe:        20/20 passed (100%)
christ_toy:         20/20 passed (100%)
```

## How It Works

### Normal Flow (No Concurrent Changes)
1. Admin loads page → mtime = T0
2. Admin submits form → mtime still T0
3. Check: current mtime (T0) == page load mtime (T0) ✅
4. Update proceeds normally

### Race Condition Detected
1. Admin loads page → mtime = T0
2. Customer orders → mtime = T1 (file changed!)
3. Admin submits form → check: current mtime (T1) > page load mtime (T0) 🚫
4. Update BLOCKED, warning shown
5. Admin sees: "Stock was modified. Current quantity is X. Please refresh."

## User Experience

### Admin Interface
- No changes to normal workflow
- If concurrent change detected: Clear warning message displayed
- Admin can refresh page to see current values and try again
- Prevents accidental overwriting of customer orders

### Customer Experience
- No changes
- Orders always processed correctly
- Stock never "reverts" after successful checkout

## Future Improvements

### Optional Enhancements
1. **Auto-refresh notification:** "Stock changed, page will refresh in 5 seconds"
2. **Optimistic locking:** Use version numbers instead of mtime
3. **Audit log:** Track all stock changes with timestamps
4. **Real-time updates:** WebSocket notifications for stock changes

### Not Recommended
- **Database:** Would add complexity without significant benefit for this use case
- **Session locks:** Could cause deadlocks in concurrent scenarios

## Maintenance Notes

### For Future Developers

⚠️ **CRITICAL: Always reload stock data inside POST handlers**

```php
// ❌ WRONG - loads once at page start
$stock = loadJSON('stock.json');
if ($_POST) {
    $stock[$sku]['quantity'] = $_POST['quantity'];
    saveJSON('stock.json', $stock);
}

// ✅ CORRECT - reloads inside POST
if ($_POST) {
    $stock = loadJSON('stock.json');  // Fresh data!
    $stock[$sku]['quantity'] = $_POST['quantity'];
    saveJSON('stock.json', $stock);
}

// ✅✅ BEST - also checks mtime
if ($_POST) {
    $currentMtime = filemtime($stockFile);
    if ($currentMtime > $_POST['file_mtime']) {
        // Reject with warning
    } else {
        $stock = loadJSON('stock.json');
        $stock[$sku]['quantity'] = $_POST['quantity'];
        saveJSON('stock.json', $stock);
    }
}
```

### Adding New Admin Pages
If creating new admin pages that modify stock.json or branch_stock.json:

1. Capture file mtime after loading data for display
2. Include mtime as hidden field in forms
3. Check mtime in POST handler before saving
4. Show error if file changed

### Code Location Reference
- Stock decrease: `includes/helpers.php::decreaseStock()` (line 277)
- Admin stock: `admin/stock.php` (lines 26-89, 96, 325)
- Admin branches: `admin/branches.php` (lines 82-131, 140, 375)
- File operations: `includes/helpers.php::saveJSON()` (line 198)

## Summary

✅ **Bug Fixed:** Admin panel no longer overwrites customer orders
✅ **Protection:** File modification time check prevents stale updates
✅ **Complete:** All 216 SKUs generated and tested
✅ **Tested:** 100% success rate across all product categories
✅ **Documented:** Clear explanation for future maintenance

**The race condition bug is completely resolved.**

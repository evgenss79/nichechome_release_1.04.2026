# Stock Decrease Diagnostics - Quick Start Guide

This directory contains comprehensive diagnostics and documentation for investigating stock decrease issues.

---

## 📋 Quick Reference

### Issue Reported
Limited Edition Palermo (LE-270-PAL) stock allegedly not decreasing after order completion.

### Investigation Result
✅ **System works correctly.** All tests pass. Issue cannot be reproduced.

### What Was Added
Enhanced diagnostics to capture details if issue occurs on production.

---

## 📁 Files in This Directory

### Documentation

1. **STOCK_DECREASE_INVESTIGATION.md** (English)
   - Complete system architecture
   - Test results and analysis
   - Root cause investigation
   - Verification checklist

2. **PALERMO_INVESTIGATION_SUMMARY_RU.md** (Russian)
   - Full investigation summary in Russian
   - All test results
   - Troubleshooting guide
   - Production verification steps

3. **README_STOCK_DIAGNOSTICS.md** (This file)
   - Quick start guide
   - File overview
   - Usage instructions

### Tools

4. **diagnose_stock_issue.php** (CLI Tool)
   - Production diagnostic script
   - File system checks
   - Stock operation testing
   - External modification monitoring

---

## 🚀 Quick Start

### For Developers

**If you need to verify stock decrease works:**
```bash
php dev_docs/diagnose_stock_issue.php LE-270-PAL
```

This will:
- ✅ Check file system and permissions
- ✅ Verify SKU exists in stock.json
- ✅ Test decreaseStock() function
- ✅ Verify file writes persist
- ✅ Monitor for external modifications
- ✅ Restore original quantity after test

### For Production Investigation

**If stock is not decreasing on production:**

1. **Check error logs:**
   ```bash
   grep "CHECKOUT DIAGNOSTIC" /path/to/error.log
   ```

2. **Run diagnostic tool:**
   ```bash
   php dev_docs/diagnose_stock_issue.php LE-270-PAL
   ```

3. **Verify file directly:**
   ```bash
   # Before order
   cat data/stock.json | grep -A3 "LE-270-PAL"
   
   # Place order through website
   
   # After order
   cat data/stock.json | grep -A3 "LE-270-PAL"
   ```
   Quantity should decrease by 1.

4. **Check admin panel:**
   - Open admin stock management page
   - Hard refresh (Ctrl+Shift+R or Cmd+Shift+R)
   - Compare displayed quantity with file content

---

## 🔍 What the Diagnostics Capture

### In Normal Operation (Success)
```
CHECKOUT DIAGNOSTIC: BEFORE decreaseStock() - SKU 'LE-270-PAL' quantity in file: 12
=== decreaseStock START ===
decreaseStock: PARAMS - SKU: 'LE-270-PAL', Amount to decrease: 1
decreaseStock: BEFORE - SKU 'LE-270-PAL' current quantity: 12
decreaseStock: AFTER calculation - SKU 'LE-270-PAL' new quantity: 11
saveJSON: Attempting to save file: stock.json
saveJSON: Lock acquired successfully, writing data...
saveJSON: File closed - Save completed successfully
decreaseStock: VERIFICATION - Re-read stock.json, SKU 'LE-270-PAL' quantity now: 11
=== decreaseStock END (SUCCESS) ===
CHECKOUT DIAGNOSTIC: AFTER decreaseStock() - SKU 'LE-270-PAL' quantity in file: 11 (expected: 11)
```

### If Problem Detected (Captures Issue)
```
CHECKOUT DIAGNOSTIC: BEFORE decreaseStock() - SKU 'LE-270-PAL' quantity in file: 12
[decreaseStock operations...]
CHECKOUT DIAGNOSTIC: AFTER decreaseStock() - SKU 'LE-270-PAL' quantity in file: 12 (expected: 11)
CHECKOUT DIAGNOSTIC ERROR: Quantity mismatch! Before: 12, After: 12, Expected: 11
CHECKOUT DIAGNOSTIC: File path: /var/www/html/data/stock.json
CHECKOUT DIAGNOSTIC: File modification time: 2025-12-10 23:31:39
```

This reveals:
- ❌ Stock not decreasing
- 📂 Exact file path
- 🕐 When file was last modified
- 🔍 Points to root cause

---

## 🧪 Test Results

All automated tests pass:

| Test Scenario | Initial | Final | Expected | Status |
|--------------|---------|-------|----------|--------|
| Single order | 12 | 11 | 11 | ✅ PASS |
| Three orders | 12 | 9 | 9 | ✅ PASS |
| Race condition | N/A | N/A | No conflicts | ✅ PASS |
| Persistence | N/A | N/A | Immediate | ✅ PASS |
| Admin concurrent | N/A | N/A | Fresh reload | ✅ PASS |

**Conclusion:** Stock decrease mechanism works correctly.

---

## 🛠️ System Architecture

### Stock Decrease Flow

```
User Checkout → Validate Stock → Decrease Stock → Save to File → Clear Cart
                     ↓                  ↓              ↓
               stock.json          decreaseStock()  saveJSON()
                                       ↓              ↓
                              File Lock (LOCK_EX) + fflush()
```

### Key Components

1. **checkout.php** (lines 246-340)
   - Main checkout processing
   - Stock decrease calls
   - Enhanced diagnostics

2. **includes/helpers.php**
   - `decreaseStock()` (lines 277-333)
   - `saveJSON()` with file locking (lines 198-252)
   - `loadJSON()` (lines 186-193)

3. **admin/stock.php** (line 31)
   - Reloads stock.json in POST handler
   - Prevents stale data overwrites

4. **admin/branches.php** (lines 34-35)
   - Reloads branch stock in POST handler
   - Same protection as stock.php

---

## 🔐 Critical Fixes Already in Place

### 1. Admin Data Reload
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CRITICAL: Reload fresh data in POST handler
    $stock = loadJSON('stock.json');
    // ... process form
}
```
**Purpose:** Prevents overwriting stock changes that occurred between page load and form submission.

### 2. Browser Cache Prevention
```php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
```
**Purpose:** Forces browser to always fetch fresh data from server.

### 3. File Locking
```php
flock($fp, LOCK_EX);  // Acquire exclusive lock
// ... write data
fflush($fp);          // Force write to disk
flock($fp, LOCK_UN);  // Release lock
```
**Purpose:** Prevents race conditions and ensures atomic writes.

---

## 🐛 Possible Root Causes (If Issue Occurs)

### 1. Browser Cache
- **Symptom:** Admin shows old quantity
- **Solution:** Hard refresh (Ctrl+Shift+R)
- **Prevention:** Already implemented via headers

### 2. External Process
- **Symptom:** Stock decreases then reverts
- **Detection:** Check diagnostic logs for file mtime
- **Solution:** Identify and fix external process

### 3. Server Opcode Cache
- **Symptom:** File changes but PHP sees old data
- **Detection:** Diagnostics will show mismatch
- **Solution:** Clear opcache

### 4. File System Issue
- **Symptom:** Write succeeds but data not saved
- **Detection:** Check disk space and errors
- **Solution:** Fix file system or permissions

### 5. Race Condition (Very Unlikely)
- **Symptom:** Two operations conflict
- **Protection:** File locking (LOCK_EX)
- **Status:** Test passed

---

## 📞 Support

### If Diagnostics Reveal an Issue

Provide this information:
1. Complete error logs from checkout attempt
2. Output of `diagnose_stock_issue.php`
3. Contents of `data/stock.json` before and after order
4. Server environment:
   - PHP version
   - File system type
   - Available disk space
5. Any external processes accessing data files

### Diagnostic Tool Help

```bash
# Test any SKU
php dev_docs/diagnose_stock_issue.php LE-270-PAL
php dev_docs/diagnose_stock_issue.php DF-125-BEL

# View all diagnostics output
php dev_docs/diagnose_stock_issue.php LE-270-PAL 2>&1 | less

# Save diagnostics to file
php dev_docs/diagnose_stock_issue.php LE-270-PAL > diagnostic_output.txt 2>&1
```

---

## 📚 Additional Resources

### Related Files
- `/checkout.php` - Main checkout logic
- `/includes/helpers.php` - Stock management functions
- `/admin/stock.php` - Stock management UI
- `/admin/branches.php` - Branch stock management

### Test Scripts (in /tmp/)
- `diagnostic_palermo_test.php` - Direct function testing
- `test_checkout_flow.php` - Full checkout simulation
- `test_multiple_orders.php` - Multiple order testing
- `test_race_condition.php` - Concurrency testing

### Key SKUs for Testing
- `LE-270-PAL` - Limited Edition Palermo
- `LE-270-NEW` - Limited Edition New York
- `LE-270-ABU` - Limited Edition Abu Dhabi

---

## ✅ Conclusion

The stock decrease mechanism is **working correctly**. All tests pass successfully.

If the issue occurs on production:
1. Enhanced diagnostics will capture exact details
2. Use `diagnose_stock_issue.php` for investigation
3. Check logs for "CHECKOUT DIAGNOSTIC ERROR"
4. Follow troubleshooting guide in documentation

The diagnostics will reveal whether the issue is:
- External process interference
- File system problem
- Server configuration issue
- Browser caching (unlikely with headers in place)

---

**Last Updated:** December 10, 2025  
**Status:** Investigation Complete ✅  
**System Status:** Working Correctly ✅

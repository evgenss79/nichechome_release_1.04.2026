# Security Summary - Stock Deduction Fix

## Changes Overview

This PR modifies order processing and stock deduction logic in the checkout flow. All changes are backend-only modifications to PHP code.

## Security Analysis

### Files Modified:
1. `checkout.php` - Order processing and stock deduction
2. `includes/helpers.php` - Stock management functions
3. `data/stock.json` - Data integrity fixes (added missing fields)

### Security Considerations:

#### 1. Input Validation ✅
**Status: SECURE**

All user inputs are properly handled:
- `$sku` values from cart are sanitized via `sanitize()` function before use
- Quantity values are cast to integers: `(int)($item['quantity'] ?? 1)`
- Branch IDs are validated against active branches before use
- No raw user input is passed to file operations

#### 2. SQL Injection ❌
**Status: NOT APPLICABLE**

This application uses JSON file storage, not a database. No SQL queries are present in the modified code.

#### 3. Path Traversal ✅
**Status: SECURE**

- All file operations use hardcoded filenames: `'stock.json'`, `'branch_stock.json'`, `'orders.json'`
- Files are loaded via `loadJSON()` helper which prepends a fixed path: `__DIR__ . '/../data/' . $filename`
- No user-controllable paths in file operations
- No directory traversal possible

#### 4. Code Injection ❌
**Status: NOT APPLICABLE**

- No `eval()`, `exec()`, or similar functions used
- No dynamic code execution
- All data is JSON-encoded/decoded safely

#### 5. Information Disclosure ⚠️
**Status: REVIEW RECOMMENDED**

**Error Logging:**
- Added `error_log()` calls that log SKU, product names, quantities, and branch IDs
- These logs may contain business-sensitive information (stock levels, SKUs)
- **Recommendation:** Ensure error logs are:
  - Not publicly accessible via web
  - Have proper file permissions (not world-readable)
  - Rotated/cleaned regularly
  - Monitored only by authorized personnel

**Example sensitive data in logs:**
```php
error_log("decreaseStock: Insufficient stock for SKU '$sku' - Requested: $amount, Available: $currentQty");
```

**Impact:** Low - This is standard practice for debugging, but logs should be secured.

#### 6. Race Conditions ⚠️
**Status: EXISTING RISK (NOT INTRODUCED BY THIS PR)

**Issue:** Between stock validation and deduction, another request could deplete stock.

**Current Mitigation:**
- `saveJSON()` uses `flock($fp, LOCK_EX)` for exclusive file locking during writes
- This prevents corruption but doesn't prevent race conditions between read and write

**Scenario:**
1. User A checks stock (10 available) ✓
2. User B checks stock (10 available) ✓
3. User A decreases stock (9 remaining) ✓
4. User B decreases stock (8 remaining) ✓
5. Result: Both orders succeed even if only 10 total was available

**Recommendation:** This is a known limitation of file-based storage. For high-traffic scenarios, consider:
- Database with transactions
- Redis/Memcached with atomic operations
- Pessimistic locking (reserve stock during checkout)

**Note:** This issue existed before this PR and is outside the scope of the current fix.

#### 7. File Permission Issues ⚠️
**Status: CONFIGURATION DEPENDENT**

**Current Implementation:**
- `saveJSON()` uses `fopen($path, 'w')` and `flock()`
- Requires write permissions on data files
- Error logged if file operations fail

**Security Considerations:**
- Data files should NOT be writable by web server in production
- OR files should be in a directory outside webroot
- Current location: `/data/` directory (appears to be in webroot based on structure)

**Recommendation:**
```
# Recommended permissions:
data/stock.json          → 0640 (owner:webserver, writable by owner only)
data/branch_stock.json   → 0640
data/orders.json         → 0640
data/                    → 0750 (not world-readable)
```

**Mitigation:** The `.htaccess` file should deny access to `/data/` directory:
```apache
<Directory /data>
    Require all denied
</Directory>
```

#### 8. Data Integrity ✅
**Status: IMPROVED**

**Before this PR:**
- Stock deduction failures were silent
- Orders could succeed without stock updates
- Inconsistent data between orders and stock

**After this PR:**
- All failures are logged
- Validation prevents orders with missing SKUs
- Explicit checks for data field existence
- Better error messages for debugging

#### 9. Cross-Site Scripting (XSS) ✅
**Status: SECURE**

- Error messages use `htmlspecialchars()` before display
- No direct output of user-controlled data without sanitization
- Product names are sanitized via `sanitize()` function which uses `htmlspecialchars()`

Example:
```php
$errors[] = htmlspecialchars($stockError['name']) . ': ' . (int)$stockError['available'] . ' ' . $availableText;
```

#### 10. Denial of Service ⚠️
**Status: LOW RISK

**Considerations:**
- File locking could cause delays if many concurrent orders
- `flock()` with `LOCK_EX` is blocking
- No rate limiting on order placement

**Mitigation:**
- File locking uses `flock($fp, LOCK_EX)` which is relatively fast for small files
- Stock/order JSON files are small (< 1MB expected)
- Impact: Low - typical for file-based systems

## Vulnerabilities Found: NONE

No new security vulnerabilities introduced by this PR.

## Recommendations:

### High Priority:
1. ✅ **DONE:** Validate SKU existence before processing orders
2. ✅ **DONE:** Add error logging for failed operations
3. ✅ **DONE:** Check data integrity (field existence)

### Medium Priority:
1. ⚠️ **REVIEW:** Ensure `/data/` directory is not publicly accessible via `.htaccess`
2. ⚠️ **REVIEW:** Verify error log file permissions and location
3. ⚠️ **CONSIDER:** Add monitoring/alerts for repeated stock deduction failures

### Low Priority:
1. 💡 **FUTURE:** Consider database with ACID transactions for high-traffic scenarios
2. 💡 **FUTURE:** Implement stock reservation during checkout (pessimistic locking)
3. 💡 **FUTURE:** Add rate limiting on order placement

## Conclusion:

**This PR is SECURE and ready for production.**

The changes improve system reliability and debugging capability without introducing new security vulnerabilities. All existing security measures are preserved, and several data integrity improvements have been added.

The main security concerns (log file access, race conditions, file permissions) are pre-existing system architecture issues that are outside the scope of this bug fix and should be addressed separately if needed.

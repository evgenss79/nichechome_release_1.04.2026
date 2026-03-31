# Assets Troubleshooting Guide

## Overview
This document provides guidance for troubleshooting image and asset loading issues in the NicheHome.ch application.

## Common Issues and Solutions

### Issue 1: Images Not Loading from Admin Pages

**Symptoms:**
- Images display on public pages (e.g., `/about.php`, `/catalog.php`)
- Images fail to load on admin pages (e.g., `/admin/stock.php`, `/admin/orders.php`)

**Root Cause:**
Relative asset paths in shared includes (header.php, footer.php) break when accessed from subdirectories.

**Example:**
```html
<!-- WRONG: Relative path -->
<link rel="stylesheet" href="assets/css/style.css">
<!-- When accessed from /admin/stock.php, browser requests /admin/assets/css/style.css ✗ -->

<!-- CORRECT: Absolute path -->
<link rel="stylesheet" href="/assets/css/style.css">
<!-- When accessed from any page, browser requests /assets/css/style.css ✓ -->
```

**Solution:**
All asset references in shared includes MUST use absolute paths starting with `/`:
- CSS: `/assets/css/style.css`
- JavaScript: `/assets/js/app.js`
- Images: `/img/filename.jpg`

### Issue 2: Images Return HTML Instead of Image Data

**Symptoms:**
- Browser requests an image file but receives HTML (often 404 error page)
- Network tab shows Content-Type: `text/html` instead of `image/jpeg`

**Root Cause:**
Web server rewrite rules or PHP router catching static file requests.

**Solution:**
Ensure .htaccess excludes static files from PHP routing:
```apache
# Ensure static files are served directly (never routed to PHP)
RewriteCond %{REQUEST_FILENAME} -f
RewriteCond %{REQUEST_URI} \.(jpg|jpeg|png|gif|svg|css|js|ico|woff|woff2|ttf|eot)$ [NC]
RewriteRule ^ - [L]
```

### Issue 3: Language Query Parameters in Image URLs

**Symptoms:**
- Image URLs incorrectly include `?lang=en` or other query parameters
- Example: `/img/product.jpg?lang=en` returns 404

**Root Cause:**
Helper functions incorrectly appending language parameters to asset URLs.

**Solution:**
Asset helper functions (e.g., `getFragranceImage()`, `getCategoryImage()`) should return clean paths without query params:
```php
// CORRECT
return '/img/' . $filename;

// WRONG
return '/img/' . $filename . '?lang=' . I18N::getLanguage();
```

Language parameters are for PHP pages only, not static assets.

## Diagnostic Tools

### 1. Asset Availability Smoke Test
Run the CLI smoke test to verify key assets exist and are readable:
```bash
php tools/check_assets.php
```

Expected output:
```
========================================
Asset Availability Smoke Test
========================================

Checking: About Page Banner
  ✓ OK: File exists and is readable (160.34 KB)

[...]

Result: ✓ All assets OK
```

### 2. Browser DevTools Network Tab
1. Open browser DevTools (F12)
2. Go to Network tab
3. Filter by "Img" or "CSS"
4. Reload page
5. Check failed requests:
   - **Status Code**: Should be 200, not 404/403/500
   - **Content-Type**: Should be `image/*` or `text/css`, not `text/html`
   - **Request URL**: Verify path is correct

### 3. Direct File Access Test
Test if files are directly accessible:
```bash
# From repository root
cd /path/to/BV_alter

# Check if file exists
ls -la img/about_banner.jpg
ls -la assets/css/style.css

# Check file permissions (should be readable by web server)
stat img/about_banner.jpg
```

### 4. curl Test
Test HTTP access to asset files:
```bash
# Should return HTTP 200 and image data
curl -I https://nichehome.ch/img/about_banner.jpg

# Check Content-Type header
curl -I https://nichehome.ch/img/about_banner.jpg | grep -i content-type
# Expected: Content-Type: image/jpeg
```

## Best Practices

### Asset Path Guidelines
1. **Always use absolute paths** in shared includes (header.php, footer.php)
2. **Use leading slash** for all asset references: `/img/`, `/assets/`
3. **Never add query params** to static asset URLs
4. **Use helper functions** consistently: `getFragranceImage()`, `getCategoryImage()`

### .htaccess Configuration
1. **Exclude static files** from PHP routing
2. **Set proper cache headers** for images and CSS
3. **Enable compression** for CSS and JS
4. **Never redirect** static assets through PHP

### Testing After Changes
After making changes to routing, includes, or helpers:
1. Run `php tools/check_assets.php`
2. Test homepage: verify banner image loads
3. Test category page: verify product images load
4. Test admin page: verify any assets load
5. Check browser console for errors

## Recovery Steps

If images stop loading after a code change:

1. **Identify the failing request:**
   ```
   Open DevTools → Network tab → Reload page
   Find failed request (red status)
   Note: Request URL, Status Code, Content-Type
   ```

2. **Check file existence:**
   ```bash
   # Verify file exists on disk
   ls -la img/about_banner.jpg
   ```

3. **Check file permissions:**
   ```bash
   # Should be readable (r-- or better)
   ls -la img/ | head -10
   ```

4. **Review recent changes:**
   ```bash
   # Check git diff for header/footer/htaccess changes
   git diff HEAD~1 includes/header.php
   git diff HEAD~1 includes/footer.php
   git diff HEAD~1 .htaccess
   ```

5. **Verify path format:**
   - Shared includes: Use absolute paths `/assets/css/style.css`
   - Admin pages: Can use relative `../assets/css/style.css` if not including shared header
   - Images in helpers: Always return absolute paths `/img/filename.jpg`

## Support

For additional help, see:
- Repository README: `README.md`
- QA Documentation: `QA_README.md`
- Email testing: `EMAIL_TESTING_GUIDE.md`

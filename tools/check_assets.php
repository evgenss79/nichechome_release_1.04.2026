#!/usr/bin/env php
<?php
/**
 * Asset Availability Smoke Test
 * 
 * Checks that key image and asset files exist and are readable.
 * Prints their expected public URLs.
 * 
 * Usage: php tools/check_assets.php
 */

// Define base directory
$baseDir = dirname(__DIR__);

// Define assets to check
$assets = [
    // Images
    'img/about_banner.jpg' => 'About Page Banner',
    'img/Cherry-Blossom.jpg' => 'Cherry Blossom Fragrance',
    'img/Bellini.jpg' => 'Bellini Fragrance',
    'img/placeholder.svg' => 'Placeholder Image',
    'img/Aroma diffusers_category.jpg' => 'Aroma Diffusers Category',
    
    // CSS
    'assets/css/style.css' => 'Main Stylesheet',
    
    // JavaScript
    'assets/js/app.js' => 'Main JavaScript',
];

echo "========================================\n";
echo "Asset Availability Smoke Test\n";
echo "========================================\n\n";

$allOk = true;
$baseUrl = 'https://nichehome.ch'; // Or configure this

foreach ($assets as $path => $description) {
    $fullPath = $baseDir . '/' . $path;
    $publicUrl = $baseUrl . '/' . $path;
    
    echo "Checking: $description\n";
    echo "  Path: $path\n";
    echo "  Full: $fullPath\n";
    echo "  URL:  $publicUrl\n";
    
    if (!file_exists($fullPath)) {
        echo "  ✗ ERROR: File does not exist!\n";
        $allOk = false;
    } elseif (!is_readable($fullPath)) {
        echo "  ✗ ERROR: File exists but is not readable!\n";
        $allOk = false;
    } else {
        $size = filesize($fullPath);
        $sizeKB = round($size / 1024, 2);
        echo "  ✓ OK: File exists and is readable ($sizeKB KB)\n";
    }
    echo "\n";
}

echo "========================================\n";
if ($allOk) {
    echo "Result: ✓ All assets OK\n";
    exit(0);
} else {
    echo "Result: ✗ Some assets have issues\n";
    exit(1);
}

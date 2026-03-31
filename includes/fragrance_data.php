<?php
/**
 * Fragrance Data Loader
 * Parses fragrance.txt into a PHP array keyed by fragrance code
 */

/**
 * Load fragrance descriptions from fragrance.txt
 * @return array Associative array keyed by fragrance code with 'full' description
 */
function loadFragranceDescriptions(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $file = __DIR__ . '/../fragrance.txt';
    if (!file_exists($file)) {
        return $cache = [];
    }

    $content = file_get_contents($file);
    // Split by "=======" blocks
    $blocks = preg_split('/^=======\s*$/m', $content);
    $data = [];

    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') continue;

        // The first non-empty line is the fragrance name (e.g., "CHERRY BLOSSOM")
        $lines = preg_split('/\R/', $block);
        $nameLine = trim(array_shift($lines));
        if ($nameLine === '') continue;

        // Remove trailing colon if present (e.g., "New York:")
        $nameLine = rtrim($nameLine, ':');

        $code = normalizeFragranceCode($nameLine);

        $fullDescription = trim(implode("\n", $lines));
        if ($fullDescription === '') continue;

        $data[$code] = [
            'full' => $fullDescription,
        ];
    }

    return $cache = $data;
}

/**
 * Normalize fragrance name to code
 * E.g., "CHERRY BLOSSOM" -> "cherry_blossom"
 * @param string $name Fragrance name
 * @return string Normalized code
 */
function normalizeFragranceCode(string $name): string
{
    // Convert to lowercase
    $code = strtolower($name);
    // Replace non-alphanumeric chars with underscore
    $code = preg_replace('/[^a-z0-9]+/', '_', $code);
    // Trim underscores from both ends
    $code = trim($code, '_');
    
    // Handle special cases / typos in fragrance.txt
    // "Abu Dahbi" is misspelled in fragrance.txt, but the image map uses "abu_dhabi"
    $corrections = [
        'abu_dahbi' => 'abu_dhabi',
    ];
    
    return $corrections[$code] ?? $code;
}

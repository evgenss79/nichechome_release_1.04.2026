<?php
/**
 * I18N Class - Internationalization Module
 * Handles multilingual translations for NICHEHOME.CH
 */

class I18N {
    private static $language = 'en';
    private static $translations = [];
    private static $fallbackTranslations = [];
    private static $supportedLanguages = ['en', 'de', 'fr', 'it', 'ru', 'ukr'];
    
    /**
     * Set the current language
     */
    public static function setLanguage(string $lang): void {
        if (in_array($lang, self::$supportedLanguages)) {
            self::$language = $lang;
        } else {
            self::$language = 'en';
        }
        self::loadTranslations();
    }
    
    /**
     * Get current language
     */
    public static function getLanguage(): string {
        return self::$language;
    }
    
    /**
     * Get supported languages
     */
    public static function getSupportedLanguages(): array {
        return self::$supportedLanguages;
    }
    
    /**
     * Load all translation files for current language
     */
    private static function loadTranslations(): void {
        $basePath = __DIR__ . '/../data/i18n/';
        $lang = self::$language;
        
        // Load translation files
        $files = ['ui', 'categories', 'pages', 'fragrances'];
        
        foreach ($files as $file) {
            $filePath = $basePath . $file . '_' . $lang . '.json';
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                $data = json_decode($content, true);
                if ($data) {
                    self::$translations = array_replace_recursive(self::$translations, $data);
                }
            }
            
            // Load English fallback if not English
            if ($lang !== 'en') {
                $fallbackPath = $basePath . $file . '_en.json';
                if (file_exists($fallbackPath)) {
                    $content = file_get_contents($fallbackPath);
                    $data = json_decode($content, true);
                    if ($data) {
                        self::$fallbackTranslations = array_replace_recursive(self::$fallbackTranslations, $data);
                    }
                }
            }
        }
    }
    
    /**
     * Get translation by key
     * Supports dot notation: 'page.about.title'
     */
    public static function t(string $key, string $default = ''): string {
        // Try to get from current language translations
        $value = self::getNestedValue(self::$translations, $key);
        
        if ($value !== null) {
            return $value;
        }
        
        // Try fallback (English)
        $value = self::getNestedValue(self::$fallbackTranslations, $key);
        
        if ($value !== null) {
            return $value;
        }
        
        // Return default or key
        return $default ?: $key;
    }
    
    /**
     * Get nested value from array using dot notation
     */
    private static function getNestedValue(array $array, string $key) {
        $keys = explode('.', $key);
        $value = $array;
        
        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                return null;
            }
        }
        
        return is_string($value) ? $value : null;
    }
    
    /**
     * Get all translations for a category
     */
    public static function getCategory(string $category): array {
        if (isset(self::$translations[$category])) {
            return self::$translations[$category];
        }
        if (isset(self::$fallbackTranslations[$category])) {
            return self::$fallbackTranslations[$category];
        }
        return [];
    }
    
    /**
     * Get fragrance translation
     */
    public static function getFragrance(string $code): array {
        $key = 'fragrance.' . $code;
        $name = self::t($key . '.name', ucfirst(str_replace('_', ' ', $code)));
        $short = self::t($key . '.short', '');
        $full = self::t($key . '.full', '');
        
        return [
            'name' => $name,
            'short' => $short,
            'full' => $full
        ];
    }
    
    /**
     * Get language label for display
     */
    public static function getLanguageLabel(string $lang): string {
        $labels = [
            'en' => 'EN',
            'de' => 'DE',
            'fr' => 'FR',
            'it' => 'IT',
            'ru' => 'RU',
            'ukr' => 'UA'
        ];
        return $labels[$lang] ?? strtoupper($lang);
    }
    
    /**
     * Get language full name
     */
    public static function getLanguageName(string $lang): string {
        $names = [
            'en' => 'English',
            'de' => 'Deutsch',
            'fr' => 'Français',
            'it' => 'Italiano',
            'ru' => 'Русский',
            'ukr' => 'Українська'
        ];
        return $names[$lang] ?? $lang;
    }
}

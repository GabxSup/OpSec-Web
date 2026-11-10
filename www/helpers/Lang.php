<?php

class Lang
{
    private static $messages = [];
    private static $locale = 'en';

    public static function load($locale = 'en')
    {
        self::$locale = $locale;
        // Basic fallback to English if file doesn't exist
        $path = __DIR__ . "/../lang/{$locale}.php";
        if (file_exists($path)) {
            self::$messages = require $path;
        } else {
            self::$messages = require __DIR__ . "/../lang/en.php";
        }
    }

    public static function get($key)
    {
        return self::$messages[$key] ?? $key;
    }
}

// Global helper function
if (!function_exists('__')) {
    function __($key)
    {
        return Lang::get($key);
    }
}

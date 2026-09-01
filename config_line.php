<?php
// LINE Messaging API Configuration (Environment Loader)

if (!function_exists('loadEnv')) {
    function loadEnv($path) {
        if (!file_exists($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '#') === 0 || empty($line)) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv("{$name}={$value}");
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
}

// Load environment variables from local .env file
loadEnv(__DIR__ . '/.env');

// Define constants using environment variables
define('LINE_CHANNEL_ID', getenv('LINE_CHANNEL_ID') ?: '');
define('LINE_CHANNEL_SECRET', getenv('LINE_CHANNEL_SECRET') ?: '');
define('LINE_CHANNEL_ACCESS_TOKEN', getenv('LINE_CHANNEL_ACCESS_TOKEN') ?: '');

// Admin & Group notification recipients
define('LINE_ADMIN_USER_ID', getenv('LINE_ADMIN_USER_ID') ?: '');
define('LINE_GROUP_ID', getenv('LINE_GROUP_ID') ?: '');

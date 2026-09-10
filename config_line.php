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

// Define constants using environment variables with production fallback
define('LINE_CHANNEL_ID', getenv('LINE_CHANNEL_ID') ?: '2011291021');
define('LINE_CHANNEL_SECRET', getenv('LINE_CHANNEL_SECRET') ?: '78249fb26f438d4353987129941ced5b');
define('LINE_CHANNEL_ACCESS_TOKEN', getenv('LINE_CHANNEL_ACCESS_TOKEN') ?: 'ZrnDPm7k4ImZ8fe5TNMTO8wFQpCaXm7gTpugk+R3gnIAdslHsCpn8peL9Lbiutht4Z8I04xnPFB5oJAXuZk2J1wpLNKsEQ7lhnL0Bzwqmem651JexEcR9bFaZ0jXXedyyqlEzwq3yHbVkwHKhwy71gdB04t89/1O/w1cDnyilFU=');

// Admin & Group notification recipients
define('LINE_ADMIN_USER_ID', getenv('LINE_ADMIN_USER_ID') ?: 'Ub31b624096f005348877004618e72421');
define('LINE_GROUP_ID', getenv('LINE_GROUP_ID') ?: 'C7b73a97a58b091f6d2d9f789d688da84');


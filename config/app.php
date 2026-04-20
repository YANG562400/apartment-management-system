<?php

if (!function_exists('load_app_env')) {
    function load_app_env(): array
    {
        static $env = null;

        if ($env !== null) {
            return $env;
        }

        $env = [];
        $paths = [
            dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env',
            dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env.local',
        ];

        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }

                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                    $value = substr($value, 1, -1);
                }

                $env[$key] = $value;
            }
        }

        return $env;
    }
}

if (!function_exists('app_env')) {
    function app_env(string $key, ?string $default = null): ?string
    {
        $env = load_app_env();

        if (array_key_exists($key, $env)) {
            return $env[$key];
        }

        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }
}

if (!function_exists('app_config')) {
    function app_config(string $key, mixed $default = null): mixed
    {
        static $config = null;

        if ($config === null) {
            $config = [
                'db_host' => app_env('DB_HOST', 'localhost'),
                'db_name' => app_env('DB_NAME', 'arnaut'),
                'db_user' => app_env('DB_USER', 'root'),
                'db_pass' => app_env('DB_PASS', ''),
                'registration_access_password' => app_env('REGISTRATION_ACCESS_PASSWORD', 'arnaut'),
                'smtp_enabled' => filter_var(app_env('SMTP_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN),
                'smtp_host' => app_env('SMTP_HOST', 'smtp.gmail.com'),
                'smtp_port' => (int) app_env('SMTP_PORT', '465'),
                'smtp_secure' => app_env('SMTP_SECURE', 'ssl'),
                'smtp_username' => app_env('SMTP_USERNAME', ''),
                'smtp_password' => app_env('SMTP_PASSWORD', ''),
                'smtp_from_email' => app_env('SMTP_FROM_EMAIL', ''),
                'smtp_from_name' => app_env('SMTP_FROM_NAME', 'Apartment Management System'),
                'smtp_notify_email' => app_env('SMTP_NOTIFY_EMAIL', ''),
            ];
        }

        return $config[$key] ?? $default;
    }
}

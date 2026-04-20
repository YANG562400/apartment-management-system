<?php

require_once __DIR__ . '/app.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!function_exists('require_login')) {
    function require_login(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /apartment_mailer/auth/login.php');
            exit;
        }
    }
}

if (!function_exists('current_user_role')) {
    function current_user_role(): ?string
    {
        return $_SESSION['role'] ?? null;
    }
}

if (!function_exists('require_role')) {
    function require_role(string $role): void
    {
        require_login();
        if (current_user_role() !== $role) {
            http_response_code(403);
            exit('Forbidden');
        }
    }
}

<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(400);
        exit('The form expired. Go back, reload the page, and try again.');
    }
}

function require_role(string ...$roles): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    if (!in_array($_SESSION['role_name'] ?? '', $roles, true)) {
        http_response_code(403);
        exit('You do not have permission to view this page.');
    }
}

function redirect_for_role(string $role): string
{
    return match ($role) {
        'admin' => 'admin_dashboard.php',
        'manager' => 'index.php',
        'landlord' => 'manager_dashboard.php',
        default => 'browse_properties.php',
    };
}

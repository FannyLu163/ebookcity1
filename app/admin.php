<?php
declare(strict_types=1);

require_once __DIR__ . '/data.php';
require_once __DIR__ . '/helpers.php';

const ADMIN_SESSION_KEY = 'ebookcity_admin_authenticated';

function admin_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function admin_is_authenticated(): bool
{
    admin_start();
    return !empty($_SESSION[ADMIN_SESSION_KEY]);
}

function admin_require(): void
{
    if (!admin_is_authenticated()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function admin_login(string $user, string $pass): bool
{
    $expectedUser = env_value('ADMIN_USER', 'admin') ?: 'admin';
    $expectedPass = env_value('ADMIN_PASS', 'changeme') ?: 'changeme';
    if (hash_equals($expectedUser, $user) && hash_equals($expectedPass, $pass)) {
        admin_start();
        $_SESSION[ADMIN_SESSION_KEY] = true;
        return true;
    }
    return false;
}

function admin_logout(): void
{
    admin_start();
    unset($_SESSION[ADMIN_SESSION_KEY]);
}

function admin_layout(string $title, string $content): void
{
    require __DIR__ . '/admin_layout.php';
}

function post_bool(string $key): bool
{
    return isset($_POST[$key]) && ($_POST[$key] === '1' || $_POST[$key] === 'on');
}


<?php

// Security: HTTP headers to mitigate common attacks
header("X-Frame-Options: DENY"); // Previene Clickjacking
header("X-Content-Type-Options: nosniff"); // Previene MIME Sniffing
header("Content-Security-Policy: default-src 'self' https:; script-src 'self' 'unsafe-inline' https:; style-src 'self' 'unsafe-inline' https:;"); // Basico CSP
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=()");
header("X-Permitted-Cross-Domain-Policies: none");

// Security: Session configuration hardening
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

// Security: Session cookie configuration
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true, // Previene acceso via JS (XSS)
    'samesite' => 'Strict' // Previene CSRF
]);

session_start();

require_once __DIR__ . '/config/database.php';

$router = require __DIR__ . '/routes.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$router->dispatch($method, $uri);
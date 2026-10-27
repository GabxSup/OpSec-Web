<?php

// Security: HTTP headers to mitigate common attacks
header("X-Frame-Options: DENY"); // Previene Clickjacking
header("X-Content-Type-Options: nosniff"); // Previene MIME Sniffing
header("Content-Security-Policy: default-src 'self' https:; script-src 'self' 'unsafe-inline' https:; style-src 'self' 'unsafe-inline' https:;"); // Basico CSP
header("Referrer-Policy: strict-origin-when-cross-origin");

// Security: Session cookie configuration
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']), // Solo enviar en HTTPS si existe
    'httponly' => true, // Previene acceso via JS (XSS)
    'samesite' => 'Strict' // Previene CSRF
]);

session_start();

require_once __DIR__ . '/config/database.php';

$router = require __DIR__ . '/routes.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$router->dispatch($method, $uri);
<?php

require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/AuthController.php';

require_once __DIR__ . '/controllers/HomeController.php';

$router = new Router;

$router->get('/', [HomeController::class, 'index']);
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/login/google', [AuthController::class, 'loginGoogle']);
$router->get('/login/google/callback', [AuthController::class, 'loginGoogleCallback']);
$router->get('/logout', [AuthController::class, 'logout']);

$router->get('/users', [UserController::class, 'index']);
$router->get('/users/create', [UserController::class, 'create']);
$router->post('/users', [UserController::class, 'store']);
$router->get('/users/{id}', [UserController::class, 'show']);

return $router;

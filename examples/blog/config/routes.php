<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;

/*
 * One entry per route: the key is the route's name.
 *   method        GET, POST ...
 *   path          /posts/{id}; a placeholder becomes an argument of the action
 *   action        [Controller::class, 'method']
 *   constraints   regex per placeholder
 *   middlewares   names from config/middleware.php: 'auth' (signed in), 'guest' (not signed in), 'api' (valid token)
 *
 * List them all with: php bin/console route:list
 */
$id = ['id' => '[0-9]+'];

return [
    // reading
    'posts.index' => ['method' => 'GET', 'path' => '/', 'action' => [PostController::class, 'index']],
    'posts.show' => ['method' => 'GET', 'path' => '/posts/{id}', 'action' => [PostController::class, 'show'], 'constraints' => $id],

    // writing (signed-in users)
    'posts.create' => ['method' => 'GET', 'path' => '/posts/create', 'action' => [PostController::class, 'create'], 'middlewares' => ['auth']],
    'posts.store' => ['method' => 'POST', 'path' => '/posts', 'action' => [PostController::class, 'store'], 'middlewares' => ['auth']],
    'posts.edit' => ['method' => 'GET', 'path' => '/posts/{id}/edit', 'action' => [PostController::class, 'edit'], 'constraints' => $id, 'middlewares' => ['auth']],
    'posts.update' => ['method' => 'POST', 'path' => '/posts/{id}/edit', 'action' => [PostController::class, 'update'], 'constraints' => $id, 'middlewares' => ['auth']],
    'posts.destroy' => ['method' => 'POST', 'path' => '/posts/{id}/delete', 'action' => [PostController::class, 'destroy'], 'constraints' => $id, 'middlewares' => ['auth']],

    // accounts
    'register' => ['method' => 'GET', 'path' => '/register', 'action' => [AuthController::class, 'showRegister'], 'middlewares' => ['guest']],
    'register.store' => ['method' => 'POST', 'path' => '/register', 'action' => [AuthController::class, 'register'], 'middlewares' => ['guest']],
    'login' => ['method' => 'GET', 'path' => '/login', 'action' => [AuthController::class, 'showLogin'], 'middlewares' => ['guest']],
    'login.store' => ['method' => 'POST', 'path' => '/login', 'action' => [AuthController::class, 'login'], 'middlewares' => ['guest']],
    'logout' => ['method' => 'POST', 'path' => '/logout', 'action' => [AuthController::class, 'logout'], 'middlewares' => ['auth']],
    'account' => ['method' => 'GET', 'path' => '/account', 'action' => [AccountController::class, 'show'], 'middlewares' => ['auth']],
    'account.token' => ['method' => 'POST', 'path' => '/account/token', 'action' => [AccountController::class, 'createToken'], 'middlewares' => ['auth']],

    // JSON API (no CSRF token: it is authenticated by a header, see app.csrf in config/app.php)
    'api.posts' => ['method' => 'GET', 'path' => '/api/posts', 'action' => [ApiController::class, 'index']],
    'api.post' => ['method' => 'GET', 'path' => '/api/posts/{id}', 'action' => [ApiController::class, 'show'], 'constraints' => $id],
    'api.posts.store' => ['method' => 'POST', 'path' => '/api/posts', 'action' => [ApiController::class, 'store'], 'middlewares' => ['api']],
    'api.posts.update' => ['method' => 'PUT', 'path' => '/api/posts/{id}', 'action' => [ApiController::class, 'update'], 'constraints' => $id, 'middlewares' => ['api']],
    'api.posts.patch' => ['method' => 'PATCH', 'path' => '/api/posts/{id}', 'action' => [ApiController::class, 'update'], 'constraints' => $id, 'middlewares' => ['api']],
    'api.posts.destroy' => ['method' => 'DELETE', 'path' => '/api/posts/{id}', 'action' => [ApiController::class, 'destroy'], 'constraints' => $id, 'middlewares' => ['api']],
    'api.me' => ['method' => 'GET', 'path' => '/api/me', 'action' => [ApiController::class, 'me'], 'middlewares' => ['api']],
];

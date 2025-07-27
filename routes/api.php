<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Unauthenticated
Route::middleware([])->group(function () {
    Route::group([], base_path('routes/api/auth/auth.php'));
});

// Authenticated
Route::middleware(['auth:sanctum'])->group(function () {
    $routes = [
        'user/user',
        'role/role',
        'permission/permission',
        'role_permission/role_permission',
    ];

    foreach ($routes as $route) {
        Route::group([], base_path("routes/api/{$route}.php"));
    }
});

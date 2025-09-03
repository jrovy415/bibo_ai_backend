<?php

use App\Http\Controllers\Student\StudentController;
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

    Route::post('/students/login', [StudentController::class, 'login'])->name('students.login');
});

// Authenticated
Route::middleware(['auth:sanctum'])->group(function () {
    $routes = [
        'user/user',
        'role/role',
        'permission/permission',
        'role_permission/role_permission',
        'student/student',
    ];

    foreach ($routes as $route) {
        Route::group([], base_path("routes/api/{$route}.php"));
    }
});

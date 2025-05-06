<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)->name('auth.')->prefix('auth')->group(function () {
    Route::post('/login', 'login')->name('login');
    Route::post('/logout', 'logout')->middleware('auth:sanctum')->name('logout');

    Route::get('/auth-user', 'authUser')->middleware('auth:sanctum')->name('auth-user');
});

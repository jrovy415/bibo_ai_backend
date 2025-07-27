<?php

use App\Http\Controllers\Permission\PermissionController;
use Illuminate\Support\Facades\Route;

Route::apiResource('/permissions', PermissionController::class);

<?php

use App\Http\Controllers\Student\StudentController;
use Illuminate\Support\Facades\Route;

Route::apiResource('/students', StudentController::class);

Route::post('/students/logout', [StudentController::class, 'logout'])->name('students.logout');

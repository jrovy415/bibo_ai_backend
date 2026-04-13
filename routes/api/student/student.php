<?php

use App\Http\Controllers\Student\StudentController;
use Illuminate\Support\Facades\Route;

Route::apiResource('/students', StudentController::class);

Route::get('/students/{student}/difficulty', [StudentController::class, 'getDifficulty'])
    ->name('students.difficulty');

Route::patch('/students/{student}/difficulty', [StudentController::class, 'updateDifficulty'])
    ->name('students.updateDifficulty');

Route::post('/students/logout', [StudentController::class, 'logout'])
    ->name('students.logout');
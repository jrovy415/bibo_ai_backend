<?php

use App\Http\Controllers\AnswerController;
use App\Http\Controllers\ChoiceController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\QuestionTypeController;
use App\Http\Controllers\QuizAttemptController;
use App\Http\Controllers\QuizController;
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
    Route::prefix('quizzes')->controller(QuizController::class)->name('quizzes.')->group(function () {
        Route::get('get-quizzes/{grade_level}', 'getAllQuizzesByGradeLevel')->name('getAllQuizzesByGradeLevel');
        Route::get('get-quiz', 'getQuiz')->name('getQuiz');
    });
    Route::apiResource('quizzes', QuizController::class);

    Route::prefix('quiz-attempts')->controller(QuizAttemptController::class)->name('quiz-attempts.')->group(function () {
        Route::get('student-attempts/{studentId}', 'getAllQuizAttemptsByStudent')->name('getAllQuizAttemptsByStudent');
        Route::get('get-quiz', 'getQuiz')->name('getQuiz');
    });
    Route::apiResource('quiz-attempts', QuizAttemptController::class);

    Route::apiResource('questions', QuestionController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('choices', ChoiceController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('answers', AnswerController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('question-types', QuestionTypeController::class);

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

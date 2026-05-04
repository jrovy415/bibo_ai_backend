<?php

use App\Http\Controllers\AnswerController;
use App\Http\Controllers\ChoiceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\QuestionTypeController;
use App\Http\Controllers\QuizAttemptController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\Student\StudentController;
use App\Http\Controllers\StudentProgressController;
use App\Http\Controllers\QuizFeedbackController;
use App\Http\Controllers\StudentLockController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Unauthenticated
Route::middleware([])->group(function () {
    Route::group([], base_path('routes/api/auth/auth.php'));

    Route::post('/students/login', [StudentController::class, 'login'])->name('students.login');
    Route::post('/quizzes/{quiz}/questions/upload-photo', [QuestionController::class, 'uploadPhoto'])->name('question.uploadPhoto');
    Route::delete('/quizzes/{quiz}/questions/delete-photo', [QuestionController::class, 'deletePhoto'])->name('question.deletePhoto');
});

// Authenticated
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('quizzes')->controller(QuizController::class)->name('quizzes.')->group(function () {
        Route::get('get-all-quizzes', 'getAllQuizzes')->name('getAllQuizzes');
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

    // Student Progression — per-student journey tracking
    Route::get('/student-progress', [StudentProgressController::class, 'index']);

    // Student lock/unlock
    Route::patch('/students/{student}/lock',   [StudentLockController::class, 'lock']);
    Route::patch('/students/{student}/unlock', [StudentLockController::class, 'unlock']);

    // ✅ Student difficulty — get and update
    Route::get('/students/{student}/difficulty',   [StudentController::class, 'getDifficulty']);
    Route::patch('/students/{student}/difficulty', [StudentController::class, 'updateDifficulty']);

    // Dashboard — single endpoint replacing 4 separate student dashboard calls
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Notifications (polling)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/quiz-feedbacks',   [QuizFeedbackController::class, 'index']);
    Route::post('/quiz-feedbacks',  [QuizFeedbackController::class, 'store']);

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

// ⚠️ TEMPORARY — Delete this route after seeding!
Route::get('/run-seeder', function () {
    Artisan::call('db:seed');
    return response()->json(['message' => 'Seeded successfully!']);
});
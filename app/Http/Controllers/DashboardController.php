<?php

namespace App\Http\Controllers;

use App\Models\QuizAttempt;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends Controller
{
    /**
     * Single endpoint that replaces the four separate dashboard requests:
     *   GET /students/:id/difficulty
     *   GET /quizzes/get-quiz
     *   GET /quiz-attempts/student-attempts/:id  (dashboard)
     *   GET /quiz-attempts/student-attempts/:id  (history)
     *
     * All queries run in one PHP process → one HTTP round trip from the client.
     */
    public function index()
    {
        try {
            $student = Auth::user();
            $student->load('currentDifficulty');

            // 1. Current difficulty level
            $difficulty = $student->currentDifficulty?->difficulty ?? 'Introduction';

            // 2. Next quiz — reuse existing getQuiz() logic via the service container
            $quizResponse = app(QuizController::class)->getQuiz();
            $quizData     = json_decode($quizResponse->getContent(), true)['data'] ?? null;

            // 3. All attempts for history — skip 'student' and 'answers' auto-loads
            //    since the dashboard only needs quiz titles and scores
            $attempts = QuizAttempt::without(['student', 'answers'])
                ->where('student_id', $student->id)
                ->with('quiz.questions')
                ->orderBy('completed_at', 'asc')
                ->get();

            return response()->json([
                'data' => [
                    'difficulty' => $difficulty,
                    'quiz'       => $quizData,
                    'attempts'   => $attempts,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error loading dashboard: ' . $e->getMessage(),
                'data'    => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\QuizFeedback;
use App\Services\Utils\ResponseServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class QuizFeedbackController extends Controller
{
    protected $responseService;

    public function __construct(ResponseServiceInterface $responseService)
    {
        $this->responseService = $responseService;
    }

    /**
     * POST /quiz-feedbacks
     * Student submits feedback after a quiz
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'student_id'      => 'required|exists:students,id',
                'quiz_id'         => 'required|exists:quizzes,id',
                'quiz_attempt_id' => 'nullable|exists:quiz_attempts,id',
                'feeling'         => 'required|in:easy,okay,hard',
            ]);

            // One feedback per student per quiz attempt — update if already exists
            $feedback = QuizFeedback::updateOrCreate(
                [
                    'student_id' => $validated['student_id'],
                    'quiz_id'    => $validated['quiz_id'],
                ],
                [
                    'quiz_attempt_id' => $validated['quiz_attempt_id'] ?? null,
                    'feeling'         => $validated['feeling'],
                ]
            );

            return $this->responseService->resolveResponse(
                'Feedback submitted successfully',
                $feedback,
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error submitting feedback',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * GET /quiz-feedbacks
     * Teacher views all feedback with filters
     */
    public function index(Request $request)
    {
        try {
            $gradeLevel = $request->input('grade_level');
            $quizId     = $request->input('quiz_id');
            $feeling    = $request->input('feeling');

            $query = QuizFeedback::with([
                'student:id,nickname,grade_level,section',
                'quiz:id,title,difficulty,grade_level',
            ])->orderBy('created_at', 'desc');

            if ($gradeLevel) {
                $query->whereHas('student', fn($q) => $q->where('grade_level', $gradeLevel));
            }
            if ($quizId)  $query->where('quiz_id', $quizId);
            if ($feeling) $query->where('feeling', $feeling);

            $feedbacks = $query->get();

            // Summary counts
            $total = $feedbacks->count();
            $easy  = $feedbacks->where('feeling', 'easy')->count();
            $okay  = $feedbacks->where('feeling', 'okay')->count();
            $hard  = $feedbacks->where('feeling', 'hard')->count();

            // Per-quiz breakdown
            $perQuiz = $feedbacks->groupBy('quiz_id')->map(function ($group) {
                $quiz = $group->first()->quiz;
                return [
                    'quiz_id'    => $quiz?->id,
                    'quiz_title' => $quiz?->title,
                    'difficulty' => $quiz?->difficulty,
                    'total'      => $group->count(),
                    'easy'       => $group->where('feeling', 'easy')->count(),
                    'okay'       => $group->where('feeling', 'okay')->count(),
                    'hard'       => $group->where('feeling', 'hard')->count(),
                ];
            })->values();

            return $this->responseService->resolveResponse(
                'Feedback retrieved successfully',
                [
                    'summary'   => compact('total', 'easy', 'okay', 'hard'),
                    'per_quiz'  => $perQuiz,
                    'feedbacks' => $feedbacks,
                ]
            );
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error retrieving feedback',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
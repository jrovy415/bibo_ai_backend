<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuizRequest as ModelRequest;
use App\Models\Answer;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\StudentDifficulty;
use App\Services\Utils\ResponseServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class QuizAttemptController extends Controller
{
    protected $responseService;
    protected $model;

    public function __construct(ResponseServiceInterface $responseService)
    {
        $this->responseService = $responseService;
        $this->model = new QuizAttempt();
    }

    public function index()
    {
        $sortByColumn = request()->input('sort_by_column', 'created_at');
        $sortBy       = request()->input('sort_by', 'desc');
        $all          = request()->boolean('all');
        $limit        = request()->input('limit', 10);

        $modelName = $this->model->model_name;

        $query = $this->model->with(['quiz', 'student'])->filter()->newQuery()->orderBy($sortByColumn, $sortBy);

        $data = $all ? $query->get() : $query->paginate($limit);

        return $this->responseService->successResponse($modelName, $data);
    }

    public function store(Request $request)
    {
        $auth = Auth::user();

        $validated = $request->validate([
            'quiz_id' => 'required|exists:quizzes,id',
        ]);

        try {
            $quiz = Quiz::findOrFail($validated['quiz_id']);

            if ($quiz) {
                $data = [
                    'title'       => $quiz->title,
                    'grade_level' => $quiz->grade_level,
                    'difficulty'  => $quiz->difficulty,
                    'student_id'  => $auth->id,
                ];

                $attempt = $this->model->updateOrCreate(
                    [
                        'quiz_id'      => $quiz->id,
                        'student_id'   => $auth->id,
                        'completed_at' => null,
                    ],
                    [
                        ...$data,
                        'started_at' => Carbon::now(),
                    ]
                );

                return $this->responseService->resolveResponse(
                    'Quiz Attempt created or reused successfully',
                    $attempt,
                    Response::HTTP_CREATED
                );
            }
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error creating quiz attempt',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function show($id)
    {
        try {
            $attempt = $this->model->with(['quiz', 'student'])->find($id);

            if (!$attempt) {
                return $this->responseService->resolveResponse(
                    'Quiz Attempt not found',
                    null,
                    Response::HTTP_NOT_FOUND
                );
            }

            return $this->responseService->resolveResponse(
                'Quiz Attempt retrieved successfully',
                $attempt
            );
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error retrieving quiz attempt',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            // Load quiz with questions so we can calculate total possible points
            $attempt   = QuizAttempt::with('quiz.questions')->findOrFail($id);
            $quiz      = $attempt->quiz;
            $studentId = $attempt->student_id;

            // ── Calculate score ──────────────────────────────────────────────
            // Pre-Test (Introduction) and Post-Test ALWAYS use word_score
            // because they are reading assessments with partial credit.
            // All other levels respect use_word_scoring per question:
            //   - use_word_scoring = true  -> use word_score (partial credit)
            //   - use_word_scoring = false -> use is_correct x points (exact match)
            $answers = Answer::where('attempt_id', $attempt->id)->get();
            $isReadingAssessment = in_array($quiz->difficulty, ['Introduction', 'PostTest']);

            // For reading assessments, word_score is stored as 0-100 percentage per question.
            // We average these percentages to get the overall score (also 0-100).
            // For other quizzes, word_score is raw points or is_correct * points.
            if ($isReadingAssessment) {
                $totalPct = 0;
                $count    = 0;
                foreach ($answers as $answer) {
                    $totalPct += (int) ($answer->word_score ?? 0); // each is 0-100
                    $count++;
                }
                // score = average percentage across all questions (0-100)
                $score = $count > 0 ? round($totalPct / $count) : 0;
            } else {
                $score = 0;
                foreach ($answers as $answer) {
                    $question = $quiz->questions->firstWhere('id', $answer->question_id);
                    if (!$question) continue;
                    if ($question->use_word_scoring) {
                        $score += (int) ($answer->word_score ?? 0);
                    } else {
                        $score += $answer->is_correct ? ($question->points ?? 1) : 0;
                    }
                }
            }

            $attempt->update([
                'score'        => $score,
                'completed_at' => now(),
            ]);

            // ── Difficulty progression ────────────────────────────────────────
            $newDifficulty = null;

            if ($quiz->difficulty === 'Introduction') {
                // ── Pre-Test: percentage-based placement ──────────────────────
                // Thresholds:
                //   >= 80% → Expert
                //   >= 60% → Hard
                //   >= 40% → Medium
                //    < 40% → Easy
                // For reading assessments, word_score is stored as:
                // Math.round((matchedWords / totalWords) * questionPoints)
                // So total score / total possible points = match percentage
                // score is already 0-100 average percentage for reading assessments
                $percentage = $score; // score IS the percentage
                \Log::info("Pre-Test placement: pct={$percentage}%");

                if ($percentage >= 80) {
                    $newDifficulty = 'Expert';
                } elseif ($percentage >= 60) {
                    $newDifficulty = 'Hard';
                } elseif ($percentage >= 40) {
                    $newDifficulty = 'Medium';
                } else {
                    $newDifficulty = 'Easy';
                }

            } elseif ($quiz->difficulty === 'PostTest') {
                // ── Post-Test: no further advancement needed ──────────────────
                // Student has completed everything. Do not change difficulty.
                $newDifficulty = null;

            } else {
                // ── Regular level (Easy / Medium / Hard / Expert) ─────────────
                //
                // KEY FIX: After finishing their assigned level, the student
                // goes directly to PostTest — NOT the next level in the sequence.
                //
                // How it works:
                //   1. Get the student's currently assigned difficulty
                //      (set by the Pre-Test placement or a previous advance).
                //   2. If the quiz they just finished matches their assigned
                //      difficulty, advance them to PostTest.
                //   3. This means:
                //        Pre-Test → Easy   assigned → finish Easy   → PostTest ✅
                //        Pre-Test → Medium assigned → finish Medium → PostTest ✅
                //        Pre-Test → Hard   assigned → finish Hard   → PostTest ✅
                //        Pre-Test → Expert assigned → finish Expert → PostTest ✅

                // Get the student's assigned difficulty from student_difficulties table
                $studentDifficulty = DB::table('student_difficulties')
                    ->where('student_id', $studentId)
                    ->value('difficulty');

                // If the quiz difficulty matches the student's assigned level,
                // they have completed their required level → go to PostTest.
                if ($studentDifficulty && $quiz->difficulty === $studentDifficulty) {
                    $newDifficulty = 'PostTest';
                } else {
                    // Fallback: if for some reason the difficulties don't match
                    // (e.g. student retaking a lower level), just advance normally.
                    $difficultySequence = ['Easy', 'Medium', 'Hard', 'Expert', 'PostTest'];
                    $currentIdx = array_search($quiz->difficulty, $difficultySequence);

                    if ($currentIdx !== false && isset($difficultySequence[$currentIdx + 1])) {
                        $newDifficulty = $difficultySequence[$currentIdx + 1];
                    }
                }
            }

            // Save the new difficulty level for the student
            if ($newDifficulty) {
                DB::table('student_difficulties')
                    ->updateOrInsert(
                        ['student_id' => $studentId],
                        [
                            'difficulty' => $newDifficulty,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
            }

            DB::commit();

            return $this->responseService->resolveResponse(
                'Quiz attempt updated successfully',
                $attempt
            );

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->responseService->resolveResponse(
                'Error updating quiz attempt',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function destroy($id)
    {
        try {
            $attempt = $this->model->findOrFail($id);
            $attempt->delete();

            return $this->responseService->deleteResponse('Quiz Attempt', null);
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error deleting quiz attempt',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function getAllQuizAttemptsByStudent(Request $request, $studentId)
    {
        try {
            $isLatest = filter_var($request->query('is_latest', false), FILTER_VALIDATE_BOOLEAN);

            if ($isLatest) {
                $quizAttempts = QuizAttempt::where('student_id', $studentId)
                    ->with('quiz.questions')
                    ->latest()
                    ->first();
            } else {
                $quizAttempts = QuizAttempt::where('student_id', $studentId)
                    ->with('quiz.questions')
                    ->get();
            }

            return $this->responseService->resolveResponse(
                "Quiz Attempts retrieved successfully",
                $quizAttempts
            );
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error retrieving quiz attempts',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
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
            $attempt   = QuizAttempt::with('quiz.questions')->findOrFail($id);
            $quiz      = $attempt->quiz;
            $studentId = $attempt->student_id;

            // ── Reading assessment difficulties (word_score = 0-100 percentage) ──
            $readingAssessmentDifficulties = [
                'Introduction',
                'EasyPostTest',
                'MediumPostTest',
                'HardPostTest',
                'ExpertPostTest',
                'PostTest',
            ];

            $isReadingAssessment = in_array($quiz->difficulty, $readingAssessmentDifficulties);

            $answers = Answer::where('attempt_id', $attempt->id)->get();

            // ── Score calculation ─────────────────────────────────────────────
            if ($isReadingAssessment) {
                // Reading assessments: word_score per question is 0-100 percentage
                // Final score = average of all word_scores (also 0-100)
                $totalPct = 0;
                $count    = 0;
                foreach ($answers as $answer) {
                    $totalPct += (int) ($answer->word_score ?? 0);
                    $count++;
                }
                $score = $count > 0 ? round($totalPct / $count) : 0;
            } else {
                // Regular level quizzes: use word_score (partial) or is_correct (exact)
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
            // Flow:
            //   Pre-Test (Introduction) → placement (Easy/Medium/Hard/Expert)
            //   Easy        → EasyPostTest     (regardless of score)
            //   EasyPostTest → done (student finished their journey)
            //   Medium      → MediumPostTest   (regardless of score)
            //   MediumPostTest → done
            //   Hard        → HardPostTest     (regardless of score)
            //   HardPostTest → done
            //   Expert      → ExpertPostTest   (regardless of score)
            //   ExpertPostTest → done
            //   PostTest → done (no further advancement)

            // ✅ Map each reading level to its own PostTest
            $levelToPostTest = [
                'Easy'   => 'EasyPostTest',
                'Medium' => 'MediumPostTest',
                'Hard'   => 'HardPostTest',
                'Expert' => 'ExpertPostTest',
            ];

            // ✅ PostTest difficulties that mark end of journey
            $terminalDifficulties = [
                'EasyPostTest',
                'MediumPostTest',
                'HardPostTest',
                'ExpertPostTest',
                'PostTest',
            ];

            $newDifficulty = null;

            if ($quiz->difficulty === 'Introduction') {
                // ── Pre-Test: percentage-based placement ──────────────────────
                // score IS the 0-100 average percentage for reading assessments
                $percentage = $score;
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

            } elseif (in_array($quiz->difficulty, $terminalDifficulties)) {
                // ── PostTest / terminal level: no further advancement ─────────
                // Note: Auto-lock is handled by the frontend Finish button
                // which calls /students/{id}/lock before logging out.
                // This keeps the results page accessible after completing PostTest.
                $newDifficulty = null;

            } elseif (isset($levelToPostTest[$quiz->difficulty])) {
                // ── Regular level (Easy/Medium/Hard/Expert) ───────────────────
                // REGARDLESS of score, always advance to that level's PostTest
                $newDifficulty = $levelToPostTest[$quiz->difficulty];

            } else {
                // ── Fallback: unknown difficulty, just stay put ───────────────
                $newDifficulty = null;
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

                \Log::info("Student {$studentId} advanced from {$quiz->difficulty} to {$newDifficulty}");
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
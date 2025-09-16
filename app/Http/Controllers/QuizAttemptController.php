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
                        'quiz_id'    => $quiz->id,
                        'student_id' => $auth->id,
                        'completed_at' => null, // only reuse if still incomplete
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
            $attempt = QuizAttempt::with('quiz', 'student')->findOrFail($id);
            $quiz = $attempt->quiz;
            $student = $attempt->student;

            // Calculate score
            $score = Answer::where('attempt_id', $attempt->id)
                ->where('is_correct', true)
                ->count();
            $totalItems = $quiz->questions()->count();

            $attempt->update([
                'score' => $score,
                'completed_at' => now(),
            ]);

            // --- Difficulty progression ---
            $newDifficulty = null;

            if ($quiz->difficulty === 'Introduction') {
                $newDifficulty = 'Easy';
            }

            if ($quiz->difficulty === 'Easy') {
                $allEasyPerfected = Quiz::where('grade_level', $student->grade_level)
                    ->where('difficulty', 'Easy')
                    ->whereDoesntHave('quizAttempt', function ($q) use ($student) {
                        $q->where('student_id', $student->id)
                            ->whereNotNull('completed_at')
                            ->whereColumn('score', DB::raw('(select count(*) from questions where questions.quiz_id = quizzes.id)'));
                    })
                    ->doesntExist();

                $newDifficulty = $allEasyPerfected ? 'Medium' : 'Easy';
            }

            if ($quiz->difficulty === 'Medium') {
                $allMediumPerfected = Quiz::where('grade_level', $student->grade_level)
                    ->where('difficulty', 'Medium')
                    ->whereDoesntHave('quizAttempt', function ($q) use ($student) {
                        $q->where('student_id', $student->id)
                            ->whereNotNull('completed_at')
                            ->whereColumn('score', DB::raw('(select count(*) from questions where questions.quiz_id = quizzes.id)'));
                    })
                    ->doesntExist();

                $newDifficulty = $allMediumPerfected ? 'Hard' : 'Medium';
            }

            if ($quiz->difficulty === 'Hard') {
                $newDifficulty = 'Hard'; // end of progression
            }

            // Save/update student's current difficulty
            if ($newDifficulty) {
                StudentDifficulty::updateOrCreate(
                    ['student_id' => $student->id], // 👈 1 row per student
                    ['difficulty' => $newDifficulty]
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
                    ->latest() // defaults to created_at
                    ->first();
            } else {
                $quizAttempts = QuizAttempt::where('student_id', $studentId)->get();
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

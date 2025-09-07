<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuizRequest as ModelRequest;
use App\Models\Answer;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Choice;
use App\Models\QuestionType;
use App\Models\QuizAttempt;
use App\Services\Utils\ResponseServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class QuizController extends Controller
{
    protected $responseService;
    protected $model;

    public function __construct(ResponseServiceInterface $responseService)
    {
        $this->responseService = $responseService;
        $this->model = new Quiz();
    }

    public function index()
    {
        $sortByColumn  = request()->input('sort_by_column', 'created_at');
        $sortBy        = request()->input('sort_by', 'desc');
        $all           = request()->boolean('all');
        $limit         = request()->input('limit', 10);

        $modelName = $this->model->model_name;

        $query = $this->model
            ->filter()
            ->newQuery()
            ->orderBy($sortByColumn, $sortBy);

        $data = $all ? $query->get() : $query->paginate($limit);

        return $this->responseService->successResponse($modelName, $data);
    }

    public function store(ModelRequest $request)
    {
        DB::beginTransaction();

        try {
            $quiz = Quiz::create([
                'teacher_id'   => auth()->id(),
                'title'        => $request->title,
                'instructions' => $request->instructions,
                'grade_level'  => $request->grade_level,
                'difficulty'   => $request->difficulty,
                'time_limit'   => $request->time_limit,
                'is_active'    => $request->is_active ?? true,
            ]);

            collect($request->questions ?? [])->map(function ($questionData) use ($quiz) {
                $question = Question::create([
                    'quiz_id'         => $quiz->id,
                    'question_text'   => $questionData['question_text'],
                    'question_type_id' => $questionData['question_type_id'],
                    'points'          => $questionData['points'],
                ]);

                collect($questionData['choices'] ?? [])->map(
                    fn($choiceData) =>
                    Choice::create([
                        'question_id'  => $question->id,
                        'choice_text'  => $choiceData['choice_text'],
                        'is_correct'   => $choiceData['is_correct'] ?? false,
                    ])
                );
            });

            DB::commit();

            $quiz->load(['questions.choices', 'questions.questionType']);

            return $this->responseService->resolveResponse(
                'Quiz created successfully',
                $quiz,
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->responseService->resolveResponse(
                'Error creating quiz',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function show($id)
    {
        try {
            $quiz = Quiz::find($id);

            if (!$quiz) {
                return $this->responseService->resolveResponse(
                    'Quiz not found',
                    null,
                    Response::HTTP_NOT_FOUND
                );
            }

            return $this->responseService->resolveResponse(
                'Quiz retrieved successfully',
                $quiz
            );
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error retrieving quiz',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function update(ModelRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            $quiz = Quiz::find($id);

            if (!$quiz) {
                return $this->responseService->resolveResponse(
                    'Quiz not found',
                    null,
                    Response::HTTP_NOT_FOUND
                );
            }

            $quiz->update([
                'title'        => $request->title,
                'instructions' => $request->instructions,
                'grade_level'  => $request->grade_level,
                'difficulty'   => $request->difficulty,
                'time_limit'   => $request->time_limit,
                'is_active'    => $request->is_active ?? true,
            ]);

            $quiz->questions()->delete();

            collect($request->questions ?? [])->map(function ($questionData) use ($quiz) {
                $question = Question::create([
                    'quiz_id'         => $quiz->id,
                    'question_text'   => $questionData['question_text'],
                    'question_type_id' => $questionData['question_type_id'],
                    'points'          => $questionData['points'],
                ]);

                collect($questionData['choices'] ?? [])->map(
                    fn($choiceData) =>
                    Choice::create([
                        'question_id'  => $question->id,
                        'choice_text'  => $choiceData['choice_text'],
                        'is_correct'   => $choiceData['is_correct'] ?? false,
                    ])
                );
            });

            DB::commit();

            $quiz->load(['questions.choices', 'questions.questionType']);

            return $this->responseService->resolveResponse(
                'Quiz updated successfully',
                $quiz
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->responseService->resolveResponse(
                'Error updating quiz',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function destroy($id)
    {
        try {
            $quiz = Quiz::findOrFail($id);
            $quiz->delete();

            return $this->responseService->deleteResponse('Quiz', null);
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error deleting quiz',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function getQuiz()
    {
        $student = Auth::user();

        try {
            // 1. Check for an incomplete attempt first
            $incompleteAttempt = QuizAttempt::where('student_id', $student->id)
                ->whereNull('completed_at')
                ->whereNull('score')
                ->latest()
                ->first();

            if ($incompleteAttempt) {
                $quiz = $incompleteAttempt->quiz()->with('questions.choices')->first();

                return $this->responseService->resolveResponse(
                    'Resuming incomplete quiz',
                    $quiz
                );
            }

            // 2. Fetch the last completed attempt
            $lastCompletedAttempt = QuizAttempt::where('student_id', $student->id)
                ->whereNotNull('completed_at')
                ->whereNotNull('score')
                ->latest()
                ->first();

            // 3. Determine next quiz
            $nextQuiz = null;

            if ($lastCompletedAttempt) {
                $nextQuiz = $this->nextQuiz($lastCompletedAttempt, $student);
            }

            // 4. If no next quiz based on last attempt, assign an unattempted Introduction quiz
            if (!$nextQuiz) {
                $nextQuiz = Quiz::where('grade_level', $student->grade_level)
                    ->whereDoesntHave('quizAttempt', function ($query) use ($student) {
                        $query->where('student_id', $student->id)
                            ->whereNotNull('completed_at')
                            ->whereNotNull('score');
                    })
                    ->with('questions.choices')
                    ->first();
            }

            if (!$nextQuiz) {
                return $this->responseService->resolveResponse(
                    'All quizzes completed for your grade level',
                    null,
                    Response::HTTP_NOT_FOUND
                );
            }

            return $this->responseService->resolveResponse(
                'Quiz retrieved successfully',
                $nextQuiz
            );
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error retrieving quiz',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    private function nextQuiz($completedQuizAttempt, $student)
    {
        $quiz = Quiz::with('questions')->find($completedQuizAttempt->quiz_id);
        if (!$quiz) return null;

        $quizItems = count($quiz->questions);
        $studentScore = $completedQuizAttempt->score;
        $currentDifficulty = $quiz->difficulty;

        // Determine next difficulty based on score
        $nextDifficulty = match ($currentDifficulty) {
            'Introduction', 'Easy' => ($studentScore == $quizItems) ? 'Medium' : (($studentScore >= intval($quizItems / 2)) ? 'Easy' : 'Introduction'),
            'Medium' => ($studentScore == $quizItems) ? 'Hard' : (($studentScore >= intval($quizItems / 2)) ? 'Medium' : 'Easy'),
            'Hard' => ($studentScore == $quizItems && $this->hasCompletedAllQuizzes($student)) ? null : (($studentScore >= intval($quizItems / 2)) ? 'Hard' : 'Medium'),
            default => null,
        };

        if (!$nextDifficulty) return null;

        // Fetch **next quiz that student has NOT completed**
        $nextQuiz = Quiz::where('grade_level', $student->grade_level)
            ->where('difficulty', $nextDifficulty)
            ->whereDoesntHave('quizAttempt', function ($query) use ($student) {
                $query->where('student_id', $student->id)
                    ->whereNotNull('completed_at')
                    ->whereNotNull('score');
            })
            ->with('questions.choices')
            ->first();

        return $nextQuiz;
    }

    /**
     * Check if student has completed all quiz difficulties for their grade level
     */
    private function hasCompletedAllQuizzes($student)
    {
        $difficulties = ['Introduction', 'Easy', 'Medium', 'Hard'];
        $completedDifficulties = [];

        foreach ($difficulties as $difficulty) {
            $completedAttempt = QuizAttempt::where('student_id', $student->id)
                ->whereHas('quiz', function ($query) use ($student, $difficulty) {
                    $query->where('grade_level', $student->grade_level)
                        ->where('difficulty', $difficulty);
                })
                ->whereNotNull('completed_at')
                ->whereNotNull('score')
                ->first();

            if ($completedAttempt) {
                $completedDifficulties[] = $difficulty;
            }
        }

        logger('Completed Diffuculties', $completedDifficulties);

        return count($completedDifficulties) === count($difficulties);
    }

    /**
     * Get all quizzes for student's grade level with completion status
     */
    public function getQuizDashboard()
    {
        $student = Auth::user();

        try {
            $quizzes = Quiz::where('grade_level', $student->grade_level)
                ->with(['questions'])
                ->get()
                ->map(function ($quiz) use ($student) {
                    // Get the best attempt for this quiz
                    $bestAttempt = QuizAttempt::where('student_id', $student->id)
                        ->where('quiz_id', $quiz->id)
                        ->whereNotNull('completed_at')
                        ->whereNotNull('score')
                        ->orderBy('score', 'desc')
                        ->first();

                    $quiz->total_questions = count($quiz->questions);
                    $quiz->best_score = $bestAttempt ? $bestAttempt->score : null;
                    $quiz->completion_percentage = $bestAttempt ?
                        round(($bestAttempt->score / $quiz->total_questions) * 100, 2) : 0;
                    $quiz->completed_at = $bestAttempt ? $bestAttempt->completed_at : null;
                    $quiz->is_completed = $bestAttempt ? true : false;
                    $quiz->attempts_count = QuizAttempt::where('student_id', $student->id)
                        ->where('quiz_id', $quiz->id)
                        ->whereNotNull('completed_at')
                        ->count();

                    // Remove questions from response for lighter payload
                    unset($quiz->questions);

                    return $quiz;
                });

            return $this->responseService->resolveResponse(
                'Quiz dashboard retrieved successfully',
                [
                    'quizzes' => $quizzes,
                    'grade_level' => $student->grade_level,
                    'total_quizzes' => $quizzes->count(),
                    'completed_quizzes' => $quizzes->where('is_completed', true)->count(),
                ]
            );
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error retrieving quiz dashboard',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function getAllQuizzesByGradeLevel($gradeLevel)
    {
        try {
            $quizzes = Quiz::where('grade_level', $gradeLevel)->get();

            return $this->responseService->resolveResponse(
                `Grade $gradeLevel quizzes retrieved successfully`,
                $quizzes
            );
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error retrieving quiz dashboard',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}

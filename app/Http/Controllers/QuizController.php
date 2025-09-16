<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuizRequest as ModelRequest;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Choice;
use App\Models\QuizAttempt;
use App\Services\Utils\ResponseServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

            collect($request->questions)->map(function ($questionData) use ($quiz) {
                $question = Question::create([
                    'quiz_id'          => $quiz->id,
                    'question_text'    => $questionData['question_text'],
                    'question_type_id' => $questionData['question_type_id'],
                    'points'           => $questionData['points'],
                    'photo'            => $questionData['photo']
                ]);

                // handle photo upload
                $this->handlePhotoUpload($quiz->id, $question, $questionData);

                // handle choices
                collect($questionData['choices'] ?? [])->map(function ($choiceData) use ($question) {
                    Choice::create([
                        'question_id' => $question->id,
                        'choice_text' => $choiceData['choice_text'],
                        'is_correct'  => $choiceData['is_correct'] ?? false,
                    ]);
                });
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

            // wipe old questions + choices
            $quiz->questions()->delete();

            collect($request->questions)->map(function ($questionData) use ($quiz) {
                $question = Question::create([
                    'quiz_id'          => $quiz->id,
                    'question_text'    => $questionData['question_text'],
                    'question_type_id' => $questionData['question_type_id'],
                    'points'           => $questionData['points'],
                    'photo'            => $questionData['photo']
                ]);

                // handle photo upload
                $this->handlePhotoUpload($quiz->id, $question, $questionData);

                // handle choices
                collect($questionData['choices'] ?? [])->map(function ($choiceData) use ($question) {
                    Choice::create([
                        'question_id' => $question->id,
                        'choice_text' => $choiceData['choice_text'],
                        'is_correct'  => $choiceData['is_correct'] ?? false,
                    ]);
                });
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

    public function getQuiz()
    {
        $student = Auth::user();

        try {
            // 1. Resume incomplete attempt
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

            // 2. Current difficulty (default: Introduction)
            $currentDifficulty = $student->currentDifficulty?->difficulty ?? 'Introduction';

            // 3. Try to get next quiz
            $nextQuiz = Quiz::where('grade_level', $student->grade_level)
                ->where('difficulty', $currentDifficulty)
                ->whereDoesntHave('quizAttempt', function ($query) use ($student) {
                    $query->where('student_id', $student->id)
                        ->whereNotNull('completed_at')
                        ->whereRaw('quiz_attempts.score = (select count(*) from questions where questions.quiz_id = quiz_attempts.quiz_id)');
                })
                ->with('questions.choices')
                ->first();

            if ($nextQuiz) {
                return $this->responseService->resolveResponse(
                    'Quiz retrieved successfully',
                    $nextQuiz
                );
            }

            // 4. If no quiz left, fetch ALL quizzes for the student's grade level
            $allQuizzes = Quiz::where('grade_level', $student->grade_level)
                ->with(['questions.choices', 'latestQuizAttempt'])
                ->get()
                ->groupBy('difficulty'); // optional: group by difficulty for frontend

            if ($allQuizzes->isEmpty()) {
                return $this->responseService->resolveResponse(
                    'No quizzes available for your grade level.',
                    null,
                    Response::HTTP_NOT_FOUND
                );
            }

            return $this->responseService->resolveResponse(
                'All quizzes are completed. Showing all quizzes for retake.',
                $allQuizzes
            );
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error retrieving quiz',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Save photo under quizzes/{quizId} and update question.photo with filename
     */
    private function handlePhotoUpload(string $quizId, Question $question, array $questionData): void
    {
        if (isset($questionData['photo']) && $questionData['photo'] instanceof UploadedFile) {
            $path = $questionData['photo']->store("quizzes/{$quizId}", 'public');
            $filename = basename($path);
            $question->update(['photo' => $filename]);
        } elseif (!empty($questionData['existing_photo'])) {
            $question->update(['photo' => basename($questionData['existing_photo'])]);
        }
    }
}

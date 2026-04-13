<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuizRequest as ModelRequest;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Choice;
use App\Models\QuizAttempt;
use App\Models\QuizMaterial;
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
                'time_limit'   => $request->time_limit ?? 10,
                'is_active'    => $request->is_active ?? true,
            ]);

            if ($request->filled('material')) {
                QuizMaterial::create([
                    'quiz_id' => $quiz->id,
                    'title'   => $request->material['title'],
                    'type'    => $request->material['type'],
                    'content' => $request->material['content'],
                ]);
            }

            collect($request->questions)->map(function ($questionData) use ($quiz) {
                $question = Question::create([
                    'quiz_id'          => $quiz->id,
                    'question_text'    => $questionData['question_text'],
                    'question_type_id' => $questionData['question_type_id'],
                    'points'           => $questionData['points'],
                    'photo'            => $questionData['photo'] ?? null,
                    // Save the teacher's scoring choice per question
                    'use_word_scoring' => $questionData['use_word_scoring'] ?? false,
                ]);

                $this->handlePhotoUpload($quiz->id, $question, $questionData);

                collect($questionData['choices'] ?? [])->map(function ($choiceData) use ($question) {
                    Choice::create([
                        'question_id' => $question->id,
                        'choice_text' => $choiceData['choice_text'] !== $question['question_text'] ? $choiceData['choice_text'] : $question['question_text'],
                        'is_correct'  => $choiceData['is_correct'] ?? false,
                    ]);
                });
            });

            DB::commit();

            $quiz->load(['questions.choices', 'questions.questionType', 'material']);

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
                'time_limit'   => $request->time_limit ?? 10,
                'is_active'    => $request->is_active ?? true,
            ]);

            // Wipe old questions + choices
            $quiz->questions()->delete();

            // Wipe old materials
            $quiz->material()->delete();

            collect($request->questions)->map(function ($questionData) use ($quiz) {
                $question = Question::create([
                    'quiz_id'          => $quiz->id,
                    'question_text'    => $questionData['question_text'],
                    'question_type_id' => $questionData['question_type_id'],
                    'points'           => $questionData['points'],
                    'photo'            => $questionData['photo'] ?? null,
                    // Save the teacher's scoring choice per question
                    'use_word_scoring' => $questionData['use_word_scoring'] ?? false,
                ]);

                $this->handlePhotoUpload($quiz->id, $question, $questionData);

                collect($questionData['choices'] ?? [])->map(function ($choiceData) use ($question) {
                    Choice::create([
                        'question_id' => $question->id,
                        'choice_text' => $choiceData['choice_text'] !== $question['question_text'] ? $choiceData['choice_text'] : $question['question_text'],
                        'is_correct'  => $choiceData['is_correct'] ?? false,
                    ]);
                });
            });

            if ($request->filled('material')) {
                QuizMaterial::create([
                    'quiz_id' => $quiz->id,
                    'title'   => $request->material['title'],
                    'type'    => $request->material['type'],
                    'content' => $request->material['content'],
                ]);
            }

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

            foreach ($quiz->questions as $question) {
                $question->choices()->delete();
                $question->delete();
            }

            if (method_exists($quiz, 'material')) {
                $quiz->material()->delete();
            }

            $quiz->delete();

            DB::commit();

            return $this->responseService->resolveResponse(
                'Quiz deleted successfully',
                null,
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            DB::rollBack();

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

        $difficultySequence = ['Introduction', 'Easy', 'Medium', 'Hard', 'Expert', 'PostTest'];

        try {
            // 1. Resume incomplete attempt
            $incompleteAttempt = QuizAttempt::where('student_id', $student->id)
                ->whereNull('completed_at')
                ->whereNull('score')
                ->latest()
                ->first();

            if ($incompleteAttempt) {
                $quiz = $incompleteAttempt->quiz()
                    ->with(['questions.choices', 'questions.questionType', 'material'])
                    ->first();

                return $this->responseService->resolveResponse(
                    'Resuming incomplete quiz',
                    $quiz
                );
            }

            // 2. Get student's current difficulty
            $currentDifficulty = $student->currentDifficulty?->difficulty ?? 'Introduction';

            // 3. If PostTest already completed, signal all done
            if ($currentDifficulty === 'PostTest') {
                $completedPostTest = Quiz::where('grade_level', $student->grade_level)
                    ->where('difficulty', 'PostTest')
                    ->whereHas('quizAttempt', function ($query) use ($student) {
                        $query->where('student_id', $student->id)
                              ->whereNotNull('completed_at');
                    })
                    ->with(['questions.choices', 'questions.questionType', 'material', 'latestQuizAttempt'])
                    ->first();

                if ($completedPostTest) {
                    return $this->responseService->resolveResponse(
                        'All levels completed including PostTest.',
                        $completedPostTest
                    );
                }
            }

            // 4. Find next uncompleted quiz at current difficulty
            $nextQuiz = Quiz::where('grade_level', $student->grade_level)
                ->where('difficulty', $currentDifficulty)
                ->whereDoesntHave('quizAttempt', function ($query) use ($student) {
                    $query->where('student_id', $student->id)
                          ->whereNotNull('completed_at');
                })
                ->with(['questions.choices', 'questions.questionType', 'material'])
                ->first();

            if ($nextQuiz) {
                return $this->responseService->resolveResponse(
                    'Quiz retrieved successfully',
                    $nextQuiz
                );
            }

            // 5. Advance to next difficulty
            $currentIdx     = array_search($currentDifficulty, $difficultySequence);
            $nextDifficulty = isset($difficultySequence[$currentIdx + 1])
                ? $difficultySequence[$currentIdx + 1]
                : null;

            if ($nextDifficulty) {
                try {
                    \DB::table('student_difficulties')
                        ->updateOrInsert(
                            ['student_id' => $student->id],
                            ['difficulty' => $nextDifficulty, 'updated_at' => now(), 'created_at' => now()]
                        );
                    $student->load('currentDifficulty');
                } catch (\Exception $advanceErr) {
                    \Log::warning("Could not auto-advance difficulty for student {$student->id}: " . $advanceErr->getMessage());
                }

                $nextLevelQuiz = Quiz::where('grade_level', $student->grade_level)
                    ->where('difficulty', $nextDifficulty)
                    ->whereDoesntHave('quizAttempt', function ($query) use ($student) {
                        $query->where('student_id', $student->id)
                              ->whereNotNull('completed_at');
                    })
                    ->with(['questions.choices', 'questions.questionType', 'material'])
                    ->first();

                if ($nextLevelQuiz) {
                    return $this->responseService->resolveResponse(
                        "Advanced to {$nextDifficulty} level. Quiz retrieved.",
                        $nextLevelQuiz
                    );
                }
            }

            // 6. Nothing left — return all grouped
            $allQuizzes = Quiz::where('grade_level', $student->grade_level)
                ->with(['questions.choices', 'questions.questionType', 'latestQuizAttempt'])
                ->get()
                ->groupBy('difficulty');

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

    private function handlePhotoUpload(string $quizId, Question $question, array $questionData): void
    {
        if (isset($questionData['photo']) && $questionData['photo'] instanceof UploadedFile) {
            $path     = $questionData['photo']->store("quizzes/{$quizId}", 'public');
            $filename = basename($path);
            $question->update(['photo' => $filename]);
        } elseif (!empty($questionData['existing_photo'])) {
            $question->update(['photo' => basename($questionData['existing_photo'])]);
        }
    }
}
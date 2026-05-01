<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuizRequest as ModelRequest;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Choice;
use App\Models\Answer;
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

            $quiz->questions()->delete();
            $quiz->material()->delete();

            collect($request->questions)->map(function ($questionData) use ($quiz) {
                $question = Question::create([
                    'quiz_id'          => $quiz->id,
                    'question_text'    => $questionData['question_text'],
                    'question_type_id' => $questionData['question_type_id'],
                    'points'           => $questionData['points'],
                    'photo'            => $questionData['photo'] ?? null,
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

        $difficultySequence = [
            'Introduction',
            'Easy',   'EasyPostTest',
            'Medium', 'MediumPostTest',
            'Hard',   'HardPostTest',
            'Expert', 'ExpertPostTest',
        ];

        $postTestDifficulties = ['EasyPostTest','MediumPostTest','HardPostTest','ExpertPostTest','PostTest'];

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

                if (!$quiz) {
                    // Quiz was deleted — orphaned attempt, skip it and fall through
                } else {
                    $totalQuestions = $quiz->questions->count();
                    $answeredCount  = Answer::where('attempt_id', $incompleteAttempt->id)->count();

                    if ($totalQuestions > 0 && $answeredCount >= $totalQuestions) {
                        // All questions answered but PATCH /quiz-attempts never reached the server
                        // (Render timeout). Auto-complete so the student can advance.
                        $this->autoCompleteAttempt($incompleteAttempt, $quiz);
                        // Fall through — find the NEXT quiz below
                        $student->load('currentDifficulty');
                    } else {
                        // Truly in-progress — resume it
                        return $this->responseService->resolveResponse(
                            'Resuming incomplete quiz',
                            $quiz
                        );
                    }
                }
            }

            // 2. Get student's current difficulty
            $currentDifficulty = $student->currentDifficulty?->difficulty ?? 'Introduction';

            // 3. If student is at a Post-Test level and already completed it — all done
            if (in_array($currentDifficulty, $postTestDifficulties)) {
                $completedPostTest = Quiz::where('grade_level', $student->grade_level)
                    ->where('difficulty', $currentDifficulty)
                    ->whereHas('quizAttempt', function ($query) use ($student) {
                        $query->where('student_id', $student->id)
                              ->whereNotNull('completed_at');
                    })
                    ->with(['questions.choices', 'questions.questionType', 'material', 'latestQuizAttempt'])
                    ->first();

                if ($completedPostTest) {
                    return $this->responseService->resolveResponse(
                        'Level journey completed.',
                        $completedPostTest
                    );
                }
            }

            // 4. ✅ FIXED: Special case for Introduction (Pre-Test) retake
            if ($currentDifficulty === 'Introduction') {
                $hasIncompleteIntro = QuizAttempt::where('student_id', $student->id)
                    ->whereHas('quiz', fn($q) => $q->where('difficulty', 'Introduction')
                        ->where('grade_level', $student->grade_level))
                    ->whereNull('completed_at')
                    ->exists();

                if (!$hasIncompleteIntro) {
                    $introQuiz = Quiz::where('grade_level', $student->grade_level)
                        ->where('difficulty', 'Introduction')
                        ->where('is_active', true)
                        ->with(['questions.choices', 'questions.questionType', 'material', 'latestQuizAttempt'])
                        ->first();

                    if ($introQuiz) {
                        return $this->responseService->resolveResponse(
                            'Quiz retrieved successfully',
                            $introQuiz
                        );
                    }
                }
            }

            // ✅ FIXED: For retake detection — find the latest completed Introduction attempt
            // Any quiz attempts BEFORE this date are from previous takes
            // Only consider attempts AFTER the latest Introduction completion as "current take"
            $latestIntroCompletion = QuizAttempt::where('student_id', $student->id)
                ->whereHas('quiz', fn($q) => $q->where('difficulty', 'Introduction'))
                ->whereNotNull('completed_at')
                ->latest('completed_at')
                ->value('completed_at');

            // 5. Find next uncompleted quiz at current difficulty
            // For retake: only look at attempts AFTER the latest Pre-Test completion
            $nextQuizQuery = Quiz::where('grade_level', $student->grade_level)
                ->where('difficulty', $currentDifficulty);

            if ($latestIntroCompletion) {
                // Only skip quizzes that were completed AFTER the latest Pre-Test
                $nextQuizQuery->whereDoesntHave('quizAttempt', function ($query) use ($student, $latestIntroCompletion) {
                    $query->where('student_id', $student->id)
                          ->whereNotNull('completed_at')
                          ->where('completed_at', '>', $latestIntroCompletion);
                });
            } else {
                $nextQuizQuery->whereDoesntHave('quizAttempt', function ($query) use ($student) {
                    $query->where('student_id', $student->id)
                          ->whereNotNull('completed_at');
                });
            }

            $nextQuiz = $nextQuizQuery
                ->with(['questions.choices', 'questions.questionType', 'material'])
                ->first();

            if ($nextQuiz) {
                return $this->responseService->resolveResponse(
                    'Quiz retrieved successfully',
                    $nextQuiz
                );
            }

            // 6. Advance to next difficulty in sequence
            if (in_array($currentDifficulty, $postTestDifficulties)) {
                return $this->responseService->resolveResponse(
                    'Level journey completed.',
                    null,
                    Response::HTTP_NOT_FOUND
                );
            }

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

                $nextLevelQuizQuery = Quiz::where('grade_level', $student->grade_level)
                    ->where('difficulty', $nextDifficulty);

                if ($latestIntroCompletion) {
                    $nextLevelQuizQuery->whereDoesntHave('quizAttempt', function ($query) use ($student, $latestIntroCompletion) {
                        $query->where('student_id', $student->id)
                              ->whereNotNull('completed_at')
                              ->where('completed_at', '>', $latestIntroCompletion);
                    });
                } else {
                    $nextLevelQuizQuery->whereDoesntHave('quizAttempt', function ($query) use ($student) {
                        $query->where('student_id', $student->id)
                              ->whereNotNull('completed_at');
                    });
                }

                $nextLevelQuiz = $nextLevelQuizQuery
                    ->with(['questions.choices', 'questions.questionType', 'material'])
                    ->first();

                if ($nextLevelQuiz) {
                    return $this->responseService->resolveResponse(
                        "Advanced to {$nextDifficulty} level. Quiz retrieved.",
                        $nextLevelQuiz
                    );
                }
            }

            // 7. Nothing left — show all quizzes
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

    /**
     * Auto-complete an attempt where all answers were saved but PATCH /quiz-attempts
     * never reached the server (e.g. Render free-tier timeout).
     * Mirrors the scoring and difficulty-advancement logic in QuizAttemptController@update.
     */
    private function autoCompleteAttempt(QuizAttempt $attempt, Quiz $quiz): void
    {
        $readingDiffs = ['Introduction','EasyPostTest','MediumPostTest','HardPostTest','ExpertPostTest','PostTest'];
        $isReading    = in_array($quiz->difficulty, $readingDiffs);
        $answers      = Answer::where('attempt_id', $attempt->id)->get();

        if ($isReading) {
            $totalPct = $answers->sum(fn($a) => (int)($a->word_score ?? 0));
            $score    = $answers->count() > 0 ? (int) round($totalPct / $answers->count()) : 0;
        } else {
            $score = 0;
            foreach ($answers as $answer) {
                $question = $quiz->questions->firstWhere('id', $answer->question_id);
                if (!$question) continue;
                $score += $question->use_word_scoring
                    ? (int)($answer->word_score ?? 0)
                    : ($answer->is_correct ? ($question->points ?? 1) : 0);
            }
        }

        $attempt->update(['score' => $score, 'completed_at' => now()]);

        $levelToPostTest = [
            'Easy'   => 'EasyPostTest',
            'Medium' => 'MediumPostTest',
            'Hard'   => 'HardPostTest',
            'Expert' => 'ExpertPostTest',
        ];
        $newDifficulty = null;

        if ($quiz->difficulty === 'Introduction') {
            if ($score >= 91)      $newDifficulty = 'Expert';
            elseif ($score >= 61)  $newDifficulty = 'Hard';
            elseif ($score >= 31)  $newDifficulty = 'Medium';
            else                   $newDifficulty = 'Easy';
        } elseif (isset($levelToPostTest[$quiz->difficulty])) {
            $newDifficulty = $levelToPostTest[$quiz->difficulty];
        }

        if ($newDifficulty) {
            DB::table('student_difficulties')->updateOrInsert(
                ['student_id' => $attempt->student_id],
                ['difficulty' => $newDifficulty, 'updated_at' => now(), 'created_at' => now()]
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
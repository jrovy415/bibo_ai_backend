<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnswerRequest as ModelRequest;
use App\Models\Answer;
use App\Models\Choice;
use App\Models\Question;
use App\Models\QuestionType;
use App\Services\Utils\ResponseServiceInterface;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AnswerController extends Controller
{
    protected $responseService;
    protected $model;

    public function __construct(ResponseServiceInterface $responseService)
    {
        $this->responseService = $responseService;
        $this->model = new Answer();
    }

    public function index()
    {
        $sortByColumn  = request()->input('sort_by_column', 'created_at');
        $sortBy        = request()->input('sort_by', 'desc');
        $all           = request()->boolean('all');
        $limit         = request()->input('limit', 10);

        $modelName = $this->model->model_name;

        $query = $this->model->filter()->newQuery()->orderBy($sortByColumn, $sortBy);

        $data = $all ? $query->get() : $query->paginate($limit);

        return $this->responseService->successResponse($modelName, $data);
    }

    private static array $questionTypeCache = [];

    private static function questionTypeId(string $name): ?string
    {
        if (!isset(self::$questionTypeCache[$name])) {
            self::$questionTypeCache[$name] = QuestionType::where('name', $name)->value('id');
        }
        return self::$questionTypeCache[$name];
    }

    public function store(ModelRequest $request)
    {
        DB::beginTransaction();

        try {
            $question = Question::findOrFail($request->question_id);

            $readingType        = self::questionTypeId('reading');
            $multipleChoiceType = self::questionTypeId('multiple_choice');
            $trueFalseType      = self::questionTypeId('true_false');

            $choiceId  = null;
            $isCorrect = false;

            if ($question->question_type_id === $readingType) {
                $transcript = trim($request->transcript ?? '');

                $choices = Choice::where('question_id', $question->id)->get();

                $matchingChoice = $choices->first(function ($choice) use ($transcript) {
                    $normalize = function ($text) {
                        return strtolower(
                            preg_replace(
                                '/\s+/',
                                ' ',
                                str_replace('.', '', trim($text))
                            )
                        );
                    };

                    $normalizedTranscript = $normalize($transcript);
                    $normalizedChoice     = $normalize($choice->choice_text);

                    if ($normalizedTranscript === $normalizedChoice) {
                        return true;
                    }

                    if (str_replace(' ', '', $normalizedTranscript) === str_replace(' ', '', $normalizedChoice)) {
                        return true;
                    }

                    return false;
                });

                $choiceId  = $matchingChoice?->id;
                $isCorrect = $matchingChoice?->is_correct ?? false;

            } elseif (in_array($question->question_type_id, [$multipleChoiceType, $trueFalseType])) {
                $choiceId  = $request->choice_id;
                $isCorrect = Choice::find($choiceId)?->is_correct ?? false;
            }

            // ── FIX: include word_score sent from the frontend ──────────────
            // For Pre-Test and Post-Test, the frontend calculates a word-by-word
            // match score and sends it as `word_score`. We must save it here so
            // QuizAttemptController@update can sum it correctly.
            $wordScore = $request->has('word_score')
                ? (int) $request->word_score
                : null;

            $answer = Answer::updateOrCreate(
                [
                    'attempt_id'  => $request->attempt_id,
                    'question_id' => $question->id,
                ],
                [
                    'choice_id'     => $choiceId,
                    'choice_string' => $request->transcript,
                    'is_correct'    => $isCorrect,
                    'word_score'    => $wordScore,   // ← was missing before
                ]
            );

            DB::commit();

            $answer->load(['choice', 'question']);

            return $this->responseService->resolveResponse(
                'Answer recorded successfully',
                $answer,
                Response::HTTP_CREATED
            );

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->responseService->resolveResponse(
                'Error recording answer',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function show($id)
    {
        try {
            $answer = Answer::with(['choice', 'question'])->find($id);

            if (!$answer) {
                return $this->responseService->resolveResponse(
                    'Answer not found',
                    null,
                    Response::HTTP_NOT_FOUND
                );
            }

            return $this->responseService->resolveResponse(
                'Answer retrieved successfully',
                $answer
            );
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error retrieving answer',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function update(ModelRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            $answer = Answer::find($id);

            if (!$answer) {
                return $this->responseService->resolveResponse(
                    'Answer not found',
                    null,
                    Response::HTTP_NOT_FOUND
                );
            }

            $answer->update([
                'attempt_id'    => $request->attempt_id,
                'question_id'   => $request->question_id,
                'choice_id'     => $request->choice_id,
                'choice_string' => $request->transcript,
                'is_correct'    => Choice::find($request->choice_id)?->is_correct ?? false,
                'word_score'    => $request->has('word_score') ? (int) $request->word_score : $answer->word_score,
            ]);

            DB::commit();

            $answer->load(['choice', 'question']);

            return $this->responseService->resolveResponse(
                'Answer updated successfully',
                $answer
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->responseService->resolveResponse(
                'Error updating answer',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function destroy($id)
    {
        try {
            $answer = Answer::findOrFail($id);
            $answer->delete();

            return $this->responseService->deleteResponse('Answer', null);
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error deleting answer',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
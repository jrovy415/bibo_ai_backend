<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuizRequest as ModelRequest;
use App\Models\Answer;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\Utils\ResponseServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
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
                $validated['title'] = $quiz->title;
                $validated['grade_level'] = $quiz->grade_level;
                $validated['difficulty'] = $quiz->difficulty;
                $validated['student_id'] = $auth->id;
                $validated['started_at'] = Carbon::now();

                $attempt = $this->model->create($validated);

                return $this->responseService->resolveResponse(
                    'Quiz Attempt created successfully',
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

    public function update($id)
    {
        $auth = Auth::user();
        logger('Updating quiz attempt: ' . $id);

        try {
            $attempt = $this->model->find($id);

            if (!$attempt) {
                return $this->responseService->resolveResponse(
                    'Quiz Attempt not found',
                    null,
                    Response::HTTP_NOT_FOUND
                );
            }

            $score = Answer::where('attempt_id', $id)
                ->where('is_correct', true)
                ->count();

            $request = [
                'score' => $score,
                'student_id' => $auth->id,
                'completed_at' => Carbon::now(),
            ];

            $attempt->update($request);

            return $this->responseService->resolveResponse(
                'Quiz Attempt updated successfully',
                $attempt
            );
        } catch (\Exception $e) {
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
}

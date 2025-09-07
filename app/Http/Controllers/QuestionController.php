<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuizRequest as ModelRequest;
use App\Models\Question;
use App\Services\Utils\ResponseServiceInterface;
use Symfony\Component\HttpFoundation\Response;

class QuestionController extends Controller
{
    protected $responseService;
    protected $model;

    public function __construct(ResponseServiceInterface $responseService)
    {
        $this->responseService = $responseService;
        $this->model = new Question();
    }

    public function index()
    {
        $sortByColumn = request()->input('sort_by_column', 'created_at');
        $sortBy       = request()->input('sort_by', 'desc');
        $all          = request()->boolean('all');
        $limit        = request()->input('limit', 10);

        $modelName = $this->model->model_name;

        $query = $this->model->with(['quiz', 'choices'])->filter()->newQuery()->orderBy($sortByColumn, $sortBy);

        $data = $all ? $query->get() : $query->paginate($limit);

        return $this->responseService->successResponse($modelName, $data);
    }

    public function store(ModelRequest $request)
    {
        try {
            $question = $this->model->create($request->validated());

            return $this->responseService->resolveResponse(
                'Question created successfully',
                $question,
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error creating question',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function show($id)
    {
        try {
            $question = $this->model->with(['quiz', 'choices'])->find($id);

            if (!$question) {
                return $this->responseService->resolveResponse(
                    'Question not found',
                    null,
                    Response::HTTP_NOT_FOUND
                );
            }

            return $this->responseService->resolveResponse(
                'Question retrieved successfully',
                $question
            );
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error retrieving question',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function update(ModelRequest $request, $id)
    {
        try {
            $question = $this->model->find($id);

            if (!$question) {
                return $this->responseService->resolveResponse(
                    'Question not found',
                    null,
                    Response::HTTP_NOT_FOUND
                );
            }

            $question->update($request->validated());

            return $this->responseService->resolveResponse(
                'Question updated successfully',
                $question
            );
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error updating question',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function destroy($id)
    {
        try {
            $question = $this->model->findOrFail($id);
            $question->delete();

            return $this->responseService->deleteResponse('Question', null);
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error deleting question',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}

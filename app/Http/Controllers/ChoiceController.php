<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuizRequest as ModelRequest;
use App\Models\Choice;
use App\Services\Utils\ResponseServiceInterface;
use Symfony\Component\HttpFoundation\Response;

class ChoiceController extends Controller
{
    protected $responseService;
    protected $model;

    public function __construct(ResponseServiceInterface $responseService)
    {
        $this->responseService = $responseService;
        $this->model = new Choice();
    }

    public function index()
    {
        $sortByColumn = request()->input('sort_by_column', 'created_at');
        $sortBy       = request()->input('sort_by', 'desc');
        $all          = request()->boolean('all');
        $limit        = request()->input('limit', 10);

        $modelName = $this->model->model_name;

        $query = $this->model->with(['question'])->filter()->newQuery()->orderBy($sortByColumn, $sortBy);

        $data = $all ? $query->get() : $query->paginate($limit);

        return $this->responseService->successResponse($modelName, $data);
    }

    public function store(ModelRequest $request)
    {
        try {
            $choice = $this->model->create($request->validated());

            return $this->responseService->resolveResponse(
                'Choice created successfully',
                $choice,
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error creating choice',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function show($id)
    {
        try {
            $choice = $this->model->with(['question'])->find($id);

            if (!$choice) {
                return $this->responseService->resolveResponse(
                    'Choice not found',
                    null,
                    Response::HTTP_NOT_FOUND
                );
            }

            return $this->responseService->resolveResponse(
                'Choice retrieved successfully',
                $choice
            );
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error retrieving choice',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function update(ModelRequest $request, $id)
    {
        try {
            $choice = $this->model->find($id);

            if (!$choice) {
                return $this->responseService->resolveResponse(
                    'Choice not found',
                    null,
                    Response::HTTP_NOT_FOUND
                );
            }

            $choice->update($request->validated());

            return $this->responseService->resolveResponse(
                'Choice updated successfully',
                $choice
            );
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error updating choice',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function destroy($id)
    {
        try {
            $choice = $this->model->findOrFail($id);
            $choice->delete();

            return $this->responseService->deleteResponse('Choice', null);
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error deleting choice',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}

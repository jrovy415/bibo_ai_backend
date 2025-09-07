<?php

namespace App\Http\Controllers;

use App\Models\QuestionType;
use App\Services\Utils\ResponseServiceInterface;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;

class QuestionTypeController extends Controller
{
    protected $responseService;
    protected $model;

    public function __construct(ResponseServiceInterface $responseService)
    {
        $this->responseService = $responseService;
        $this->model = new QuestionType();
    }

    public function index()
    {
        try {
            $sortByColumn = request()->input('sort_by_column', 'created_at');
            $sortBy       = request()->input('sort_by', 'desc');
            $all          = request()->boolean('all');
            $limit        = request()->input('limit', 10);

            $query = $this->model->newQuery()->orderBy($sortByColumn, $sortBy);
            $data = $all ? $query->get() : $query->paginate($limit);

            return $this->responseService->successResponse('Question Type', $data);
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error fetching question types',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function store(Request $request)
    {
        try {
            $type = $this->model->create($request->all());

            return $this->responseService->resolveResponse(
                'Question Type created successfully',
                $type,
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error creating question type',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function show($id)
    {
        try {
            $type = $this->model->find($id);

            if (!$type) {
                return $this->responseService->resolveResponse(
                    'Question Type not found',
                    null,
                    Response::HTTP_NOT_FOUND
                );
            }

            return $this->responseService->resolveResponse(
                'Question Type retrieved successfully',
                $type
            );
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error retrieving question type',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $type = $this->model->find($id);

            if (!$type) {
                return $this->responseService->resolveResponse(
                    'Question Type not found',
                    null,
                    Response::HTTP_NOT_FOUND
                );
            }

            $type->update($request->all());

            return $this->responseService->resolveResponse(
                'Question Type updated successfully',
                $type
            );
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error updating question type',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function destroy($id)
    {
        try {
            $type = $this->model->findOrFail($id);
            $type->delete();

            return $this->responseService->deleteResponse('Question Type', null);
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error deleting question type',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}

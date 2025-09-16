<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuizRequest as ModelRequest;
use App\Models\Question;
use App\Services\Utils\ResponseServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            $validated = $request->validated();

            if ($request->hasFile('photo')) {
                $quizId = $validated['quiz_id']; // make sure quiz_id is in validated data
                $path = $request->file('photo')->store("questions/{$quizId}", 'public');
                $validated['photo'] = $path;
            }

            $question = $this->model->create($validated);

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

    public function uploadPhoto(Request $request, $quizId)
    {
        try {
            $request->validate([
                'photo' => 'required|image|max:2048',
            ]);

            // Save into "questions/{quizId}"
            $path = $request->file('photo')->store("questions/{$quizId}", 'public');

            $data = [
                'filename' => $path, // store this in DB
                'url'      => asset("storage/{$path}"),
            ];

            return $this->responseService->storeResponse('Photo', $data);
        } catch (\Exception $e) {
            return $this->responseService->rejectResponse(
                'Error uploading photo',
                $e->getMessage()
            );
        }
    }

    public function deletePhoto(Request $request, $quizId)
    {
        try {
            $request->validate([
                'filename' => 'required|string',
            ]);

            // File path under "questions/{quizId}"
            $path = $request->input('filename');

            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            return $this->responseService->deleteResponse('Photo', $path);
        } catch (\Exception $e) {
            return $this->responseService->rejectResponse(
                'Error deleting photo',
                $e->getMessage()
            );
        }
    }
}

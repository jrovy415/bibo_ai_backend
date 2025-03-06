<?php

namespace App\Services\Utils;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ResponseService implements ResponseServiceInterface
{
    
    public function rejectResponse($message, $data): JsonResponse
    {
        return $this->resolveResponse(
            $message,
            $data,
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }
    
    public function resolveResponse($message, $data, $statusCode = Response::HTTP_OK): JsonResponse
    {
        return response()->json(
            [
                "message" => $message,
                "data"    => $data,
            ],
            $statusCode
        );
    }
    
    // list response 200 status
    public function successResponse($model, $data): JsonResponse
    {
        $message = __('messages.fetch_success', ['name' => $model]);
        
        return $this->resolveResponse(
            $message,
            $data
        );
    }
    
    // delete response
    public function deleteResponse($model, $data): JsonResponse
    {
        $message = __('messages.delete_success', ['name' => $model]);
        
        return $this->resolveResponse(
            $message,
            $data,
        );
    }
    
    public function restoreResponse($model, $data): JsonResponse
    {
        $message = __('messages.restore_success', ['name' => $model]);
        
        return $this->resolveResponse(
            $message,
            $data
        );
    }
    
    // created response 201 status
    public function storeResponse($model, $data): JsonResponse
    {
        $message = __('messages.create_success', ['name' => $model]);
        
        return $this->resolveResponse(
            $message,
            $data,
            Response::HTTP_CREATED
        );
    }
    
    // accepted response 202
    public function updateResponse($model, $data): JsonResponse
    {
        $message = __('messages.update_success', ['name' => $model]);
        
        return $this->resolveResponse(
            $message,
            $data,
            Response::HTTP_ACCEPTED
        );
    }
}

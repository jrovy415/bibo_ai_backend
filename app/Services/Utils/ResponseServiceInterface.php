<?php

namespace App\Services\Utils;

use Illuminate\Http\JsonResponse;

interface ResponseServiceInterface
{
    public function resolveResponse($message, $data): JsonResponse;
    
    public function rejectResponse($message, $data): JsonResponse;
    
    public function successResponse($model, $data): JsonResponse;
    
    public function storeResponse($model, $data): JsonResponse;
    
    public function updateResponse($model, $data): JsonResponse;
    
    public function deleteResponse($model, $data): JsonResponse;
    
    public function restoreResponse($model, $data): JsonResponse;
}

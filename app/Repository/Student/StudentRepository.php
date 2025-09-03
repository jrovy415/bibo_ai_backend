<?php

namespace App\Repository\Student;

use App\Models\Student;
use App\Repository\Base\BaseRepository;
use App\Services\AuditLog\AuditLogServiceInterface;
use App\Services\Utils\ResponseServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class StudentRepository extends BaseRepository implements StudentRepositoryInterface
{
    public function __construct(Student $model, ResponseServiceInterface $responseService, AuditLogServiceInterface $auditLogService)
    {
        parent::__construct($model, $responseService, $auditLogService);
    }

    public function login(array $attributes): array
    {
        $nickname = $attributes['nickname'];

        $student = $this->model->where('nickname', $nickname)->first();

        if (!$student) {
            $student = $this->model->create($attributes)->fresh();
            $this->auditLogService->insertLog($this->model, 'create', $attributes);
        }

        // Revoke existing tokens
        $student->tokens()->delete();

        // Create new token
        $token = $student->createToken('StudentLogin')->plainTextToken;

        // Load relations if requested
        if ($relations = request()->input('relations')) {
            $relations = explode(',', $relations);
            $student->load($relations);
        }

        return [
            'token'   => $token,
            'student' => $student,
        ];
    }

    public function logout(): JsonResponse
    {
        Auth::user()->currentAccessToken()->delete();

        return $this->responseService->resolveResponse('Logout Successful', null);
    }
}

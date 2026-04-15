<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\Utils\ResponseServiceInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StudentLockController extends Controller
{
    protected $responseService;

    public function __construct(ResponseServiceInterface $responseService)
    {
        $this->responseService = $responseService;
    }

    /**
     * PATCH /students/{student}/lock
     * Lock a student account — revokes all tokens immediately
     */
    public function lock(Student $student)
    {
        try {
            $student->update(['is_locked' => true]);
            // Revoke all tokens so student is logged out immediately
            $student->tokens()->delete();

            return $this->responseService->resolveResponse(
                'Student account locked successfully',
                $student
            );
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error locking student',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * PATCH /students/{student}/unlock
     * Unlock a student account
     */
    public function unlock(Student $student)
    {
        try {
            $student->update(['is_locked' => false]);

            return $this->responseService->resolveResponse(
                'Student account unlocked successfully',
                $student
            );
        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error unlocking student',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
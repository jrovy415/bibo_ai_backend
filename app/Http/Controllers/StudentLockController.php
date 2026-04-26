<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\Utils\ResponseServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            $student->tokens()->delete();

            // ✅ Reset difficulty to Introduction so next retake starts from Pre-Test
            DB::table('student_difficulties')
                ->updateOrInsert(
                    ['student_id' => $student->id],
                    [
                        'difficulty'  => 'Introduction',
                        'updated_at'  => now(),
                        'created_at'  => now(),
                    ]
                );

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
     * Unlock a student account and reset difficulty to Introduction
     * so the student can retake from Pre-Test
     */
    public function unlock(Student $student)
    {
        try {
            // Unlock the account
            $student->update(['is_locked' => false]);

            // ✅ Reset difficulty back to Introduction so student starts from Pre-Test
            DB::table('student_difficulties')
                ->updateOrInsert(
                    ['student_id' => $student->id],
                    [
                        'difficulty'  => 'Introduction',
                        'updated_at'  => now(),
                        'created_at'  => now(),
                    ]
                );

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
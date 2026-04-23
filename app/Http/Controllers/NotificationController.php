<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\QuizAttempt;
use App\Services\Utils\ResponseServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    protected $responseService;

    public function __construct(ResponseServiceInterface $responseService)
    {
        $this->responseService = $responseService;
    }

    /**
     * GET /notifications
     * Returns recent events for the teacher dashboard
     * - New student logins (last 30 mins)
     * - Completed quizzes (last 30 mins)
     * - New students registered (last 24 hrs)
     */
    public function index(Request $request)
    {
        try {
            $since     = now()->subMinutes(30);
            $sinceNew  = now()->subHours(24);
            $notifications = collect();

            // 1. Recent student logins (updated_at within last 30 mins, has token)
            $recentLogins = DB::table('students')
                ->join('personal_access_tokens', function($join) {
                    $join->on('personal_access_tokens.tokenable_id', '=', 'students.id')
                         ->where('personal_access_tokens.tokenable_type', 'like', '%Student%');
                })
                ->where('personal_access_tokens.created_at', '>=', $since)
                ->select('students.id', 'students.nickname', 'students.grade_level', 'students.section', 'personal_access_tokens.created_at as login_at')
                ->orderByDesc('personal_access_tokens.created_at')
                ->limit(10)
                ->get();

            foreach ($recentLogins as $s) {
                $notifications->push([
                    'id'      => 'login_' . $s->id,
                    'type'    => 'login',
                    'icon'    => '🔑',
                    'title'   => 'Student logged in',
                    'message' => "{$s->nickname} ({$s->grade_level} — Section {$s->section}) just logged in",
                    'time'    => $s->login_at,
                    'color'   => '#6C63FF',
                ]);
            }

            // 2. Recently completed quizzes (last 30 mins)
            $completedQuizzes = QuizAttempt::with(['student', 'quiz'])
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', $since)
                ->orderByDesc('completed_at')
                ->limit(10)
                ->get();

            foreach ($completedQuizzes as $attempt) {
                $nick  = $attempt->student?->nickname ?? 'Unknown';
                $quiz  = $attempt->quiz?->title ?? 'a quiz';
                $score = $attempt->score ?? 0;
                $notifications->push([
                    'id'      => 'quiz_' . $attempt->id,
                    'type'    => 'quiz_complete',
                    'icon'    => '✅',
                    'title'   => 'Quiz completed',
                    'message' => "{$nick} completed \"{$quiz}\" with score {$score}",
                    'time'    => $attempt->completed_at,
                    'color'   => '#43D9AD',
                ]);
            }

            // 3. New students registered (last 24 hrs)
            $newStudents = Student::where('created_at', '>=', $sinceNew)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();

            foreach ($newStudents as $s) {
                $notifications->push([
                    'id'      => 'new_' . $s->id,
                    'type'    => 'new_student',
                    'icon'    => '👋',
                    'title'   => 'New student registered',
                    'message' => "{$s->nickname} ({$s->grade_level} — Section {$s->section}) joined the system",
                    'time'    => $s->created_at,
                    'color'   => '#FFB830',
                ]);
            }

            // Sort all by time desc
            $sorted = $notifications->sortByDesc('time')->values();

            return $this->responseService->resolveResponse(
                'Notifications retrieved',
                $sorted
            );

        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error retrieving notifications',
                $e->getMessage(),
                500
            );
        }
    }
}
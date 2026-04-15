<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\Utils\ResponseServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class StudentProgressController extends Controller
{
    protected $responseService;

    public function __construct(ResponseServiceInterface $responseService)
    {
        $this->responseService = $responseService;
    }

    public function index(Request $request)
    {
        try {
            $gradeLevel = $request->input('grade_level');
            $section    = $request->input('section');

            // Fetch all students with their difficulty
            $studentsQuery = Student::with('currentDifficulty');
            if ($gradeLevel) $studentsQuery->where('grade_level', $gradeLevel);
            if ($section)    $studentsQuery->where('section', $section);
            $students = $studentsQuery->get();

            // Fetch all completed attempts with quiz info in one query
            $studentIds = $students->pluck('id');
            $attempts   = DB::table('quiz_attempts')
                ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
                ->whereIn('quiz_attempts.student_id', $studentIds)
                ->whereNotNull('quiz_attempts.completed_at')
                ->select(
                    'quiz_attempts.id as attempt_id',
                    'quiz_attempts.student_id',
                    'quiz_attempts.score',
                    'quiz_attempts.completed_at',
                    'quizzes.id as quiz_id',
                    'quizzes.title',
                    'quizzes.difficulty'
                )
                ->orderBy('quiz_attempts.completed_at', 'asc')
                ->get()
                ->groupBy('student_id');

            // Fetch question counts per quiz
            $quizIds       = $attempts->flatten()->pluck('quiz_id')->unique();
            $questionCounts = DB::table('questions')
                ->whereIn('quiz_id', $quizIds)
                ->select('quiz_id', DB::raw('COUNT(*) as total'))
                ->groupBy('quiz_id')
                ->pluck('total', 'quiz_id');

            $difficultyOrder  = ['Introduction','Easy','Medium','Hard','Expert','PostTest'];
            $readingAssess    = ['Introduction','PostTest'];

            $result = $students->map(function ($student) use (
                $attempts, $questionCounts, $difficultyOrder, $readingAssess
            ) {
                $assignedLevel   = $student->currentDifficulty?->difficulty ?? 'Easy';
                $studentAttempts = $attempts->get($student->id, collect());

                // Build level map — keep best score per difficulty
                $levelMap = [];
                foreach ($studentAttempts as $attempt) {
                    $diff  = $attempt->difficulty;
                    $score = (int) ($attempt->score ?? 0);
                    $total = (int) ($questionCounts[$attempt->quiz_id] ?? 1);

                    $isReading = in_array($diff, $readingAssess);
                    // Reading assessments: score is already 0-100 percentage
                    // Other levels: score / total questions * 100
                    $pct = $isReading
                        ? min(100, $score)
                        : ($total > 0 ? (int) round(($score / $total) * 100) : 0);

                    if (!isset($levelMap[$diff]) || $pct > $levelMap[$diff]['pct']) {
                        $levelMap[$diff] = [
                            'pct'          => $pct,
                            'score'        => $score,
                            'title'        => $attempt->title,
                            'completed_at' => $attempt->completed_at,
                            'attempt_id'   => $attempt->attempt_id,
                        ];
                    }
                }

                // Build ordered journey
                $journey = [];
                foreach ($difficultyOrder as $diff) {
                    $journey[$diff] = $levelMap[$diff] ?? null;
                }

                // Improvement: Post-Test % - Pre-Test %
                $prePct  = $journey['Introduction']['pct'] ?? null;
                $postPct = $journey['PostTest']['pct']     ?? null;
                $improvement = ($prePct !== null && $postPct !== null)
                    ? $postPct - $prePct
                    : null;

                // Overall average of completed levels
                $completedPcts = array_values(array_filter(
                    array_map(fn($v) => $v['pct'] ?? null, array_filter($journey)),
                    fn($v) => $v !== null
                ));
                $overallAvg = count($completedPcts) > 0
                    ? (int) round(array_sum($completedPcts) / count($completedPcts))
                    : null;

                return [
                    'id'             => $student->id,
                    'nickname'       => $student->nickname,
                    'grade_level'    => $student->grade_level,
                    'section'        => $student->section,
                    'assigned_level' => $assignedLevel,
                    'journey'        => $journey,
                    'improvement'    => $improvement,
                    'overall_avg'    => $overallAvg,
                ];
            });

            return $this->responseService->resolveResponse(
                'Student progress retrieved successfully',
                $result
            );

        } catch (\Exception $e) {
            return $this->responseService->resolveResponse(
                'Error retrieving student progress',
                $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
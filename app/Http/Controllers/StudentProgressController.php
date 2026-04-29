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

            $studentsQuery = Student::with('currentDifficulty');
            if ($gradeLevel) $studentsQuery->where('grade_level', $gradeLevel);
            if ($section)    $studentsQuery->where('section', $section);
            $students = $studentsQuery->get();

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

            $quizIds        = $attempts->flatten()->pluck('quiz_id')->unique();
            $questionCounts = DB::table('questions')
                ->whereIn('quiz_id', $quizIds)
                ->select('quiz_id', DB::raw('COUNT(*) as total'))
                ->groupBy('quiz_id')
                ->pluck('total', 'quiz_id');

            // ✅ FIXED: Full difficulty order including all PostTest variants
            $difficultyOrder = [
                'Introduction',
                'Easy', 'EasyPostTest',
                'Medium', 'MediumPostTest',
                'Hard', 'HardPostTest',
                'Expert', 'ExpertPostTest',
                'PostTest',
            ];

            // ✅ FIXED: All reading assessments where score is stored as 0-100 percentage
            $readingAssess = [
                'Introduction',
                'EasyPostTest',
                'MediumPostTest',
                'HardPostTest',
                'ExpertPostTest',
                'PostTest',
            ];

            // Helper: build one take's journey + stats from a slice of attempts
            $buildTake = function (array $takeAttempts) use ($difficultyOrder, $readingAssess, $questionCounts) {
                $levelMap = [];
                foreach ($takeAttempts as $attempt) {
                    $diff      = $attempt->difficulty;
                    $score     = (int) ($attempt->score ?? 0);
                    $total     = (int) ($questionCounts[$attempt->quiz_id] ?? 1);
                    $isReading = in_array($diff, $readingAssess);
                    $pct       = $isReading
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

                $journey = [];
                foreach ($difficultyOrder as $diff) {
                    $journey[$diff] = $levelMap[$diff] ?? null;
                }

                $prePct        = $journey['Introduction']['pct'] ?? null;
                $postTestDiffs = ['EasyPostTest','MediumPostTest','HardPostTest','ExpertPostTest','PostTest'];
                $postPct       = null;
                foreach ($postTestDiffs as $ptDiff) {
                    if (!empty($journey[$ptDiff])) { $postPct = $journey[$ptDiff]['pct']; break; }
                }

                $completedPcts = array_values(array_filter(
                    array_map(fn($v) => $v['pct'] ?? null, array_filter($journey)),
                    fn($v) => $v !== null
                ));
                $overallAvg = count($completedPcts) > 0
                    ? (int) round(array_sum($completedPcts) / count($completedPcts))
                    : null;

                return [
                    'journey'     => $journey,
                    'improvement' => ($prePct !== null && $postPct !== null) ? $postPct - $prePct : null,
                    'overall_avg' => $overallAvg,
                ];
            };

            $result = $students->map(function ($student) use (
                $attempts, $questionCounts, $difficultyOrder, $readingAssess, $buildTake
            ) {
                $assignedLevel   = $student->currentDifficulty?->difficulty ?? 'Easy';
                $studentAttempts = $attempts->get($student->id, collect());

                // Group attempts into takes — each new Pre-Test starts a new take
                $takes       = [];
                $currentTake = [];
                foreach ($studentAttempts as $attempt) {
                    if ($attempt->difficulty === 'Introduction' && !empty($currentTake)) {
                        $takes[] = $currentTake;
                        $currentTake = [];
                    }
                    $currentTake[] = $attempt;
                }
                if (!empty($currentTake)) $takes[] = $currentTake;
                if (empty($takes))        $takes[] = [];

                // Build journey data per take
                $takesData = [];
                foreach ($takes as $index => $takeAttempts) {
                    $data        = $buildTake($takeAttempts);
                    $takesData[] = [
                        'take_number' => $index + 1,
                        'journey'     => $data['journey'],
                        'improvement' => $data['improvement'],
                        'overall_avg' => $data['overall_avg'],
                    ];
                }

                // Use latest take's data for the student card summary
                $latest = end($takesData);

                return [
                    'id'             => $student->id,
                    'nickname'       => $student->nickname,
                    'grade_level'    => $student->grade_level,
                    'section'        => $student->section,
                    'assigned_level' => $assignedLevel,
                    'takes'          => $takesData,
                    'journey'        => $latest['journey']     ?? [],
                    'improvement'    => $latest['improvement'] ?? null,
                    'overall_avg'    => $latest['overall_avg'] ?? null,
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
<?php
namespace App\Http\Controllers\Api\Admin;

use App\Models\Account;
use App\Models\UserProfile;
use App\Models\LearningContent;
use App\Models\LearningProgress;
use App\Models\ScreeningResult;
use App\Models\ChallengeAttempt;
use App\Models\ContentAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends BaseAdminController
{
    public function index(Request $request): JsonResponse
    {
        $instId = $this->getInstitutionId($request);

        // --- 기본 통계 ---
        $totalUsers = Account::where('role', 'USER')
            ->when($instId, fn ($q) => $q->where('institution_id', $instId))
            ->count();

        $totalLearners = UserProfile::where('user_type', 'LEARNER')
            ->when($instId, function ($q) use ($instId) {
                $q->whereHas('account', fn ($aq) => $aq->where('institution_id', $instId));
            })
            ->count();

        $totalContents = LearningContent::forInstitution($instId)->where('is_active', true)->count();

        $totalScreenings = ScreeningResult::when($instId, function ($q) use ($instId) {
            $q->whereHas('profile', function ($pq) use ($instId) {
                $pq->whereHas('account', fn ($aq) => $aq->where('institution_id', $instId));
            });
        })->count();

        // --- 학습 완료율 ---
        $assignmentScope = ContentAssignment::where('assignable_type', 'learning_content')
            ->when($instId, function ($q) use ($instId) {
                $q->whereHas('profile', function ($pq) use ($instId) {
                    $pq->whereHas('account', fn ($aq) => $aq->where('institution_id', $instId));
                });
            });

        $totalAssignments = (clone $assignmentScope)->count();
        $completedAssignments = (clone $assignmentScope)->where('status', 'COMPLETED')->count();
        $completionRate = $totalAssignments > 0 ? round(($completedAssignments / $totalAssignments) * 100) : 0;

        // --- 최근 7일 활동 추이 ---
        $weekAgo = Carbon::now()->subDays(6)->startOfDay();
        $dailyActivity = $this->getDailyActivity($instId, $weekAgo);

        // --- 최근 진단 결과 (5건) ---
        $recentScreenings = ScreeningResult::with(['profile', 'test'])
            ->when($instId, function ($q) use ($instId) {
                $q->whereHas('profile', function ($pq) use ($instId) {
                    $pq->whereHas('account', fn ($aq) => $aq->where('institution_id', $instId));
                });
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'result_id' => $r->result_id,
                'profile_name' => $r->profile?->decrypted_name ?? $r->profile?->name ?? '-',
                'test_title' => $r->test?->title ?? '-',
                'score' => $r->score,
                'level' => $r->level,
                'date' => $r->created_at?->format('Y-m-d'),
            ]);

        // --- 최근 가입자 (5건) ---
        $recentUsers = Account::where('role', 'USER')
            ->when($instId, fn ($q) => $q->where('institution_id', $instId))
            ->with('profile')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($a) => [
                'account_id' => $a->account_id,
                'username' => $a->username,
                'name' => $a->profile?->decrypted_name ?? $a->profile?->name ?? '-',
                'user_type' => $a->profile?->user_type ?? '-',
                'date' => $a->created_at?->format('Y-m-d'),
            ]);

        // --- 주간 학습 완료 추이 (최근 8주) ---
        $weeklyLearning = $this->getWeeklyLearningTrend($instId);

        // --- 월간 진단 점수 추이 (최근 6개월) ---
        $monthlyScreening = $this->getMonthlyScreeningTrend($instId);

        return response()->json([
            'success' => true,
            'data' => [
                'total_users' => $totalUsers,
                'total_learners' => $totalLearners,
                'total_contents' => $totalContents,
                'total_screenings' => $totalScreenings,
                'completion_rate' => $completionRate,
                'total_assignments' => $totalAssignments,
                'completed_assignments' => $completedAssignments,
                'daily_activity' => $dailyActivity,
                'recent_screenings' => $recentScreenings,
                'recent_users' => $recentUsers,
                'weekly_learning' => $weeklyLearning,
                'monthly_screening' => $monthlyScreening,
            ],
        ]);
    }

    /**
     * 주간 학습 완료 추이 (최근 8주)
     */
    private function getWeeklyLearningTrend(?int $instId): array
    {
        $weeks = [];
        for ($i = 7; $i >= 0; $i--) {
            $weekStart = Carbon::now()->subWeeks($i)->startOfWeek();
            $weekEnd = $weekStart->copy()->endOfWeek();

            $completed = LearningProgress::where('status', 'COMPLETED')
                ->whereBetween('updated_at', [$weekStart, $weekEnd])
                ->when($instId, function ($q) use ($instId) {
                    $q->whereHas('profile', function ($pq) use ($instId) {
                        $pq->whereHas('account', fn ($aq) => $aq->where('institution_id', $instId));
                    });
                })
                ->count();

            $started = LearningProgress::whereBetween('created_at', [$weekStart, $weekEnd])
                ->when($instId, function ($q) use ($instId) {
                    $q->whereHas('profile', function ($pq) use ($instId) {
                        $pq->whereHas('account', fn ($aq) => $aq->where('institution_id', $instId));
                    });
                })
                ->count();

            $weeks[] = [
                'label' => $weekStart->format('n/j'),
                'completed' => $completed,
                'started' => $started,
            ];
        }

        return $weeks;
    }

    /**
     * 월간 진단 점수 추이 (최근 6개월)
     */
    private function getMonthlyScreeningTrend(?int $instId): array
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = Carbon::now()->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $results = ScreeningResult::whereBetween('created_at', [$monthStart, $monthEnd])
                ->when($instId, function ($q) use ($instId) {
                    $q->whereHas('profile', function ($pq) use ($instId) {
                        $pq->whereHas('account', fn ($aq) => $aq->where('institution_id', $instId));
                    });
                });

            $count = (clone $results)->count();
            $avgScore = $count > 0 ? round((clone $results)->avg('score'), 1) : null;

            $months[] = [
                'label' => $monthStart->format('n월'),
                'count' => $count,
                'avg_score' => $avgScore,
            ];
        }

        return $months;
    }

    private function getDailyActivity(?int $instId, Carbon $since): array
    {
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $since->copy()->addDays($i);
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            $screenings = ScreeningResult::whereBetween('created_at', [$dayStart, $dayEnd])
                ->when($instId, function ($q) use ($instId) {
                    $q->whereHas('profile', function ($pq) use ($instId) {
                        $pq->whereHas('account', fn ($aq) => $aq->where('institution_id', $instId));
                    });
                })
                ->count();

            $learningCompleted = LearningProgress::where('status', 'COMPLETED')
                ->whereBetween('updated_at', [$dayStart, $dayEnd])
                ->when($instId, function ($q) use ($instId) {
                    $q->whereHas('profile', function ($pq) use ($instId) {
                        $pq->whereHas('account', fn ($aq) => $aq->where('institution_id', $instId));
                    });
                })
                ->count();

            $challenges = ChallengeAttempt::whereBetween('created_at', [$dayStart, $dayEnd])
                ->when($instId, function ($q) use ($instId) {
                    $q->whereHas('profile', function ($pq) use ($instId) {
                        $pq->whereHas('account', fn ($aq) => $aq->where('institution_id', $instId));
                    });
                })
                ->count();

            $days[] = [
                'date' => $date->format('m/d'),
                'day' => ['일', '월', '화', '수', '목', '금', '토'][$date->dayOfWeek],
                'screenings' => $screenings,
                'learning_completed' => $learningCompleted,
                'challenges' => $challenges,
            ];
        }

        return $days;
    }
}

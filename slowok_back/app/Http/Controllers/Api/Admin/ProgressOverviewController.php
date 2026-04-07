<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Account;
use App\Models\Challenge;
use App\Models\ContentAssignment;
use App\Models\ScreeningTest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressOverviewController extends BaseAdminController
{
    public function index(Request $request): JsonResponse
    {
        $instId = $this->getInstitutionId($request);

        // 활성 사용자
        $users = Account::where('role', 'USER')
            ->where('is_active', true)
            ->when($instId, fn($q) => $q->where('institution_id', $instId))
            ->with(['profile'])
            ->get()
            ->map(fn($u) => [
                'account_id' => $u->account_id,
                'name' => $u->profile?->decrypted_name ?? $u->profile?->name ?? $u->username,
                'username' => $u->username,
            ]);

        // 활성 챌린지 (주차+순서 정렬)
        $challenges = Challenge::where('is_active', true)
            ->when($instId, fn($q) => $q->where('institution_id', $instId))
            ->orderByRaw("CASE WHEN challenge_type REGEXP '^[0-9]+주차$' THEN CAST(SUBSTRING_INDEX(challenge_type, '주차', 1) AS UNSIGNED) ELSE 999 END")
            ->orderBy('sort_order')
            ->get(['challenge_id', 'title', 'challenge_type', 'sort_order']);

        // 활성 진단
        $screenings = ScreeningTest::where('is_active', true)
            ->when($instId, fn($q) => $q->where('institution_id', $instId))
            ->get(['test_id', 'title']);

        // 전체 할당 현황
        $assignments = ContentAssignment::query()
            ->when($instId, fn($q) => $q->whereHas('profile', fn($pq) => $pq->whereHas('account', fn($aq) => $aq->where('institution_id', $instId))))
            ->get(['profile_id', 'assignable_type', 'assignable_id', 'status']);

        // account_id → profile_id 매핑
        $profileMap = [];
        foreach ($users as $u) {
            $profileId = Account::find($u['account_id'])?->profile?->profile_id;
            if ($profileId) $profileMap[$u['account_id']] = $profileId;
        }

        // 매트릭스 데이터 구성
        $matrix = [];
        foreach ($users as $u) {
            $profileId = $profileMap[$u['account_id']] ?? null;
            if (!$profileId) continue;

            $userAssignments = $assignments->where('profile_id', $profileId);

            $challengeStatus = [];
            foreach ($challenges as $c) {
                $a = $userAssignments->where('assignable_type', 'challenge')->where('assignable_id', $c->challenge_id)->first();
                $challengeStatus[$c->challenge_id] = $a ? $a->status : null; // null=미할당, ASSIGNED, IN_PROGRESS, COMPLETED
            }

            $screeningStatus = [];
            foreach ($screenings as $s) {
                $a = $userAssignments->where('assignable_type', 'screening_test')->where('assignable_id', $s->test_id)->first();
                $screeningStatus[$s->test_id] = $a ? $a->status : null;
            }

            $matrix[] = [
                'account_id' => $u['account_id'],
                'name' => $u['name'],
                'username' => $u['username'],
                'challenges' => $challengeStatus,
                'screenings' => $screeningStatus,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'challenges' => $challenges,
                'screenings' => $screenings,
                'matrix' => $matrix,
            ],
        ]);
    }
}

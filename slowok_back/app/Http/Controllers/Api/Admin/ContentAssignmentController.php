<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\ContentAssignment;
use App\Models\UserProfile;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentAssignmentController extends BaseAdminController
{
    public function index(Request $request): JsonResponse
    {
        $instId = $this->getInstitutionId($request);

        $query = ContentAssignment::with(['profile', 'assigner']);

        if ($instId) {
            $query->whereHas('profile', function ($pq) use ($instId) {
                $pq->whereHas('account', fn ($aq) => $aq->where('institution_id', $instId));
            });
        }

        if ($request->profile_id) {
            $query->where('profile_id', $request->profile_id);
        }
        if ($request->assignable_type) {
            $query->where('assignable_type', $request->assignable_type);
        }

        $assignments = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $assignments,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'profile_id' => 'required|exists:user_profile,profile_id',
            'assignable_type' => 'required|in:screening_test,learning_content,challenge',
            'assignable_id' => 'required|integer',
            'due_date' => 'nullable|date',
            'note' => 'nullable|string|max:500',
        ]);

        $instId = $this->getInstitutionId($request);
        if ($instId) {
            $profile = UserProfile::whereHas('account', fn ($q) => $q->where('institution_id', $instId))
                ->where('profile_id', $request->profile_id)
                ->firstOrFail();
        }

        // sort_order: 기존 최대값 + 1
        $maxSort = ContentAssignment::where('profile_id', $request->profile_id)
            ->where('assignable_type', $request->assignable_type)
            ->max('sort_order') ?? -1;

        $assignment = ContentAssignment::create([
            'profile_id' => $request->profile_id,
            'assignable_type' => $request->assignable_type,
            'assignable_id' => $request->assignable_id,
            'assigned_by' => $request->user()->account_id,
            'assigned_at' => now(),
            'due_date' => $request->due_date,
            'note' => $request->note,
            'sort_order' => $maxSort + 1,
        ]);

        $assignment->load(['profile', 'assigner']);

        // 할당 알림
        $assignedProfile = UserProfile::find($request->profile_id);
        if ($assignedProfile) {
            app(NotificationService::class)->notify(
                $assignedProfile->account_id,
                'content_assigned',
                '새 콘텐츠 할당',
                '새로운 학습 콘텐츠가 할당되었습니다.',
            );
        }

        return response()->json([
            'success' => true,
            'data' => $assignment,
            'message' => '콘텐츠가 할당되었습니다.',
        ], 201);
    }

    public function bulkAssign(Request $request): JsonResponse
    {
        $request->validate([
            'profile_ids' => 'required|array|min:1',
            'profile_ids.*' => 'exists:user_profile,profile_id',
            'assignments' => 'required|array|min:1',
            'assignments.*.assignable_type' => 'required|in:screening_test,learning_content,challenge',
            'assignments.*.assignable_id' => 'required|integer',
            'due_date' => 'nullable|date',
            'note' => 'nullable|string|max:500',
        ]);

        $instId = $this->getInstitutionId($request);
        if ($instId) {
            $validProfileIds = UserProfile::whereHas('account', fn ($q) => $q->where('institution_id', $instId))
                ->whereIn('profile_id', $request->profile_ids)
                ->pluck('profile_id');

            if ($validProfileIds->count() !== count($request->profile_ids)) {
                return response()->json(['success' => false, 'message' => '기관에 속하지 않는 프로필이 포함되어 있습니다.'], 403);
            }
        }

        $created = 0;
        $assignerId = $request->user()->account_id;
        $perUserCount = [];

        foreach ($request->profile_ids as $profileId) {
            $userCreated = 0;
            foreach ($request->assignments as $item) {
                $exists = ContentAssignment::where('profile_id', $profileId)
                    ->where('assignable_type', $item['assignable_type'])
                    ->where('assignable_id', $item['assignable_id'])
                    ->exists();

                if (!$exists) {
                    $maxSort = ContentAssignment::where('profile_id', $profileId)
                        ->where('assignable_type', $item['assignable_type'])
                        ->max('sort_order') ?? -1;

                    ContentAssignment::create([
                        'profile_id' => $profileId,
                        'assignable_type' => $item['assignable_type'],
                        'assignable_id' => $item['assignable_id'],
                        'assigned_by' => $assignerId,
                        'assigned_at' => now(),
                        'due_date' => $request->due_date,
                        'note' => $request->note,
                        'sort_order' => $maxSort + 1,
                    ]);
                    $created++;
                    $userCreated++;
                }
            }
            if ($userCreated > 0) {
                $perUserCount[$profileId] = $userCreated;
            }
        }

        // 사용자별 할당 알림 (각자에게 본인 할당 수만 표시)
        foreach ($perUserCount as $profileId => $count) {
            $profile = UserProfile::find($profileId);
            if ($profile) {
                app(NotificationService::class)->notify(
                    $profile->account_id,
                    'content_assigned',
                    '새 콘텐츠 할당',
                    "{$count}건의 새로운 학습 콘텐츠가 할당되었습니다.",
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$created}건의 콘텐츠가 할당되었습니다.",
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $assignment = ContentAssignment::with('profile.account')->findOrFail($id);

        $instId = $this->getInstitutionId($request);
        if ($instId && $assignment->profile?->account?->institution_id !== $instId) {
            return response()->json(['success' => false, 'message' => '권한이 없습니다.'], 403);
        }

        $request->validate([
            'allow_retry' => 'sometimes|boolean',
        ]);

        if ($request->has('allow_retry')) {
            $assignment->update(['allow_retry' => $request->allow_retry]);
        }

        return response()->json(['success' => true, 'data' => $assignment, 'message' => '수정되었습니다.']);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'orders' => 'required|array',
            'orders.*.assignment_id' => 'required|integer|exists:content_assignment,assignment_id',
            'orders.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->orders as $item) {
            ContentAssignment::where('assignment_id', $item['assignment_id'])
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => '순서가 변경되었습니다.',
        ]);
    }

    public function resetAttempts(Request $request, int $id): JsonResponse
    {
        $assignment = ContentAssignment::with('profile.account')->findOrFail($id);

        $instId = $this->getInstitutionId($request);
        if ($instId && $assignment->profile?->account?->institution_id !== $instId) {
            return response()->json(['success' => false, 'message' => '권한이 없습니다.'], 403);
        }

        if ($assignment->assignable_type !== 'challenge') {
            return response()->json(['success' => false, 'message' => '챌린지 할당만 초기화할 수 있습니다.'], 400);
        }

        // 해당 프로필 + 챌린지의 시도 기록 전부 삭제
        $deleted = \App\Models\ChallengeAttempt::where('profile_id', $assignment->profile_id)
            ->where('challenge_id', $assignment->assignable_id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "{$deleted}건의 시도 기록이 초기화되었습니다.",
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $assignment = ContentAssignment::with('profile.account')->findOrFail($id);

        $instId = $this->getInstitutionId($request);
        if ($instId && $assignment->profile?->account?->institution_id !== $instId) {
            return response()->json(['success' => false, 'message' => '권한이 없습니다.'], 403);
        }

        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => '할당이 해제되었습니다.',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LearnerMemo;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearnerMemoController extends Controller
{
    /**
     * 학습자 메모 목록 조회
     */
    public function index(Request $request, int $profileId): JsonResponse
    {
        $institutionId = $request->attributes->get('institution_id');

        $profile = UserProfile::whereHas('account', function ($q) use ($institutionId) {
            if ($institutionId) {
                $q->where('institution_id', $institutionId);
            }
        })->where('profile_id', $profileId)->first();

        if (!$profile) {
            return response()->json(['success' => false, 'message' => '해당 학습자를 찾을 수 없습니다.'], 404);
        }

        $memos = LearnerMemo::with('account')
            ->where('profile_id', $profileId)
            ->orderByDesc('is_pinned')
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $memos]);
    }

    /**
     * 메모 작성
     */
    public function store(Request $request, int $profileId): JsonResponse
    {
        $institutionId = $request->attributes->get('institution_id');

        $profile = UserProfile::whereHas('account', function ($q) use ($institutionId) {
            if ($institutionId) {
                $q->where('institution_id', $institutionId);
            }
        })->where('profile_id', $profileId)->first();

        if (!$profile) {
            return response()->json(['success' => false, 'message' => '해당 학습자를 찾을 수 없습니다.'], 404);
        }

        $validated = $request->validate([
            'category' => 'required|in:observation,consultation,handover,general',
            'content' => 'required|string|max:2000',
            'is_pinned' => 'sometimes|boolean',
        ]);

        $memo = LearnerMemo::create([
            'profile_id' => $profileId,
            'account_id' => $request->user()->account_id,
            'category' => $validated['category'],
            'content' => $validated['content'],
            'is_pinned' => $validated['is_pinned'] ?? false,
        ]);

        $memo->load('account');

        return response()->json([
            'success' => true,
            'data' => $memo,
            'message' => '메모가 등록되었습니다.',
        ], 201);
    }

    /**
     * 메모 수정 (작성자 본인만)
     */
    public function update(Request $request, int $memoId): JsonResponse
    {
        $memo = LearnerMemo::findOrFail($memoId);

        if ($memo->account_id !== $request->user()->account_id) {
            return response()->json(['success' => false, 'message' => '본인의 메모만 수정할 수 있습니다.'], 403);
        }

        $validated = $request->validate([
            'category' => 'sometimes|in:observation,consultation,handover,general',
            'content' => 'sometimes|string|max:2000',
            'is_pinned' => 'sometimes|boolean',
        ]);

        $memo->update($validated);
        $memo->load('account');

        return response()->json([
            'success' => true,
            'data' => $memo,
            'message' => '메모가 수정되었습니다.',
        ]);
    }

    /**
     * 메모 삭제 (작성자 본인만)
     */
    public function destroy(Request $request, int $memoId): JsonResponse
    {
        $memo = LearnerMemo::findOrFail($memoId);

        if ($memo->account_id !== $request->user()->account_id) {
            return response()->json(['success' => false, 'message' => '본인의 메모만 삭제할 수 있습니다.'], 403);
        }

        $memo->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => '메모가 삭제되었습니다.',
        ]);
    }

    /**
     * 고정 토글
     */
    public function togglePin(Request $request, int $memoId): JsonResponse
    {
        $memo = LearnerMemo::findOrFail($memoId);

        $memo->update(['is_pinned' => !$memo->is_pinned]);
        $memo->load('account');

        return response()->json([
            'success' => true,
            'data' => $memo,
            'message' => $memo->is_pinned ? '메모가 고정되었습니다.' : '메모 고정이 해제되었습니다.',
        ]);
    }
}

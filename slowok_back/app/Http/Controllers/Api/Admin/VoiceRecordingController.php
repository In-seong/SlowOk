<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RecordingFeedback;
use App\Models\VoiceRecording;
use App\Models\UserProfile;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceRecordingController extends Controller
{
    /**
     * 기관 소속 사용자들의 녹음 목록 조회
     */
    public function index(Request $request): JsonResponse
    {
        $institutionId = $request->attributes->get('institution_id');

        $query = VoiceRecording::with(['profile', 'feedbacks.account'])
            ->whereHas('profile', function ($q) use ($institutionId) {
                $q->whereHas('account', function ($aq) use ($institutionId) {
                    if ($institutionId) {
                        $aq->where('institution_id', $institutionId);
                    }
                });
            })
            ->latest();

        // 프로필 ID로 필터
        if ($request->filled('profile_id')) {
            $query->where('profile_id', $request->input('profile_id'));
        }

        // 콘텐츠 타입으로 필터
        if ($request->filled('assignable_type')) {
            $query->where('assignable_type', $request->input('assignable_type'));
        }

        $recordings = $query->get();

        return response()->json(['success' => true, 'data' => $recordings]);
    }

    /**
     * 녹음에 피드백 추가
     */
    public function storeFeedback(Request $request, int $recordingId): JsonResponse
    {
        $recording = VoiceRecording::with('profile')->findOrFail($recordingId);

        $validated = $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        $feedback = RecordingFeedback::create([
            'recording_id' => $recordingId,
            'account_id' => $request->user()->account_id,
            'comment' => $validated['comment'],
        ]);

        $feedback->load('account');

        // 녹음 작성자에게 알림
        $profile = $recording->profile;
        if ($profile) {
            $commenterName = $request->user()->profile->decrypted_name
                ?? $request->user()->profile->name
                ?? $request->user()->username;

            app(NotificationService::class)->notifyWithParent(
                $profile,
                'recording_feedback',
                '녹음 피드백',
                "{$commenterName}님이 녹음에 피드백을 남겼습니다: \"{$this->truncate($validated['comment'], 50)}\""
            );
        }

        return response()->json([
            'success' => true,
            'data' => $feedback,
            'message' => '피드백이 등록되었습니다.',
        ], 201);
    }

    /**
     * 녹음의 피드백 목록 조회
     */
    public function feedbacks(int $recordingId): JsonResponse
    {
        $recording = VoiceRecording::findOrFail($recordingId);

        $feedbacks = RecordingFeedback::with('account')
            ->where('recording_id', $recording->recording_id)
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $feedbacks]);
    }

    /**
     * 피드백 삭제 (작성자 본인만)
     */
    public function destroyFeedback(Request $request, int $feedbackId): JsonResponse
    {
        $feedback = RecordingFeedback::findOrFail($feedbackId);

        if ($feedback->account_id !== $request->user()->account_id) {
            return response()->json(['success' => false, 'message' => '본인의 피드백만 삭제할 수 있습니다.'], 403);
        }

        $feedback->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => '피드백이 삭제되었습니다.',
        ]);
    }

    private function truncate(string $text, int $length): string
    {
        return mb_strlen($text) > $length
            ? mb_substr($text, 0, $length) . '...'
            : $text;
    }
}

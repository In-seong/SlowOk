<?php
namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\RecordingFeedback;
use App\Models\UserProfile;
use App\Models\VoiceRecording;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VoiceRecordingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profile = $request->attributes->get('active_profile');
        if (!$profile) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $query = VoiceRecording::with('feedbacks.account')
            ->where('profile_id', $profile->profile_id);

        if ($request->filled('assignable_type')) {
            $query->where('assignable_type', $request->input('assignable_type'));
        }
        if ($request->filled('assignable_id')) {
            $query->where('assignable_id', $request->input('assignable_id'));
        }

        $recordings = $query->latest()->get();

        return response()->json(['success' => true, 'data' => $recordings]);
    }

    public function store(Request $request): JsonResponse
    {
        $profile = $request->attributes->get('active_profile');
        if (!$profile) {
            return response()->json(['success' => false, 'message' => '프로필이 존재하지 않습니다.'], 400);
        }

        $validated = $request->validate([
            'file' => 'required|file|mimes:webm,mp3,wav,ogg,m4a|max:20480',
            'assignable_type' => 'required|in:learning_content,challenge',
            'assignable_id' => 'required|integer',
            'duration' => 'nullable|integer',
            'memo' => 'nullable|string|max:500',
            'stt_text' => 'nullable|string|max:5000',
            'stt_confidence' => 'nullable|numeric|min:0|max:1',
            'question_id' => 'nullable|integer',
        ]);

        $file = $request->file('file');
        $filename = time() . '_' . $profile->profile_id . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('recordings', $filename, 'public');

        $recording = VoiceRecording::create([
            'profile_id' => $profile->profile_id,
            'assignable_type' => $validated['assignable_type'],
            'assignable_id' => $validated['assignable_id'],
            'file_path' => $path,
            'file_url' => '/storage/' . $path,
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'duration' => $validated['duration'] ?? null,
            'memo' => $validated['memo'] ?? null,
            'stt_text' => $validated['stt_text'] ?? null,
            'stt_confidence' => $validated['stt_confidence'] ?? null,
            'question_id' => $validated['question_id'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $recording,
            'message' => '녹음이 저장되었습니다.',
        ], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $recording = VoiceRecording::where('recording_id', $id)->firstOrFail();

        $profile = request()->attributes->get('active_profile');
        if (!$profile || $recording->profile_id !== $profile->profile_id) {
            return response()->json(['success' => false, 'message' => '삭제 권한이 없습니다.'], 403);
        }

        Storage::disk('public')->delete($recording->file_path);
        $recording->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => '녹음이 삭제되었습니다.',
        ]);
    }

    /**
     * 자녀 녹음 목록 조회 (학부모 전용)
     */
    public function childRecordings(Request $request, int $childProfileId): JsonResponse
    {
        $profile = $request->attributes->get('active_profile');
        if (!$profile || $profile->user_type !== 'PARENT') {
            return response()->json(['success' => false, 'message' => '학부모만 접근할 수 있습니다.'], 403);
        }

        // 본인의 자녀인지 확인
        $child = UserProfile::where('profile_id', $childProfileId)
            ->where('parent_profile_id', $profile->profile_id)
            ->first();

        if (!$child) {
            return response()->json(['success' => false, 'message' => '자녀 프로필을 찾을 수 없습니다.'], 404);
        }

        $recordings = VoiceRecording::with('feedbacks.account')
            ->where('profile_id', $childProfileId)
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $recordings]);
    }

    /**
     * 녹음에 피드백 작성 (학부모: 자녀 녹음에만 / 학습자: 본인 녹음에만 코멘트 불가)
     */
    public function storeFeedback(Request $request, int $recordingId): JsonResponse
    {
        $profile = $request->attributes->get('active_profile');
        if (!$profile) {
            return response()->json(['success' => false, 'message' => '프로필이 존재하지 않습니다.'], 400);
        }

        $recording = VoiceRecording::with('profile')->findOrFail($recordingId);

        // 학부모는 자녀 녹음에만 피드백 가능
        if ($profile->user_type === 'PARENT') {
            $child = UserProfile::where('profile_id', $recording->profile_id)
                ->where('parent_profile_id', $profile->profile_id)
                ->first();

            if (!$child) {
                return response()->json(['success' => false, 'message' => '자녀의 녹음에만 피드백을 남길 수 있습니다.'], 403);
            }
        } else {
            return response()->json(['success' => false, 'message' => '학부모만 피드백을 남길 수 있습니다.'], 403);
        }

        $validated = $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        $feedback = RecordingFeedback::create([
            'recording_id' => $recordingId,
            'account_id' => $request->user()->account_id,
            'comment' => $validated['comment'],
        ]);

        $feedback->load('account');

        // 학습자에게 알림
        $recordingProfile = $recording->profile;
        if ($recordingProfile) {
            $parentName = $profile->decrypted_name ?? $profile->name;
            app(NotificationService::class)->notify(
                $recordingProfile->account_id,
                'recording_feedback',
                '녹음 피드백',
                "{$parentName}님이 녹음에 피드백을 남겼습니다: \"" . $this->truncate($validated['comment'], 50) . "\""
            );
        }

        return response()->json([
            'success' => true,
            'data' => $feedback,
            'message' => '피드백이 등록되었습니다.',
        ], 201);
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

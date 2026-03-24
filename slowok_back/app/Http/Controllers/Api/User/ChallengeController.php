<?php
namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\ContentAssignment;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChallengeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profile = $request->attributes->get('active_profile');

        $query = Challenge::with('category');

        if (!$profile) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $assignedIds = ContentAssignment::where('profile_id', $profile->profile_id)
            ->where('assignable_type', 'challenge')
            ->pluck('assignable_id');

        $query->whereIn('challenge_id', $assignedIds);

        $challenges = $query->get();
        return response()->json(['success' => true, 'data' => $challenges]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $profile = $request->attributes->get('active_profile');
        if (!$profile) {
            return response()->json(['success' => false, 'message' => '프로필을 먼저 생성해주세요.'], 400);
        }

        $assigned = ContentAssignment::where('profile_id', $profile->profile_id)
            ->where('assignable_type', 'challenge')
            ->where('assignable_id', $id)
            ->exists();

        if (!$assigned) {
            return response()->json(['success' => false, 'message' => '할당되지 않은 챌린지입니다.'], 403);
        }

        $challenge = Challenge::with(['category', 'questions'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $challenge]);
    }

    public function attempt(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'score' => 'required|integer',
            'is_passed' => 'required|boolean',
            'answers' => 'nullable|array',
            'time_spent' => 'nullable|integer',
        ]);

        $profile = $request->attributes->get('active_profile');
        if (!$profile) {
            return response()->json(['success' => false, 'message' => '프로필을 먼저 생성해주세요.'], 400);
        }

        $assigned = ContentAssignment::where('profile_id', $profile->profile_id)
            ->where('assignable_type', 'challenge')
            ->where('assignable_id', $id)
            ->exists();

        if (!$assigned) {
            return response()->json(['success' => false, 'message' => '할당되지 않은 챌린지입니다.'], 403);
        }

        $attempt = ChallengeAttempt::create([
            'profile_id' => $profile->profile_id,
            'challenge_id' => $id,
            'score' => $request->score,
            'is_passed' => $request->is_passed,
            'answers' => $request->answers,
            'time_spent' => $request->time_spent,
        ]);

        // 챌린지 합격 알림
        if ($request->is_passed) {
            $challenge = Challenge::find($id);
            $challengeTitle = $challenge?->title ?? '챌린지';
            app(NotificationService::class)->notifyWithParent(
                $profile,
                'challenge_passed',
                '챌린지 합격',
                "{$challengeTitle}에 합격했습니다! ({$request->score}점)",
            );
        }

        return response()->json(['success' => true, 'data' => $attempt, 'message' => '챌린지 결과가 기록되었습니다.'], 201);
    }
}

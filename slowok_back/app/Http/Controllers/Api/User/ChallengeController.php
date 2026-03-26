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

        if (!$profile) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $profileId = $profile->profile_id;

        $assignments = ContentAssignment::where('profile_id', $profileId)
            ->where('assignable_type', 'challenge')
            ->orderBy('sort_order')
            ->orderBy('assignment_id')
            ->get();

        $assignedIds = $assignments->pluck('assignable_id');
        $sortMap = $assignments->pluck('sort_order', 'assignable_id');

        $challenges = Challenge::with(['category'])
            ->whereIn('challenge_id', $assignedIds)
            ->get()
            ->sortBy(fn ($c) => $sortMap->get($c->challenge_id, 0))
            ->values();

        // 프로필의 latest_attempt를 각 챌린지에 추가
        $latestAttempts = ChallengeAttempt::where('profile_id', $profileId)
            ->whereIn('challenge_id', $assignedIds)
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('challenge_id')
            ->keyBy('challenge_id');

        $challenges->each(function ($challenge) use ($latestAttempts) {
            $challenge->setAttribute('latest_attempt', $latestAttempts->get($challenge->challenge_id));
        });

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

        // 플레이 시 정답 판정을 위해 correct_answer 노출
        $challenge->questions->each(fn ($q) => $q->makeVisible('correct_answer'));

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

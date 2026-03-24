<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profile = $request->attributes->get('active_profile');

        if (!$profile || $profile->user_type !== 'PARENT') {
            return response()->json(['success' => false, 'message' => '학부모 프로필만 접근 가능합니다.'], 403);
        }

        $children = $profile->children()
            ->withCount([
                'learningProgress as learning_total',
                'learningProgress as learning_completed' => fn($q) => $q->where('status', 'COMPLETED'),
                'learningProgress as learning_in_progress' => fn($q) => $q->where('status', 'IN_PROGRESS'),
                'challengeAttempts as challenge_total',
                'challengeAttempts as challenge_passed' => fn($q) => $q->where('is_passed', true),
            ])
            ->withMax('learningProgress', 'updated_at')
            ->with(['screeningResults' => fn($q) => $q->with('test')->latest()->limit(1)])
            ->get();

        $data = $children->map(function ($child) {
            $latestScreening = $child->screeningResults->first();

            return [
                'profile_id' => $child->profile_id,
                'name' => $child->name,
                'decrypted_name' => $child->decrypted_name,
                'birth_date' => $child->birth_date,
                'latest_screening' => $latestScreening ? [
                    'score' => $latestScreening->score,
                    'level' => $latestScreening->level,
                    'test_title' => $latestScreening->test?->title,
                    'date' => $latestScreening->created_at,
                ] : null,
                'learning' => [
                    'total' => (int) $child->learning_total,
                    'completed' => (int) $child->learning_completed,
                    'in_progress' => (int) $child->learning_in_progress,
                ],
                'challenge' => [
                    'total' => (int) $child->challenge_total,
                    'passed' => (int) $child->challenge_passed,
                ],
                'latest_activity_at' => $child->learning_progress_max_updated_at,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }
}

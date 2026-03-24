<?php
namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\ContentAssignment;
use App\Models\LearningContent;
use App\Models\LearningProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningController extends Controller
{
    public function contents(Request $request): JsonResponse
    {
        $profile = $request->attributes->get('active_profile');

        $query = LearningContent::with('category');

        if (!$profile) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $assignedIds = ContentAssignment::where('profile_id', $profile->profile_id)
            ->where('assignable_type', 'learning_content')
            ->pluck('assignable_id');

        $query->whereIn('content_id', $assignedIds);

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }
        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function updateProgress(Request $request): JsonResponse
    {
        $request->validate([
            'content_id' => 'required|exists:learning_content,content_id',
            'status' => 'required|in:NOT_STARTED,IN_PROGRESS,COMPLETED',
            'score' => 'nullable|integer',
        ]);

        $profile = $request->attributes->get('active_profile');
        if (!$profile) {
            return response()->json(['success' => false, 'message' => '프로필을 먼저 생성해주세요.'], 400);
        }

        $progress = LearningProgress::updateOrCreate(
            ['profile_id' => $profile->profile_id, 'content_id' => $request->content_id],
            ['status' => $request->status, 'score' => $request->score, 'attempts' => \DB::raw('attempts + 1')]
        );
        return response()->json(['success' => true, 'data' => $progress]);
    }
}

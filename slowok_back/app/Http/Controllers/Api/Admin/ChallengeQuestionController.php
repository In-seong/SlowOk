<?php
namespace App\Http\Controllers\Api\Admin;

use App\Models\Challenge;
use App\Models\ChallengeQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChallengeQuestionController extends BaseAdminController
{
    public function index(Request $request, int $challengeId): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        Challenge::forInstitution($instId)->findOrFail($challengeId);

        $questions = ChallengeQuestion::where('challenge_id', $challengeId)
            ->orderBy('order')
            ->get()
            ->each->makeVisible('correct_answer');

        return response()->json(['success' => true, 'data' => $questions]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_id' => 'required|exists:challenge,challenge_id',
            'content' => 'required|string',
            'question_type' => 'nullable|string|max:50',
            'options' => 'nullable|array',
            'correct_answer' => 'nullable|string',
            'image_url' => 'nullable|string|max:500',
            'order' => 'nullable|integer',
            'match_pairs' => 'nullable|array',
            'match_pairs.*.left' => 'required_with:match_pairs|string',
            'match_pairs.*.right' => 'nullable|string',
            'match_pairs.*.right_image' => 'nullable|string|max:500',
            'accept_answers' => 'nullable|array',
            'accept_answers.*' => 'string',
        ]);

        $instId = $this->getInstitutionId($request);
        Challenge::forInstitution($instId)->findOrFail($request->challenge_id);

        $question = ChallengeQuestion::create($request->only(['challenge_id', 'content', 'question_type', 'options', 'correct_answer', 'image_url', 'order', 'match_pairs', 'accept_answers']));
        $question->makeVisible('correct_answer');

        return response()->json(['success' => true, 'data' => $question], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'content' => 'sometimes|string',
            'question_type' => 'nullable|string|max:50',
            'options' => 'nullable|array',
            'correct_answer' => 'nullable|string',
            'image_url' => 'nullable|string|max:500',
            'order' => 'nullable|integer',
            'match_pairs' => 'nullable|array',
            'match_pairs.*.left' => 'required_with:match_pairs|string',
            'match_pairs.*.right' => 'nullable|string',
            'match_pairs.*.right_image' => 'nullable|string|max:500',
            'accept_answers' => 'nullable|array',
            'accept_answers.*' => 'string',
        ]);

        $question = ChallengeQuestion::findOrFail($id);

        $instId = $this->getInstitutionId($request);
        Challenge::forInstitution($instId)->findOrFail($question->challenge_id);

        $question->update($request->only(['content', 'question_type', 'options', 'correct_answer', 'image_url', 'order', 'match_pairs', 'accept_answers']));
        $question->makeVisible('correct_answer');

        return response()->json(['success' => true, 'data' => $question]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $question = ChallengeQuestion::findOrFail($id);

        $instId = $this->getInstitutionId($request);
        Challenge::forInstitution($instId)->findOrFail($question->challenge_id);

        $question->delete();
        return response()->json(['success' => true, 'message' => '문항이 삭제되었습니다.']);
    }
}

<?php
namespace App\Http\Controllers\Api\Admin;

use App\Models\Challenge;
use App\Models\ChallengeQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChallengeController extends BaseAdminController
{
    public function index(Request $request): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        return response()->json(['success' => true, 'data' => Challenge::forInstitution($instId)->where('is_active', true)->with(['category', 'questions'])->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'required|exists:learning_category,category_id',
            'title' => 'required|string|max:200',
            'challenge_type' => 'nullable|string|max:50',
            'difficulty_level' => 'nullable|integer|min:1|max:10',
        ]);
        $challenge = Challenge::create([
            ...$request->only(['category_id', 'title', 'challenge_type', 'difficulty_level']),
            'institution_id' => $this->getInstitutionId($request),
        ]);
        return response()->json(['success' => true, 'data' => $challenge], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        return response()->json(['success' => true, 'data' => Challenge::forInstitution($instId)->with(['category', 'questions'])->findOrFail($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'category_id' => 'sometimes|exists:learning_category,category_id',
            'title' => 'sometimes|string|max:200',
            'challenge_type' => 'nullable|string|max:50',
            'difficulty_level' => 'nullable|integer|min:1|max:10',
            'is_active' => 'sometimes|boolean',
        ]);
        $instId = $this->getInstitutionId($request);
        $challenge = Challenge::forInstitution($instId)->findOrFail($id);
        $challenge->update($request->only(['category_id', 'title', 'challenge_type', 'difficulty_level', 'is_active']));
        return response()->json(['success' => true, 'data' => $challenge]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        Challenge::forInstitution($instId)->findOrFail($id)->update(['is_active' => false]);
        return response()->json(['success' => true, 'message' => '챌린지가 삭제되었습니다.']);
    }

    public function duplicate(Request $request, int $id): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        $source = Challenge::forInstitution($instId)->with('questions')->findOrFail($id);

        $newChallenge = DB::transaction(function () use ($source, $instId) {
            $new = Challenge::create([
                'category_id' => $source->category_id,
                'title' => $source->title . ' (복사본)',
                'challenge_type' => $source->challenge_type,
                'difficulty_level' => $source->difficulty_level,
                'institution_id' => $instId,
                'is_active' => true,
                'allow_retry' => $source->allow_retry,
            ]);

            foreach ($source->questions as $q) {
                ChallengeQuestion::create([
                    'challenge_id' => $new->challenge_id,
                    'content' => $q->content,
                    'question_type' => $q->question_type,
                    'options' => $q->options,
                    'correct_answer' => $q->correct_answer,
                    'image_url' => $q->image_url,
                    'match_pairs' => $q->match_pairs,
                    'accept_answers' => $q->accept_answers,
                    'order' => $q->order,
                ]);
            }

            return $new->load(['category', 'questions']);
        });

        return response()->json([
            'success' => true,
            'data' => $newChallenge,
            'message' => "'{$source->title}' 챌린지가 복제되었습니다. ({$source->questions->count()}개 문항 포함)",
        ], 201);
    }
}

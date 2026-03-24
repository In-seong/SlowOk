<?php
namespace App\Http\Controllers\Api\Admin;

use App\Models\LearningContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningContentController extends BaseAdminController
{
    public function index(Request $request): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        $data = LearningContent::forInstitution($instId)->where('is_active', true)->with('category')->get();
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'required|exists:learning_category,category_id',
            'title' => 'required|string|max:200',
            'content_type' => 'required|in:VIDEO,QUIZ,GAME,READING',
            'content_data' => 'nullable|array',
            'difficulty_level' => 'nullable|integer|min:1|max:10',
        ]);
        $content = LearningContent::create([
            ...$request->only(['category_id', 'title', 'content_type', 'content_data', 'difficulty_level']),
            'institution_id' => $this->getInstitutionId($request),
        ]);
        return response()->json(['success' => true, 'data' => $content], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        $content = LearningContent::forInstitution($instId)->with('category')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $content]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'category_id' => 'sometimes|exists:learning_category,category_id',
            'title' => 'sometimes|string|max:200',
            'content_type' => 'sometimes|in:VIDEO,QUIZ,GAME,READING',
            'content_data' => 'nullable|array',
            'difficulty_level' => 'nullable|integer|min:1|max:10',
            'is_active' => 'sometimes|boolean',
        ]);
        $instId = $this->getInstitutionId($request);
        $content = LearningContent::forInstitution($instId)->findOrFail($id);
        $content->update($request->only(['category_id', 'title', 'content_type', 'content_data', 'difficulty_level', 'is_active']));
        return response()->json(['success' => true, 'data' => $content]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        LearningContent::forInstitution($instId)->findOrFail($id)->update(['is_active' => false]);
        return response()->json(['success' => true, 'message' => '콘텐츠가 삭제되었습니다.']);
    }
}

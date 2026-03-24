<?php
namespace App\Http\Controllers\Api\Admin;

use App\Models\LearningCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningCategoryController extends BaseAdminController
{
    public function index(Request $request): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        return response()->json(['success' => true, 'data' => LearningCategory::forInstitution($instId)->where('is_active', true)->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:100', 'description' => 'nullable|string', 'icon' => 'nullable|string|max:100']);
        $category = LearningCategory::create([
            ...$request->only(['name', 'description', 'icon']),
            'institution_id' => $this->getInstitutionId($request),
        ]);
        return response()->json(['success' => true, 'data' => $category], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        return response()->json(['success' => true, 'data' => LearningCategory::forInstitution($instId)->findOrFail($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate(['name' => 'sometimes|string|max:100', 'description' => 'nullable|string', 'icon' => 'nullable|string|max:100', 'is_active' => 'sometimes|boolean']);
        $instId = $this->getInstitutionId($request);
        $category = LearningCategory::forInstitution($instId)->findOrFail($id);
        $category->update($request->only(['name', 'description', 'icon', 'is_active']));
        return response()->json(['success' => true, 'data' => $category]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        LearningCategory::forInstitution($instId)->findOrFail($id)->update(['is_active' => false]);
        return response()->json(['success' => true, 'message' => '카테고리가 삭제되었습니다.']);
    }
}

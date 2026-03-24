<?php
namespace App\Http\Controllers\Api\Admin;

use App\Models\ScreeningTest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScreeningTestController extends BaseAdminController
{
    public function index(Request $request): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        return response()->json(['success' => true, 'data' => ScreeningTest::forInstitution($instId)->where('is_active', true)->with('category')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'test_type' => 'sometimes|string|in:MULTIPLE_CHOICE,LIKERT',
            'category_id' => 'required|exists:learning_category,category_id',
            'sub_domains' => 'nullable|array',
            'sub_domains.*.name' => 'required|string|max:50',
            'sub_domains.*.description' => 'nullable|string|max:500',
            'question_count' => 'nullable|integer',
            'time_limit' => 'nullable|integer',
        ]);
        $test = ScreeningTest::create([
            ...$request->only(['title', 'description', 'test_type', 'sub_domains', 'category_id', 'question_count', 'time_limit']),
            'institution_id' => $this->getInstitutionId($request),
        ]);
        return response()->json(['success' => true, 'data' => $test, 'message' => '진단 테스트가 생성되었습니다.'], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        return response()->json(['success' => true, 'data' => ScreeningTest::forInstitution($instId)->with(['category', 'questions'])->findOrFail($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'title' => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'test_type' => 'sometimes|string|in:MULTIPLE_CHOICE,LIKERT',
            'category_id' => 'sometimes|exists:learning_category,category_id',
            'sub_domains' => 'nullable|array',
            'sub_domains.*.name' => 'required|string|max:50',
            'sub_domains.*.description' => 'nullable|string|max:500',
            'question_count' => 'nullable|integer',
            'time_limit' => 'nullable|integer',
            'is_active' => 'sometimes|boolean',
        ]);
        $instId = $this->getInstitutionId($request);
        $test = ScreeningTest::forInstitution($instId)->findOrFail($id);
        $test->update($request->only(['title', 'description', 'test_type', 'sub_domains', 'category_id', 'question_count', 'time_limit', 'is_active']));
        return response()->json(['success' => true, 'data' => $test, 'message' => '진단 테스트가 업데이트되었습니다.']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        ScreeningTest::forInstitution($instId)->findOrFail($id)->update(['is_active' => false]);
        return response()->json(['success' => true, 'message' => '진단 테스트가 삭제되었습니다.']);
    }
}

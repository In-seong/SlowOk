<?php
namespace App\Http\Controllers\Api\Admin;

use App\Models\ScreeningQuestion;
use App\Models\ScreeningTest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScreeningQuestionController extends BaseAdminController
{
    public function index(Request $request, int $testId): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        ScreeningTest::forInstitution($instId)->findOrFail($testId);

        $questions = ScreeningQuestion::where('test_id', $testId)->orderBy('order')->get();
        return response()->json(['success' => true, 'data' => $questions]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'test_id' => 'required|exists:screening_test,test_id',
            'content' => 'required|string',
            'question_type' => 'nullable|string|max:50',
            'sub_domain' => 'nullable|string|max:50',
            'options' => 'nullable|array',
            'correct_answer' => 'nullable|string',
            'order' => 'nullable|integer',
        ]);

        $instId = $this->getInstitutionId($request);
        $test = ScreeningTest::forInstitution($instId)->findOrFail($request->test_id);

        // sub_domain이 test의 sub_domains 목록에 있는지 검증
        if ($request->sub_domain && !empty($test->sub_domains)) {
            $validNames = array_column($test->sub_domains, 'name');
            if (!in_array($request->sub_domain, $validNames)) {
                return response()->json(['success' => false, 'message' => '유효하지 않은 하위영역입니다.'], 422);
            }
        }

        $question = ScreeningQuestion::create($request->only(['test_id', 'content', 'question_type', 'sub_domain', 'options', 'correct_answer', 'order']));
        return response()->json(['success' => true, 'data' => $question], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'content' => 'sometimes|string',
            'question_type' => 'nullable|string|max:50',
            'sub_domain' => 'nullable|string|max:50',
            'options' => 'nullable|array',
            'correct_answer' => 'nullable|string',
            'order' => 'nullable|integer',
        ]);

        $question = ScreeningQuestion::findOrFail($id);

        $instId = $this->getInstitutionId($request);
        $test = ScreeningTest::forInstitution($instId)->findOrFail($question->test_id);

        // sub_domain이 test의 sub_domains 목록에 있는지 검증
        if ($request->sub_domain && !empty($test->sub_domains)) {
            $validNames = array_column($test->sub_domains, 'name');
            if (!in_array($request->sub_domain, $validNames)) {
                return response()->json(['success' => false, 'message' => '유효하지 않은 하위영역입니다.'], 422);
            }
        }

        $question->update($request->only(['content', 'question_type', 'sub_domain', 'options', 'correct_answer', 'order']));
        return response()->json(['success' => true, 'data' => $question]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $question = ScreeningQuestion::findOrFail($id);

        $instId = $this->getInstitutionId($request);
        ScreeningTest::forInstitution($instId)->findOrFail($question->test_id);

        $question->delete();
        return response()->json(['success' => true, 'message' => '질문이 삭제되었습니다.']);
    }
}

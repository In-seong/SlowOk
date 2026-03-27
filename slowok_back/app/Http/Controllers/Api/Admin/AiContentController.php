<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Challenge;
use App\Models\ChallengeQuestion;
use App\Models\ContentPackage;
use App\Models\ContentPackageItem;
use App\Models\LearningContent;
use App\Models\ScreeningQuestion;
use App\Models\ScreeningTest;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiContentController extends BaseAdminController
{
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'prompt' => 'required|string|min:10|max:2000',
        ]);

        $instId = $this->getInstitutionId($request);
        if (!$instId) {
            return response()->json([
                'success' => false,
                'message' => '기관을 선택해주세요.',
            ], 422);
        }

        try {
            $service = new GeminiService();
            $accountId = $request->user()->account_id;
            $data = $service->generateContentPackage($request->input('prompt'), $instId, $accountId);

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'AI 콘텐츠가 생성되었습니다. 미리보기를 확인 후 저장해주세요.',
            ]);
        } catch (\RuntimeException $e) {
            $code = str_contains($e->getMessage(), '카테고리') ? 422 : 502;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $code);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '[서버 오류] ' . $e->getMessage(),
            ], 500);
        }
    }

    public function usage(Request $request): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        if (!$instId) {
            return response()->json([
                'success' => false,
                'message' => '기관을 선택해주세요.',
            ], 422);
        }

        $stats = GeminiService::getUsageStats($instId);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $request->validate([
            'package' => 'nullable|array',
            'package.name' => 'nullable|string',
            'learning_contents' => 'nullable|array',
            'challenge' => 'nullable|array',
            'screening_test' => 'nullable|array',
        ]);

        // package가 없으면 자동 생성
        if (!$request->input('package.name')) {
            $request->merge([
                'package' => ['name' => 'AI 생성 패키지 (' . now()->format('Y-m-d H:i') . ')'],
            ]);
        }

        $instId = $this->getInstitutionId($request);
        if (!$instId) {
            return response()->json([
                'success' => false,
                'message' => '기관을 선택해주세요.',
            ], 422);
        }

        $data = $request->all();
        $accountId = $request->user()->account_id;

        try {
            $result = DB::transaction(function () use ($data, $instId, $accountId) {
                $counts = ['learning_contents' => 0, 'challenges' => 0, 'screening_tests' => 0];
                $packageItems = [];
                $sortOrder = 1;

                // 1. LearningContent 생성
                if (!empty($data['learning_contents'])) {
                    foreach ($data['learning_contents'] as $lc) {
                        $content = LearningContent::create([
                            'category_id' => $lc['category_id'],
                            'title' => $lc['title'],
                            'content_type' => $lc['content_type'],
                            'content_data' => $lc['content_data'] ?? null,
                            'difficulty_level' => $lc['difficulty_level'] ?? 1,
                            'institution_id' => $instId,
                            'is_active' => true,
                        ]);
                        $packageItems[] = ['type' => 'learning_content', 'id' => $content->content_id, 'order' => $sortOrder++];
                        $counts['learning_contents']++;
                    }
                }

                // 2. Challenge + Questions 생성
                if (!empty($data['challenge'])) {
                    $ch = $data['challenge'];
                    $challenge = Challenge::create([
                        'category_id' => $ch['category_id'],
                        'title' => $ch['title'],
                        'challenge_type' => $ch['challenge_type'] ?? 'daily',
                        'difficulty_level' => $ch['difficulty_level'] ?? 1,
                        'institution_id' => $instId,
                        'is_active' => true,
                    ]);

                    if (!empty($ch['questions'])) {
                        foreach ($ch['questions'] as $q) {
                            ChallengeQuestion::create([
                                'challenge_id' => $challenge->challenge_id,
                                'content' => $q['content'],
                                'question_type' => $q['question_type'] ?? 'multiple_choice',
                                'options' => $q['options'] ?? null,
                                'correct_answer' => $q['correct_answer'] ?? null,
                                'match_pairs' => $q['match_pairs'] ?? null,
                                'order' => $q['order'] ?? 1,
                            ]);
                        }
                    }

                    $packageItems[] = ['type' => 'challenge', 'id' => $challenge->challenge_id, 'order' => $sortOrder++];
                    $counts['challenges'] = 1;
                }

                // 3. ScreeningTest + Questions 생성
                if (!empty($data['screening_test'])) {
                    $st = $data['screening_test'];
                    $questions = $st['questions'] ?? [];

                    $test = ScreeningTest::create([
                        'title' => $st['title'],
                        'description' => $st['description'] ?? '',
                        'test_type' => $st['test_type'] ?? 'LIKERT',
                        'sub_domains' => $st['sub_domains'] ?? null,
                        'category_id' => $st['category_id'],
                        'question_count' => count($questions),
                        'time_limit' => $st['time_limit'] ?? null,
                        'institution_id' => $instId,
                        'is_active' => true,
                    ]);

                    foreach ($questions as $q) {
                        ScreeningQuestion::create([
                            'test_id' => $test->test_id,
                            'content' => $q['content'],
                            'question_type' => $q['question_type'] ?? 'likert_5',
                            'sub_domain' => $q['sub_domain'] ?? null,
                            'options' => $q['options'] ?? null,
                            'correct_answer' => $q['correct_answer'] ?? null,
                            'order' => $q['order'] ?? 1,
                        ]);
                    }

                    $packageItems[] = ['type' => 'screening_test', 'id' => $test->test_id, 'order' => $sortOrder++];
                    $counts['screening_tests'] = 1;
                }

                // 4. ContentPackage 생성 + Items 연결
                $pkg = $data['package'];
                $package = ContentPackage::create([
                    'name' => $pkg['name'],
                    'description' => $pkg['description'] ?? '',
                    'created_by' => $accountId,
                    'institution_id' => $instId,
                    'is_active' => true,
                ]);

                foreach ($packageItems as $item) {
                    ContentPackageItem::create([
                        'package_id' => $package->package_id,
                        'assignable_type' => $item['type'],
                        'assignable_id' => $item['id'],
                        'sort_order' => $item['order'],
                    ]);
                }

                return [
                    'package_id' => $package->package_id,
                    'package_name' => $package->name,
                    'counts' => $counts,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => "콘텐츠 패키지가 생성되었습니다. (학습 {$result['counts']['learning_contents']}개, 챌린지 {$result['counts']['challenges']}개, 검사 {$result['counts']['screening_tests']}개)",
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '저장 중 오류가 발생했습니다: ' . $e->getMessage(),
            ], 500);
        }
    }
}

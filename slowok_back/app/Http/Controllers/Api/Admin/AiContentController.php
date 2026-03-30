<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Challenge;
use App\Models\ChallengeQuestion;
use App\Models\ScreeningQuestion;
use App\Models\ScreeningTest;
use App\Models\AiGenerationLog;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiContentController extends BaseAdminController
{
    public function recentPrompts(Request $request): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        if (!$instId) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $prompts = AiGenerationLog::where('institution_id', $instId)
            ->where('status', 'success')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['log_id', 'prompt', 'created_at'])
            ->unique('prompt')
            ->take(10)
            ->values();

        return response()->json(['success' => true, 'data' => $prompts]);
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'prompt' => 'required|string|min:10|max:2000',
            'mode' => 'nullable|string|in:challenge,screening,all',
        ]);

        $instId = $this->getInstitutionId($request);
        if (!$instId) {
            return response()->json([
                'success' => false,
                'message' => '기관을 선택해주세요.',
            ], 422);
        }

        $mode = $request->input('mode', 'all');

        try {
            $service = new GeminiService();
            $accountId = $request->user()->account_id;
            $data = $service->generateContentPackage($request->input('prompt'), $instId, $accountId, $mode);

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
            'challenge' => 'nullable|array',
            'screening_test' => 'nullable|array',
        ]);

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
            $result = DB::transaction(function () use ($data, $instId) {
                $counts = ['challenges' => 0, 'screening_tests' => 0];

                // 1. Challenge + Questions 생성
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
                                'accept_answers' => $q['accept_answers'] ?? null,
                                'order' => $q['order'] ?? 1,
                            ]);
                        }
                    }

                    $counts['challenges'] = 1;
                }

                // 2. ScreeningTest + Questions 생성
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

                    $counts['screening_tests'] = 1;
                }

                return ['counts' => $counts];
            });

            $parts = [];
            if ($result['counts']['challenges'] > 0) $parts[] = "챌린지 {$result['counts']['challenges']}개";
            if ($result['counts']['screening_tests'] > 0) $parts[] = "진단검사 {$result['counts']['screening_tests']}개";

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'AI 생성 완료! (' . implode(', ', $parts) . ')',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '저장 중 오류가 발생했습니다: ' . $e->getMessage(),
            ], 500);
        }
    }
}

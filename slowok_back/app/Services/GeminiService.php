<?php

namespace App\Services;

use App\Models\AiGenerationLog;
use App\Models\LearningCategory;
use Illuminate\Support\Facades\Http;

class GeminiService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model');
    }

    public function generateContentPackage(string $prompt, int $institutionId, int $accountId): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('GEMINI_API_KEY가 설정되지 않았습니다.');
        }

        $categories = LearningCategory::where('institution_id', $institutionId)
            ->where('is_active', true)
            ->get(['category_id', 'name', 'description'])
            ->toArray();

        if (empty($categories)) {
            throw new \RuntimeException('등록된 카테고리가 없습니다. 먼저 카테고리를 추가해주세요.');
        }

        $systemPrompt = $this->buildSystemPrompt($categories);

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        try {
            $response = Http::timeout(120)
                ->retry(2, 3000)
                ->post($url, [
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $prompt]]],
                    ],
                    'systemInstruction' => [
                        'parts' => [['text' => $systemPrompt]],
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'temperature' => 0.8,
                    ],
                ]);
        } catch (\Exception $e) {
            $this->logUsage($accountId, $institutionId, $prompt, 0, 0, 0, 'error', $e->getMessage());
            throw new \RuntimeException('Gemini API 연결 실패: ' . $e->getMessage());
        }

        if (!$response->successful()) {
            $error = $response->json('error.message') ?? $response->body();
            $this->logUsage($accountId, $institutionId, $prompt, 0, 0, 0, 'error', $error);
            throw new \RuntimeException('Gemini API 호출 실패: ' . $error);
        }

        // 토큰 사용량 추출
        $usage = $response->json('usageMetadata') ?? [];
        $promptTokens = $usage['promptTokenCount'] ?? 0;
        $completionTokens = $usage['candidatesTokenCount'] ?? 0;
        $totalTokens = $usage['totalTokenCount'] ?? 0;

        $text = $response->json('candidates.0.content.parts.0.text');
        if (!$text) {
            $this->logUsage($accountId, $institutionId, $prompt, $promptTokens, $completionTokens, $totalTokens, 'error', '응답 텍스트 없음');
            throw new \RuntimeException('Gemini 응답에서 텍스트를 추출할 수 없습니다.');
        }

        $data = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logUsage($accountId, $institutionId, $prompt, $promptTokens, $completionTokens, $totalTokens, 'error', 'JSON 파싱 실패');
            throw new \RuntimeException('Gemini 응답 JSON 파싱 실패: ' . json_last_error_msg());
        }

        // 성공 로그
        $this->logUsage($accountId, $institutionId, $prompt, $promptTokens, $completionTokens, $totalTokens, 'success');

        return $data;
    }

    private function logUsage(int $accountId, int $institutionId, string $prompt, int $promptTokens, int $completionTokens, int $totalTokens, string $status, ?string $errorMessage = null): void
    {
        AiGenerationLog::create([
            'account_id' => $accountId,
            'institution_id' => $institutionId,
            'prompt' => mb_substr($prompt, 0, 2000),
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }

    public static function getUsageStats(int $institutionId): array
    {
        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();

        $todayStats = AiGenerationLog::where('institution_id', $institutionId)
            ->where('created_at', '>=', $today)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_tokens), 0) as tokens, COALESCE(SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END), 0) as success_count')
            ->first();

        $monthStats = AiGenerationLog::where('institution_id', $institutionId)
            ->where('created_at', '>=', $monthStart)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_tokens), 0) as tokens, COALESCE(SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END), 0) as success_count')
            ->first();

        return [
            'today' => [
                'requests' => (int) $todayStats->count,
                'success' => (int) $todayStats->success_count,
                'tokens' => (int) $todayStats->tokens,
                'limit' => 1500,
            ],
            'month' => [
                'requests' => (int) $monthStats->count,
                'success' => (int) $monthStats->success_count,
                'tokens' => (int) $monthStats->tokens,
            ],
        ];
    }

    private function buildSystemPrompt(array $categories): string
    {
        $categoryList = collect($categories)
            ->map(fn($c) => "  - category_id: {$c['category_id']}, name: \"{$c['name']}\"")
            ->implode("\n");

        return <<<PROMPT
당신은 아동·청소년 언어/사회성 발달 교육 콘텐츠 전문가입니다.
사용자의 요청에 따라 학습 콘텐츠 패키지를 생성합니다.
모든 텍스트는 반드시 한국어로 작성해야 합니다.

## 사용 가능한 카테고리
{$categoryList}

위 category_id 중에서만 선택해서 사용하세요.

## 반드시 아래 JSON 구조로 응답하세요

{
  "package": {
    "name": "패키지 제목",
    "description": "패키지 설명"
  },
  "learning_contents": [
    {
      "category_id": 숫자,
      "title": "콘텐츠 제목",
      "content_type": "VIDEO|QUIZ|GAME|READING",
      "difficulty_level": 1~5,
      "content_data": {
        "description": "콘텐츠 상세 설명",
        "instructions": "학습 안내 (선택)",
        "game_type": "matching|sequence|puzzle (GAME인 경우만)"
      }
    }
  ],
  "challenge": {
    "category_id": 숫자,
    "title": "챌린지 제목",
    "challenge_type": "daily|weekly|special",
    "difficulty_level": 1~5,
    "questions": [
      {
        "content": "문제 내용",
        "question_type": "multiple_choice|matching",
        "options": ["보기1", "보기2", "보기3", "보기4"],
        "correct_answer": "정답 (multiple_choice인 경우)",
        "match_pairs": [{"left": "왼쪽", "right": "오른쪽"}],
        "order": 순번
      }
    ]
  },
  "screening_test": {
    "category_id": 숫자,
    "title": "검사 제목",
    "description": "검사 설명",
    "test_type": "LIKERT",
    "sub_domains": ["하위영역1", "하위영역2"],
    "time_limit": 분(숫자),
    "questions": [
      {
        "content": "질문 내용",
        "question_type": "likert_5",
        "sub_domain": "하위영역",
        "options": ["전혀 그렇지 않다", "그렇지 않다", "보통이다", "그렇다", "매우 그렇다"],
        "order": 순번
      }
    ]
  }
}

## 규칙
- question_type이 "multiple_choice"일 때: options 4개 필수, correct_answer는 options 중 하나
- question_type이 "matching"일 때: match_pairs 배열 필수 (3~5쌍), options와 correct_answer는 null
- learning_contents 배열의 content_type은 다양하게 섞어서 구성
- screening_test의 questions는 최소 5개 이상
- 난이도(difficulty_level)는 대상 연령에 맞게 조절
- challenge의 questions는 최소 3개 이상
PROMPT;
    }
}

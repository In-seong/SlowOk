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

    public function generateContentPackage(string $prompt, int $institutionId, int $accountId, string $mode = 'all'): array
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

        $systemPrompt = $this->buildSystemPrompt($categories, $mode);

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
        try {
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
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('AI 사용량 로그 저장 실패: ' . $e->getMessage());
        }
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

    private function buildSystemPrompt(array $categories, string $mode = 'all'): string
    {
        $categoryList = collect($categories)
            ->map(fn($c) => "  - category_id: {$c['category_id']}, name: \"{$c['name']}\"")
            ->implode("\n");

        $base = <<<PROMPT
# SlowOK - 아동 사회성 발달 치료 보조 플랫폼

## 서비스 소개
SlowOK는 사회성 활동이 필요한 아동(유아~초등)을 대상으로 점진적 교육자료를 제공하는 치료 보조 서비스입니다.
듀오링고처럼 다양한 유형의 문제·퀴즈·활동을 제공하여 아이들이 재미있게 학습하면서 사회성을 기를 수 있도록 돕습니다.

## 핵심 교육 영역
- **감정 인식/표현**: 표정 읽기, 감정 단어 익히기, 감정 표현 연습
- **또래 상호작용**: 인사하기, 차례 지키기, 함께 놀기, 나누기
- **자기 조절**: 화 다스리기, 기다리기, 충동 조절, 규칙 따르기
- **사회적 상황 이해**: 상황 판단, 적절한 행동 선택, 원인-결과 이해
- **의사소통**: 요청하기, 거절하기, 도움 구하기, 대화 이어가기

## 콘텐츠 설계 원칙
1. **아이 눈높이**: 쉬운 한국어, 친근한 상황, 또래가 등장하는 시나리오
2. **점진적 난이도**: Lv.1(단순 인식) → Lv.2(선택하기) → Lv.3(상황 판단) → Lv.4(복합 상황) → Lv.5(일반화)
3. **긍정적 톤**: "틀렸어"가 아니라 "이렇게 하면 더 좋아요" 식의 격려
4. **실생활 연결**: 학교, 놀이터, 가정, 마트 등 아이가 실제 겪는 장소와 상황

## 사용 가능한 카테고리
{$categoryList}

위 category_id 중에서만 선택해서 사용하세요.
- 난이도(difficulty_level)는 대상 연령에 맞게: 유아 1~2, 초등 저학년 2~3, 초등 고학년 3~4
- 모든 텍스트는 한국어, 아이와 보호자가 이해하기 쉬운 표현 사용
- 실제 치료/교육 현장에서 활용 가능한 전문적이면서도 친근한 콘텐츠
PROMPT;

        if ($mode === 'challenge') {
            return $base . <<<'PROMPT'


## 반드시 아래 JSON 구조로 응답하세요 (챌린지만 생성)

{
  "challenge": {
    "category_id": 숫자,
    "title": "챌린지 제목",
    "challenge_type": "daily|weekly|special",
    "difficulty_level": 1~5,
    "questions": [
      {
        "content": "문제 내용 (상황 제시 + 질문, 아이 눈높이)",
        "question_type": "multiple_choice|matching|image_choice|image_text",
        "options": ["보기1", "보기2", "보기3", "보기4"],
        "correct_answer": "정답 (multiple_choice/image_choice인 경우)",
        "match_pairs": [{"left": "왼쪽", "right": "오른쪽"}],
        "accept_answers": ["허용정답1", "허용정답2"],
        "order": 순번
      }
    ]
  }
}

## 규칙
- question_type이 "multiple_choice" 또는 "image_choice"일 때: options 4개 필수, correct_answer는 options 중 하나
- question_type이 "matching"일 때: match_pairs 배열 필수 (3~5쌍), options와 correct_answer는 null
- question_type이 "image_text"일 때: accept_answers 배열 필수, options와 correct_answer는 null
- challenge의 questions는 아이가 직접 푸는 문제 (최소 5개, 다양한 유형 섞어서)
- question_type을 multiple_choice, matching, image_choice, image_text 골고루 섞어서 구성
PROMPT;
        }

        if ($mode === 'screening') {
            return $base . <<<'PROMPT'


## 반드시 아래 JSON 구조로 응답하세요 (진단검사만 생성)

{
  "screening_test": {
    "category_id": 숫자,
    "title": "검사 제목 (예: '또래 상호작용 수준 체크리스트')",
    "description": "이 검사의 목적과 대상 설명",
    "test_type": "LIKERT",
    "sub_domains": ["하위영역1", "하위영역2", "하위영역3"],
    "time_limit": 분(숫자),
    "questions": [
      {
        "content": "보호자/교사가 관찰하여 평가하는 문항",
        "question_type": "likert_5",
        "sub_domain": "하위영역",
        "options": ["전혀 그렇지 않다", "그렇지 않다", "보통이다", "그렇다", "매우 그렇다"],
        "order": 순번
      }
    ]
  }
}

## 규칙
- screening_test의 questions는 보호자/교사가 관찰 평가하는 리커트 문항 (최소 10개)
- sub_domains는 최소 2개 이상, 각 하위영역에 3개 이상의 문항
- 문항은 구체적 행동 관찰 기반 (예: "아이가 또래에게 먼저 인사를 합니다")
PROMPT;
        }

        // mode === 'all' (함께 생성)
        return $base . <<<'PROMPT'


## 반드시 아래 JSON 구조로 응답하세요 (챌린지 + 진단검사 함께 생성)

{
  "challenge": {
    "category_id": 숫자,
    "title": "챌린지 제목",
    "challenge_type": "daily|weekly|special",
    "difficulty_level": 1~5,
    "questions": [
      {
        "content": "문제 내용 (상황 제시 + 질문, 아이 눈높이)",
        "question_type": "multiple_choice|matching|image_choice|image_text",
        "options": ["보기1", "보기2", "보기3", "보기4"],
        "correct_answer": "정답 (multiple_choice/image_choice인 경우)",
        "match_pairs": [{"left": "왼쪽", "right": "오른쪽"}],
        "accept_answers": ["허용정답1", "허용정답2"],
        "order": 순번
      }
    ]
  },
  "screening_test": {
    "category_id": 숫자,
    "title": "검사 제목",
    "description": "이 검사의 목적과 대상 설명",
    "test_type": "LIKERT",
    "sub_domains": ["하위영역1", "하위영역2"],
    "time_limit": 분(숫자),
    "questions": [
      {
        "content": "보호자/교사가 관찰하여 평가하는 문항",
        "question_type": "likert_5",
        "sub_domain": "하위영역",
        "options": ["전혀 그렇지 않다", "그렇지 않다", "보통이다", "그렇다", "매우 그렇다"],
        "order": 순번
      }
    ]
  }
}

## 규칙
- question_type이 "multiple_choice" 또는 "image_choice"일 때: options 4개 필수, correct_answer는 options 중 하나
- question_type이 "matching"일 때: match_pairs 배열 필수 (3~5쌍), options와 correct_answer는 null
- question_type이 "image_text"일 때: accept_answers 배열 필수, options와 correct_answer는 null
- challenge의 questions는 아이가 직접 푸는 문제 (최소 5개, 다양한 유형 섞어서)
- screening_test의 questions는 보호자/교사가 관찰 평가하는 리커트 문항 (최소 8개)
PROMPT;
    }
}

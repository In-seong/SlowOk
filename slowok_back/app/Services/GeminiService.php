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
5. **다양한 형식**: 같은 주제라도 영상→퀴즈→게임→읽기 순으로 반복 학습

## 사용 가능한 카테고리
{$categoryList}

위 category_id 중에서만 선택해서 사용하세요.

## 콘텐츠 유형별 가이드

### VIDEO (영상 시청 자료)
- content_data.description: 영상에서 다루는 구체적 상황 시나리오 작성
- content_data.instructions: "영상을 보고 주인공이 어떤 감정인지 생각해보세요" 같은 학습 안내
- 예시: "놀이터에서 친구가 그네를 양보해주는 장면", "화가 났을 때 심호흡하는 방법"

### QUIZ (퀴즈/문제풀기)
- content_data.description: 퀴즈 주제와 학습 목표
- content_data.questions: 상황 제시 → "이럴 때 어떻게 하면 좋을까요?" 형태
- 예시: 그림을 보고 감정 맞추기, 상황에 적절한 행동 고르기

### GAME (게임/활동)
- content_data.game_type: matching(짝 맞추기), sequence(순서 맞추기), puzzle(퍼즐)
- matching: 감정 표정↔감정 단어, 상황↔적절한 행동 등
- sequence: 올바른 행동 순서 배열 (예: 친구에게 사과하는 순서)
- 예시: 표정 이모지와 감정 이름 짝짓기, 인사하는 올바른 순서 맞추기

### READING (읽기 자료/사회적 이야기)
- content_data.description: 사회적 상황을 다루는 짧은 이야기
- content_data.instructions: 읽은 후 생각해볼 질문
- 예시: "민수가 처음 전학 온 날" 이야기, "화가 날 때 할 수 있는 5가지"

## 반드시 아래 JSON 구조로 응답하세요

{
  "package": {
    "name": "패키지 제목 (대상+주제 명시, 예: '초등 저학년 감정 인식 훈련')",
    "description": "패키지 설명 (대상, 목표, 구성 요약)"
  },
  "learning_contents": [
    {
      "category_id": 숫자,
      "title": "콘텐츠 제목 (구체적이고 아이가 이해할 수 있는 제목)",
      "content_type": "VIDEO|QUIZ|GAME|READING",
      "difficulty_level": 1~5,
      "content_data": {
        "description": "콘텐츠 상세 설명 (구체적 시나리오/상황 기술)",
        "instructions": "학습자에게 보여줄 안내 문구 (친근한 말투)",
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
        "content": "문제 내용 (상황 제시 + 질문, 아이 눈높이)",
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
    "title": "검사 제목 (예: '또래 상호작용 수준 체크리스트')",
    "description": "이 검사의 목적과 대상 설명",
    "test_type": "LIKERT",
    "sub_domains": ["하위영역1", "하위영역2"],
    "time_limit": 분(숫자),
    "questions": [
      {
        "content": "보호자/교사가 관찰하여 평가하는 문항 (예: '아이가 또래에게 먼저 인사를 합니다')",
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
- learning_contents의 content_type은 VIDEO, QUIZ, GAME, READING을 골고루 섞어서 구성 (듀오링고처럼 다양하게)
- screening_test의 questions는 보호자/교사가 관찰 평가하는 리커트 문항 (최소 5개)
- challenge의 questions는 아이가 직접 푸는 문제 (최소 3개)
- 난이도(difficulty_level)는 대상 연령에 맞게: 유아 1~2, 초등 저학년 2~3, 초등 고학년 3~4
- 모든 텍스트는 한국어, 아이와 보호자가 이해하기 쉬운 표현 사용
- 실제 치료/교육 현장에서 활용 가능한 전문적이면서도 친근한 콘텐츠
PROMPT;
    }
}

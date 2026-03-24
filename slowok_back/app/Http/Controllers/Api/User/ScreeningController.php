<?php
namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\ContentAssignment;
use App\Models\ScreeningTest;
use App\Models\ScreeningQuestion;
use App\Models\ScreeningResult;
use App\Models\RecommendationRule;
use App\Models\ContentPackage;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScreeningController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profile = $request->attributes->get('active_profile');

        $query = ScreeningTest::with('category');

        if (!$profile) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $assignedIds = ContentAssignment::where('profile_id', $profile->profile_id)
            ->where('assignable_type', 'screening_test')
            ->pluck('assignable_id');

        $query->whereIn('test_id', $assignedIds);

        $tests = $query->get();
        return response()->json(['success' => true, 'data' => $tests]);
    }

    public function questions(Request $request, int $id): JsonResponse
    {
        ScreeningTest::findOrFail($id);

        $profile = $request->attributes->get('active_profile');
        if (!$profile) {
            return response()->json(['success' => false, 'message' => '프로필을 먼저 생성해주세요.'], 400);
        }

        $assigned = ContentAssignment::where('profile_id', $profile->profile_id)
            ->where('assignable_type', 'screening_test')
            ->where('assignable_id', $id)
            ->exists();

        if (!$assigned) {
            return response()->json(['success' => false, 'message' => '할당되지 않은 진단 검사입니다.'], 403);
        }

        $questions = ScreeningQuestion::where('test_id', $id)
            ->orderBy('order')
            ->get(['question_id', 'test_id', 'content', 'question_type', 'sub_domain', 'options', 'order']);
        return response()->json(['success' => true, 'data' => $questions]);
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'answers' => 'required|array',
        ]);

        $profile = $request->attributes->get('active_profile');
        if (!$profile) {
            return response()->json(['success' => false, 'message' => '프로필을 먼저 생성해주세요.'], 400);
        }

        $assigned = ContentAssignment::where('profile_id', $profile->profile_id)
            ->where('assignable_type', 'screening_test')
            ->where('assignable_id', $id)
            ->exists();

        if (!$assigned) {
            return response()->json(['success' => false, 'message' => '할당되지 않은 진단 검사입니다.'], 403);
        }

        $submittedAnswers = $request->answers;

        $test = ScreeningTest::findOrFail($id);
        $questions = ScreeningQuestion::where('test_id', $id)->get();
        $totalQuestions = $questions->count();

        $score = 0;
        $level = '노력';
        $subScores = null;

        if ($test->test_type === 'LIKERT') {
            // 리커트 척도: 각 문항 응답값(1~5)을 하위영역별로 합산
            $domainScores = [];
            foreach ($questions as $question) {
                $questionId = $question->question_id;
                $answerValue = isset($submittedAnswers[$questionId]) ? (int) $submittedAnswers[$questionId] : 0;
                $domain = $question->sub_domain ?? '기타';
                if (!isset($domainScores[$domain])) {
                    $domainScores[$domain] = ['score' => 0, 'count' => 0];
                }
                $domainScores[$domain]['score'] += $answerValue;
                $domainScores[$domain]['count']++;
            }

            $subScores = [];
            $totalScore = 0;
            foreach ($domainScores as $domain => $data) {
                $max = $data['count'] * 5;
                $avg = $data['count'] > 0 ? round($data['score'] / $data['count'], 1) : 0;
                $subScores[$domain] = ['score' => $data['score'], 'max' => $max, 'avg' => $avg];
                $totalScore += $data['score'];
            }

            $maxTotal = $totalQuestions * 5;
            $score = $maxTotal > 0 ? (int) round(($totalScore / $maxTotal) * 100) : 0;

            if ($score >= 80) {
                $level = '상';
            } elseif ($score >= 60) {
                $level = '중';
            } else {
                $level = '하';
            }
        } else {
            // 기존 객관식: correct_answer 비교
            $correctCount = 0;
            foreach ($questions as $question) {
                $questionId = $question->question_id;
                if (isset($submittedAnswers[$questionId]) && $submittedAnswers[$questionId] === $question->correct_answer) {
                    $correctCount++;
                }
            }
            $score = $totalQuestions > 0 ? (int) round(($correctCount / $totalQuestions) * 100) : 0;

            if ($score >= 80) {
                $level = '우수';
            } elseif ($score >= 50) {
                $level = '보통';
            } else {
                $level = '노력';
            }
        }

        $result = ScreeningResult::create([
            'profile_id' => $profile->profile_id,
            'test_id' => $id,
            'score' => $score,
            'level' => $level,
            'analysis' => $submittedAnswers,
            'sub_scores' => $subScores,
        ]);

        // 진단 완료 알림
        $testTitle = $test->title ?? '진단 검사';
        app(NotificationService::class)->notifyWithParent(
            $profile,
            'screening_complete',
            '진단 완료',
            "{$testTitle} 결과: {$score}점 ({$level})",
        );

        // 추천 규칙에 따른 자동 콘텐츠 할당
        if ($test) {
            $assigned = $this->applyRecommendationRules($profile, $test->category_id, $score);
            if ($assigned > 0) {
                app(NotificationService::class)->notify(
                    $profile->account_id,
                    'content_assigned',
                    '학습 콘텐츠 자동 추천',
                    "진단 결과에 따라 {$assigned}건의 콘텐츠가 자동 할당되었습니다.",
                );
            }
        }

        return response()->json(['success' => true, 'data' => $result, 'message' => '진단이 완료되었습니다.'], 201);
    }

    private function applyRecommendationRules($profile, int $categoryId, int $score): int
    {
        $institutionId = $profile->account?->institution_id;
        $created = 0;

        $rules = RecommendationRule::where('category_id', $categoryId)
            ->where('is_active', true)
            ->where('score_min', '<=', $score)
            ->where('score_max', '>=', $score)
            ->when($institutionId, function ($q) use ($institutionId) {
                $q->where('institution_id', $institutionId);
            })
            ->get();

        foreach ($rules as $rule) {
            $package = ContentPackage::with('items')->find($rule->package_id);
            if (!$package) {
                continue;
            }

            foreach ($package->items as $item) {
                $exists = ContentAssignment::where('profile_id', $profile->profile_id)
                    ->where('assignable_type', $item->assignable_type)
                    ->where('assignable_id', $item->assignable_id)
                    ->exists();

                if (!$exists) {
                    ContentAssignment::create([
                        'profile_id' => $profile->profile_id,
                        'assignable_type' => $item->assignable_type,
                        'assignable_id' => $item->assignable_id,
                        'assigned_by' => $profile->account_id,
                        'assigned_at' => now(),
                        'note' => '진단 결과 자동 추천',
                    ]);
                    $created++;
                }
            }
        }

        return $created;
    }

    public function results(Request $request): JsonResponse
    {
        $profile = $request->attributes->get('active_profile');
        if (!$profile) {
            return response()->json(['success' => true, 'data' => []]);
        }
        $results = ScreeningResult::where('profile_id', $profile->profile_id)->with('test')->latest()->get();
        return response()->json(['success' => true, 'data' => $results]);
    }
}

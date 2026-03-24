<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\RecommendationRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationRuleController extends BaseAdminController
{
    public function index(Request $request): JsonResponse
    {
        $instId = $this->getInstitutionId($request);

        $rules = RecommendationRule::forInstitution($instId)
            ->where('is_active', true)
            ->with(['category', 'package'])
            ->orderBy('category_id')
            ->orderBy('score_min')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rules,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'required|exists:learning_category,category_id',
            'score_min' => 'required|integer|min:0|max:100',
            'score_max' => 'required|integer|min:0|max:100|gte:score_min',
            'package_id' => 'required|exists:content_package,package_id',
        ]);

        $instId = $this->getInstitutionId($request);

        // 같은 카테고리+기관 내 점수 범위 겹침 체크
        $overlap = RecommendationRule::forInstitution($instId)
            ->where('is_active', true)
            ->where('category_id', $request->category_id)
            ->where(function ($q) use ($request) {
                $q->whereBetween('score_min', [$request->score_min, $request->score_max])
                    ->orWhereBetween('score_max', [$request->score_min, $request->score_max])
                    ->orWhere(function ($q2) use ($request) {
                        $q2->where('score_min', '<=', $request->score_min)
                            ->where('score_max', '>=', $request->score_max);
                    });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => '해당 카테고리에 점수 범위가 겹치는 규칙이 있습니다.',
            ], 422);
        }

        $rule = RecommendationRule::create([
            'category_id' => $request->category_id,
            'score_min' => $request->score_min,
            'score_max' => $request->score_max,
            'package_id' => $request->package_id,
            'institution_id' => $instId,
        ]);

        $rule->load(['category', 'package']);

        return response()->json([
            'success' => true,
            'data' => $rule,
            'message' => '추천 규칙이 생성되었습니다.',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'category_id' => 'required|exists:learning_category,category_id',
            'score_min' => 'required|integer|min:0|max:100',
            'score_max' => 'required|integer|min:0|max:100|gte:score_min',
            'package_id' => 'required|exists:content_package,package_id',
            'is_active' => 'sometimes|boolean',
        ]);

        $instId = $this->getInstitutionId($request);
        $rule = RecommendationRule::forInstitution($instId)->findOrFail($id);

        // 겹침 체크 (자기 자신 제외)
        $overlap = RecommendationRule::forInstitution($instId)
            ->where('is_active', true)
            ->where('category_id', $request->category_id)
            ->where('rule_id', '!=', $id)
            ->where(function ($q) use ($request) {
                $q->whereBetween('score_min', [$request->score_min, $request->score_max])
                    ->orWhereBetween('score_max', [$request->score_min, $request->score_max])
                    ->orWhere(function ($q2) use ($request) {
                        $q2->where('score_min', '<=', $request->score_min)
                            ->where('score_max', '>=', $request->score_max);
                    });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => '해당 카테고리에 점수 범위가 겹치는 규칙이 있습니다.',
            ], 422);
        }

        $updateData = [
            'category_id' => $request->category_id,
            'score_min' => $request->score_min,
            'score_max' => $request->score_max,
            'package_id' => $request->package_id,
        ];
        if ($request->has('is_active')) {
            $updateData['is_active'] = $request->boolean('is_active');
        }
        $rule->update($updateData);

        $rule->load(['category', 'package']);

        return response()->json([
            'success' => true,
            'data' => $rule,
            'message' => '추천 규칙이 수정되었습니다.',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        $rule = RecommendationRule::forInstitution($instId)->findOrFail($id);
        $rule->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => '추천 규칙이 삭제되었습니다.',
        ]);
    }
}

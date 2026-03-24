<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstitutionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstitutionPlanController extends Controller
{
    /**
     * 전체 기관-플랜 매핑 목록 (MASTER용)
     */
    public function index(): JsonResponse
    {
        $plans = InstitutionPlan::with(['institution', 'plan.features'])
            ->latest('started_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    /**
     * 기관에 플랜 배정/변경
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'institution_id' => 'required|exists:institution,institution_id',
            'plan_id' => 'required|exists:plan,plan_id',
            'expires_at' => 'nullable|date',
        ]);

        $plan = InstitutionPlan::updateOrCreate(
            ['institution_id' => $request->institution_id],
            [
                'plan_id' => $request->plan_id,
                'started_at' => now(),
                'expires_at' => $request->expires_at,
            ]
        );

        $plan->load(['institution', 'plan.features']);

        return response()->json([
            'success' => true,
            'data' => $plan,
            'message' => '기관 플랜이 배정되었습니다.',
        ]);
    }

    /**
     * 기관 플랜 해제
     */
    public function destroy(int $id): JsonResponse
    {
        InstitutionPlan::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => '기관 플랜이 해제되었습니다.',
        ]);
    }
}

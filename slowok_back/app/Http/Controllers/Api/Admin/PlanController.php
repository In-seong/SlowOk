<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = Plan::with('features')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'price' => 'nullable|integer|min:0',
            'sort_order' => 'nullable|integer',
            'is_active' => 'sometimes|boolean',
            'feature_ids' => 'required|array|min:1',
            'feature_ids.*' => 'exists:feature,feature_id',
        ]);

        $plan = Plan::create($request->only(['name', 'description', 'price', 'sort_order', 'is_active']));
        $plan->features()->sync($request->feature_ids);
        $plan->load('features');

        return response()->json([
            'success' => true,
            'data' => $plan,
            'message' => '플랜이 생성되었습니다.',
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $plan = Plan::with('features')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $plan,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'price' => 'nullable|integer|min:0',
            'sort_order' => 'nullable|integer',
            'is_active' => 'sometimes|boolean',
            'feature_ids' => 'required|array|min:1',
            'feature_ids.*' => 'exists:feature,feature_id',
        ]);

        $plan->update($request->only(['name', 'description', 'price', 'sort_order', 'is_active']));
        $plan->features()->sync($request->feature_ids);
        $plan->load('features');

        return response()->json([
            'success' => true,
            'data' => $plan,
            'message' => '플랜이 수정되었습니다.',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        Plan::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => '플랜이 삭제되었습니다.',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureController extends Controller
{
    public function index(): JsonResponse
    {
        $features = Feature::orderBy('category')->orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'data' => $features,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'feature_key' => 'required|string|max:100|unique:feature,feature_key',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string|max:500',
            'category' => 'required|string|max:100',
            'sort_order' => 'nullable|integer',
        ]);

        $feature = Feature::create($request->only([
            'feature_key', 'name', 'description', 'category', 'sort_order',
        ]));

        return response()->json([
            'success' => true,
            'data' => $feature,
            'message' => '기능이 등록되었습니다.',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $feature = Feature::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string|max:500',
            'category' => 'required|string|max:100',
            'sort_order' => 'nullable|integer',
        ]);

        $feature->update($request->only(['name', 'description', 'category', 'sort_order']));

        return response()->json([
            'success' => true,
            'data' => $feature,
            'message' => '기능이 수정되었습니다.',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        Feature::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => '기능이 삭제되었습니다.',
        ]);
    }
}

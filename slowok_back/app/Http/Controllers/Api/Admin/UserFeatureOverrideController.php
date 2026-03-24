<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\UserFeatureOverride;
use App\Services\FeatureGateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserFeatureOverrideController extends BaseAdminController
{
    /**
     * 특정 프로필의 기능 override 목록
     */
    public function index(Request $request, int $profileId): JsonResponse
    {
        $overrides = UserFeatureOverride::where('profile_id', $profileId)->get();

        // 기관 플랜의 전체 기능 목록도 함께 반환
        $instId = $this->getInstitutionId($request);
        $featureGate = app(FeatureGateService::class);
        $institutionFeatures = $featureGate->getInstitutionFeatures($instId);

        return response()->json([
            'success' => true,
            'data' => [
                'overrides' => $overrides,
                'institution_features' => $institutionFeatures,
            ],
        ]);
    }

    /**
     * 프로필별 기능 on/off 설정
     */
    public function store(Request $request, int $profileId): JsonResponse
    {
        $request->validate([
            'feature_key' => 'required|string|exists:feature,feature_key',
            'enabled' => 'required|boolean',
        ]);

        // 기관 플랜에 해당 기능이 있는지 확인
        $instId = $this->getInstitutionId($request);
        $featureGate = app(FeatureGateService::class);

        if (!$featureGate->institutionHasFeature($instId, $request->feature_key)) {
            return response()->json([
                'success' => false,
                'message' => '현재 기관 플랜에 포함되지 않은 기능입니다.',
            ], 422);
        }

        $override = UserFeatureOverride::updateOrCreate(
            [
                'profile_id' => $profileId,
                'feature_key' => $request->feature_key,
            ],
            ['enabled' => $request->enabled]
        );

        return response()->json([
            'success' => true,
            'data' => $override,
            'message' => $request->enabled ? '기능이 활성화되었습니다.' : '기능이 비활성화되었습니다.',
        ]);
    }

    /**
     * override 삭제 (플랜 기본값으로 복원)
     */
    public function destroy(int $profileId, int $overrideId): JsonResponse
    {
        UserFeatureOverride::where('profile_id', $profileId)
            ->where('override_id', $overrideId)
            ->firstOrFail()
            ->delete();

        return response()->json([
            'success' => true,
            'message' => '기능 설정이 초기화되었습니다.',
        ]);
    }
}

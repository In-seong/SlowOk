<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Services\FeatureGateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureCheckController extends Controller
{
    public function __construct(private FeatureGateService $featureGate)
    {
    }

    /**
     * 현재 사용자가 접근 가능한 기능 목록 반환
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $request->attributes->get('active_profile');
        $institutionId = $user->institution_id;

        // 기관 플랜 기능 목록
        $institutionFeatures = $this->featureGate->getInstitutionFeatures($institutionId);

        // 프로필별 override 적용
        $enabledFeatures = [];
        if ($profile) {
            foreach ($institutionFeatures as $featureKey) {
                if ($this->featureGate->profileCanAccess($institutionId, $profile->profile_id, $featureKey)) {
                    $enabledFeatures[] = $featureKey;
                }
            }
        } else {
            $enabledFeatures = $institutionFeatures;
        }

        return response()->json([
            'success' => true,
            'data' => $enabledFeatures,
        ]);
    }
}

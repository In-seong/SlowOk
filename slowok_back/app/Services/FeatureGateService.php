<?php

namespace App\Services;

use App\Models\InstitutionPlan;
use App\Models\UserFeatureOverride;

class FeatureGateService
{
    /**
     * 기관이 특정 기능을 사용할 수 있는지 확인
     */
    public function institutionHasFeature(?int $institutionId, string $featureKey): bool
    {
        if ($institutionId === null) {
            return true; // MASTER는 전체 접근
        }

        $plan = InstitutionPlan::where('institution_id', $institutionId)
            ->with('plan.features')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->latest('started_at')
            ->first();

        if (!$plan) {
            return false; // 플랜 없으면 기능 없음
        }

        return $plan->plan->features->contains('feature_key', $featureKey);
    }

    /**
     * 특정 프로필이 기능을 사용할 수 있는지 (기관 플랜 + 사용자 override 조합)
     */
    public function profileCanAccess(?int $institutionId, int $profileId, string $featureKey): bool
    {
        // 1. 기관 플랜 체크 (천장)
        if (!$this->institutionHasFeature($institutionId, $featureKey)) {
            return false;
        }

        // 2. 사용자별 override 체크
        $override = UserFeatureOverride::where('profile_id', $profileId)
            ->where('feature_key', $featureKey)
            ->first();

        if ($override) {
            return $override->enabled;
        }

        // override 없으면 플랜 기본값(허용)
        return true;
    }

    /**
     * 기관의 현재 플랜에 포함된 기능 키 목록
     */
    public function getInstitutionFeatures(?int $institutionId): array
    {
        if ($institutionId === null) {
            return []; // MASTER용 - 별도 처리 필요
        }

        $plan = InstitutionPlan::where('institution_id', $institutionId)
            ->with('plan.features')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->latest('started_at')
            ->first();

        if (!$plan) {
            return [];
        }

        return $plan->plan->features->pluck('feature_key')->toArray();
    }
}

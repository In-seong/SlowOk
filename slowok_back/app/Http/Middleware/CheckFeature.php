<?php

namespace App\Http\Middleware;

use App\Services\FeatureGateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeature
{
    public function __construct(private FeatureGateService $featureGate)
    {
    }

    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '인증이 필요합니다.',
            ], 401);
        }

        // MASTER는 전체 통과
        if ($user->isMaster()) {
            return $next($request);
        }

        $institutionId = $user->institution_id;

        if (!$this->featureGate->institutionHasFeature($institutionId, $featureKey)) {
            return response()->json([
                'success' => false,
                'message' => '현재 플랜에서 사용할 수 없는 기능입니다.',
                'feature' => $featureKey,
            ], 403);
        }

        return $next($request);
    }
}

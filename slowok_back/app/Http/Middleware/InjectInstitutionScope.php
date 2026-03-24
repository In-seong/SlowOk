<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectInstitutionScope
{
    /**
     * MASTER: X-Institution-Id 헤더로 기관 전환 가능 (없으면 null = 전체)
     * ADMIN/TEST: 본인 account의 institution_id 강제 적용
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if ($user->isMaster()) {
            $headerValue = $request->header('X-Institution-Id');
            $institutionId = $headerValue ? (int) $headerValue : null;
        } else {
            // ADMIN/TEST는 본인 기관으로 강제
            $institutionId = $user->institution_id;
        }

        $request->attributes->set('institution_id', $institutionId);

        return $next($request);
    }
}

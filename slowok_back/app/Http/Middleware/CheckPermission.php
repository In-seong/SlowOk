<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '인증이 필요합니다.',
            ], 401);
        }

        // MASTER는 모든 권한 자동 통과
        if ($user->isMaster()) {
            return $next($request);
        }

        foreach ($permissions as $permission) {
            if (!$user->hasPermission($permission)) {
                return response()->json([
                    'success' => false,
                    'message' => '해당 기능에 대한 권한이 없습니다.',
                ], 403);
            }
        }

        return $next($request);
    }
}

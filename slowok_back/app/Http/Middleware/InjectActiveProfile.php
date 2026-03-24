<?php

namespace App\Http\Middleware;

use App\Models\UserProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectActiveProfile
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $profileId = $request->header('X-Active-Profile-Id');

        if ($profileId) {
            $profile = UserProfile::where('profile_id', $profileId)
                ->where('account_id', $user->account_id)
                ->first();

            if (!$profile) {
                return response()->json([
                    'success' => false,
                    'message' => '유효하지 않은 프로필입니다.',
                ], 403);
            }
        } else {
            $profile = $user->profile;
        }

        $request->attributes->set('active_profile', $profile);

        return $next($request);
    }
}

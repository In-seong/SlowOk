<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    /**
     * FCM 토큰 등록/갱신
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => 'required|string|max:500',
            'device_type' => 'required|in:android,ios,web',
        ]);

        $accountId = $request->user()->account_id;

        // 같은 토큰이 이미 있으면 갱신, 없으면 생성
        DeviceToken::updateOrCreate(
            [
                'account_id' => $accountId,
                'fcm_token' => $request->fcm_token,
            ],
            [
                'device_type' => $request->device_type,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => '디바이스 토큰이 등록되었습니다.',
        ]);
    }

    /**
     * FCM 토큰 삭제 (로그아웃 시)
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        DeviceToken::where('account_id', $request->user()->account_id)
            ->where('fcm_token', $request->fcm_token)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => '디바이스 토큰이 삭제되었습니다.',
        ]);
    }
}

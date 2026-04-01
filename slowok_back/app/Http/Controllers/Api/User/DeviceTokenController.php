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
        $deviceType = $request->device_type;

        // 같은 계정+기기유형의 기존 토큰 삭제 후 새 토큰 등록
        // → 토큰 갱신/기기 변경 시 이전 토큰이 남지 않음
        DeviceToken::where('account_id', $accountId)
            ->where('device_type', $deviceType)
            ->delete();

        DeviceToken::create([
            'account_id' => $accountId,
            'fcm_token' => $request->fcm_token,
            'device_type' => $deviceType,
        ]);

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

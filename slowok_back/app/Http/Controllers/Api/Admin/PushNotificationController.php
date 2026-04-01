<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Account;
use App\Models\DeviceToken;
use App\Services\FcmService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushNotificationController extends BaseAdminController
{
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'body' => 'required|string|max:500',
            'target' => 'required|in:all,specific',
            'account_ids' => 'nullable|array',
            'account_ids.*' => 'integer|exists:account,account_id',
        ]);

        $instId = $this->getInstitutionId($request);
        $title = $request->input('title');
        $body = $request->input('body');
        $target = $request->input('target');
        $debug = [];

        $fcm = new FcmService();

        if ($target === 'all') {
            $query = Account::where('role', 'USER');
            if ($instId) {
                $query->where('institution_id', $instId);
            }
            $accountIds = $query->pluck('account_id')->toArray();
        } else {
            $accountIds = $request->input('account_ids', []);
        }

        $debug['account_ids'] = $accountIds;

        if (empty($accountIds)) {
            return response()->json([
                'success' => false,
                'message' => '발송 대상이 없습니다.',
                'debug' => $debug,
            ], 422);
        }

        // 토큰 조회
        $tokens = DeviceToken::whereIn('account_id', $accountIds)->get();
        $debug['tokens_found'] = $tokens->count();
        $debug['token_details'] = $tokens->map(fn($t) => [
            'account_id' => $t->account_id,
            'device_type' => $t->device_type,
            'token_prefix' => substr($t->fcm_token, 0, 20) . '...',
        ])->toArray();

        if ($tokens->isEmpty()) {
            // 인앱 알림은 저장
            $notificationService = app(NotificationService::class);
            foreach ($accountIds as $accountId) {
                $notificationService->notify($accountId, 'admin_push', $title, $body);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'target_count' => count($accountIds),
                    'sent_count' => 0,
                ],
                'message' => count($accountIds) . "명에게 인앱 알림 저장 (푸시 토큰 없음 — 앱에서 로그인 필요)",
                'debug' => $debug,
            ]);
        }

        // FCM 발송 (상세 결과 포함)
        $sendResults = $fcm->sendToAccountsWithDebug($accountIds, $title, $body);
        $debug['fcm_results'] = $sendResults['details'];

        // 인앱 알림 저장
        $notificationService = app(NotificationService::class);
        foreach ($accountIds as $accountId) {
            $notificationService->notify($accountId, 'admin_push', $title, $body);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'target_count' => count($accountIds),
                'sent_count' => $sendResults['sent'],
            ],
            'message' => count($accountIds) . "명에게 발송 완료 (푸시 {$sendResults['sent']}건)",
            'debug' => $debug,
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $instId = $this->getInstitutionId($request);

        $query = DeviceToken::query();
        if ($instId) {
            $query->whereHas('account', fn($q) => $q->where('institution_id', $instId));
        }

        $totalTokens = $query->count();
        $uniqueAccounts = (clone $query)->distinct('account_id')->count('account_id');
        $accountIdsWithToken = (clone $query)->distinct()->pluck('account_id')->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'total_tokens' => $totalTokens,
                'unique_accounts' => $uniqueAccounts,
                'account_ids_with_token' => $accountIdsWithToken,
            ],
        ]);
    }
}

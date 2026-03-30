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

        $fcm = new FcmService();

        if ($target === 'all') {
            // 기관 내 전체 USER에게 발송
            $query = Account::where('role', 'USER');
            if ($instId) {
                $query->where('institution_id', $instId);
            }
            $accountIds = $query->pluck('account_id')->toArray();
        } else {
            $accountIds = $request->input('account_ids', []);
        }

        if (empty($accountIds)) {
            return response()->json([
                'success' => false,
                'message' => '발송 대상이 없습니다.',
            ], 422);
        }

        $sent = $fcm->sendToAccounts($accountIds, $title, $body);

        // 인앱 알림도 함께 저장
        $notificationService = app(NotificationService::class);
        foreach ($accountIds as $accountId) {
            $notificationService->notify($accountId, 'admin_push', $title, $body);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'target_count' => count($accountIds),
                'sent_count' => $sent,
            ],
            'message' => count($accountIds) . "명에게 발송 완료 (푸시 {$sent}건)",
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

        return response()->json([
            'success' => true,
            'data' => [
                'total_tokens' => $totalTokens,
                'unique_accounts' => $uniqueAccounts,
            ],
        ]);
    }
}

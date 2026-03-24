<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Google\Auth\Credentials\ServiceAccountCredentials;

class FcmService
{
    private ?string $projectId;
    private ?string $credentialsPath;

    public function __construct()
    {
        $this->credentialsPath = config('services.firebase.credentials');
        $this->projectId = config('services.firebase.project_id');
    }

    /**
     * 특정 계정의 모든 디바이스에 푸시 발송
     */
    public function sendToAccount(int $accountId, string $title, string $body, array $data = []): int
    {
        $tokens = DeviceToken::where('account_id', $accountId)->pluck('fcm_token');
        $sent = 0;

        foreach ($tokens as $token) {
            if ($this->sendToToken($token, $title, $body, $data)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * 여러 계정에 푸시 발송
     */
    public function sendToAccounts(array $accountIds, string $title, string $body, array $data = []): int
    {
        $tokens = DeviceToken::whereIn('account_id', $accountIds)->pluck('fcm_token');
        $sent = 0;

        foreach ($tokens as $token) {
            if ($this->sendToToken($token, $title, $body, $data)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * 단일 FCM 토큰에 발송 (FCM v1 HTTP API)
     */
    private function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        if (!$this->credentialsPath || !$this->projectId) {
            Log::warning('FCM: credentials or project_id not configured, skipping push');
            return false;
        }

        if (!file_exists($this->credentialsPath)) {
            Log::warning('FCM: credentials file not found: ' . $this->credentialsPath);
            return false;
        }

        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return false;
            }

            $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

            $message = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => array_map('strval', $data),
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'channel_id' => 'slowok_notifications',
                            'sound' => 'default',
                        ],
                    ],
                ],
            ];

            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->post($url, $message);

            if ($response->successful()) {
                return true;
            }

            $errorBody = $response->json();
            $errorCode = $errorBody['error']['details'][0]['errorCode'] ?? ($errorBody['error']['status'] ?? '');

            // 유효하지 않은 토큰이면 삭제
            if (in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'])) {
                DeviceToken::where('fcm_token', $token)->delete();
                Log::info('FCM: Removed invalid token: ' . substr($token, 0, 20) . '...');
            } else {
                Log::error('FCM: Send failed', [
                    'status' => $response->status(),
                    'error' => $errorBody,
                ]);
            }

            return false;
        } catch (\Throwable $e) {
            Log::error('FCM: Exception during send', ['message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Google OAuth2 access token 획득 (서비스 계정 JSON)
     */
    private function getAccessToken(): ?string
    {
        try {
            $credentials = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/firebase.messaging',
                json_decode(file_get_contents($this->credentialsPath), true),
            );

            $token = $credentials->fetchAuthToken();

            return $token['access_token'] ?? null;
        } catch (\Throwable $e) {
            Log::error('FCM: Failed to get access token', ['message' => $e->getMessage()]);
            return null;
        }
    }
}

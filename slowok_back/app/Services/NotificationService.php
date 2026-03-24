<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\UserProfile;

class NotificationService
{
    private FcmService $fcmService;

    public function __construct(FcmService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * 특정 계정에 알림 발송 + FCM 푸시
     */
    public function notify(int $accountId, string $type, string $title, string $message): Notification
    {
        $notification = Notification::create([
            'account_id' => $accountId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ]);

        // FCM 푸시 발송
        $this->fcmService->sendToAccount($accountId, $title, $message, [
            'type' => $type,
            'notification_id' => (string) $notification->notification_id,
        ]);

        return $notification;
    }

    /**
     * 프로필 소유자에게 알림 발송 + 학부모가 있으면 학부모에게도 발송
     */
    public function notifyWithParent(UserProfile $profile, string $type, string $title, string $message): void
    {
        // 본인 알림
        $this->notify($profile->account_id, $type, $title, $message);

        // 학부모 알림 (LEARNER이고 parent_profile_id가 있는 경우)
        if ($profile->user_type === 'LEARNER' && $profile->parent_profile_id) {
            $parent = UserProfile::find($profile->parent_profile_id);
            if ($parent) {
                $childName = $profile->decrypted_name ?? $profile->name;
                $this->notify(
                    $parent->account_id,
                    $type,
                    $title,
                    "[{$childName}] {$message}",
                );
            }
        }
    }

    /**
     * 여러 프로필 소유자에게 동일 알림 발송 (중복 account_id 방지)
     */
    public function notifyProfiles(array $profileIds, string $type, string $title, string $message): void
    {
        $profiles = UserProfile::whereIn('profile_id', $profileIds)->get();
        $notifiedAccountIds = [];

        foreach ($profiles as $profile) {
            if (!in_array($profile->account_id, $notifiedAccountIds, true)) {
                $this->notify($profile->account_id, $type, $title, $message);
                $notifiedAccountIds[] = $profile->account_id;
            }
        }
    }
}

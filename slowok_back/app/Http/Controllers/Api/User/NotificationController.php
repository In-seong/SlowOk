<?php
namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('account_id', $request->user()->account_id)->latest()->get();
        return response()->json(['success' => true, 'data' => $notifications]);
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('account_id', $request->user()->account_id)->findOrFail($id);
        $notification->update(['is_read' => true]);
        return response()->json(['success' => true, 'data' => $notification]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        Notification::where('account_id', $request->user()->account_id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true, 'message' => '모든 알림을 읽음 처리했습니다.']);
    }
}

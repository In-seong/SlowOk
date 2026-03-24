<?php
namespace App\Http\Controllers\Api\Admin;

use App\Models\Subscription;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends BaseAdminController
{
    public function index(Request $request): JsonResponse
    {
        $instId = $this->getInstitutionId($request);

        $subscriptions = Subscription::with('account')
            ->when($instId, function ($q) use ($instId) {
                $q->whereHas('account', fn ($aq) => $aq->where('institution_id', $instId));
            })
            ->get();

        return response()->json(['success' => true, 'data' => $subscriptions]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'account_id' => 'required|exists:account,account_id',
            'plan_type' => 'required|string|max:50',
            'status' => 'nullable|in:ACTIVE,EXPIRED,CANCELLED',
            'started_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
        ]);

        $instId = $this->getInstitutionId($request);
        if ($instId) {
            Account::where('account_id', $request->account_id)
                ->where('institution_id', $instId)
                ->firstOrFail();
        }

        $subscription = Subscription::create($request->only(['account_id', 'plan_type', 'status', 'started_at', 'expires_at']));
        return response()->json(['success' => true, 'data' => $subscription], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $subscription = Subscription::with('account')->findOrFail($id);

        $instId = $this->getInstitutionId($request);
        if ($instId && $subscription->account?->institution_id !== $instId) {
            return response()->json(['success' => false, 'message' => '권한이 없습니다.'], 403);
        }

        return response()->json(['success' => true, 'data' => $subscription]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'plan_type' => 'sometimes|string|max:50',
            'status' => 'nullable|in:ACTIVE,EXPIRED,CANCELLED',
            'started_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
        ]);

        $subscription = Subscription::with('account')->findOrFail($id);

        $instId = $this->getInstitutionId($request);
        if ($instId && $subscription->account?->institution_id !== $instId) {
            return response()->json(['success' => false, 'message' => '권한이 없습니다.'], 403);
        }

        $subscription->update($request->only(['plan_type', 'status', 'started_at', 'expires_at']));
        return response()->json(['success' => true, 'data' => $subscription]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $subscription = Subscription::with('account')->findOrFail($id);

        $instId = $this->getInstitutionId($request);
        if ($instId && $subscription->account?->institution_id !== $instId) {
            return response()->json(['success' => false, 'message' => '권한이 없습니다.'], 403);
        }

        $subscription->delete();
        return response()->json(['success' => true, 'message' => '구독이 삭제되었습니다.']);
    }
}

<?php
namespace App\Http\Controllers\Api\Admin;

use App\Models\ScreeningResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScreeningResultController extends BaseAdminController
{
    public function index(Request $request): JsonResponse
    {
        $instId = $this->getInstitutionId($request);

        $results = ScreeningResult::with(['test', 'profile'])
            ->when($instId, function ($q) use ($instId) {
                $q->whereHas('profile', function ($pq) use ($instId) {
                    $pq->whereHas('account', fn ($aq) => $aq->where('institution_id', $instId));
                });
            })
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $results]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $instId = $this->getInstitutionId($request);

        $result = ScreeningResult::with('profile.account')->findOrFail($id);

        if ($instId && $result->profile?->account?->institution_id !== $instId) {
            return response()->json(['success' => false, 'message' => '권한이 없습니다.'], 403);
        }

        $result->delete();
        return response()->json(['success' => true, 'message' => '진단 결과가 삭제되었습니다.']);
    }
}

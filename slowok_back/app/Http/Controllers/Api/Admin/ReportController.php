<?php
namespace App\Http\Controllers\Api\Admin;

use App\Models\LearningReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends BaseAdminController
{
    public function index(Request $request): JsonResponse
    {
        $instId = $this->getInstitutionId($request);

        $reports = LearningReport::with('profile')
            ->when($instId, function ($q) use ($instId) {
                $q->whereHas('profile', function ($pq) use ($instId) {
                    $pq->whereHas('account', fn ($aq) => $aq->where('institution_id', $instId));
                });
            })
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $reports]);
    }
}

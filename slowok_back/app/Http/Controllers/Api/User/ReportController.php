<?php
namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\LearningReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profile = $request->attributes->get('active_profile');
        if (!$profile) {
            return response()->json(['success' => true, 'data' => []]);
        }
        $reports = LearningReport::where('profile_id', $profile->profile_id)->latest()->get();
        return response()->json(['success' => true, 'data' => $reports]);
    }
}

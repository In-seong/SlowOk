<?php
namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profile = $request->attributes->get('active_profile');
        if (!$profile) {
            return response()->json(['success' => true, 'data' => []]);
        }
        $assessments = Assessment::where('profile_id', $profile->profile_id)->with('category')->latest()->get();
        return response()->json(['success' => true, 'data' => $assessments]);
    }
}

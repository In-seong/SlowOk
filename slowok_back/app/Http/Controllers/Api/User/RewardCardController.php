<?php
namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RewardCardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profile = $request->attributes->get('active_profile');
        if (!$profile) {
            return response()->json(['success' => true, 'data' => []]);
        }
        $cards = $profile->rewardCards()->get();
        return response()->json(['success' => true, 'data' => $cards]);
    }
}

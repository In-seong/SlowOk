<?php
namespace App\Http\Controllers\Api\Admin;

use App\Models\RewardCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RewardCardController extends BaseAdminController
{
    public function index(Request $request): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        return response()->json(['success' => true, 'data' => RewardCard::forInstitution($instId)->where('is_active', true)->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'rarity' => 'nullable|string|max:50',
        ]);
        $card = RewardCard::create([
            ...$request->only(['name', 'description', 'image_url', 'rarity']),
            'institution_id' => $this->getInstitutionId($request),
        ]);
        return response()->json(['success' => true, 'data' => $card], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        return response()->json(['success' => true, 'data' => RewardCard::forInstitution($instId)->findOrFail($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'rarity' => 'nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
        ]);
        $instId = $this->getInstitutionId($request);
        $card = RewardCard::forInstitution($instId)->findOrFail($id);
        $card->update($request->only(['name', 'description', 'image_url', 'rarity', 'is_active']));
        return response()->json(['success' => true, 'data' => $card]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        RewardCard::forInstitution($instId)->findOrFail($id)->update(['is_active' => false]);
        return response()->json(['success' => true, 'message' => '보상 카드가 삭제되었습니다.']);
    }
}

<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\UserProfile;
use App\Services\EncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChildProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profile = $request->attributes->get('active_profile');

        if (!$profile || $profile->user_type !== 'PARENT') {
            return response()->json(['success' => false, 'message' => '학부모 프로필만 접근 가능합니다.'], 403);
        }

        $children = $profile->children()->get();

        return response()->json(['success' => true, 'data' => $children]);
    }

    public function store(Request $request, EncryptionService $encryptionService): JsonResponse
    {
        $profile = $request->attributes->get('active_profile');

        if (!$profile || $profile->user_type !== 'PARENT') {
            return response()->json(['success' => false, 'message' => '학부모 프로필만 자녀를 추가할 수 있습니다.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:100',
            'birth_date' => 'nullable|date',
            'phone' => 'nullable|string|max:20',
        ]);

        $child = UserProfile::create([
            'account_id' => $request->user()->account_id,
            'name' => $request->name,
            'phone' => $request->phone,
            'birth_date' => $request->birth_date,
            'user_type' => 'LEARNER',
            'parent_profile_id' => $profile->profile_id,
            'encrypted_name' => $encryptionService->encrypt($request->name),
            'encrypted_phone' => $request->phone ? $encryptionService->encrypt($request->phone) : null,
            'encrypted_birth_date' => $request->birth_date ? $encryptionService->encrypt($request->birth_date) : null,
            'is_encrypted' => true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $child,
            'message' => '자녀 프로필이 추가되었습니다.',
        ], 201);
    }

    public function update(Request $request, int $id, EncryptionService $encryptionService): JsonResponse
    {
        $profile = $request->attributes->get('active_profile');

        if (!$profile || $profile->user_type !== 'PARENT') {
            return response()->json(['success' => false, 'message' => '학부모 프로필만 접근 가능합니다.'], 403);
        }

        $child = UserProfile::where('profile_id', $id)
            ->where('parent_profile_id', $profile->profile_id)
            ->first();

        if (!$child) {
            return response()->json(['success' => false, 'message' => '해당 자녀 프로필을 찾을 수 없습니다.'], 404);
        }

        $request->validate([
            'name' => 'required|string|max:100',
            'birth_date' => 'nullable|date',
            'phone' => 'nullable|string|max:20',
        ]);

        $child->update([
            'name' => $request->name,
            'birth_date' => $request->birth_date,
            'phone' => $request->phone,
            'encrypted_name' => $encryptionService->encrypt($request->name),
            'encrypted_phone' => $request->phone ? $encryptionService->encrypt($request->phone) : null,
            'encrypted_birth_date' => $request->birth_date ? $encryptionService->encrypt($request->birth_date) : null,
            'is_encrypted' => true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $child->fresh(),
            'message' => '자녀 프로필이 수정되었습니다.',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $profile = $request->attributes->get('active_profile');

        if (!$profile || $profile->user_type !== 'PARENT') {
            return response()->json(['success' => false, 'message' => '학부모 프로필만 접근 가능합니다.'], 403);
        }

        $child = UserProfile::where('profile_id', $id)
            ->where('parent_profile_id', $profile->profile_id)
            ->first();

        if (!$child) {
            return response()->json(['success' => false, 'message' => '해당 자녀 프로필을 찾을 수 없습니다.'], 404);
        }

        $child->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => '자녀 프로필이 삭제되었습니다.',
        ]);
    }
}

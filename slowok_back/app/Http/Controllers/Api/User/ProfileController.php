<?php
namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Services\EncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function joinInstitution(Request $request): JsonResponse
    {
        $request->validate([
            'invite_code' => 'required|string|max:20',
        ]);

        $account = $request->user();

        if ($account->institution_id) {
            return response()->json([
                'success' => false,
                'message' => '이미 기관에 연결된 계정입니다.',
            ], 422);
        }

        $institution = Institution::where('invite_code', $request->invite_code)
            ->where('is_active', true)
            ->first();

        if (!$institution) {
            throw ValidationException::withMessages([
                'invite_code' => ['유효하지 않은 초대코드입니다.'],
            ]);
        }

        $account->update(['institution_id' => $institution->institution_id]);
        $account->load('institution');

        return response()->json([
            'success' => true,
            'data' => $account,
            'message' => "'{$institution->name}' 기관에 연결되었습니다.",
        ]);
    }

    public function profiles(Request $request): JsonResponse
    {
        $profiles = $request->user()->profiles()->get();
        return response()->json(['success' => true, 'data' => $profiles]);
    }

    public function show(Request $request): JsonResponse
    {
        $profile = $request->attributes->get('active_profile');
        return response()->json(['success' => true, 'data' => $profile]);
    }

    public function update(Request $request, EncryptionService $encryptionService): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'birth_date' => 'nullable|date',
        ]);

        $profile = $request->attributes->get('active_profile');
        $data = $request->only(['name', 'phone', 'email', 'birth_date']);

        $profile->update(array_merge($data, $encryptionService->encryptProfileData($data)));
        return response()->json(['success' => true, 'data' => $profile->fresh(), 'message' => '프로필이 업데이트되었습니다.']);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Institution;
use App\Models\UserProfile;
use App\Services\EncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required',
            'role' => 'nullable|string|in:USER,ADMIN',
        ]);

        $account = Account::where('username', $request->username)->first();

        if (!$account || !Hash::check($request->password, $account->password_hash)) {
            throw ValidationException::withMessages([
                'username' => ['아이디 또는 비밀번호가 올바르지 않습니다.'],
            ]);
        }

        // 관리자 앱에서 ADMIN role로 요청 시 MASTER/TEST도 허용
        if ($request->role === 'ADMIN' && !$account->isAdminLevel()) {
            throw ValidationException::withMessages([
                'username' => ['해당 계정은 이 서비스에 접근할 수 없습니다.'],
            ]);
        }

        if ($request->role === 'USER' && $account->role !== 'USER') {
            throw ValidationException::withMessages([
                'username' => ['해당 계정은 이 서비스에 접근할 수 없습니다.'],
            ]);
        }

        if (!$account->is_active) {
            throw ValidationException::withMessages([
                'username' => ['비활성화된 계정입니다.'],
            ]);
        }

        // 중복 로그인 허용 (여러 기기에서 동시 사용 가능)
        $token = $account->createToken('auth-token')->plainTextToken;
        $account->update(['last_login_at' => now()]);
        $account->load(['profile', 'profiles', 'institution']);

        return response()->json([
            'success' => true,
            'data' => [
                'account' => $account,
                'token' => $token,
            ],
            'message' => '로그인 성공',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => '로그아웃 성공',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $account = $request->user();
        $account->load(['profile', 'profiles', 'institution']);

        $data = $account->toArray();

        if ($account->isAdminLevel()) {
            if ($account->isMaster()) {
                $data['permissions'] = ['*'];
            } else {
                $data['permissions'] = $account->permissions()->pluck('permission_key')->toArray();
            }
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        $account = $request->user();

        if (!Hash::check($request->current_password, $account->password_hash)) {
            throw ValidationException::withMessages([
                'current_password' => ['현재 비밀번호가 올바르지 않습니다.'],
            ]);
        }

        $account->update(['password_hash' => Hash::make($request->new_password)]);

        return response()->json([
            'success' => true,
            'message' => '비밀번호가 변경되었습니다.',
        ]);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required',
        ]);

        $account = $request->user();

        if (!Hash::check($request->password, $account->password_hash)) {
            throw ValidationException::withMessages([
                'password' => ['비밀번호가 올바르지 않습니다.'],
            ]);
        }

        // 연관 프로필 개인정보 익명화
        $account->profiles()->update([
            'name' => '탈퇴회원',
            'phone' => null,
            'email' => null,
            'birth_date' => null,
            'encrypted_name' => null,
            'encrypted_phone' => null,
            'encrypted_email' => null,
            'encrypted_birth_date' => null,
            'is_encrypted' => false,
        ]);

        // 계정 비활성화 (soft delete)
        $account->update(['is_active' => false]);

        // 모든 토큰 삭제
        $account->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => '회원 탈퇴가 완료되었습니다.',
        ]);
    }

    public function register(Request $request, EncryptionService $encryptionService): JsonResponse
    {
        $request->validate([
            'username' => 'required|string|max:50|unique:account,username',
            'password' => 'required|min:8|confirmed',
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'user_type' => 'nullable|string|in:LEARNER,PARENT',
            'invite_code' => 'nullable|string|max:20',
        ]);

        $institutionId = null;
        if ($request->invite_code) {
            $institution = Institution::where('invite_code', $request->invite_code)
                ->where('is_active', true)
                ->first();

            if (!$institution) {
                throw ValidationException::withMessages([
                    'invite_code' => ['유효하지 않은 초대코드입니다.'],
                ]);
            }

            $institutionId = $institution->institution_id;
        }

        $account = Account::create([
            'username' => $request->username,
            'password_hash' => Hash::make($request->password),
            'role' => Account::ROLE_USER,
            'institution_id' => $institutionId,
            'is_active' => true,
        ]);

        $profileData = [
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
        ];

        UserProfile::create(array_merge([
            'account_id' => $account->account_id,
            'user_type' => $request->user_type ?? 'LEARNER',
        ], $profileData, $encryptionService->encryptProfileData($profileData)));

        $token = $account->createToken('auth-token')->plainTextToken;
        $account->load(['profile', 'profiles', 'institution']);

        return response()->json([
            'success' => true,
            'data' => [
                'account' => $account,
                'token' => $token,
            ],
            'message' => '회원가입 성공',
        ], 201);
    }
}

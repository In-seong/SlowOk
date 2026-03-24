<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Account;
use App\Models\UserProfile;
use App\Services\EncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminManagementController extends BaseAdminController
{
    public function index(): JsonResponse
    {
        $admins = Account::whereIn('role', [Account::ROLE_ADMIN, Account::ROLE_TEST])
            ->with(['profile', 'institution'])
            ->orderBy('account_id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $admins,
        ]);
    }

    public function store(Request $request, EncryptionService $encryptionService): JsonResponse
    {
        $request->validate([
            'username' => 'required|string|max:50|unique:account,username',
            'password' => 'required|min:8',
            'role' => 'required|in:ADMIN,TEST',
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'institution_id' => 'nullable|exists:institution,institution_id',
        ]);

        $account = Account::create([
            'username' => $request->username,
            'password_hash' => Hash::make($request->password),
            'role' => $request->role,
            'institution_id' => $request->institution_id,
            'is_active' => true,
        ]);

        $profileData = [
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
        ];

        UserProfile::create(array_merge([
            'account_id' => $account->account_id,
            'user_type' => 'ADMIN',
        ], $profileData, $encryptionService->encryptProfileData($profileData)));

        $account->load(['profile', 'institution']);

        return response()->json([
            'success' => true,
            'data' => $account,
            'message' => '관리자가 생성되었습니다.',
        ], 201);
    }

    public function update(Request $request, int $id, EncryptionService $encryptionService): JsonResponse
    {
        $account = Account::whereIn('role', [Account::ROLE_ADMIN, Account::ROLE_TEST])->findOrFail($id);

        $request->validate([
            'role' => 'sometimes|in:ADMIN,TEST',
            'is_active' => 'sometimes|boolean',
            'name' => 'sometimes|string|max:100',
            'phone' => 'sometimes|nullable|string|max:20',
            'email' => 'sometimes|nullable|email|max:100',
            'password' => 'sometimes|nullable|min:8',
            'institution_id' => 'sometimes|nullable|exists:institution,institution_id',
        ]);

        if ($request->has('role')) {
            $account->role = $request->role;
        }
        if ($request->has('is_active')) {
            $account->is_active = $request->is_active;
        }
        if ($request->filled('password')) {
            $account->password_hash = Hash::make($request->password);
        }
        if ($request->has('institution_id')) {
            $account->institution_id = $request->institution_id;
        }
        $account->save();

        if ($account->profile && ($request->has('name') || $request->has('phone') || $request->has('email'))) {
            $profileData = $request->only(['name', 'phone', 'email']);
            $account->profile->update(array_merge($profileData, $encryptionService->encryptProfileData($profileData)));
        }

        $account->load(['profile', 'institution']);

        return response()->json([
            'success' => true,
            'data' => $account,
            'message' => '관리자 정보가 수정되었습니다.',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $account = Account::whereIn('role', [Account::ROLE_ADMIN, Account::ROLE_TEST])->findOrFail($id);
        $account->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => '관리자가 비활성화되었습니다.',
        ]);
    }

    public function resetPassword(int $id): JsonResponse
    {
        $account = Account::whereIn('role', [Account::ROLE_ADMIN, Account::ROLE_TEST])->findOrFail($id);

        $tempPassword = Str::random(10);
        $account->update(['password_hash' => Hash::make($tempPassword)]);

        return response()->json([
            'success' => true,
            'data' => ['temp_password' => $tempPassword],
            'message' => '비밀번호가 초기화되었습니다.',
        ]);
    }
}

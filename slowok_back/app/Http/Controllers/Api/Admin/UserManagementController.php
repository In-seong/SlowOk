<?php
namespace App\Http\Controllers\Api\Admin;

use App\Models\Account;
use App\Models\UserProfile;
use App\Models\ContentAssignment;
use App\Models\ScreeningResult;
use App\Models\LearningProgress;
use App\Models\ChallengeAttempt;
use App\Models\Assessment;
use App\Services\EncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserManagementController extends BaseAdminController
{
    public function index(Request $request): JsonResponse
    {
        $instId = $this->getInstitutionId($request);

        $users = Account::where('role', 'USER')
            ->when($instId, fn ($q) => $q->where('institution_id', $instId))
            ->with(['profile'])
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $instId = $this->getInstitutionId($request);

        $query = Account::where('role', 'USER');
        if ($instId) {
            $query->where('institution_id', $instId);
        }
        $user = $query->with(['profile'])->findOrFail($id);

        $detail = $user->toArray();

        $profileIds = $user->profile ? [$user->profile->profile_id] : [];

        if (!empty($profileIds)) {
            $detail['screening_results'] = ScreeningResult::whereIn('profile_id', $profileIds)
                ->with('test')
                ->latest()
                ->get();

            $detail['learning_progress'] = LearningProgress::whereIn('profile_id', $profileIds)
                ->with('content')
                ->get();

            $detail['challenge_attempts'] = ChallengeAttempt::whereIn('profile_id', $profileIds)
                ->with('challenge')
                ->latest()
                ->get();

            $detail['assessments'] = Assessment::whereIn('profile_id', $profileIds)
                ->with('category')
                ->latest()
                ->get();

            $detail['assignments'] = ContentAssignment::whereIn('profile_id', $profileIds)
                ->with(['profile', 'assigner', 'screeningTest', 'learningContent', 'challenge'])
                ->latest()
                ->get()
                ->map(function ($a) {
                    $a->assignable_title = $a->assignable_title;
                    return $a;
                });
        }

        return response()->json(['success' => true, 'data' => $detail]);
    }

    public function store(Request $request, EncryptionService $encryptionService): JsonResponse
    {
        $request->validate([
            'username' => 'required|string|max:50|unique:account,username',
            'password' => 'required|string|min:6',
            'name' => 'required|string|max:100',
            // user_type은 항상 LEARNER 고정
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
        ]);

        $instId = $this->getInstitutionId($request);

        $account = Account::create([
            'username' => $request->username,
            'password_hash' => Hash::make($request->password),
            'role' => 'USER',
            'institution_id' => $instId,
            'is_active' => true,
        ]);

        $profileData = [
            'account_id' => $account->account_id,
            'name' => $request->name,
            'user_type' => 'LEARNER',
            'phone' => $request->phone,
            'email' => $request->email,
        ];

        // 암호화
        $profileData['encrypted_name'] = $encryptionService->encrypt($request->name);
        if ($request->phone) {
            $profileData['encrypted_phone'] = $encryptionService->encrypt($request->phone);
        }
        if ($request->email) {
            $profileData['encrypted_email'] = $encryptionService->encrypt($request->email);
        }
        $profileData['is_encrypted'] = true;

        UserProfile::create($profileData);

        $account->load('profile');

        return response()->json([
            'success' => true,
            'data' => $account,
            'message' => '사용자가 생성되었습니다.',
        ], 201);
    }

    public function update(Request $request, int $id, EncryptionService $encryptionService): JsonResponse
    {
        $instId = $this->getInstitutionId($request);

        $query = Account::where('role', 'USER');
        if ($instId) {
            $query->where('institution_id', $instId);
        }
        $account = $query->with('profile')->findOrFail($id);

        $request->validate([
            'is_active' => 'sometimes|boolean',
            'username' => 'sometimes|string|max:50|unique:account,username,' . $id . ',account_id',
            'password' => 'sometimes|string|min:4',
            'name' => 'sometimes|string|max:100',
            'phone' => 'sometimes|nullable|string|max:20',
            'email' => 'sometimes|nullable|email|max:100',
        ]);

        $accountData = [];
        if ($request->has('is_active')) {
            $accountData['is_active'] = $request->is_active;
        }
        if ($request->has('username')) {
            $accountData['username'] = $request->username;
        }
        if ($request->has('password')) {
            $accountData['password_hash'] = Hash::make($request->password);
        }
        if (!empty($accountData)) {
            $account->update($accountData);
        }

        if ($account->profile && ($request->has('name') || $request->has('phone') || $request->has('email'))) {
            $profileData = $request->only(['name', 'phone', 'email']);
            $encryptedData = [];

            if (isset($profileData['name'])) {
                $encryptedData['encrypted_name'] = $encryptionService->encrypt($profileData['name']);
            }
            if (array_key_exists('phone', $profileData)) {
                $encryptedData['encrypted_phone'] = $profileData['phone']
                    ? $encryptionService->encrypt($profileData['phone'])
                    : null;
            }
            if (array_key_exists('email', $profileData)) {
                $encryptedData['encrypted_email'] = $profileData['email']
                    ? $encryptionService->encrypt($profileData['email'])
                    : null;
            }
            $encryptedData['is_encrypted'] = true;

            $account->profile->update(array_merge($profileData, $encryptedData));
        }

        $account->load('profile');
        return response()->json(['success' => true, 'data' => $account, 'message' => '사용자 정보가 업데이트되었습니다.']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $instId = $this->getInstitutionId($request);

        $query = Account::where('role', 'USER');
        if ($instId) {
            $query->where('institution_id', $instId);
        }
        $account = $query->findOrFail($id);
        $account->update(['is_active' => false]);
        return response()->json(['success' => true, 'message' => '사용자가 비활성화되었습니다.']);
    }

    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $instId = $this->getInstitutionId($request);

        $query = Account::where('role', 'USER');
        if ($instId) {
            $query->where('institution_id', $instId);
        }
        $account = $query->findOrFail($id);

        // 대문자 4자 + 숫자 4자 (예: ABCD1234)
        $letters = collect(range('A', 'Z'))->random(4)->implode('');
        $numbers = collect(range(0, 9))->random(4)->implode('');
        $tempPassword = $letters . $numbers;
        $account->update(['password_hash' => Hash::make($tempPassword)]);

        return response()->json([
            'success' => true,
            'data' => ['temp_password' => $tempPassword],
            'message' => '비밀번호가 초기화되었습니다.',
        ]);
    }
}

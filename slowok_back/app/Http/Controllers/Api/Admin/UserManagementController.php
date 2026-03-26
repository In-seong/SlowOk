<?php
namespace App\Http\Controllers\Api\Admin;

use App\Models\Account;
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
            ->with(['profile', 'profiles'])
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
        $user = $query->with('profile')->findOrFail($id);

        $profileId = $user->profile?->profile_id;
        $detail = $user->toArray();

        if ($profileId) {
            $detail['screening_results'] = ScreeningResult::where('profile_id', $profileId)
                ->with('test')
                ->latest()
                ->get();

            $detail['learning_progress'] = LearningProgress::where('profile_id', $profileId)
                ->with('content')
                ->get();

            $detail['challenge_attempts'] = ChallengeAttempt::where('profile_id', $profileId)
                ->with('challenge')
                ->latest()
                ->get();

            $detail['assessments'] = Assessment::where('profile_id', $profileId)
                ->with('category')
                ->latest()
                ->get();

            $detail['assignments'] = ContentAssignment::where('profile_id', $profileId)
                ->with(['assigner', 'screeningTest', 'learningContent', 'challenge'])
                ->latest()
                ->get();
        }

        return response()->json(['success' => true, 'data' => $detail]);
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
            'name' => 'sometimes|string|max:100',
            'phone' => 'sometimes|nullable|string|max:20',
            'email' => 'sometimes|nullable|email|max:100',
        ]);

        if ($request->has('is_active')) {
            $account->update(['is_active' => $request->is_active]);
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

        $tempPassword = Str::random(10);
        $account->update(['password_hash' => Hash::make($tempPassword)]);

        return response()->json([
            'success' => true,
            'data' => ['temp_password' => $tempPassword],
            'message' => '비밀번호가 초기화되었습니다.',
        ]);
    }
}

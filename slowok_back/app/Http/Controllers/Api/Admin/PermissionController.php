<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminPermission;
use App\Models\AdminPermissionGrant;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = AdminPermission::orderBy('category')
            ->orderBy('permission_id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $account = Account::whereIn('role', [Account::ROLE_ADMIN, Account::ROLE_TEST])->findOrFail($id);
        $permissionIds = $account->permissions()->pluck('admin_permission.permission_id')->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'account_id' => $account->account_id,
                'permission_ids' => $permissionIds,
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $account = Account::whereIn('role', [Account::ROLE_ADMIN, Account::ROLE_TEST])->findOrFail($id);

        $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:admin_permission,permission_id',
        ]);

        $granterId = $request->user()->account_id;

        // 기존 권한 삭제 → 새로 insert
        AdminPermissionGrant::where('account_id', $account->account_id)->delete();

        foreach ($request->permission_ids as $permissionId) {
            AdminPermissionGrant::create([
                'account_id' => $account->account_id,
                'permission_id' => $permissionId,
                'granted_by' => $granterId,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => '권한이 업데이트되었습니다.',
        ]);
    }
}

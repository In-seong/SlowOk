<?php
namespace App\Http\Controllers\Api\Admin;

use App\Models\Institution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstitutionController extends BaseAdminController
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Institution::where('is_active', true)->latest()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'type' => 'nullable|string|max:50',
            'contact_info' => 'nullable|array',
            'address' => 'nullable|string|max:300',
            'invite_code' => 'nullable|string|max:20|unique:institution,invite_code',
        ]);
        $institution = Institution::create($request->only(['name', 'type', 'contact_info', 'address', 'invite_code']));
        return response()->json(['success' => true, 'data' => $institution], 201);
    }

    public function show(int $id): JsonResponse
    {
        $institution = Institution::findOrFail($id);
        $institution->load('admins.profile');
        return response()->json(['success' => true, 'data' => $institution]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:200',
            'type' => 'nullable|string|max:50',
            'contact_info' => 'nullable|array',
            'address' => 'nullable|string|max:300',
            'invite_code' => 'nullable|string|max:20|unique:institution,invite_code,' . $id . ',institution_id',
            'is_active' => 'sometimes|boolean',
        ]);
        $institution = Institution::findOrFail($id);
        $institution->update($request->only(['name', 'type', 'contact_info', 'address', 'invite_code', 'is_active']));
        return response()->json(['success' => true, 'data' => $institution]);
    }

    public function destroy(int $id): JsonResponse
    {
        Institution::findOrFail($id)->update(['is_active' => false]);
        return response()->json(['success' => true, 'message' => '기관이 삭제되었습니다.']);
    }
}

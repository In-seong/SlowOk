<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\ContentAssignment;
use App\Models\ContentPackage;
use App\Models\ContentPackageItem;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentPackageController extends BaseAdminController
{
    public function index(Request $request): JsonResponse
    {
        $instId = $this->getInstitutionId($request);

        $packages = ContentPackage::forInstitution($instId)
            ->with('items')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $packages,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.assignable_type' => 'required|in:screening_test,learning_content,challenge',
            'items.*.assignable_id' => 'required|integer',
        ]);

        $package = ContentPackage::create([
            'name' => $request->name,
            'description' => $request->description,
            'created_by' => $request->user()->account_id,
            'institution_id' => $this->getInstitutionId($request),
        ]);

        foreach ($request->items as $index => $item) {
            ContentPackageItem::create([
                'package_id' => $package->package_id,
                'assignable_type' => $item['assignable_type'],
                'assignable_id' => $item['assignable_id'],
                'sort_order' => $index,
            ]);
        }

        $package->load('items');

        return response()->json([
            'success' => true,
            'data' => $package,
            'message' => '패키지가 생성되었습니다.',
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        $package = ContentPackage::forInstitution($instId)->with('items')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $package,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
            'items' => 'required|array|min:1',
            'items.*.assignable_type' => 'required|in:screening_test,learning_content,challenge',
            'items.*.assignable_id' => 'required|integer',
        ]);

        $instId = $this->getInstitutionId($request);
        $package = ContentPackage::forInstitution($instId)->findOrFail($id);

        $updateData = [
            'name' => $request->name,
            'description' => $request->description,
        ];
        if ($request->has('is_active')) {
            $updateData['is_active'] = $request->boolean('is_active');
        }
        $package->update($updateData);

        $package->items()->delete();

        foreach ($request->items as $index => $item) {
            ContentPackageItem::create([
                'package_id' => $package->package_id,
                'assignable_type' => $item['assignable_type'],
                'assignable_id' => $item['assignable_id'],
                'sort_order' => $index,
            ]);
        }

        $package->load('items');

        return response()->json([
            'success' => true,
            'data' => $package,
            'message' => '패키지가 수정되었습니다.',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        ContentPackage::forInstitution($instId)->findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => '패키지가 삭제되었습니다.',
        ]);
    }

    public function assignPackage(Request $request, int $id): JsonResponse
    {
        $instId = $this->getInstitutionId($request);
        $package = ContentPackage::forInstitution($instId)->with('items')->findOrFail($id);

        $request->validate([
            'profile_ids' => 'required|array|min:1',
            'profile_ids.*' => 'exists:user_profile,profile_id',
            'due_date' => 'nullable|date',
            'note' => 'nullable|string|max:500',
        ]);

        $created = 0;
        $assignerId = $request->user()->account_id;

        foreach ($request->profile_ids as $profileId) {
            foreach ($package->items as $item) {
                $exists = ContentAssignment::where('profile_id', $profileId)
                    ->where('assignable_type', $item->assignable_type)
                    ->where('assignable_id', $item->assignable_id)
                    ->exists();

                if (!$exists) {
                    ContentAssignment::create([
                        'profile_id' => $profileId,
                        'assignable_type' => $item->assignable_type,
                        'assignable_id' => $item->assignable_id,
                        'assigned_by' => $assignerId,
                        'assigned_at' => now(),
                        'due_date' => $request->due_date,
                        'note' => $request->note,
                    ]);
                    $created++;
                }
            }
        }

        // 패키지 할당 알림
        if ($created > 0) {
            app(NotificationService::class)->notifyProfiles(
                $request->profile_ids,
                'content_assigned',
                '콘텐츠 패키지 할당',
                "'{$package->name}' 패키지의 콘텐츠 {$created}건이 할당되었습니다.",
            );
        }

        return response()->json([
            'success' => true,
            'message' => "{$created}건의 콘텐츠가 할당되었습니다.",
        ], 201);
    }
}

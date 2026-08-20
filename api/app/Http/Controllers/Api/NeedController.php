<?php

namespace App\Http\Controllers\Api;

use App\Helpers\GroupPermissionHelper;
use App\Http\Controllers\Controller;
use App\Models\GroupMember;
use App\Models\Need;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NeedController extends Controller
{
    /**
     * Get paginated list of needs for the authenticated user and their groups.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->query('per_page', 9);
        $search = $request->query('search');
        $groupId = $request->query('group_id') ?: $request->query('groupId');
        $type = $request->query('type');
        $status = $request->query('status');

        $query = Need::query()
            ->with(['category:id,category_name', 'group:id,group_name', 'item:id,name'])
            ->where(function ($q) use ($user) {
                // User's own needs OR group needs where user is an active member
                $q->where('user_id', $user->id)
                  ->orWhereHas('group.members', function ($mq) use ($user) {
                      $mq->where('status', 'active')
                        ->where(function ($sq) use ($user) {
                            $sq->where('user_id', $user->id);
                            if ($user->email) $sq->orWhere('email', $user->email);
                            if ($user->phone_number) $sq->orWhere('phone_number', $user->phone_number);
                        });
                  });
            });

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('purpose', 'like', "%{$search}%")
                  ->orWhereHas('category', fn($cq) => $cq->where('category_name', 'like', "%{$search}%"))
                  ->orWhereHas('group', fn($gq) => $gq->where('group_name', 'like', "%{$search}%"));
            });
        }

        if (! empty($groupId) && $groupId !== 'all') {
            $query->where('group_id', $groupId);
        }

        if (! empty($type) && $type !== 'all') {
            $query->where('type', $type);
        }

        if (! empty($status) && $status !== 'all') {
            $statusVal = strtolower($status);
            if ($statusVal === 'closed' || $statusVal === 'close') {
                $query->whereIn('status', ['closed', 'close']);
            } else {
                $query->where('status', $statusVal);
            }
        }

        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Filter items for visibility restrictions (selected_individuals)
        $items = collect($paginated->items())->filter(function ($need) use ($user) {
            if ($need->type === 'group' && ($need->visibility_type ?? 'all_members') === 'selected_individuals') {
                if ((string) $need->user_id === (string) $user->id) return true;
                $allowed = is_array($need->visible_user_ids) ? $need->visible_user_ids : json_decode($need->visible_user_ids ?? '[]', true);
                if (is_array($allowed) && (in_array((string) $user->id, $allowed) || in_array((string) $user->email, $allowed))) {
                    return true;
                }
                return false;
            }
            return true;
        })->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Helper function to check group member permission for creating/modifying needs.
     */
    private function checkGroupPermission($user, string $groupId): bool
    {
        $member = GroupMember::where('group_id', $groupId)
            ->where('status', 'active')
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if ($user->email) $q->orWhere('email', $user->email);
            })->first();

        if (! $member) {
            return false;
        }

        // Owner and Admin roles always have full permission
        if (in_array(strtolower($member->role ?? 'member'), ['owner', 'admin'])) {
            return true;
        }

        // If member role, check custom permissions array if present
        $permissions = $member->permissions ?? [];
        if (is_array($permissions) && ! empty($permissions)) {
            return in_array('manage_needs', $permissions) || in_array('create_needs', $permissions);
        }

        return true; // Default active member access
    }

    /**
     * Store a new need or an array of needs (bulk creation).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Support array of needs (bulk creation) or single payload
        $rawNeeds = $request->has('needs') && is_array($request->input('needs'))
            ? $request->input('needs')
            : [$request->all()];

        $createdNeeds = [];

        foreach ($rawNeeds as $index => $itemData) {
            $name = trim($itemData['name'] ?? '');
            if (! $name) {
                return response()->json([
                    'message' => 'Need name is required.',
                    'errors' => ["needs.{$index}.name" => ['Need name is required']],
                ], 422);
            }

            $type = $itemData['type'] ?? 'personal';
            $groupId = $itemData['groupId'] ?? $itemData['group_id'] ?? null;

            if ($type === 'group') {
                if (! $groupId) {
                    return response()->json([
                        'message' => 'Group is required when type is group.',
                        'errors' => ["needs.{$index}.groupId" => ['Select a group']],
                    ], 422);
                }

                if (! GroupPermissionHelper::canCreate($user, $groupId)) {
                    return response()->json([
                        'message' => 'Forbidden: Viewer access level cannot create group needs.',
                    ], 403);
                }
            }

            $categoryId = $itemData['categoryId'] ?? $itemData['category_id'] ?? null;
            if (! $categoryId) {
                return response()->json([
                    'message' => 'Category is required.',
                    'errors' => ["needs.{$index}.categoryId" => ['Select a category']],
                ], 422);
            }

            $visibilityType = $itemData['visibilityType'] ?? $itemData['visibility_type'] ?? 'all_members';
            $visibleUserIds = $itemData['visibleUserIds'] ?? $itemData['visible_user_ids'] ?? null;

            $need = Need::create([
                'user_id' => $user->id,
                'name' => $name,
                'purpose' => ! empty($itemData['purpose']) ? trim($itemData['purpose']) : null,
                'item_id' => $itemData['itemId'] ?? $itemData['item_id'] ?? null,
                'type' => $type,
                'visibility_type' => $visibilityType,
                'visible_user_ids' => is_array($visibleUserIds) ? json_encode($visibleUserIds) : $visibleUserIds,
                'amount' => $itemData['amount'] ?? 0,
                'category_id' => $categoryId,
                'start_date' => $itemData['startDate'] ?? $itemData['start_date'] ?? null,
                'end_date' => $itemData['endDate'] ?? $itemData['end_date'] ?? null,
                'group_id' => $type === 'group' ? $groupId : null,
                'allow_member_contribution' => $itemData['allowMemberContribution'] ?? $itemData['allow_member_contribution'] ?? false,
                'status' => $itemData['status'] ?? 'pending',
            ]);

            $need->load(['category:id,category_name', 'group:id,group_name', 'item:id,name']);
            $createdNeeds[] = $need;
        }

        return response()->json([
            'success' => true,
            'data' => count($createdNeeds) === 1 ? $createdNeeds[0] : $createdNeeds,
        ], 201);
    }

    /**
     * Display a specific need.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $need = Need::query()
            ->with(['category:id,category_name', 'group:id,group_name', 'item:id,name'])
            ->where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('group.members', function ($mq) use ($user) {
                      $mq->where('status', 'active')
                        ->where(function ($sq) use ($user) {
                            $sq->where('user_id', $user->id);
                            if ($user->email) $sq->orWhere('email', $user->email);
                            if ($user->phone_number) $sq->orWhere('phone_number', $user->phone_number);
                        });
                  });
            })
            ->first();

        if (! $need) {
            return response()->json(['success' => false, 'message' => 'Need not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $need]);
    }

    /**
     * Update a need with group permission checks.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $need = Need::query()
            ->where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('group.members', function ($mq) use ($user) {
                      $mq->where('status', 'active')
                        ->where(function ($sq) use ($user) {
                            $sq->where('user_id', $user->id);
                            if ($user->email) $sq->orWhere('email', $user->email);
                            if ($user->phone_number) $sq->orWhere('phone_number', $user->phone_number);
                        });
                  });
            })
            ->first();

        if (! $need) {
            return response()->json(['success' => false, 'message' => 'Need not found.'], 404);
        }

        if (! GroupPermissionHelper::canUpdate($user, $need->group_id, $need->user_id)) {
            return response()->json([
                'message' => 'Forbidden: You do not have permission to modify this group need.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:1000'],
            'type' => ['nullable', 'string', 'in:personal,group'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'categoryId' => ['nullable', 'string', 'exists:categories,id'],
            'category_id' => ['nullable', 'string', 'exists:categories,id'],
            'itemId' => ['nullable', 'string', 'exists:items,id'],
            'item_id' => ['nullable', 'string', 'exists:items,id'],
            'startDate' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'groupId' => ['nullable', 'string', 'exists:groups,id'],
            'group_id' => ['nullable', 'string', 'exists:groups,id'],
            'allowMemberContribution' => ['nullable', 'boolean'],
            'allow_member_contribution' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:pending,completed,expired,close,closed'],
        ]);

        $updateData = [];
        if (isset($validated['name'])) $updateData['name'] = trim($validated['name']);
        if (array_key_exists('purpose', $validated)) $updateData['purpose'] = $validated['purpose'];
        if (isset($validated['type'])) $updateData['type'] = $validated['type'];
        if (isset($validated['amount'])) $updateData['amount'] = $validated['amount'];
        if (isset($validated['categoryId']) || isset($validated['category_id'])) {
            $updateData['category_id'] = $validated['categoryId'] ?? $validated['category_id'];
        }
        if (array_key_exists('itemId', $validated) || array_key_exists('item_id', $validated)) {
            $updateData['item_id'] = $validated['itemId'] ?? $validated['item_id'];
        }
        if (isset($validated['startDate']) || isset($validated['start_date'])) {
            $updateData['start_date'] = $validated['startDate'] ?? $validated['start_date'];
        }
        if (isset($validated['endDate']) || isset($validated['end_date'])) {
            $updateData['end_date'] = $validated['endDate'] ?? $validated['end_date'];
        }
        if (isset($validated['groupId']) || isset($validated['group_id'])) {
            $updateData['group_id'] = $validated['groupId'] ?? $validated['group_id'];
        }
        if (isset($validated['allowMemberContribution']) || isset($validated['allow_member_contribution'])) {
            $updateData['allow_member_contribution'] = $validated['allowMemberContribution'] ?? $validated['allow_member_contribution'];
        }
        if (isset($validated['status'])) $updateData['status'] = $validated['status'];

        $need->update($updateData);
        $need->load(['category:id,category_name', 'group:id,group_name', 'item:id,name']);

        return response()->json(['success' => true, 'data' => $need]);
    }

    /**
     * Delete a need with group permission checks.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $need = Need::query()
            ->where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('group.members', function ($mq) use ($user) {
                      $mq->where('status', 'active')
                        ->where(function ($sq) use ($user) {
                            $sq->where('user_id', $user->id);
                            if ($user->email) $sq->orWhere('email', $user->email);
                            if ($user->phone_number) $sq->orWhere('phone_number', $user->phone_number);
                        });
                  });
            })
            ->first();

        if (! $need) {
            return response()->json(['success' => false, 'message' => 'Need not found.'], 404);
        }

        if (! GroupPermissionHelper::canDelete($user, $need->group_id, $need->user_id)) {
            return response()->json([
                'message' => 'Forbidden: Only the need owner or members with Full Access can delete this group need.',
            ], 403);
        }

        $need->delete();

        return response()->json(['success' => true]);
    }
}

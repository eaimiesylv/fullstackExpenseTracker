<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupMemberController extends Controller
{
    /**
     * Search registered app users by name, email, or phone.
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $query = trim($request->query('query', ''));
        if (! $query || strlen($query) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $users = User::query()
            ->where('fullname', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->orWhere('phone_number', 'like', "%{$query}%")
            ->limit(10)
            ->get(['id', 'fullname', 'email', 'phone_number', 'profile_image']);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Display a listing of active group members.
     */
    public function index(Request $request, string $groupId): JsonResponse
    {
        $user = $request->user();

        if ($user && ($user->email || $user->phone_number)) {
            GroupMember::where('group_id', $groupId)
                ->whereNull('user_id')
                ->where(function ($q) use ($user) {
                    if ($user->email) $q->where('email', $user->email);
                    if ($user->phone_number) $q->orWhere('phone_number', $user->phone_number);
                })
                ->update(['user_id' => $user->id]);
        }

        $group = Group::where('id', $groupId)
            ->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                  ->orWhereHas('members', function ($mq) use ($user) {
                      $mq->where('status', 'active')
                        ->where(function ($sq) use ($user) {
                            $sq->where('user_id', $user->id);
                            if ($user->email) $sq->orWhere('email', $user->email);
                            if ($user->phone_number) $sq->orWhere('phone_number', $user->phone_number);
                        });
                  });
            })
            ->first();

        if (! $group) {
            return response()->json(['success' => false, 'message' => 'Group not found or access denied.'], 404);
        }

        $members = GroupMember::where('group_id', $groupId)
            ->where('status', 'active')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $members,
        ]);
    }

    /**
     * Add a member to a group (STRICTLY original creator/owner of group only).
     */
    public function store(Request $request, string $groupId): JsonResponse
    {
        $group = Group::find($groupId);

        if (! $group) {
            return response()->json(['success' => false, 'message' => 'Group not found.'], 404);
        }

        $user = $request->user();

        // Backend Protection: Check that logged in user is the original creator/owner of group
        if (! $user || (string) $group->owner_id !== (string) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Only the original creator/owner of the group can add members.',
            ], 403);
        }

        $validated = $request->validate([
            'user_id' => ['nullable', 'string', 'exists:users,id'],
            'fullname' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'permission' => ['nullable', 'string', 'in:viewer,contributor,editor,full_access'],
        ]);

        $appUser = null;
        if (! empty($validated['user_id'])) {
            $appUser = User::find($validated['user_id']);
        } elseif (! empty($validated['email'])) {
            $appUser = User::where('email', trim($validated['email']))->first();
        } elseif (! empty($validated['phone_number'])) {
            $appUser = User::where('phone_number', trim($validated['phone_number']))->first();
        }

        if ($appUser) {
            $existing = GroupMember::where('group_id', $group->id)
                ->where(function ($q) use ($appUser) {
                    $q->where('user_id', $appUser->id)
                      ->orWhere('email', $appUser->email);
                })
                ->first();

            if ($existing) {
                if ($existing->status === 'inactive') {
                    $existing->update([
                        'status' => 'active',
                        'permission' => $validated['permission'] ?? 'viewer',
                        'joined_at' => now(),
                    ]);

                    return response()->json([
                        'success' => true,
                        'data' => $existing,
                    ], 200);
                }

                return response()->json([
                    'message' => 'This user is already an active member of the group.',
                    'errors' => ['email' => ['User is already an active member of this group.']],
                ], 422);
            }

            $member = GroupMember::create([
                'group_id' => $group->id,
                'user_id' => $appUser->id,
                'fullname' => $appUser->fullname,
                'email' => $appUser->email,
                'phone_number' => $appUser->phone_number,
                'role' => 'member',
                'permission' => $validated['permission'] ?? 'viewer',
                'status' => 'active',
                'joined_at' => now(),
            ]);
        } else {
            if (empty($validated['fullname']) && empty($validated['email'])) {
                return response()->json([
                    'message' => 'Please select a registered app user or enter member details.',
                    'errors' => ['fullname' => ['Select an app user or provide member details']],
                ], 422);
            }

            $inputEmail = isset($validated['email']) ? trim($validated['email']) : null;
            if ($inputEmail) {
                $existingByEmail = GroupMember::where('group_id', $group->id)
                    ->where('email', $inputEmail)
                    ->first();

                if ($existingByEmail) {
                    if ($existingByEmail->status === 'inactive') {
                        $existingByEmail->update([
                            'status' => 'active',
                            'permission' => $validated['permission'] ?? 'viewer',
                            'joined_at' => now(),
                        ]);

                        return response()->json([
                            'success' => true,
                            'data' => $existingByEmail,
                        ], 200);
                    }

                    return response()->json([
                        'message' => 'This email is already an active member of the group.',
                        'errors' => ['email' => ['Email is already an active member of this group.']],
                    ], 422);
                }
            }

            $member = GroupMember::create([
                'group_id' => $group->id,
                'user_id' => null,
                'fullname' => trim($validated['fullname'] ?? $validated['email']),
                'email' => $inputEmail,
                'phone_number' => isset($validated['phone_number']) ? trim($validated['phone_number']) : null,
                'role' => 'member',
                'permission' => $validated['permission'] ?? 'viewer',
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $member,
        ], 201);
    }

    /**
     * Update a group member's details or permission (STRICTLY original creator/owner only).
     */
    public function update(Request $request, string $groupId, string $memberId): JsonResponse
    {
        $group = Group::find($groupId);

        if (! $group) {
            return response()->json(['success' => false, 'message' => 'Group not found.'], 404);
        }

        $user = $request->user();
        if (! $user || (string) $group->owner_id !== (string) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Only the original creator/owner of the group can update member permissions.',
            ], 403);
        }

        $member = GroupMember::where('group_id', $groupId)
            ->where('id', $memberId)
            ->first();

        if (! $member) {
            return response()->json(['success' => false, 'message' => 'Member not found.'], 404);
        }

        $validated = $request->validate([
            'fullname' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'permission' => ['nullable', 'string', 'in:viewer,contributor,editor,full_access'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $updateData = [];
        if (isset($validated['fullname'])) $updateData['fullname'] = trim($validated['fullname']);
        if (isset($validated['email'])) $updateData['email'] = trim($validated['email']);
        if (isset($validated['phone_number'])) $updateData['phone_number'] = trim($validated['phone_number']);
        if (isset($validated['permission'])) $updateData['permission'] = $validated['permission'];
        if (isset($validated['status'])) $updateData['status'] = $validated['status'];

        $member->update($updateData);

        return response()->json([
            'success' => true,
            'data' => $member,
        ]);
    }

    /**
     * Remove a member from a group (STRICTLY original creator/owner only).
     */
    public function destroy(Request $request, string $groupId, string $memberId): JsonResponse
    {
        $group = Group::find($groupId);

        if (! $group) {
            return response()->json(['success' => false, 'message' => 'Group not found.'], 404);
        }

        $user = $request->user();
        if (! $user || (string) $group->owner_id !== (string) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Only the original creator/owner of the group can remove members.',
            ], 403);
        }

        $member = GroupMember::where('group_id', $groupId)
            ->where('id', $memberId)
            ->first();

        if (! $member) {
            return response()->json(['success' => false, 'message' => 'Member not found.'], 404);
        }

        // Soft remove member from group by updating status to 'inactive'
        $member->update(['status' => 'inactive']);

        return response()->json(['success' => true]);
    }
}

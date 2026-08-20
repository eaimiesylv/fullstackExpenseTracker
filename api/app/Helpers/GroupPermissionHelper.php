<?php

namespace App\Helpers;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

class GroupPermissionHelper
{
    /**
     * Get member info and permission record for a user in a group.
     */
    public static function getMemberPermission(?User $user, ?string $groupId): array
    {
        if (! $user || ! $groupId) {
            return [
                'is_owner' => false,
                'permission' => 'none',
                'can_view' => false,
                'can_create' => false,
                'can_update' => false,
                'can_delete' => false,
            ];
        }

        $group = Group::find($groupId);
        if (! $group) {
            return [
                'is_owner' => false,
                'permission' => 'none',
                'can_view' => false,
                'can_create' => false,
                'can_update' => false,
                'can_delete' => false,
            ];
        }

        // Group owner always has full_access
        if ((string) $group->owner_id === (string) $user->id) {
            return [
                'is_owner' => true,
                'permission' => 'full_access',
                'can_view' => true,
                'can_create' => true,
                'can_update' => true,
                'can_delete' => true,
            ];
        }

        $member = GroupMember::where('group_id', $groupId)
            ->where('status', 'active')
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if ($user->email) $q->orWhere('email', $user->email);
                if ($user->phone_number) $q->orWhere('phone_number', $user->phone_number);
            })
            ->first();

        if (! $member) {
            return [
                'is_owner' => false,
                'permission' => 'none',
                'can_view' => false,
                'can_create' => false,
                'can_update' => false,
                'can_delete' => false,
            ];
        }

        if ($member->role === 'owner') {
            return [
                'is_owner' => true,
                'permission' => 'full_access',
                'can_view' => true,
                'can_create' => true,
                'can_update' => true,
                'can_delete' => true,
            ];
        }

        $permission = strtolower($member->permission ?? 'viewer');

        return [
            'is_owner' => false,
            'permission' => $permission,
            'can_view' => true,
            'can_create' => in_array($permission, ['contributor', 'editor', 'full_access']),
            'can_update' => in_array($permission, ['editor', 'full_access']),
            'can_delete' => $permission === 'full_access',
        ];
    }

    /**
     * Check if user can create items in a group.
     */
    public static function canCreate(?User $user, ?string $groupId): bool
    {
        if (! $groupId) return true; // Personal item
        $perm = self::getMemberPermission($user, $groupId);
        return $perm['can_create'];
    }

    /**
     * Check if user can update a group item.
     */
    public static function canUpdate(?User $user, ?string $groupId, ?string $itemOwnerId): bool
    {
        if ($user && $itemOwnerId && (string) $user->id === (string) $itemOwnerId) {
            return true; // Item owner can update their own item
        }

        if (! $groupId) return false;
        $perm = self::getMemberPermission($user, $groupId);
        return $perm['can_update'];
    }

    /**
     * Check if user can delete a group item.
     */
    public static function canDelete(?User $user, ?string $groupId, ?string $itemOwnerId): bool
    {
        if ($user && $itemOwnerId && (string) $user->id === (string) $itemOwnerId) {
            return true; // Item owner can delete their own item
        }

        if (! $groupId) return false;
        $perm = self::getMemberPermission($user, $groupId);
        return $perm['can_delete'];
    }
}

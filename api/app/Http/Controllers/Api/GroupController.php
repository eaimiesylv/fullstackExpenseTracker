<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Group\StoreGroupRequest;
use App\Http\Requests\Group\UpdateGroupRequest;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    /**
     * Auto-link unlinked GroupMember records matching user's email or phone.
     */
    private function autoLinkUserMemberships($user): void
    {
        if (! $user) return;

        if ($user->email || $user->phone_number) {
            GroupMember::whereNull('user_id')
                ->where(function ($q) use ($user) {
                    if ($user->email) {
                        $q->where('email', $user->email);
                    }
                    if ($user->phone_number) {
                        $q->orWhere('phone_number', $user->phone_number);
                    }
                })
                ->update(['user_id' => $user->id]);
        }
    }

    /**
     * Return layman permission levels and descriptions.
     */
    public function getPermissions(Request $request): JsonResponse
    {
        $permissions = [
            [
                'key' => 'viewer',
                'name' => 'Viewer (Read Only)',
                'summary' => 'Read Only',
                'description' => 'Can view group content and details only. Default for all members.',
                'access_level' => 1,
            ],
            [
                'key' => 'contributor',
                'name' => 'Contributor',
                'summary' => 'Read, Write',
                'description' => 'Can view group content and add new items.',
                'access_level' => 2,
            ],
            [
                'key' => 'editor',
                'name' => 'Editor',
                'summary' => 'Read, Write, Update',
                'description' => 'Can view, add, and edit group content.',
                'access_level' => 3,
            ],
            [
                'key' => 'full_access',
                'name' => 'Full Access',
                'summary' => 'Read, Write, Update, Delete',
                'description' => 'Can view, add, edit, and delete items & member settings.',
                'access_level' => 4,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }

    public function store(StoreGroupRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $data['owner_id'] = $user->id;

        $group = Group::create($data);

        // Add creator as member with owner role & full_access permission
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'fullname' => $user->fullname,
            'email' => $user->email,
            'role' => 'owner',
            'permission' => 'full_access',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $group->load(['members' => function ($mq) {
            $mq->where('status', 'active');
        }, 'owner:id,fullname,email']);

        return response()->json([
            'success' => true,
            'data' => new GroupResource($group),
        ], 201);
    }

    public function list(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->autoLinkUserMemberships($user);

        $groups = Group::query()
            ->where('owner_id', $user->id)
            ->orWhereHas('members', function ($q) use ($user) {
                $q->where('status', 'active')
                  ->where(function ($sq) use ($user) {
                      $sq->where('user_id', $user->id);
                      if ($user->email) $sq->orWhere('email', $user->email);
                      if ($user->phone_number) $sq->orWhere('phone_number', $user->phone_number);
                  });
            })
            ->orderBy('group_name', 'asc')
            ->get(['id', 'group_name']);

        $formatted = $groups->map(fn ($g) => [
            'id' => $g->id,
            'group_name' => $g->group_name,
            'name' => $g->group_name,
        ]);

        return response()->json([
            'success' => true,
            'data' => $formatted,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->autoLinkUserMemberships($user);

        $perPage = (int) $request->query('per_page', 9);
        $search = $request->query('search');

        $query = Group::query()
            ->with(['members' => function ($mq) {
                $mq->where('status', 'active');
            }, 'owner:id,fullname,email'])
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
            });

        if ($search) {
            $query->where('group_name', 'like', "%{$search}%");
        }

        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => GroupResource::collection($paginated),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem() ?? 0,
                'to' => $paginated->lastItem() ?? 0,
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $this->autoLinkUserMemberships($user);

        $group = Group::query()
            ->with(['members' => function ($mq) {
                $mq->where('status', 'active');
            }, 'owner:id,fullname,email'])
            ->where('id', $id)
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
            return response()->json(['success' => false, 'message' => 'Group not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => new GroupResource($group)]);
    }

    public function update(UpdateGroupRequest $request, string $id): JsonResponse
    {
        $group = Group::query()
            ->where('id', $id)
            ->where('owner_id', $request->user()->id)
            ->first();

        if (! $group) {
            return response()->json(['success' => false, 'message' => 'Group not found.'], 404);
        }

        $group->update($request->validated());
        $group->load(['members' => function ($mq) {
            $mq->where('status', 'active');
        }, 'owner:id,fullname,email']);

        return response()->json(['success' => true, 'data' => new GroupResource($group)]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $group = Group::query()
            ->where('id', $id)
            ->where('owner_id', $request->user()->id)
            ->first();

        if (! $group) {
            return response()->json(['success' => false, 'message' => 'Group not found.'], 404);
        }

        // Safely disassociate group from budgets, expenses, needs, and bills
        \App\Models\Budget::where('group_id', $group->id)->update(['group_id' => null, 'scope' => 'personal']);
        \App\Models\Expense::where('group_id', $group->id)->update(['group_id' => null, 'expense_type' => 'personal']);
        \App\Models\Need::where('group_id', $group->id)->update(['group_id' => null, 'type' => 'personal']);
        \App\Models\Bill::where('group_id', $group->id)->update(['group_id' => null, 'scope' => 'personal']);

        $group->delete();

        return response()->json(['success' => true]);
    }
}

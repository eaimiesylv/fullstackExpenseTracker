<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Group\StoreGroupRequest;
use App\Http\Requests\Group\UpdateGroupRequest;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function store(StoreGroupRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['owner_id'] = $request->user()->id;

        $group = Group::create($data);

        return response()->json([
            'success' => true,
            'data' => new GroupResource($group),
        ], 201);
    }

    public function list(Request $request): JsonResponse
    {
        $groups = Group::query()
            ->where('owner_id', $request->user()->id)
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
        $groups = Group::query()
            ->where('owner_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => GroupResource::collection($groups),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {

        $group = Group::query()
            ->where('id', '=', $id, 'and')
            ->where('owner_id', '=', $request->user()->id, 'and')
            ->first();

        if (! $group) {
            return response()->json(['success' => false, 'message' => 'Group not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => new GroupResource($group)]);
    }

    public function update(UpdateGroupRequest $request, string $id): JsonResponse
    {
        $group = Group::query()
            ->where('id', '=', $id, 'and')
            ->where('owner_id', '=', $request->user()->id, 'and')
            ->first();

        if (! $group) {
            return response()->json(['success' => false, 'message' => 'Group not found.'], 404);
        }

        $group->update($request->validated());

        return response()->json(['success' => true, 'data' => new GroupResource($group)]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $group = Group::query()
            ->where('id', '=', $id, 'and')
            ->where('owner_id', '=', $request->user()->id, 'and')
            ->first();

        if (! $group) {
            return response()->json(['success' => false, 'message' => 'Group not found.'], 404);
        }

        Group::destroy($group->id);

        return response()->json(['success' => true]);
    }
}

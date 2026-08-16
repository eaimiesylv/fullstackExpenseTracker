<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Need;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NeedController extends Controller
{
    /**
     * Get list of needs for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $needs = Need::query()
            ->with(['category:id,category_name', 'group:id,group_name', 'item:id,name'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $needs,
        ]);
    }

    /**
     * Store a new need.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:personal,group'],
            'amount' => ['required', 'numeric', 'min:0'],
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

        $categoryId = $validated['categoryId'] ?? $validated['category_id'] ?? null;
        if (! $categoryId) {
            return response()->json([
                'message' => 'Category is required.',
                'errors' => ['categoryId' => ['Select a category']],
            ], 422);
        }

        $groupId = $validated['groupId'] ?? $validated['group_id'] ?? null;
        if ($validated['type'] === 'group' && ! $groupId) {
            return response()->json([
                'message' => 'Group is required when type is group.',
                'errors' => [
                    'groupId' => ['Select a group'],
                    'group_id' => ['Select a group'],
                ],
            ], 422);
        }

        $need = Need::create([
            'user_id' => $request->user()->id,
            'name' => trim($validated['name']),
            'item_id' => $validated['itemId'] ?? $validated['item_id'] ?? null,
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'category_id' => $categoryId,
            'start_date' => $validated['startDate'] ?? $validated['start_date'] ?? null,
            'end_date' => $validated['endDate'] ?? $validated['end_date'] ?? null,
            'group_id' => $validated['type'] === 'group' ? ($validated['groupId'] ?? $validated['group_id'] ?? null) : null,
            'allow_member_contribution' => $validated['allowMemberContribution'] ?? $validated['allow_member_contribution'] ?? false,
            'status' => $validated['status'] ?? 'pending',
        ]);

        $need->load(['category:id,category_name', 'group:id,group_name', 'item:id,name']);

        return response()->json([
            'success' => true,
            'data' => $need,
        ], 201);
    }

    /**
     * Display a specific need.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $need = Need::query()
            ->with(['category:id,category_name', 'group:id,group_name', 'item:id,name'])
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $need) {
            return response()->json(['success' => false, 'message' => 'Need not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $need]);
    }

    /**
     * Update a need.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $need = Need::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $need) {
            return response()->json(['success' => false, 'message' => 'Need not found.'], 404);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
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
     * Delete a need.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $need = Need::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $need) {
            return response()->json(['success' => false, 'message' => 'Need not found.'], 404);
        }

        $need->delete();

        return response()->json(['success' => true]);
    }
}

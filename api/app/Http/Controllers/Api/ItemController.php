<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    /**
     * Get items filtered by type available for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type');

        $query = Item::query()
            ->where(function ($q) use ($request) {
                $q->whereNull('user_id')
                  ->orWhere('user_id', $request->user()->id);
            });

        if (! empty($type)) {
            $query->where('type', $type);
        }

        $items = $query->orderBy('name', 'asc')->get(['id', 'name', 'type']);

        $formatted = $items->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->name,
            'type' => $item->type,
        ]);

        return response()->json([
            'success' => true,
            'data' => $formatted,
        ]);
    }

    /**
     * Store a new item with a specific type namespace.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
        ]);

        $item = Item::create([
            'user_id' => $request->user()->id,
            'name' => trim($validated['name']),
            'type' => $validated['type'] ?? 'general',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->type,
            ],
        ], 201);
    }
}

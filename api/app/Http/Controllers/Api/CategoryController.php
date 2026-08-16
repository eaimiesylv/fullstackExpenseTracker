<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Get categories: paginated (20 per page) by default, or all if requested.
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->has('all') && $request->boolean('all')) {
            return $this->all($request);
        }

        $categories = Category::query()
            ->where(function ($q) use ($request) {
                $q->whereNull('user_id')
                  ->orWhere('user_id', $request->user()->id);
            })
            ->orderBy('category_name', 'asc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Get all categories returning only id, category_name and mapped name.
     */
    public function all(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->where(function ($q) use ($request) {
                $q->whereNull('user_id')
                  ->orWhere('user_id', $request->user()->id);
            })
            ->orderBy('category_name', 'asc')
            ->get(['id', 'category_name']);

        $formatted = $categories->map(fn ($c) => [
            'id' => $c->id,
            'category_name' => $c->category_name,
            'name' => $c->category_name,
        ]);

        return response()->json([
            'success' => true,
            'data' => $formatted,
        ]);
    }

    /**
     * Create category.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_name' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'category_description' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'category_type' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
        ]);

        $name = $validated['category_name'] ?? $validated['name'] ?? null;
        if (! $name) {
            return response()->json([
                'message' => 'Category name is required',
                'errors' => ['category_name' => ['Category name is required']],
            ], 422);
        }

        $category = Category::create([
            'user_id' => $request->user()->id,
            'category_name' => trim($name),
            'category_description' => $validated['category_description'] ?? $validated['description'] ?? null,
            'category_type' => $validated['category_type'] ?? $validated['type'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $category->id,
                'category_name' => $category->category_name,
                'name' => $category->category_name,
                'category_description' => $category->category_description,
                'category_type' => $category->category_type,
            ],
        ], 201);
    }

    /**
     * Edit category.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json(['success' => false, 'message' => 'Category not found.'], 404);
        }

        $validated = $request->validate([
            'category_name' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'category_description' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'category_type' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
        ]);

        $name = $validated['category_name'] ?? $validated['name'] ?? $category->category_name;

        $category->update([
            'category_name' => trim($name),
            'category_description' => $validated['category_description'] ?? $validated['description'] ?? $category->category_description,
            'category_type' => $validated['category_type'] ?? $validated['type'] ?? $category->category_type,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $category->id,
                'category_name' => $category->category_name,
                'name' => $category->category_name,
                'category_description' => $category->category_description,
                'category_type' => $category->category_type,
            ],
        ]);
    }
}

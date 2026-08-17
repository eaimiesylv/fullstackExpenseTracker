<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\GroupMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    /**
     * Display a paginated listing of expenses with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $perPage = (int) $request->query('per_page', 9);
        $search = $request->query('search');
        $groupId = $request->query('group_id') ?: $request->query('groupId');
        $categoryId = $request->query('category_id') ?: $request->query('categoryId');
        $budgetId = $request->query('budget_id') ?: $request->query('budgetId');
        $scope = $request->query('scope') ?: $request->query('expense_type');
        $status = $request->query('status');

        $query = Expense::query()
            ->with([
                'category:id,category_name',
                'group:id,group_name',
                'budget:id,budget_name,amount',
                'user:id,fullname,email,profile_image',
            ])
            ->where(function ($q) use ($user) {
                // Personal expenses by user OR group expenses where user is member
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
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('category', fn($cq) => $cq->where('category_name', 'like', "%{$search}%"))
                  ->orWhereHas('group', fn($gq) => $gq->where('group_name', 'like', "%{$search}%"));
            });
        }

        if (! empty($groupId) && $groupId !== 'all') {
            $query->where('group_id', $groupId);
        }

        if (! empty($categoryId) && $categoryId !== 'all') {
            $query->where('category_id', $categoryId);
        }

        if (! empty($budgetId) && $budgetId !== 'all') {
            $query->where('budget_id', $budgetId);
        }

        if (! empty($scope) && $scope !== 'all') {
            $query->where('expense_type', $scope);
        }

        if (! empty($status) && $status !== 'all') {
            $query->where('status', strtolower($status));
        }

        $paginated = $query->orderBy('expense_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $items = collect($paginated->items())->map(function ($e) use ($user) {
            return array_merge($e->toArray(), [
                'name' => $e->title,
                'date' => $e->expense_date ? $e->expense_date->toDateString() : null,
                'is_owner' => $e->user_id === $user->id,
            ]);
        });

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
     * Store a new expense.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['nullable', 'date'],
            'expense_date' => ['nullable', 'date'],
            'categoryId' => ['nullable', 'string', 'exists:categories,id'],
            'category_id' => ['nullable', 'string', 'exists:categories,id'],
            'budgetId' => ['nullable', 'string', 'exists:budgets,id'],
            'budget_id' => ['nullable', 'string', 'exists:budgets,id'],
            'expenseType' => ['nullable', 'string', 'in:personal,group'],
            'expense_type' => ['nullable', 'string', 'in:personal,group'],
            'groupId' => ['nullable', 'string', 'exists:groups,id'],
            'group_id' => ['nullable', 'string', 'exists:groups,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', 'in:pending,approved,paid,rejected'],
        ]);

        $title = trim($validated['name'] ?? $validated['title'] ?? '');
        if (! $title) {
            return response()->json([
                'message' => 'Expense title is required.',
                'errors' => ['name' => ['Expense title is required']],
            ], 422);
        }

        $categoryId = $validated['categoryId'] ?? $validated['category_id'] ?? null;
        if (! $categoryId) {
            return response()->json([
                'message' => 'Category is required.',
                'errors' => ['categoryId' => ['Select a category']],
            ], 422);
        }

        $expenseType = $validated['expenseType'] ?? $validated['expense_type'] ?? 'personal';
        $groupId = $validated['groupId'] ?? $validated['group_id'] ?? null;

        if ($expenseType === 'group' && ! $groupId) {
            return response()->json([
                'message' => 'Group is required when expense type is group.',
                'errors' => ['groupId' => ['Select a group']],
            ], 422);
        }

        $budgetId = $validated['budgetId'] ?? $validated['budget_id'] ?? null;
        $expenseDate = $validated['date'] ?? $validated['expense_date'] ?? now()->toDateString();
        $status = strtolower($validated['status'] ?? 'paid');

        $groupMemberId = null;
        if ($expenseType === 'group' && $groupId) {
            $gm = GroupMember::where('group_id', $groupId)
                ->where('user_id', $user->id)
                ->first();
            if ($gm) $groupMemberId = $gm->id;
        }

        $expense = Expense::create([
            'user_id' => $user->id,
            'group_id' => $expenseType === 'group' ? $groupId : null,
            'group_member_id' => $groupMemberId,
            'budget_id' => $budgetId,
            'category_id' => $categoryId,
            'title' => $title,
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'currency' => 'NGN',
            'expense_type' => $expenseType,
            'expense_date' => $expenseDate,
            'status' => $status,
        ]);

        $expense->load(['category:id,category_name', 'group:id,group_name', 'budget:id,budget_name']);

        return response()->json([
            'success' => true,
            'data' => array_merge($expense->toArray(), [
                'name' => $expense->title,
                'date' => $expense->expense_date->toDateString(),
            ]),
        ], 201);
    }

    /**
     * Display a specific expense.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $expense = Expense::query()
            ->with([
                'category:id,category_name',
                'group:id,group_name',
                'budget:id,budget_name,amount',
                'user:id,fullname,email,profile_image',
            ])
            ->where('id', $id)
            ->first();

        if (! $expense) {
            return response()->json(['success' => false, 'message' => 'Expense not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => array_merge($expense->toArray(), [
                'name' => $expense->title,
                'date' => $expense->expense_date ? $expense->expense_date->toDateString() : null,
                'is_owner' => $expense->user_id === $user->id,
            ]),
        ]);
    }

    /**
     * Update an expense.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $expense = Expense::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $expense) {
            return response()->json(['success' => false, 'message' => 'Expense not found or unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'date' => ['nullable', 'date'],
            'expense_date' => ['nullable', 'date'],
            'categoryId' => ['nullable', 'string', 'exists:categories,id'],
            'category_id' => ['nullable', 'string', 'exists:categories,id'],
            'budgetId' => ['nullable', 'string', 'exists:budgets,id'],
            'budget_id' => ['nullable', 'string', 'exists:budgets,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', 'in:pending,approved,paid,rejected'],
        ]);

        $updateData = [];
        if (isset($validated['name']) || isset($validated['title'])) {
            $updateData['title'] = trim($validated['name'] ?? $validated['title']);
        }
        if (isset($validated['amount'])) $updateData['amount'] = $validated['amount'];
        if (isset($validated['date']) || isset($validated['expense_date'])) {
            $updateData['expense_date'] = $validated['date'] ?? $validated['expense_date'];
        }
        if (isset($validated['categoryId']) || isset($validated['category_id'])) {
            $updateData['category_id'] = $validated['categoryId'] ?? $validated['category_id'];
        }
        if (isset($validated['budgetId']) || isset($validated['budget_id'])) {
            $updateData['budget_id'] = $validated['budgetId'] ?? $validated['budget_id'];
        }
        if (isset($validated['description'])) $updateData['description'] = $validated['description'];
        if (isset($validated['status'])) $updateData['status'] = strtolower($validated['status']);

        $expense->update($updateData);
        $expense->load(['category:id,category_name', 'group:id,group_name', 'budget:id,budget_name']);

        return response()->json([
            'success' => true,
            'data' => array_merge($expense->toArray(), [
                'name' => $expense->title,
                'date' => $expense->expense_date->toDateString(),
            ]),
        ]);
    }

    /**
     * Delete an expense.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $expense = Expense::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $expense) {
            return response()->json(['success' => false, 'message' => 'Expense not found or unauthorized.'], 403);
        }

        $expense->delete();

        return response()->json(['success' => true]);
    }
}

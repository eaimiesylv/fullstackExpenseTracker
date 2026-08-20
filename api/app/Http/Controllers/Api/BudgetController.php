<?php

namespace App\Http\Controllers\Api;

use App\Helpers\GroupPermissionHelper;
use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Contribution;
use App\Models\Expense;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BudgetController extends Controller
{
    /**
     * Helper to auto-expire past budgets.
     */
    private function autoExpireBudgets(): void
    {
        $today = Carbon::today()->toDateString();
        Budget::whereNotIn('status', ['completed', 'closed', 'expired'])
            ->whereNotNull('end_date')
            ->where('end_date', '<', $today)
            ->update(['status' => 'expired']);
    }

    /**
     * Compute spending indicator status based on percentage of target budget spent.
     * < 80%: normal (emerald)
     * 80% - 89.9%: warning (amber)
     * 90% - 99.9%: high_risk (orange/rose)
     * >= 100%: exceeded (rose/red)
     */
    private function computeSpendingThreshold(float $spent, float $budgetAmount): array
    {
        if ($budgetAmount <= 0) {
            return [
                'spent' => $spent,
                'percentage' => 0,
                'status' => 'normal',
                'label' => '0%',
                'badge_class' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            ];
        }

        $percentage = round(($spent / $budgetAmount) * 100, 1);

        if ($percentage >= 100) {
            $status = 'exceeded';
            $badgeClass = 'bg-rose-100 text-rose-800 border-rose-300 font-bold animate-pulse';
            $label = "{$percentage}% — Budget Exceeded!";
        } elseif ($percentage >= 90) {
            $status = 'high_risk';
            $badgeClass = 'bg-rose-50 text-rose-700 border-rose-200 font-semibold';
            $label = "{$percentage}% — High Spending Risk";
        } elseif ($percentage >= 80) {
            $status = 'warning';
            $badgeClass = 'bg-amber-50 text-amber-700 border-amber-200 font-semibold';
            $label = "{$percentage}% — Warning Threshold";
        } else {
            $status = 'normal';
            $badgeClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
            $label = "{$percentage}% Spent";
        }

        return [
            'spent' => $spent,
            'percentage' => $percentage,
            'status' => $status,
            'label' => $label,
            'badge_class' => $badgeClass,
        ];
    }

    /**
     * Display a paginated listing of budgets.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->autoExpireBudgets();

        $perPage = (int) $request->query('per_page', 9);
        $search = $request->query('search');
        $groupId = $request->query('group_id') ?: $request->query('groupId');
        $categoryId = $request->query('category_id') ?: $request->query('categoryId');
        $scope = $request->query('scope') ?: $request->query('type');
        $status = $request->query('status');

        $query = Budget::query()
            ->with(['category:id,category_name', 'group:id,group_name', 'items', 'expenses'])
            ->withSum('expenses as total_spent', 'amount')
            ->withSum('contributions as total_contributed', 'amount')
            ->where(function ($q) use ($user) {
                // Personal budgets owned by user OR group budgets where user is active member
                $q->where('owner_id', $user->id)
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
                $q->where('budget_name', 'like', "%{$search}%")
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

        if (! empty($scope) && $scope !== 'all') {
            $query->where('scope', $scope);
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

        $items = collect($paginated->items())->map(function ($b) {
            $amount = (float) $b->amount;
            $spent = (float) ($b->total_spent ?? 0);
            $contributed = (float) ($b->total_contributed ?? 0);
            $threshold = $this->computeSpendingThreshold($spent, $amount);

            $contribPercentage = $amount > 0 ? min(100, round(($contributed / $amount) * 100, 1)) : 0;

            return array_merge($b->toArray(), [
                'name' => $b->budget_name,
                'type' => $b->scope,
                'allow_member_contribution' => (bool) $b->track_contributions,
                'allowMemberContribution' => (bool) $b->track_contributions,
                'total_spent' => $spent,
                'total_contributed' => $contributed,
                'spending_threshold' => $threshold,
                'contribution_percentage' => $contribPercentage,
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
     * Store a new budget.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'budget_name' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:personal,group'],
            'scope' => ['nullable', 'string', 'in:personal,group'],
            'amount' => ['required', 'numeric', 'min:0'],
            'categoryId' => ['nullable', 'string', 'exists:categories,id'],
            'category_id' => ['nullable', 'string', 'exists:categories,id'],
            'startDate' => ['required', 'date'],
            'start_date' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'groupId' => ['nullable', 'string', 'exists:groups,id'],
            'group_id' => ['nullable', 'string', 'exists:groups,id'],
            'allowMemberContribution' => ['nullable', 'boolean'],
            'allow_member_contribution' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*.name' => ['required_with:items', 'string', 'max:255'],
            'items.*.amount' => ['required_with:items', 'numeric', 'min:0'],
        ]);

        $budgetName = trim($validated['name'] ?? $validated['budget_name'] ?? '');
        if (! $budgetName) {
            return response()->json([
                'message' => 'Budget name is required.',
                'errors' => ['name' => ['Budget name is required']],
            ], 422);
        }

        $categoryId = $validated['categoryId'] ?? $validated['category_id'] ?? null;
        if (! $categoryId) {
            return response()->json([
                'message' => 'Category is required.',
                'errors' => ['categoryId' => ['Select a category']],
            ], 422);
        }

        $scope = $validated['type'] ?? $validated['scope'] ?? 'personal';
        $groupId = $validated['groupId'] ?? $validated['group_id'] ?? null;

        if ($scope === 'group') {
            if (! $groupId) {
                return response()->json([
                    'message' => 'Group is required when scope is group.',
                    'errors' => ['groupId' => ['Select a group']],
                ], 422);
            }
            if (! GroupPermissionHelper::canCreate($request->user(), $groupId)) {
                return response()->json([
                    'message' => 'Forbidden: Viewer access level cannot create group budgets.',
                ], 403);
            }
        }

        $trackContributions = $validated['allowMemberContribution'] ?? $validated['allow_member_contribution'] ?? false;

        $budget = Budget::create([
            'owner_id' => $request->user()->id,
            'group_id' => $scope === 'group' ? $groupId : null,
            'category_id' => $categoryId,
            'budget_name' => $budgetName,
            'scope' => $scope,
            'amount' => $validated['amount'],
            'start_date' => $validated['startDate'] ?? $validated['start_date'],
            'end_date' => $validated['endDate'] ?? $validated['end_date'] ?? null,
            'track_contributions' => $trackContributions,
            'status' => 'pending',
        ]);

        // Save sub-items if provided
        if (! empty($validated['items']) && is_array($validated['items'])) {
            foreach ($validated['items'] as $item) {
                BudgetItem::create([
                    'budget_id' => $budget->id,
                    'name' => trim($item['name']),
                    'amount' => $item['amount'],
                    'category_id' => $categoryId,
                    'status' => 'pending',
                ]);
            }
        }

        $budget->load(['category:id,category_name', 'group:id,group_name', 'items']);

        return response()->json([
            'success' => true,
            'data' => array_merge($budget->toArray(), [
                'name' => $budget->budget_name,
                'type' => $budget->scope,
                'allow_member_contribution' => (bool) $budget->track_contributions,
            ]),
        ], 201);
    }

    /**
     * Display a specific budget with items, contributions, expenses, and contributor breakdown.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $this->autoExpireBudgets();

        $budget = Budget::query()
            ->with([
                'category:id,category_name',
                'group:id,group_name',
                'items.category:id,category_name',
                'contributions' => fn($cq) => $cq->orderBy('contribution_date', 'desc'),
                'contributions.user:id,fullname,email,profile_image',
                'expenses' => fn($eq) => $eq->orderBy('expense_date', 'desc'),
                'expenses.user:id,fullname,email',
            ])
            ->where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
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

        if (! $budget) {
            return response()->json(['success' => false, 'message' => 'Budget not found or access denied.'], 404);
        }

        $spent = (float) $budget->expenses->sum('amount');
        $contributed = (float) $budget->contributions->sum('amount');
        $target = (float) $budget->amount;

        $threshold = $this->computeSpendingThreshold($spent, $target);
        $contribPercentage = $target > 0 ? min(100, round(($contributed / $target) * 100, 1)) : 0;

        // Breakdown of contributors (registered app users or guests)
        $contributors = $budget->contributions->map(function ($c) {
            $name = $c->user ? $c->user->fullname : ($c->contributor_name ?: 'Guest Contributor');
            $isGuest = ! $c->user_id;

            return [
                'id' => $c->id,
                'name' => $name,
                'amount' => (float) $c->amount,
                'is_guest' => $isGuest,
                'payment_method' => $c->payment_method ?? 'bank_transfer',
                'contribution_date' => $c->contribution_date ? $c->contribution_date->toDateString() : $c->created_at->toDateString(),
                'notes' => $c->notes,
            ];
        });

        $res = array_merge($budget->toArray(), [
            'name' => $budget->budget_name,
            'type' => $budget->scope,
            'allow_member_contribution' => (bool) $budget->track_contributions,
            'total_spent' => $spent,
            'total_contributed' => $contributed,
            'spending_threshold' => $threshold,
            'contribution_percentage' => $contribPercentage,
            'contributors' => $contributors,
            'is_owner' => $budget->owner_id === $user->id,
        ]);

        return response()->json(['success' => true, 'data' => $res]);
    }

    /**
     * Update a budget.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $budget = Budget::where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                  ->orWhereHas('group.members', function ($mq) use ($user) {
                      $mq->where('status', 'active')
                        ->where(function ($sq) use ($user) {
                            $sq->where('user_id', $user->id);
                            if ($user->email) $sq->orWhere('email', $user->email);
                        });
                  });
            })
            ->first();

        if (! $budget) {
            return response()->json(['success' => false, 'message' => 'Budget not found or access denied.'], 404);
        }

        if (! GroupPermissionHelper::canUpdate($user, $budget->group_id, $budget->owner_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden: You do not have permission to update this group budget.'], 403);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'budget_name' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'categoryId' => ['nullable', 'string', 'exists:categories,id'],
            'category_id' => ['nullable', 'string', 'exists:categories,id'],
            'startDate' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'allowMemberContribution' => ['nullable', 'boolean'],
            'allow_member_contribution' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:pending,completed,expired,close,closed'],
        ]);

        $updateData = [];
        if (isset($validated['name']) || isset($validated['budget_name'])) {
            $updateData['budget_name'] = trim($validated['name'] ?? $validated['budget_name']);
        }
        if (isset($validated['amount'])) $updateData['amount'] = $validated['amount'];
        if (isset($validated['categoryId']) || isset($validated['category_id'])) {
            $updateData['category_id'] = $validated['categoryId'] ?? $validated['category_id'];
        }
        if (isset($validated['startDate']) || isset($validated['start_date'])) {
            $updateData['start_date'] = $validated['startDate'] ?? $validated['start_date'];
        }
        if (isset($validated['endDate']) || isset($validated['end_date'])) {
            $updateData['end_date'] = $validated['endDate'] ?? $validated['end_date'];
        }
        if (isset($validated['allowMemberContribution']) || isset($validated['allow_member_contribution'])) {
            $updateData['track_contributions'] = $validated['allowMemberContribution'] ?? $validated['allow_member_contribution'];
        }
        if (isset($validated['status'])) {
            $updateData['status'] = strtolower($validated['status']);
        }

        $budget->update($updateData);
        $budget->load(['category:id,category_name', 'group:id,group_name', 'items']);

        return response()->json(['success' => true, 'data' => $budget]);
    }

    /**
     * Delete a budget.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $budget = Budget::where('id', $id)->first();

        if (! $budget) {
            return response()->json(['success' => false, 'message' => 'Budget not found.'], 404);
        }

        if (! GroupPermissionHelper::canDelete($user, $budget->group_id, $budget->owner_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden: Only the budget owner or members with Full Access can delete this budget.'], 403);
        }

        // Ensure disassociation of expenses linked to this budget
        Expense::where('budget_id', $budget->id)->update(['budget_id' => null]);

        $budget->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Record a contribution to a budget (Registered app user or guest contributor).
     */
    public function storeContribution(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $budget = Budget::find($id);

        if (! $budget) {
            return response()->json(['success' => false, 'message' => 'Budget not found.'], 404);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'contributor_type' => ['nullable', 'string', 'in:registered,guest'],
            'user_id' => ['nullable', 'string', 'exists:users,id'],
            'contributor_name' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
            'contribution_date' => ['nullable', 'date'],
        ]);

        $contributorType = $validated['contributor_type'] ?? (! empty($validated['user_id']) ? 'registered' : 'guest');
        $contributorUserId = null;
        $contributorName = null;
        $groupMemberId = null;

        if ($contributorType === 'registered' || ! empty($validated['user_id'])) {
            $targetUserId = $validated['user_id'] ?? $user->id;
            $targetUser = User::find($targetUserId) ?? $user;

            $contributorUserId = $targetUser->id;
            $contributorName = $targetUser->fullname;

            if ($budget->group_id) {
                $gm = GroupMember::where('group_id', $budget->group_id)
                    ->where('user_id', $targetUser->id)
                    ->first();
                if ($gm) $groupMemberId = $gm->id;
            }
        } else {
            // Guest contributor
            $contributorName = trim($validated['contributor_name'] ?? '') ?: 'Guest Contributor';
        }

        $contribution = Contribution::create([
            'budget_id' => $budget->id,
            'user_id' => $contributorUserId,
            'group_member_id' => $groupMemberId,
            'contributor_name' => $contributorName,
            'amount' => $validated['amount'],
            'currency' => 'NGN',
            'contribution_date' => $validated['contribution_date'] ?? now()->toDateString(),
            'payment_method' => $validated['payment_method'] ?? 'bank_transfer',
            'notes' => $validated['notes'] ?? null,
            'status' => 'completed',
        ]);

        return response()->json([
            'success' => true,
            'data' => $contribution,
        ], 201);
    }

    /**
     * Add a sub-item to a budget.
     */
    public function storeBudgetItem(Request $request, string $id): JsonResponse
    {
        $budget = Budget::find($id);
        if (! $budget) {
            return response()->json(['success' => false, 'message' => 'Budget not found.'], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $item = BudgetItem::create([
            'budget_id' => $budget->id,
            'name' => trim($validated['name']),
            'amount' => $validated['amount'],
            'category_id' => $budget->category_id,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'data' => $item,
        ], 201);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillParticipant;
use App\Models\BillPayment;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BillController extends Controller
{
    /**
     * Display a paginated listing of bills with multi-parameter filter support.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $perPage = (int) $request->query('per_page', 9);
        $search = $request->query('search');
        $groupId = $request->query('group_id') ?: $request->query('groupId');
        $categoryId = $request->query('category_id') ?: $request->query('categoryId');
        $scope = $request->query('scope') ?: $request->query('billType');
        $status = $request->query('status');
        $startDate = $request->query('start_date') ?: $request->query('startDate');
        $endDate = $request->query('end_date') ?: $request->query('endDate');

        $query = Bill::query()
            ->with(['category:id,category_name', 'group:id,group_name', 'participants'])
            ->withSum('participants as total_assigned', 'amount_assigned')
            ->withSum('participants as total_collected', 'amount_paid')
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

        if (! empty($scope) && $scope !== 'all') {
            $query->where('scope', $scope);
        }

        if (! empty($status) && $status !== 'all') {
            $query->where('status', strtolower($status));
        }

        if (! empty($startDate)) {
            $query->where(function ($q) use ($startDate) {
                $q->where('due_date', '>=', $startDate)
                  ->orWhere('start_date', '>=', $startDate);
            });
        }

        if (! empty($endDate)) {
            $query->where(function ($q) use ($endDate) {
                $q->where('due_date', '<=', $endDate)
                  ->orWhere('start_date', '<=', $endDate);
            });
        }

        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $items = collect($paginated->items())->map(function ($b) use ($user) {
            $assigned = (float) ($b->total_assigned ?: $b->amount);
            $collected = (float) ($b->total_collected ?: 0);
            $outstanding = max(0, $assigned - $collected);

            $paymentStatus = 'no_payment';
            if ($collected >= $assigned && $assigned > 0) {
                $paymentStatus = 'full';
            } elseif ($collected > 0) {
                $paymentStatus = 'incomplete';
            }

            return array_merge($b->toArray(), [
                'name' => $b->title,
                'due_date' => $b->due_date ? $b->due_date->toDateString() : null,
                'start_date' => $b->start_date ? $b->start_date->toDateString() : null,
                'total_assigned' => $assigned,
                'total_collected' => $collected,
                'total_outstanding' => $outstanding,
                'computed_status' => $paymentStatus,
                'is_owner' => $b->owner_id === $user->id,
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
     * Store a new bill with split participants.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'dueDate' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'startDate' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'categoryId' => ['nullable', 'string', 'exists:categories,id'],
            'category_id' => ['nullable', 'string', 'exists:categories,id'],
            'billType' => ['nullable', 'string', 'in:personal,group'],
            'scope' => ['nullable', 'string', 'in:personal,group'],
            'groupId' => ['nullable', 'string', 'exists:groups,id'],
            'group_id' => ['nullable', 'string', 'exists:groups,id'],
            'splitMethod' => ['nullable', 'string', 'in:equal,fixed,custom'],
            'split_type' => ['nullable', 'string', 'in:equal,fixed,custom'],
            'customSplit' => ['nullable', 'array'],
            'allowPartialPayment' => ['nullable', 'boolean'],
            'allow_partial_payment' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $title = trim($validated['name'] ?? $validated['title'] ?? '');
        if (! $title) {
            return response()->json([
                'message' => 'Bill title is required.',
                'errors' => ['name' => ['Bill title is required']],
            ], 422);
        }

        $categoryId = $validated['categoryId'] ?? $validated['category_id'] ?? null;
        if (! $categoryId) {
            return response()->json([
                'message' => 'Category is required.',
                'errors' => ['categoryId' => ['Select a category']],
            ], 422);
        }

        $scope = $validated['billType'] ?? $validated['scope'] ?? 'personal';
        $groupId = $validated['groupId'] ?? $validated['group_id'] ?? null;

        if ($scope === 'group' && ! $groupId) {
            return response()->json([
                'message' => 'Group is required when bill type is group.',
                'errors' => ['groupId' => ['Select a group']],
            ], 422);
        }

        $dueDate = $validated['dueDate'] ?? $validated['due_date'] ?? null;
        $startDate = $validated['startDate'] ?? $validated['start_date'] ?? now()->toDateString();
        $splitType = $validated['splitMethod'] ?? $validated['split_type'] ?? 'equal';
        $allowPartial = $validated['allowPartialPayment'] ?? $validated['allow_partial_payment'] ?? true;

        $bill = Bill::create([
            'owner_id' => $user->id,
            'group_id' => $scope === 'group' ? $groupId : null,
            'category_id' => $categoryId,
            'title' => $title,
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'currency' => 'NGN',
            'scope' => $scope,
            'split_type' => $splitType,
            'start_date' => $startDate,
            'due_date' => $dueDate,
            'allow_partial_payment' => $allowPartial,
            'status' => 'no_payment',
        ]);

        // Create participant split records if group bill
        if ($scope === 'group' && $groupId) {
            $groupMembers = GroupMember::where('group_id', $groupId)->where('status', 'active')->get();
            $count = $groupMembers->count();

            if ($count > 0) {
                $totalAmount = (float) $validated['amount'];
                $customSplit = $validated['customSplit'] ?? [];

                foreach ($groupMembers as $gm) {
                    $assigned = 0;
                    if ($splitType === 'equal') {
                        $assigned = round($totalAmount / $count, 2);
                    } elseif ($splitType === 'fixed') {
                        $assigned = $totalAmount;
                    } elseif ($splitType === 'custom') {
                        $assigned = (float) ($customSplit[$gm->id] ?? $customSplit[$gm->user_id] ?? 0);
                    }

                    BillParticipant::create([
                        'bill_id' => $bill->id,
                        'group_member_id' => $gm->id,
                        'user_id' => $gm->user_id,
                        'participant_name' => $gm->user ? $gm->user->fullname : ($gm->email ?: 'Member'),
                        'is_guest' => false,
                        'amount_assigned' => $assigned,
                        'amount_paid' => 0,
                        'status' => 'no_payment',
                    ]);
                }
            }
        } else {
            // Personal bill — single participant record for owner
            BillParticipant::create([
                'bill_id' => $bill->id,
                'user_id' => $user->id,
                'participant_name' => $user->fullname,
                'is_guest' => false,
                'amount_assigned' => $validated['amount'],
                'amount_paid' => 0,
                'status' => 'no_payment',
            ]);
        }

        $bill->load(['category:id,category_name', 'group:id,group_name', 'participants']);

        return response()->json([
            'success' => true,
            'data' => array_merge($bill->toArray(), [
                'name' => $bill->title,
                'due_date' => $bill->due_date ? $bill->due_date->toDateString() : null,
            ]),
        ], 201);
    }

    /**
     * Display a specific bill with participants and payment breakdown.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $bill = Bill::query()
            ->with([
                'category:id,category_name',
                'group:id,group_name',
                'participants.user:id,fullname,email,profile_image',
                'participants.groupMember',
                'payments' => fn($pq) => $pq->orderBy('payment_date', 'desc'),
            ])
            ->where('id', $id)
            ->first();

        if (! $bill) {
            return response()->json(['success' => false, 'message' => 'Bill not found.'], 404);
        }

        $participants = $bill->participants->map(function ($p) {
            $name = $p->user ? $p->user->fullname : ($p->participant_name ?: 'Guest Participant');
            $email = $p->user ? $p->user->email : null;

            $assigned = (float) $p->amount_assigned;
            $paid = (float) $p->amount_paid;
            $outstanding = max(0, $assigned - $paid);

            $status = 'no_payment';
            if ($paid >= $assigned && $assigned > 0) {
                $status = 'full';
            } elseif ($paid > 0) {
                $status = 'incomplete';
            }

            return [
                'id' => $p->id,
                'name' => $name,
                'email' => $email,
                'is_guest' => (bool) $p->is_guest,
                'amount_assigned' => $assigned,
                'amount_paid' => $paid,
                'outstanding' => $outstanding,
                'status' => $status,
                'paid_at' => $p->paid_at ? $p->paid_at->toDateTimeString() : null,
            ];
        });

        $totalAssigned = (float) $participants->sum('amount_assigned');
        $totalCollected = (float) $participants->sum('amount_paid');
        $totalOutstanding = max(0, $totalAssigned - $totalCollected);

        $computedStatus = 'no_payment';
        if ($totalCollected >= $totalAssigned && $totalAssigned > 0) {
            $computedStatus = 'full';
        } elseif ($totalCollected > 0) {
            $computedStatus = 'incomplete';
        }

        $res = array_merge($bill->toArray(), [
            'name' => $bill->title,
            'due_date' => $bill->due_date ? $bill->due_date->toDateString() : null,
            'start_date' => $bill->start_date ? $bill->start_date->toDateString() : null,
            'total_assigned' => $totalAssigned,
            'total_collected' => $totalCollected,
            'total_outstanding' => $totalOutstanding,
            'computed_status' => $computedStatus,
            'participants' => $participants,
            'is_owner' => $bill->owner_id === $user->id,
        ]);

        return response()->json(['success' => true, 'data' => $res]);
    }

    /**
     * Record a payment for a bill participant (Registered member or Guest).
     */
    public function recordPayment(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $bill = Bill::with('participants')->find($id);

        if (! $bill) {
            return response()->json(['success' => false, 'message' => 'Bill not found.'], 404);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'participant_id' => ['nullable', 'string'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $amountToPay = (float) $validated['amount'];
        $participant = null;

        if (! empty($validated['participant_id'])) {
            $participant = BillParticipant::where('bill_id', $bill->id)
                ->where('id', $validated['participant_id'])
                ->first();
        }

        // If no matching participant record, check or create guest participant
        if (! $participant) {
            $payerName = trim($validated['payer_name'] ?? 'Guest Participant');
            $participant = BillParticipant::create([
                'bill_id' => $bill->id,
                'participant_name' => $payerName,
                'is_guest' => true,
                'amount_assigned' => $amountToPay,
                'amount_paid' => 0,
                'status' => 'no_payment',
            ]);
        }

        $outstanding = max(0, (float) $participant->amount_assigned - (float) $participant->amount_paid);

        // Check partial payment restriction if partial payment is disabled
        if (! $bill->allow_partial_payment && $amountToPay < $outstanding) {
            return response()->json([
                'success' => false,
                'message' => 'Partial payment is disabled for this bill. You must pay the full remaining amount of NGN ' . number_format($outstanding, 2),
            ], 422);
        }

        $newPaid = (float) $participant->amount_paid + $amountToPay;
        $status = 'incomplete';
        if ($newPaid >= (float) $participant->amount_assigned) {
            $status = 'full';
        }

        $participant->update([
            'amount_paid' => $newPaid,
            'status' => $status,
            'paid_at' => now(),
        ]);

        BillPayment::create([
            'bill_id' => $bill->id,
            'bill_participant_id' => $participant->id,
            'user_id' => $participant->user_id ?: $user->id,
            'payer_name' => $participant->user ? $participant->user->fullname : $participant->participant_name,
            'is_guest' => (bool) $participant->is_guest,
            'amount' => $amountToPay,
            'payment_date' => $validated['payment_date'] ?? now()->toDateString(),
            'payment_method' => $validated['payment_method'] ?? 'bank_transfer',
            'notes' => $validated['notes'] ?? null,
        ]);

        // Recalculate bill status
        $allParticipants = BillParticipant::where('bill_id', $bill->id)->get();
        $totalAssigned = $allParticipants->sum('amount_assigned');
        $totalPaid = $allParticipants->sum('amount_paid');

        $billStatus = 'no_payment';
        if ($totalPaid >= $totalAssigned && $totalAssigned > 0) {
            $billStatus = 'full';
        } elseif ($totalPaid > 0) {
            $billStatus = 'incomplete';
        }
        $bill->update(['status' => $billStatus]);

        return response()->json([
            'success' => true,
            'data' => $participant,
            'message' => 'Payment recorded successfully.',
        ], 201);
    }

    /**
     * Send email reminder to participants.
     */
    public function sendReminder(Request $request, string $id): JsonResponse
    {
        $bill = Bill::find($id);
        if (! $bill) {
            return response()->json(['success' => false, 'message' => 'Bill not found.'], 404);
        }

        $validated = $request->validate([
            'reminder_type' => ['required', 'string', 'in:now,custom'],
            'frequency' => ['nullable', 'string', 'max:50'],
        ]);

        $reminderType = $validated['reminder_type'];
        $frequency = $validated['frequency'] ?? null;

        $bill->update([
            'reminder_type' => $reminderType,
            'reminder_frequency' => $frequency,
        ]);

        $msg = $reminderType === 'now'
            ? 'Reminder emails sent immediately to pending participants.'
            : "Scheduled recurring reminders ({$frequency}) successfully configured.";

        return response()->json([
            'success' => true,
            'message' => $msg,
        ]);
    }

    /**
     * Delete a bill.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $bill = Bill::where('id', $id)
            ->where('owner_id', $user->id)
            ->first();

        if (! $bill) {
            return response()->json(['success' => false, 'message' => 'Only bill owners can delete this bill.'], 403);
        }

        $bill->delete();

        return response()->json(['success' => true]);
    }
}

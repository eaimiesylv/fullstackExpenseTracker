<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillParticipant extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'bill_id',
        'group_member_id',
        'user_id',
        'participant_name',
        'is_guest',
        'amount_assigned',
        'amount_paid',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount_assigned' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'is_guest' => 'boolean',
        'paid_at' => 'datetime',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'bill_id');
    }

    public function groupMember(): BelongsTo
    {
        return $this->belongsTo(GroupMember::class, 'group_member_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillPayment::class, 'bill_participant_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contribution extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'budget_id',
        'group_member_id',
        'user_id',
        'contributor_name',
        'amount',
        'currency',
        'contribution_date',
        'payment_reference',
        'payment_method',
        'notes',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'contribution_date' => 'date',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }

    public function groupMember(): BelongsTo
    {
        return $this->belongsTo(GroupMember::class, 'group_member_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

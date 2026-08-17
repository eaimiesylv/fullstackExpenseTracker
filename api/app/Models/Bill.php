<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'owner_id',
        'group_id',
        'category_id',
        'title',
        'description',
        'amount',
        'currency',
        'scope',
        'split_type',
        'start_date',
        'due_date',
        'allow_partial_payment',
        'reminder_type',
        'reminder_frequency',
        'reminder_start_date',
        'reminder_interval_days',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'allow_partial_payment' => 'boolean',
        'start_date' => 'date',
        'due_date' => 'date',
        'reminder_start_date' => 'date',
        'reminder_interval_days' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(BillParticipant::class, 'bill_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillPayment::class, 'bill_id');
    }
}

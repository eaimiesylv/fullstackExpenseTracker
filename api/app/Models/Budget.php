<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'owner_id',
        'group_id',
        'category_id',
        'budget_name',
        'description',
        'scope',
        'purpose_type',
        'amount',
        'currency',
        'start_date',
        'end_date',
        'is_recurring',
        'recurring_frequency',
        'allow_member_submission',
        'require_approval',
        'track_contributions',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_recurring' => 'boolean',
        'allow_member_submission' => 'boolean',
        'require_approval' => 'boolean',
        'track_contributions' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
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

    public function items(): HasMany
    {
        return $this->hasMany(BudgetItem::class, 'budget_id');
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class, 'budget_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'budget_id');
    }
}

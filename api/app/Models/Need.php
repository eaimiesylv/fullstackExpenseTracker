<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Need extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'needs';

    protected $fillable = [
        'user_id',
        'item_id',
        'name',
        'purpose',
        'type',
        'amount',
        'category_id',
        'start_date',
        'end_date',
        'group_id',
        'allow_member_contribution',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'allow_member_contribution' => 'boolean',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}

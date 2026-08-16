<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'categories';

    protected $fillable = [
        'user_id',
        'category_name',
        'category_description',
        'category_type',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

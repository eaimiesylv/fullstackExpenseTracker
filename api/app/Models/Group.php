<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Group extends Model
{
    use HasFactory; use HasUlids;

    protected $table = 'groups';

    protected $fillable = [
        'owner_id',
        'group_name',
        'description',
        'status',
    ];
}

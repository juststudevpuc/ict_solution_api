<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = [
        'module_name',
        'key',
        'admin',
        'staff',
        'user',
    ];

    protected $casts = [
        'admin' => 'boolean',
        'staff' => 'boolean',
        'user' => 'boolean',
    ];
}

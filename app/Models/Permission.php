<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model; // <-- MUST BE THIS, NOT Illuminate\Database\Eloquent\Model

class Permission extends Model
{
    // 1. Point to the exact MongoDB collection
    protected $collection = 'permissions';

    // 2. Tell Laravel the primary key is _id, not id
    protected $primaryKey = '_id';
    protected $keyType = 'string';

    // 3. Allow these fields to be updated
    protected $fillable = [
        'module_name',
        'key',
        'staff',
        'user'
    ];
}

<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Category extends Model
{
    //
    protected $connection = "mongodb";
    protected $table = "categories";
    protected $fillable = [
        "name",
        "description",
        "status"
    ];
    public function product()
    {
        return $this->hasMany(Product::class, 'category_id', 'id');
    }
}

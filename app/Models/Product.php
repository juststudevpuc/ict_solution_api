<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;


class Product extends Model
{
    //
    protected $connection = "mongodb";
    protected $table = "products";
    protected $fillable = [
        "name",
        "description",
        "price",
        "image",
        "image_url",
        "image_public_id",
        "status",
        "category_id",
        "stock_qty"
    ];
    public function category()
    {
        // A product belongs to a category
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }
    public function inventories()
    {
        return $this->hasMany(Inventory::class, 'product_id', '_id');
    }
}

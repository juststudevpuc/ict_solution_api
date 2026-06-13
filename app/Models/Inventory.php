<?php

namespace App\Models;

use MongoDB\laravel\Eloquent\Model;

class Inventory extends Model
{
    //
    protected $connection = "mongodb";
    protected $table = "inventories";
    protected $fillable = [
        "product_id",
        "type",
        "qty",
        "stock_left",
        "reference_id", // ddd order id
        "remark",
    ];

    public function product(){
        return $this->belongsTo(Product::class, 'product_id','_id');
    }
    public function order() {
        return $this->belongsTo(Order::class, 'order_id' , '_id');
    }
}

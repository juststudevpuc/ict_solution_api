<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Order extends Model
{
    //
    protected $connection = "mongodb";
    protected $table = "orders";
    protected $fillable = [
        "order_no",
        'user_id',
        'customer_name',
        "total_amount",
        "total_paid",
        "remark",
        "payment_method",
        'duration_days', // 🔥 ADD THIS
        'approved_at',   // 🔥 ADD THIS
        'deadline_at'    // 🔥 ADD THIS
    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
        // Note: Change '_id' to 'id' if you are using standard MySQL instead of MongoDB
    }
    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, "order_id", "_id");
    }
    public function inventory() {
        return $this->hasMany(Inventory::class, 'order_id', '_id');
    }


}

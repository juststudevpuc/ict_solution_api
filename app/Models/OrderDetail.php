<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDetail extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'price',
        'qty',
        'discount',
        'total',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'qty' => 'integer',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

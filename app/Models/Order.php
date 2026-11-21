<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_no',
        'user_id',
        'customer_name',
        'phone',
        'address',
        'total_amount',
        'total_paid',
        'remark',
        'payment_method',
        'status',
        'duration_days',
        'approved_at',
        'deadline_at',
        'payment_slip',
        'payway_tran_id',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'duration_days' => 'integer',
        'approved_at' => 'datetime',
        'deadline_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function inventory(): HasMany
    {
        return $this->inventories();
    }
}

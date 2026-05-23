<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; // IMPORTANT!
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderAlert implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $order;
    /**
     * Create a new event instance.
     */
    public function __construct($order)
    {
        //
        $this->order = $order;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        // return [
        //     new PrivateChannel('channel-name'),
        // ];
        return [
            new Channel('admin-notifications'),
        ];
    }
    // CORRECT: Use $this->order!
    public function broadcastWith(): array
    {
        return [
            'order_no' => $this->order->order_no,
            'customer_name' => $this->order->customer_name,
            'total_amount' => $this->order->total_amount,
        ];
    }
}

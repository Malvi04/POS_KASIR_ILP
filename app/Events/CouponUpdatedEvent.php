<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CouponUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $couponData;

    public function __construct(array $couponData = [])
    {
        $this->couponData = $couponData;
    }

    public function broadcastOn(): array
    {
        $channels = [
            new Channel('coupons'),
            new Channel('inventory'),
        ];

        if (!empty($this->couponData['location_id'])) {
            $channels[] = new Channel('branch.' . $this->couponData['location_id']);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'coupon.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'coupon' => $this->couponData,
            'timestamp' => now()->toISOString(),
        ];
    }
}

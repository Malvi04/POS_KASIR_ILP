<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $stockData;

    public function __construct(array $stockData)
    {
        $this->stockData = $stockData;
    }

    public function broadcastOn(): array
    {
        $channels = [new Channel('inventory')];
        if (!empty($this->stockData['location_id'])) {
            $channels[] = new Channel('branch.' . $this->stockData['location_id']);
        }
        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'stock.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'stock' => $this->stockData,
        ];
    }
}

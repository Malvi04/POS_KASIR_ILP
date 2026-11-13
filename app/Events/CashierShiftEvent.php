<?php

namespace App\Events;

use App\Models\CashierShift;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CashierShiftEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $shift;
    public string $action;

    public function __construct(CashierShift|array $shift, string $action = 'opened')
    {
        $this->action = $action;
        if ($shift instanceof CashierShift) {
            $this->shift = [
                'id' => $shift->id,
                'shift_code' => $shift->shift_code,
                'terminal_code' => $shift->terminal_code,
                'cashier_name' => $shift->cashier_name,
                'cashier_nik' => $shift->cashier_nik,
                'status' => $shift->status,
                'opening_cash' => (float)($shift->opening_cash ?? 0),
                'opened_at' => $shift->opened_at ? $shift->opened_at->format('d/m/Y H:i') : null,
                'closed_at' => $shift->closed_at ? $shift->closed_at->format('d/m/Y H:i') : null,
            ];
        } else {
            $this->shift = $shift;
        }
    }

    public function broadcastOn(): array
    {
        return [new Channel('cashier-shifts')];
    }

    public function broadcastAs(): string
    {
        return 'shift.' . $this->action;
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'shift' => $this->shift,
        ];
    }
}

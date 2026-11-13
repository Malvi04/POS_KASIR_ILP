<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionCreatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $transaction;

    public function __construct(Transaction|array $transaction)
    {
        if ($transaction instanceof Transaction) {
            $this->transaction = [
                'id' => $transaction->id,
                'invoice_number' => $transaction->invoice_number,
                'total_amount' => (float)($transaction->grand_total ?? $transaction->total_amount ?? 0),
                'grand_total' => (float)($transaction->grand_total ?? 0),
                'payment_method' => $transaction->payment_method,
                'terminal_code' => $transaction->terminal_code,
                'cashier_nik' => $transaction->cashier_nik,
                'cashier_name' => $transaction->cashier_name,
                'location_id' => $transaction->location_id ?? $transaction->shift?->location_id,
                'status' => $transaction->status,
                'created_at' => $transaction->created_at ? $transaction->created_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i'),
            ];
        } else {
            $this->transaction = $transaction;
        }
    }

    public function broadcastOn(): array
    {
        $channels = [new Channel('pos-transactions')];
        if (!empty($this->transaction['location_id'])) {
            $channels[] = new Channel('branch.' . $this->transaction['location_id']);
        }
        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'transaction.created';
    }

    public function broadcastWith(): array
    {
        return [
            'transaction' => $this->transaction,
        ];
    }
}

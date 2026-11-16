<?php

namespace App\Events;

use App\Models\Attendance;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendanceRecordedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $attendance;
    public string $type; // 'MASUK' | 'PULANG'

    public function __construct(Attendance|array $attendance, string $type = 'MASUK')
    {
        $this->type = strtoupper($type);
        if ($attendance instanceof Attendance) {
            $this->attendance = [
                'id' => $attendance->id,
                'staffName' => $attendance->staff_name,
                'user_id' => $attendance->user_id,
                'terminal' => $attendance->terminal,
                'branch' => $attendance->branch,
                'date' => $attendance->date ? $attendance->date->toDateString() : date('Y-m-d'),
                'checkIn' => $attendance->check_in ?: '-',
                'checkOut' => $attendance->check_out ?: '-',
                'status' => $attendance->status,
                'notes' => $attendance->notes,
                'type' => $this->type,
            ];
        } else {
            $this->attendance = $attendance;
        }
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('attendance'),
            new Channel('cashier-shifts'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'attendance.recorded';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'attendance' => $this->attendance,
        ];
    }
}

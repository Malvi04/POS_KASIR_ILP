<?php

namespace App\Events;

use App\Models\SystemNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationBroadcastEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $notification;

    public function __construct(SystemNotification|array $notification)
    {
        if ($notification instanceof SystemNotification) {
            $this->notification = [
                'id' => $notification->id,
                'role' => $notification->role,
                'title' => $notification->title,
                'message' => $notification->message,
                'category' => $notification->category,
                'icon' => $notification->icon ?: 'notifications',
                'color' => $notification->color ?: 'blue',
                'link' => $notification->link,
                'reference_id' => $notification->reference_id,
                'is_read' => (bool)$notification->is_read,
                'time_ago' => 'Baru saja',
                'created_at' => now()->format('d/m/Y H:i'),
            ];
        } else {
            $this->notification = $notification;
        }
    }

    public function broadcastOn(): array
    {
        $role = strtolower($this->notification['role'] ?? 'all');
        $channels = [new Channel('notifications')];

        if ($role && $role !== 'all') {
            $channels[] = new Channel("notifications.{$role}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'notification' => $this->notification,
        ];
    }
}

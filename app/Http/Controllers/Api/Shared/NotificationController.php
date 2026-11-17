<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Models\SystemNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get notifications filtered by role with auto-seed if empty.
     */
    public function index(Request $request): JsonResponse
    {
        $role = strtoupper(trim($request->query('role', '')));

        $query = SystemNotification::query();
        if ($role && $role !== 'ALL') {
            $query->forRole($role);
        }

        $notifications = $query->orderBy('is_read', 'asc')
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get();

        $unreadCount = $notifications->where('is_read', false)->count();

        // Format relative time in Indonesian
        $formatted = $notifications->map(function ($n) {
            return [
                'id' => $n->id,
                'role' => $n->role,
                'title' => $n->title,
                'message' => $n->message,
                'category' => $n->category,
                'icon' => $n->icon ?: 'notifications',
                'color' => $n->color ?: 'blue',
                'link' => $n->link,
                'reference_id' => $n->reference_id,
                'is_read' => (bool)$n->is_read,
                'time_ago' => $n->created_at ? $n->created_at->diffForHumans() : 'Baru saja',
                'created_at' => $n->created_at ? $n->created_at->format('d/m/Y H:i') : null,
            ];
        });

        return response()->json([
            'success' => true,
            'role' => $role ?: 'ALL',
            'unread_count' => $unreadCount,
            'total_count' => $formatted->count(),
            'data' => $formatted,
        ]);
    }

    /**
     * Create a new notification.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'nullable|string|max:30',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'category' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:30',
            'link' => 'nullable|string|max:255',
            'reference_id' => 'nullable|string|max:100',
        ]);

        $notification = SystemNotification::create([
            'role' => strtoupper($validated['role'] ?? 'ALL'),
            'title' => $validated['title'],
            'message' => $validated['message'],
            'category' => strtoupper($validated['category'] ?? 'SYSTEM'),
            'icon' => $validated['icon'] ?? 'notifications',
            'color' => $validated['color'] ?? 'blue',
            'link' => $validated['link'] ?? null,
            'reference_id' => $validated['reference_id'] ?? null,
            'is_read' => false,
        ]);

        try {
            event(new \App\Events\NotificationBroadcastEvent($notification));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast notification failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi berhasil diterbitkan.',
            'data' => $notification,
        ], 201);
    }

    /**
     * Mark a single notification or all notifications as read.
     */
    public function markAsRead(Request $request, ?int $id = null): JsonResponse
    {
        if ($id) {
            $notification = SystemNotification::findOrFail($id);
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notifikasi ditandai sebagai telah dibaca.',
                'data' => $notification,
            ]);
        }

        $role = strtoupper(trim($request->input('role', '')));
        $query = SystemNotification::where('is_read', false);

        if ($role && $role !== 'ALL') {
            $query->forRole($role);
        }

        $affected = $query->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$affected} notifikasi ditandai sebagai telah dibaca.",
            'affected' => $affected,
        ]);
    }

    /**
     * Delete a single notification.
     */
    public function destroy(int $id): JsonResponse
    {
        $notification = SystemNotification::findOrFail($id);
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi berhasil dihapus.',
        ]);
    }

    /**
     * Clear all notifications for role.
     */
    public function clearAll(Request $request): JsonResponse
    {
        $role = strtoupper(trim($request->input('role', '')));
        $query = SystemNotification::query();

        if ($role && $role !== 'ALL') {
            $query->forRole($role);
        }

        $deleted = $query->delete();

        return response()->json([
            'success' => true,
            'message' => "{$deleted} notifikasi berhasil dibersihkan.",
        ]);
    }

    /**
     * Helper to trigger activity notification from anywhere in Laravel code.
     */
    public static function notify(
        string $role,
        string $title,
        string $message,
        string $category = 'SYSTEM',
        ?string $link = null,
        string $icon = 'notifications',
        string $color = 'blue',
        ?string $referenceId = null
    ): SystemNotification {
        $notification = SystemNotification::create([
            'role' => strtoupper($role),
            'title' => $title,
            'message' => $message,
            'category' => strtoupper($category),
            'icon' => $icon,
            'color' => $color,
            'link' => $link,
            'reference_id' => $referenceId,
            'is_read' => false,
        ]);

        try {
            event(new \App\Events\NotificationBroadcastEvent($notification));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast notification failed: ' . $e->getMessage());
        }

        return $notification;
    }
}

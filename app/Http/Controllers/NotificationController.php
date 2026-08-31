<?php

namespace App\Http\Controllers;

use App\Support\Concerns\PaginatesLegacySqlServer;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    use PaginatesLegacySqlServer;

    /**
     * Display all notifications untuk user yang sedang login
     */
    public function index(): View
    {
        $user = Auth::user();

        $notificationsQuery = DatabaseNotification::query()
            ->where('notifiable_id', $user->getKey())
            ->where('notifiable_type', $user->getMorphClass());

        $orderClause = 'notifications.created_at DESC, notifications.id DESC';
        $notificationsQuery->orderByDesc('created_at')->orderByDesc('id');

        $notifications = $this->paginateEloquentForCurrentConnection($notificationsQuery, $orderClause, 20);

        $unreadCount = (clone $notificationsQuery)->unread()->count();
        $readCount = (clone $notificationsQuery)->read()->count();

        return view('pages.notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'readCount' => $readCount,
        ]);
    }

    /**
     * Get unread notifications count (untuk AJAX)
     */
    public function getUnreadCount()
    {
        $count = Auth::user()->unreadNotifications->count();

        return response()->json([
            'count' => $count,
        ]);
    }

    /**
     * Get recent notifications (untuk dropdown)
     */
    public function getRecent()
    {
        $notifications = Auth::user()
            ->notifications()
            ->limit(5)
            ->get();

        return response()->json([
            'notifications' => $notifications,
        ]);
    }

    /**
     * Mark single notification as read
     */
    public function markAsRead(string $notificationId)
    {
        $notification = Auth::user()
            ->notifications()
            ->where('id', $notificationId)
            ->first();

        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        Auth::user()->unreadNotifications->markAsRead();

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read',
            ]);
        }

        return redirect()->back()->with('success', 'All notifications marked as read');
    }

    /**
     * Delete single notification
     */
    public function destroy(string $notificationId)
    {
        Auth::user()
            ->notifications()
            ->where('id', $notificationId)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted',
        ]);
    }

    /**
     * Clear all read notifications
     */
    public function clearRead()
    {
        Auth::user()
            ->notifications()
            ->whereNotNull('read_at')
            ->delete();

        return redirect()->back()->with('success', 'All read notifications cleared');
    }
}

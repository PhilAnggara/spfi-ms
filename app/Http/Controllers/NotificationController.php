<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Display all notifications untuk user yang sedang login
     */
    public function index()
    {
        $notifications = Auth::user()
            ->notifications()
            ->paginate(20);

        return view('pages.notifications.index', compact('notifications'));
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

        return redirect()->back();
        return redirect()->back()->with('success', 'All notifications marked as read');
        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
        ]);
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

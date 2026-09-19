<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Display the System Notification Center view.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = Notification::forUser($user->id)->orderBy('created_at', 'desc');

        if ($request->filled('module') && $request->input('module') !== 'all') {
            $query->forModule($request->input('module'));
        }

        if ($request->filled('status')) {
            if ($request->input('status') === 'unread') {
                $query->unread();
            } elseif ($request->input('status') === 'read') {
                $query->read();
            }
        }

        $notifications = $query->paginate(20)->withQueryString();

        return view('notifications.index', compact('notifications'));
    }

    /**
     * Get unread notification badge count and top recent items for header dropdown.
     */
    public function unread(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['unread_count' => 0, 'notifications' => []]);
        }

        $unreadCount = Notification::forUser($user->id)->unread()->count();

        $notifications = Notification::forUser($user->id)
            ->orderByRaw('read_at IS NULL DESC')
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get()
            ->map(function ($n) {
                $module = strtolower($n->module ?? 'system');
                $badgeClass = match ($module) {
                    'hrms' => 'bg-soft-primary text-primary',
                    'purchase' => 'bg-soft-info text-info',
                    'production' => 'bg-soft-warning text-warning',
                    'sales' => 'bg-soft-success text-success',
                    'crm' => 'bg-soft-danger text-danger',
                    'inventory' => 'bg-soft-purple text-purple',
                    'accounting' => 'bg-soft-dark text-dark',
                    'projects' => 'bg-soft-secondary text-secondary',
                    default => 'bg-soft-secondary text-dark',
                };

                return [
                    'id' => $n->id,
                    'module' => $module,
                    'module_label' => strtoupper($module),
                    'module_badge_class' => $badgeClass,
                    'title' => $n->title,
                    'message' => $n->message,
                    'type' => $n->type,
                    'icon_class' => $n->icon_class ?? 'feather-bell',
                    'action_url' => $n->action_url ?? '#',
                    'is_read' => !is_null($n->read_at),
                    'time_ago' => $n->created_at ? $n->created_at->diffForHumans() : 'Just now',
                ];
            });

        return response()->json([
            'unread_count' => $unreadCount,
            'notifications' => $notifications,
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $notification = Notification::where('user_id', $user->id)->findOrFail($id);
        $notification->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }

    /**
     * Mark all notifications as read for current user.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        Notification::forUser($user->id)->unread()->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
        ]);
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $notification = Notification::where('user_id', $user->id)->findOrFail($id);
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully.',
        ]);
    }
}

<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\HrmsNotification;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrmsNotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = HrmsNotification::forUser($user->id)->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            if ($request->input('status') === 'unread') {
                $query->unread();
            } elseif ($request->input('status') === 'read') {
                $query->read();
            }
        }

        $notifications = $query->paginate(20)->withQueryString();

        return view('modules.hrms.notifications.index', compact('notifications'));
    }

    public function unread(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['unread_count' => 0, 'notifications' => []]);
        }

        $unreadCount = HrmsNotification::forUser($user->id)->unread()->count();

        $notifications = HrmsNotification::forUser($user->id)
            ->orderByRaw('read_at IS NULL DESC')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($n) {
                return [
                    'id' => $n->id,
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

    public function markAsRead(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $notification = HrmsNotification::where('user_id', $user->id)->findOrFail($id);
        $notification->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        HrmsNotification::forUser($user->id)->unread()->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $notification = HrmsNotification::where('user_id', $user->id)->findOrFail($id);
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully.',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\UserDeviceToken;
use App\Services\Firebase\FcmService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class NotificationApiController extends Controller
{
    /**
     * Allowed HRMS module names and sub-modules.
     */
    private const HRMS_MODULES = [
        'hrms',
        'attendance',
        'leaves',
        'leave',
        'payroll',
        'holiday',
        'travel',
        'claim',
        'appraisal',
        'resignation',
        'recruitment',
        'training',
        'asset',
    ];

    /**
     * Base query restricted strictly to HRMS notifications for the authenticated user.
     */
    private function hrmsQuery(int $userId): Builder
    {
        return Notification::forUser($userId)
            ->where(function (Builder $query) {
                $query->whereIn('module', self::HRMS_MODULES)
                      ->orWhereNotNull('employee_id');
            });
    }

    /**
     * Get paginated HRMS notifications for the authenticated user.
     * Response is optimized to be lightweight and fast for mobile apps.
     *
     * GET /api/notifications?type=leave&status=unread&page=1&per_page=20
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $perPage = min((int) $request->input('per_page', 20), 50); // max 50 to avoid oversized responses
        $perPage = $perPage > 0 ? $perPage : 20;

        $query = $this->hrmsQuery($user->id)
            ->select([
                'id',
                'module',
                'type',
                'title',
                'message',
                'action_url',
                'icon_class',
                'data',
                'read_at',
                'created_at',
            ])
            ->orderBy('created_at', 'desc');

        // Optional sub-type or category filter (e.g. 'leave', 'attendance', 'payroll', 'announcement')
        if ($request->filled('type')) {
            $query->where('type', strtolower($request->input('type')));
        }

        // Optional status filter ('unread' or 'read')
        if ($request->filled('status')) {
            $status = strtolower($request->input('status'));
            if ($status === 'unread') {
                $query->unread();
            } elseif ($status === 'read') {
                $query->read();
            }
        }

        $unreadCount = $this->hrmsQuery($user->id)->unread()->count();
        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(function ($item) {
            return $this->formatNotification($item);
        });

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount,
            'data' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    /**
     * Mark a single HRMS notification as read.
     *
     * POST /api/notifications/{id}/read
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $notification = $this->hrmsQuery($user->id)->find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        if (is_null($notification->read_at)) {
            $notification->update(['read_at' => now()]);
        }

        $unreadCount = $this->hrmsQuery($user->id)->unread()->count();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark a single HRMS notification as unread.
     *
     * POST /api/notifications/{id}/unread
     */
    public function markAsUnread(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $notification = $this->hrmsQuery($user->id)->find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        $notification->update(['read_at' => null]);
        $unreadCount = $this->hrmsQuery($user->id)->unread()->count();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as unread.',
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark all HRMS notifications as read for current user.
     *
     * POST /api/notifications/mark-all-read
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->hrmsQuery($user->id)->unread()->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All HRMS notifications marked as read.',
            'unread_count' => 0,
        ]);
    }

    /**
     * Delete a single HRMS notification.
     *
     * DELETE /api/notifications/{id}
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $notification = $this->hrmsQuery($user->id)->find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        $notification->delete();
        $unreadCount = $this->hrmsQuery($user->id)->unread()->count();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully.',
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Register or update user device push notification token (FCM / APNs).
     *
     * POST /api/notifications/device-token
     */
    public function updateDeviceToken(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => 'required|string',
            'device_type' => 'nullable|string|in:android,ios,web,other',
            'device_name' => 'nullable|string|max:150',
        ]);

        $user = $request->user();
        $token = trim($request->input('fcm_token'));
        $deviceType = $request->input('device_type', 'android');
        $deviceName = $request->input('device_name');

        // 1. Maintain backward compatibility with $user->settings
        $settings = is_array($user->settings) ? $user->settings : json_decode($user->settings ?? '{}', true) ?? [];
        $settings['fcm_token'] = $token;
        $settings['device_type'] = $deviceType;
        $settings['fcm_updated_at'] = now()->toISOString();
        $user->settings = $settings;
        $user->saveQuietly();

        // 2. Multi-device table support
        try {
            if (Schema::hasTable('user_device_tokens')) {
                $hash = hash('sha256', $token);
                UserDeviceToken::updateOrCreate(
                    [
                        'token_hash' => $hash,
                    ],
                    [
                        'tenant_id' => $user->tenant_id ?? 1,
                        'user_id' => $user->id,
                        'fcm_token' => $token,
                        'device_type' => $deviceType,
                        'device_name' => $deviceName,
                        'is_active' => true,
                        'last_used_at' => now(),
                    ]
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to store user device token in table: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Device token registered successfully.',
        ]);
    }

    /**
     * Unregister / Deactivate a device token upon logout or toggle.
     *
     * DELETE /api/notifications/device-token
     */
    public function removeDeviceToken(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $user = $request->user();
        $token = trim($request->input('fcm_token'));

        // Deactivate in dedicated table
        FcmService::deactivateToken($token);

        // Clear settings if matching
        $settings = is_array($user->settings) ? $user->settings : json_decode($user->settings ?? '{}', true) ?? [];
        if (!empty($settings['fcm_token']) && $settings['fcm_token'] === $token) {
            unset($settings['fcm_token']);
            $user->settings = $settings;
            $user->saveQuietly();
        }

        return response()->json([
            'success' => true,
            'message' => 'Device token removed successfully.',
        ]);
    }

    /**
     * Authenticated endpoint to test FCM push notification delivery to the caller's device(s).
     *
     * POST /api/notifications/test-fcm
     */
    public function testFcm(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!FcmService::isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Firebase credentials are not configured or incomplete on the server.',
            ], 422);
        }

        $targetToken = $request->input('fcm_token');
        $title = $request->input('title', 'MossiERP Push Notification Test');
        $message = $request->input('message', 'Firebase FCM is configured and working perfectly! 🚀');

        if (!empty($targetToken)) {
            $result = FcmService::sendToToken(
                token: $targetToken,
                title: $title,
                body: $message,
                data: [
                    'type' => 'test',
                    'timestamp' => now()->toIso8601String(),
                ]
            );
        } else {
            $result = FcmService::sendToUser(
                user: $user,
                title: $title,
                body: $message,
                extraData: [
                    'type' => 'test',
                    'timestamp' => now()->toIso8601String(),
                ]
            );
        }

        return response()->json([
            'success' => $result['success'] ?? false,
            'message' => ($result['success'] ?? false) ? 'Test push notification sent successfully.' : 'Failed to send test push notification.',
            'result' => $result,
        ]);
    }

    /**
     * Helper to format compact and clean notification object for mobile JSON.
     */
    private function formatNotification(Notification $n): array
    {
        $module = strtolower($n->module ?? 'hrms');

        return [
            'id' => (int) $n->id,
            'module' => $module,
            'type' => $n->type ?? 'general',
            'title' => (string) $n->title,
            'message' => (string) $n->message,
            'icon_class' => $n->icon_class ?? 'feather-bell',
            'action_url' => $n->action_url,
            'payload' => !empty($n->data) ? $n->data : null,
            'is_read' => !is_null($n->read_at),
            'read_at' => $n->read_at ? $n->read_at->toIso8601String() : null,
            'created_at' => $n->created_at ? $n->created_at->toIso8601String() : null,
            'time_ago' => $n->created_at ? $n->created_at->diffForHumans() : 'Just now',
        ];
    }
}

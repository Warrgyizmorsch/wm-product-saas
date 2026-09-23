<?php

namespace App\Domains\Platform\Controllers;

use App\Domains\Platform\Models\NotificationRule;
use App\Domains\Platform\Services\NotificationEventCatalog;
use App\Domains\Platform\Services\NotificationRuleService;
use App\Http\Controllers\Controller;
use App\Models\Access\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationRuleController extends Controller
{
    /**
     * Display a listing of notification rules.
     */
    public function index(Request $request): View
    {
        $tenantId = function_exists('tenant_id') ? tenant_id() : (auth()->user()?->tenant_id ?? 1);

        $query = NotificationRule::query()->where('tenant_id', $tenantId)->orderBy('module', 'asc')->orderBy('name', 'asc');

        if ($request->filled('module') && $request->input('module') !== 'all') {
            $query->where('module', $request->input('module'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('event_key', 'like', "%{$search}%")
                  ->orWhere('title_template', 'like', "%{$search}%");
            });
        }

        $rules = $query->paginate(20)->withQueryString();
        $eventCatalog = NotificationEventCatalog::getEvents();

        return view('modules.platform.notifications.rules.index', compact('rules', 'eventCatalog'));
    }

    /**
     * Show the form for creating a new notification rule.
     */
    public function create(Request $request): View
    {
        $tenantId = function_exists('tenant_id') ? tenant_id() : (auth()->user()?->tenant_id ?? 1);
        $eventCatalog = NotificationEventCatalog::getEvents();
        $roles = Role::where('tenant_id', $tenantId)->orWhereNull('tenant_id')->orderBy('name')->get();
        $users = User::where('tenant_id', $tenantId)->orderBy('name')->get();

        $selectedEventKey = $request->query('event_key');
        $eventDetails = $selectedEventKey ? NotificationEventCatalog::getEventDetails($selectedEventKey) : null;

        return view('modules.platform.notifications.rules.create', compact('eventCatalog', 'roles', 'users', 'selectedEventKey', 'eventDetails'));
    }

    /**
     * Store a newly created notification rule in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $tenantId = function_exists('tenant_id') ? tenant_id() : (auth()->user()?->tenant_id ?? 1);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'event_key' => 'required|string|max:255',
            'module' => 'required|string|max:50',
            'description' => 'nullable|string',
            'recipient_roles' => 'nullable|array',
            'recipient_roles.*' => 'string',
            'recipient_user_ids' => 'nullable|array',
            'recipient_user_ids.*' => 'integer',
            'notify_creator' => 'nullable|boolean',
            'notify_assigned_user' => 'nullable|boolean',
            'title_template' => 'required|string|max:255',
            'body_template' => 'required|string',
            'action_route' => 'nullable|string|max:255',
            'icon_class' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['company_id'] = auth()->user()?->company_id;
        $validated['notify_creator'] = $request->boolean('notify_creator');
        $validated['notify_assigned_user'] = $request->boolean('notify_assigned_user');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['created_by'] = auth()->id();
        $validated['icon_class'] = $validated['icon_class'] ?: 'feather-bell';

        NotificationRule::create($validated);

        return redirect()->route('platform.notification-rules.index')
            ->with('success', __('notifications.rule_created'));
    }

    /**
     * Show the form for editing the specified notification rule.
     */
    public function edit(NotificationRule $notificationRule): View
    {
        $tenantId = function_exists('tenant_id') ? tenant_id() : (auth()->user()?->tenant_id ?? 1);
        $eventCatalog = NotificationEventCatalog::getEvents();
        $roles = Role::where('tenant_id', $tenantId)->orWhereNull('tenant_id')->orderBy('name')->get();
        $users = User::where('tenant_id', $tenantId)->orderBy('name')->get();
        $eventDetails = NotificationEventCatalog::getEventDetails($notificationRule->event_key);

        return view('modules.platform.notifications.rules.edit', compact('notificationRule', 'eventCatalog', 'roles', 'users', 'eventDetails'));
    }

    /**
     * Update the specified notification rule in storage.
     */
    public function update(Request $request, NotificationRule $notificationRule): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'event_key' => 'required|string|max:255',
            'module' => 'required|string|max:50',
            'description' => 'nullable|string',
            'recipient_roles' => 'nullable|array',
            'recipient_roles.*' => 'string',
            'recipient_user_ids' => 'nullable|array',
            'recipient_user_ids.*' => 'integer',
            'notify_creator' => 'nullable|boolean',
            'notify_assigned_user' => 'nullable|boolean',
            'title_template' => 'required|string|max:255',
            'body_template' => 'required|string',
            'action_route' => 'nullable|string|max:255',
            'icon_class' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['notify_creator'] = $request->boolean('notify_creator');
        $validated['notify_assigned_user'] = $request->boolean('notify_assigned_user');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['updated_by'] = auth()->id();
        $validated['icon_class'] = $validated['icon_class'] ?: 'feather-bell';

        $notificationRule->update($validated);

        return redirect()->route('platform.notification-rules.index')
            ->with('success', __('notifications.rule_updated'));
    }

    /**
     * Remove the specified notification rule from storage.
     */
    public function destroy(NotificationRule $notificationRule): RedirectResponse
    {
        $notificationRule->delete();

        return redirect()->route('platform.notification-rules.index')
            ->with('success', __('notifications.rule_deleted'));
    }

    /**
     * Toggle active status via AJAX.
     */
    public function toggleStatus(NotificationRule $notificationRule): JsonResponse
    {
        $notificationRule->update(['is_active' => !$notificationRule->is_active]);

        return response()->json([
            'success' => true,
            'is_active' => $notificationRule->is_active,
            'message' => __('notifications.rule_toggled'),
        ]);
    }

    /**
     * Send a test notification to the current logged-in user to verify header bell.
     */
    public function testSend(NotificationRule $notificationRule): JsonResponse
    {
        $sampleData = [
            'doc_no' => 'SO-TEST-101',
            'customer_name' => 'Demo Customer Ltd',
            'vendor_name' => 'Apex Suppliers Inc',
            'item_name' => 'Industrial Table 1800x900',
            'sku' => 'IT-1800',
            'current_stock' => '2',
            'reorder_point' => '10',
            'uom' => 'Pcs',
            'amount' => '$1,200.00',
            'due_date' => now()->addDays(15)->format('d M Y'),
            'date' => now()->format('d M Y h:i A'),
            'created_by' => auth()->user()?->name ?? 'Admin',
            'confirmed_by' => auth()->user()?->name ?? 'Admin',
            'approved_by' => auth()->user()?->name ?? 'Admin',
            'received_by' => auth()->user()?->name ?? 'Admin',
            'from_warehouse' => 'Main Warehouse',
            'to_warehouse' => 'Branch Store',
            'warehouse' => 'Main Warehouse',
            'employee_name' => auth()->user()?->name ?? 'Staff',
            'days' => '2',
            'leave_type' => 'Casual Leave',
            'from_date' => now()->format('d M Y'),
            'to_date' => now()->addDays(2)->format('d M Y'),
        ];

        \App\Services\Notification\NotificationService::send(
            user: auth()->user(),
            title: '[TEST BELL] ' . $notificationRule->renderTitle($sampleData),
            message: $notificationRule->renderBody($sampleData),
            actionUrl: $notificationRule->action_route ? route($notificationRule->action_route) : '#',
            module: $notificationRule->module,
            type: 'alert',
            iconClass: $notificationRule->icon_class ?: 'feather-bell'
        );

        return response()->json([
            'success' => true,
            'message' => 'Test Notification successfully dispatched to your Header Bell!',
        ]);
    }
}

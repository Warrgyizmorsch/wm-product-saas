<?php

namespace App\Http\Controllers;

use App\Domains\Platform\Services\UsageLimitService;
use App\Models\EmailConfiguration;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppConfiguration;
use App\Services\Access\AccessService;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use RuntimeException;

class AccountSettingsController extends Controller
{
    public function __construct(
        protected AccountService $accountService,
        protected UsageLimitService $usageLimits,
        protected AccessService $accessService
    ) {}

    /**
     * Display the unified Account Settings dashboard.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['primaryRole', 'company', 'branch']);

        $tenant = tenant() ?? $user->tenant ?? Tenant::find($user->tenant_id);

        // Subscription & Quotas from existing source
        $maxUsers = $tenant ? $this->usageLimits->maxUsers($tenant) : null;
        $currentUserCount = $tenant ? $this->usageLimits->currentUserCount($tenant) : 1;
        $remainingUserSlots = $tenant ? $this->usageLimits->remainingUserSlots($tenant) : null;
        $maxStorageMb = $tenant ? $tenant->max_storage_mb : 10240;

        // Integrations Status
        $emailConfigured = false;
        $emailAccountCount = 0;
        if (class_exists(EmailConfiguration::class)) {
            $emailAccountCount = EmailConfiguration::forCurrentContext()->count();
            $emailConfigured = EmailConfiguration::forCurrentContext()->where('is_active', true)->exists();
        }

        $whatsappConnected = false;
        $whatsappStatus = 'disconnected';
        $whatsappNumber = null;
        if (class_exists(WhatsAppConfiguration::class)) {
            $waConfig = WhatsAppConfiguration::getForCurrentContext();
            $whatsappStatus = $waConfig?->status ?? 'disconnected';
            $whatsappConnected = ($whatsappStatus === 'connected');
            $whatsappNumber = $waConfig?->phone_number;
        }

        // Authorization check for platform setting links
        $canManagePlatformSettings = $this->accessService->allows($user, 'platform.settings.manage')
            || $this->accessService->allows($user, 'platform.tenants.manage')
            || in_array($user->role, ['admin', 'super_admin', 'tenant_owner'], true);

        // Notification preferences stored in user settings JSON
        $notificationPrefs = $user->settings['notifications'] ?? [
            'in_app'   => true,
            'email'    => true,
            'whatsapp' => false,
        ];

        return view('account.settings', compact(
            'user',
            'tenant',
            'maxUsers',
            'currentUserCount',
            'remainingUserSlots',
            'maxStorageMb',
            'emailConfigured',
            'emailAccountCount',
            'whatsappConnected',
            'whatsappStatus',
            'whatsappNumber',
            'canManagePlatformSettings',
            'notificationPrefs'
        ));
    }

    /**
     * Update user credentials / personal details from account settings.
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name'   => ['required', 'string', 'max:255'],
            'email'  => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'  => ['nullable', 'string', 'max:30'],
        ]);

        $this->accountService->updateProfile($user, $validated);

        return redirect()->route('account.settings', ['tab' => 'profile'])
            ->with('success', __('Account details updated successfully.'));
    }

    /**
     * Update user password from account settings.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'string', 'min:8', 'confirmed', Password::default()],
        ]);

        $this->accountService->updatePassword($user, $validated['password']);

        return redirect()->route('account.settings', ['tab' => 'profile'])
            ->with('success', __('Password changed successfully.'));
    }

    /**
     * Update notification channel preferences.
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'in_app'   => ['nullable', 'boolean'],
            'email'    => ['nullable', 'boolean'],
            'whatsapp' => ['nullable', 'boolean'],
        ]);

        $this->accountService->updateNotificationPreferences($user, [
            'in_app'   => $request->boolean('in_app'),
            'email'    => $request->boolean('email'),
            'whatsapp' => $request->boolean('whatsapp'),
        ]);

        return redirect()->route('account.settings', ['tab' => 'notifications'])
            ->with('success', __('Notification preferences saved successfully.'));
    }

    /**
     * Deactivate the account safely.
     */
    public function deactivateAccount(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($request->has('confirmation')) {
            $request->merge([
                'confirmation' => strtoupper(trim((string) $request->input('confirmation'))),
            ]);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'current_password'],
            'confirmation'     => ['required', 'in:DEACTIVATE'],
        ], [
            'confirmation.in'                   => __('Please type DEACTIVATE in capital letters to confirm account deactivation.'),
            'confirmation.required'             => __('Please type DEACTIVATE to confirm account deactivation.'),
            'current_password.current_password' => __('The password you entered is incorrect.'),
            'current_password.required'         => __('Please enter your current password to confirm.'),
        ]);

        if ($validator->fails()) {
            return redirect()->route('account.settings', ['tab' => 'danger'])
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $this->accountService->deactivateAccount($user);
        } catch (RuntimeException $e) {
            return redirect()->route('account.settings', ['tab' => 'danger'])
                ->with('error', $e->getMessage());
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', __('Your account has been deactivated successfully.'));
    }
}

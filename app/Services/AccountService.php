<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AccountService
{
    /**
     * Update the user's basic profile and optional avatar.
     * Synchronizes phone and email with HRMS Employee if present.
     */
    public function updateProfile(User $user, array $data, ?UploadedFile $avatarFile = null): User
    {
        return DB::transaction(function () use ($user, $data, $avatarFile) {
            $user->name = $data['name'] ?? $user->name;

            if (isset($data['email']) && $data['email'] !== $user->email) {
                $user->email = $data['email'];
            }

            if (array_key_exists('phone', $data)) {
                $user->phone = $data['phone'];
            }

            // Handle avatar file upload
            if ($avatarFile && $avatarFile->isValid()) {
                if (!empty($user->avatar)) {
                    if (Storage::disk('public')->exists($user->avatar)) {
                        Storage::disk('public')->delete($user->avatar);
                    }
                    $oldPublicPath = public_path('storage/' . $user->avatar);
                    if (file_exists($oldPublicPath) && is_file($oldPublicPath)) {
                        @unlink($oldPublicPath);
                    }
                }

                $path = $avatarFile->store('avatars', 'public');
                $user->avatar = $path;

                // Mirror into public/storage/avatars for web servers that serve public directly without symlinks
                try {
                    $targetDir = public_path('storage/' . dirname($path));
                    if (!is_dir($targetDir)) {
                        @mkdir($targetDir, 0755, true);
                    }
                    $sourceFile = Storage::disk('public')->path($path);
                    if (file_exists($sourceFile)) {
                        @copy($sourceFile, public_path('storage/' . $path));
                    }
                } catch (\Throwable) {
                    // Handled gracefully; fallback storage route will serve the file if copy fails
                }
            }

            $user->save();

            // Synchronize with linked HRMS Employee if HRMS module / model is present
            if (class_exists(\App\Domains\HRMS\Models\Employee::class)) {
                $employee = \App\Domains\HRMS\Models\Employee::resolveForUser($user);

                if ($employee && $employee->tenant_id === $user->tenant_id) {
                    $employeeUpdates = [];

                    if (array_key_exists('phone', $data) && !empty($data['phone'])) {
                        $employeeUpdates['personal_mobile_number'] = $data['phone'];
                    }

                    if (!empty($employeeUpdates)) {
                        $employee->updateQuietly($employeeUpdates);
                    }
                }
            }

            return $user;
        });
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(User $user, string $newPassword): void
    {
        $user->password = $newPassword;
        $user->save();
    }

    /**
     * Update user-level notification preferences in settings JSON.
     */
    public function updateNotificationPreferences(User $user, array $preferences): void
    {
        $settings = $user->settings ?? [];
        $existingNotifications = $settings['notifications'] ?? [];

        $settings['notifications'] = array_merge($existingNotifications, [
            'in_app'   => (bool) ($preferences['in_app'] ?? false),
            'email'    => (bool) ($preferences['email'] ?? false),
            'whatsapp' => (bool) ($preferences['whatsapp'] ?? false),
        ]);

        $user->settings = $settings;
        $user->save();
    }

    /**
     * Safely deactivate the user account.
     * Prevents tenant abandonment by blocking sole owner deactivation.
     */
    public function deactivateAccount(User $user): void
    {
        $tenant = $user->tenant ?: Tenant::find($user->tenant_id);

        if ($tenant && $tenant->owner_user_id === $user->id) {
            $otherAdmins = User::where('tenant_id', $user->tenant_id)
                ->where('id', '!=', $user->id)
                ->where(function ($q) {
                    $q->where('role', 'admin')
                      ->orWhereHas('primaryRole', fn ($r) => $r->whereIn('slug', ['admin', 'tenant_owner', 'super_admin']));
                })
                ->exists();

            if (!$otherAdmins) {
                throw new RuntimeException(__('Cannot deactivate the primary tenant owner account without another active administrator. Please transfer tenant ownership first.'));
            }
        }

        DB::transaction(function () use ($user) {
            $settings = $user->settings ?? [];
            $settings['is_deactivated'] = true;
            $settings['deactivated_at'] = now()->toIso8601String();

            $user->settings = $settings;
            $user->remember_token = null;
            $user->save();
        });
    }
}

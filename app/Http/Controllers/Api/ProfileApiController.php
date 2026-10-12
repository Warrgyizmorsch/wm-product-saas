<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class ProfileApiController extends Controller
{
    public function __construct(
        protected AccountService $accountService
    ) {}

    /**
     * Change the authenticated user's password.
     *
     * POST /api/profile/change-password
     * POST /api/auth/change-password
     */
    public function changePassword(Request $request): JsonResponse
    {
        // Support common mobile / client parameter aliases
        if (!$request->has('current_password') && $request->has('old_password')) {
            $request->merge(['current_password' => $request->input('old_password')]);
        }
        if (!$request->has('password') && $request->has('new_password')) {
            $request->merge(['password' => $request->input('new_password')]);
        }
        if (!$request->has('password_confirmation') && $request->has('new_password_confirmation')) {
            $request->merge(['password_confirmation' => $request->input('new_password_confirmation')]);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'string', 'min:8', 'confirmed', Password::default()],
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
            'current_password.required'         => __('Please enter your current password.'),
            'password.required'                 => __('Please enter a new password.'),
            'password.confirmed'                => __('The password confirmation does not match.'),
            'password.min'                      => __('The password must be at least 8 characters.'),
        ]);

        /** @var User $user */
        $user = $request->user();

        $this->accountService->updatePassword($user, $validated['password']);

        return response()->json([
            'success' => true,
            'message' => __('Password changed successfully.'),
        ], 200);
    }
}

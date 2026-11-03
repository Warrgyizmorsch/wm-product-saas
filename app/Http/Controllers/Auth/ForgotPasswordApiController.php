<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\ForgotPasswordOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class ForgotPasswordApiController extends Controller
{
    public function __construct(
        protected ForgotPasswordOtpService $otpService
    ) {}

    /**
     * Send a 6-digit OTP to the requested email.
     *
     * POST /api/auth/forgot-password/send-otp
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ], [
            'email.required' => __('Please enter your registered email address.'),
            'email.email'    => __('Please enter a valid email address.'),
        ]);

        $result = $this->otpService->sendOtp($validated['email']);

        if (!$result['success']) {
            $status = isset($result['cooldown']) ? 429 : 422;
            return response()->json(array_merge([
                'success'  => false,
                'message'  => $result['message'],
                'cooldown' => $result['cooldown'] ?? null,
            ], $result), $status);
        }

        return response()->json(array_merge([
            'success' => true,
            'message' => $result['message'],
        ], $result), 200);
    }

    /**
     * Verify the 6-digit OTP and return a reset token.
     *
     * POST /api/auth/forgot-password/verify-otp
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'otp'   => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ], [
            'email.required' => __('Email address is required.'),
            'otp.required'   => __('Please enter the 6-digit verification code.'),
            'otp.regex'      => __('The verification code must be exactly 6 digits.'),
        ]);

        $result = $this->otpService->verifyOtp($validated['email'], $validated['otp']);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'success'     => true,
            'message'     => $result['message'],
            'reset_token' => $result['reset_token'],
        ], 200);
    }

    /**
     * Reset the password using the verified reset token.
     *
     * POST /api/auth/forgot-password/reset
     */
    public function resetPassword(Request $request): JsonResponse
    {
        // Support common parameter aliases from different clients
        if (!$request->has('password') && $request->has('new_password')) {
            $request->merge(['password' => $request->input('new_password')]);
        }
        if (!$request->has('password_confirmation') && $request->has('new_password_confirmation')) {
            $request->merge(['password_confirmation' => $request->input('new_password_confirmation')]);
        }

        $validated = $request->validate([
            'email'       => ['required', 'string', 'email'],
            'reset_token' => ['required', 'string'],
            'password'    => ['required', 'string', 'min:8', 'confirmed', Password::default()],
        ], [
            'email.required'       => __('Email address is required.'),
            'reset_token.required' => __('Reset token is required.'),
            'password.required'    => __('Please enter a new password.'),
            'password.min'         => __('Password must be at least 8 characters long.'),
            'password.confirmed'   => __('Password confirmation does not match.'),
        ]);

        $result = $this->otpService->resetPassword(
            $validated['email'],
            $validated['reset_token'],
            $validated['password']
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
        ], 200);
    }
}


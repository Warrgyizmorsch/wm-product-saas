<?php

namespace App\Services\Auth;

use App\Mail\ResetPasswordOtpMail;
use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Services\AccountService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ForgotPasswordOtpService
{
    public const OTP_EXPIRY_MINUTES = 10;
    public const RESET_TOKEN_EXPIRY_MINUTES = 15;
    public const RESEND_COOLDOWN_SECONDS = 60;
    public const MAX_ATTEMPTS = 5;

    public function __construct(
        protected ?AccountService $accountService = null
    ) {
        $this->ensureTableExists();
    }

    /**
     * Find user by email across tenant scope and unscoped.
     */
    public function findUserByEmail(string $email): ?User
    {
        $normalizedEmail = strtolower(trim($email));

        return User::where('email', $normalizedEmail)->first()
            ?? User::withoutGlobalScopes()->where('email', $normalizedEmail)->first();
    }

    /**
     * Generate and dispatch an OTP to the given email.
     *
     * @return array{success: bool, message: string, cooldown?: int}
     */
    public function sendOtp(string $email): array
    {
        $email = strtolower(trim($email));
        $user = $this->findUserByEmail($email);

        // Security: If account exists and is deactivated, do not proceed
        if ($user && method_exists($user, 'isDeactivated') && $user->isDeactivated()) {
            return [
                'success' => false,
                'message' => __('This account has been deactivated. Please contact your workspace administrator.'),
            ];
        }

        // Check resend cooldown
        $lastSentAt = $this->getLastSentTimestamp($email);
        if ($lastSentAt !== null) {
            $secondsElapsed = now()->timestamp - $lastSentAt;
            if ($secondsElapsed < self::RESEND_COOLDOWN_SECONDS) {
                $remaining = self::RESEND_COOLDOWN_SECONDS - $secondsElapsed;
                return [
                    'success' => false,
                    'cooldown' => $remaining,
                    'message' => __('Please wait :seconds seconds before requesting another code.', ['seconds' => $remaining]),
                ];
            }
        }

        // If user does not exist, reject immediately so user stays on Step 1
        if (!$user) {
            return [
                'success' => false,
                'message' => __('This email address is not valid or registered.'),
            ];
        }

        // Check if email belongs to a dummy domain or is undeliverable
        if ($this->isDummyOrUndeliverableEmail($email)) {
            return [
                'success' => false,
                'message' => __('This email address is invalid and cannot receive verification codes.'),
            ];
        }

        // Generate 6-digit numeric OTP
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpHash = Hash::make($otp);
        $expiresAt = now()->addMinutes(self::OTP_EXPIRY_MINUTES);

        // Store OTP state
        $this->storeOtpRecord($email, $otpHash, $expiresAt);

        // Resolve dynamic ERP EmailConfiguration (as used by Recruitment, CRM, and Invoicing)
        $account = $this->resolveEmailConfiguration($user);
        if ($account) {
            $this->applyEmailConfiguration($account);
        }

        // Send Email
        $mailSent = false;
        try {
            Mail::to($user->email)->send(new ResetPasswordOtpMail($otp, $user, self::OTP_EXPIRY_MINUTES));
            $mailSent = true;

            // Log outbound message into ERP email history if EmailMessage model exists
            if ($account && class_exists(\App\Models\EmailMessage::class)) {
                try {
                    $html = view('emails.auth.reset-password-otp', [
                        'otp' => $otp,
                        'userName' => $user->name ?? '',
                        'expiresInMinutes' => self::OTP_EXPIRY_MINUTES,
                    ])->render();

                    \App\Models\EmailMessage::create([
                        'tenant_id' => $user->tenant_id ?? $account->tenant_id,
                        'email_configuration_id' => $account->id,
                        'thread_id' => (string) Str::uuid(),
                        'message_id' => '<' . Str::uuid() . '@' . parse_url(config('app.url', 'http://localhost'), PHP_URL_HOST) . '>',
                        'direction' => 'outbound',
                        'folder' => 'sent',
                        'from_name' => $account->from_name ?: ($account->name ?: config('app.name')),
                        'from_email' => $account->email_address,
                        'to_email' => $user->email,
                        'subject' => __('Your Password Reset Verification Code - :app', ['app' => config('app.name', 'ERP Portal')]),
                        'body_html' => $html,
                        'body_plain' => strip_tags($html),
                        'is_read' => true,
                    ]);
                } catch (\Throwable $me) {
                    Log::warning('ForgotPasswordOtp: Could not record in EmailMessage: ' . $me->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::error('ForgotPasswordOtp: Mail sending failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => __('Unable to send verification email. Please verify system email settings or try again shortly.'),
            ];
        }

        return [
            'success' => true,
            'message' => __('A 6-digit verification code has been sent to your email address.'),
        ];
    }

    /**
     * Verify the OTP and issue a secure one-time reset token.
     *
     * @return array{success: bool, message: string, reset_token?: string}
     */
    public function verifyOtp(string $email, string $otp): array
    {
        $email = strtolower(trim($email));
        $otp = trim($otp);

        $record = $this->getOtpRecord($email);

        if (!$record || empty($record['otp_hash'])) {
            return [
                'success' => false,
                'message' => __('No pending verification code found. Please request a new code.'),
            ];
        }

        $expiresAt = $record['expires_at'] instanceof Carbon ? $record['expires_at'] : Carbon::parse($record['expires_at']);
        if ($expiresAt->isPast()) {
            $this->clearOtpRecord($email);
            return [
                'success' => false,
                'message' => __('Verification code has expired. Please request a new one.'),
            ];
        }

        $attempts = (int) ($record['attempts'] ?? 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->clearOtpRecord($email);
            return [
                'success' => false,
                'message' => __('Too many incorrect attempts. Please request a new code.'),
            ];
        }

        if (!Hash::check($otp, $record['otp_hash'])) {
            $newAttempts = $attempts + 1;
            $this->incrementAttempts($email, $newAttempts);

            $remaining = self::MAX_ATTEMPTS - $newAttempts;
            if ($remaining <= 0) {
                $this->clearOtpRecord($email);
                return [
                    'success' => false,
                    'message' => __('Too many incorrect attempts. Please request a new code.'),
                ];
            }

            return [
                'success' => false,
                'message' => __('Invalid verification code. :count attempts remaining.', ['count' => $remaining]),
            ];
        }

        // OTP is valid! Generate one-time reset token
        $resetToken = Str::random(64);
        $resetTokenExpiresAt = now()->addMinutes(self::RESET_TOKEN_EXPIRY_MINUTES);

        $this->storeResetToken($email, $resetToken, $resetTokenExpiresAt);

        return [
            'success' => true,
            'message' => __('Verification successful.'),
            'reset_token' => $resetToken,
        ];
    }

    /**
     * Reset the user's password using the validated reset token.
     *
     * @return array{success: bool, message: string}
     */
    public function resetPassword(string $email, string $resetToken, string $newPassword): array
    {
        $email = strtolower(trim($email));
        $resetToken = trim($resetToken);

        $record = $this->getOtpRecord($email);

        if (!$record || empty($record['reset_token'])) {
            return [
                'success' => false,
                'message' => __('Invalid or expired reset session. Please restart the password reset process.'),
            ];
        }

        if (!hash_equals($record['reset_token'], $resetToken)) {
            return [
                'success' => false,
                'message' => __('Invalid reset token.'),
            ];
        }

        $tokenExpiresAt = $record['reset_token_expires_at'] ?? null;
        if ($tokenExpiresAt) {
            $carbonTokenExpires = $tokenExpiresAt instanceof Carbon ? $tokenExpiresAt : Carbon::parse($tokenExpiresAt);
            if ($carbonTokenExpires->isPast()) {
                $this->clearOtpRecord($email);
                return [
                    'success' => false,
                    'message' => __('Reset session has expired. Please restart the process.'),
                ];
            }
        }

        $user = $this->findUserByEmail($email);
        if (!$user) {
            $this->clearOtpRecord($email);
            return [
                'success' => false,
                'message' => __('User account not found.'),
            ];
        }

        // Update password
        if ($this->accountService) {
            $this->accountService->updatePassword($user, $newPassword);
        } else {
            $user->password = $newPassword; // Casted to hashed automatically in User model
            $user->save();
        }

        // Revoke Sanctum API tokens and remember tokens for security
        try {
            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }
            $user->setRememberToken(Str::random(60));
            $user->saveQuietly();
        } catch (\Throwable $e) {
            Log::warning('ForgotPasswordOtp: Token revocation notice: ' . $e->getMessage());
        }

        // Clear reset record
        $this->clearOtpRecord($email);

        return [
            'success' => true,
            'message' => __('Your password has been reset successfully. Please log in with your new credentials.'),
        ];
    }

    // --------------------------------------------------------------------------
    // Storage Helpers (Zero Single Point of Failure: DB + Cache Fallback)
    // --------------------------------------------------------------------------

    protected function getCacheKey(string $email): string
    {
        return 'pwd_reset_otp_' . md5(strtolower(trim($email)));
    }

    protected function getCooldownCacheKey(string $email): string
    {
        return 'pwd_reset_cooldown_' . md5(strtolower(trim($email)));
    }

    protected function setCooldownCache(string $email): void
    {
        Cache::put($this->getCooldownCacheKey($email), now()->timestamp, self::RESEND_COOLDOWN_SECONDS);
    }

    protected function getLastSentTimestamp(string $email): ?int
    {
        $cached = Cache::get($this->getCooldownCacheKey($email));
        if ($cached) {
            return (int) $cached;
        }

        $record = $this->getOtpRecord($email);
        if ($record && !empty($record['created_at'])) {
            $time = $record['created_at'] instanceof Carbon ? $record['created_at']->timestamp : Carbon::parse($record['created_at'])->timestamp;
            return $time;
        }

        return null;
    }

    protected function storeOtpRecord(string $email, string $otpHash, Carbon $expiresAt): void
    {
        $now = now();
        $this->setCooldownCache($email);

        $data = [
            'email' => $email,
            'otp_hash' => $otpHash,
            'reset_token' => null,
            'attempts' => 0,
            'expires_at' => $expiresAt,
            'reset_token_expires_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // Cache layer
        Cache::put($this->getCacheKey($email), $data, self::OTP_EXPIRY_MINUTES * 60);

        // Database layer
        try {
            if ($this->hasOtpTable()) {
                PasswordResetOtp::updateOrCreate(
                    ['email' => $email],
                    [
                        'otp_hash' => $otpHash,
                        'reset_token' => null,
                        'attempts' => 0,
                        'expires_at' => $expiresAt,
                        'reset_token_expires_at' => null,
                        'updated_at' => $now,
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('ForgotPasswordOtp: Database record save fallback to cache: ' . $e->getMessage());
        }
    }

    protected function getOtpRecord(string $email): ?array
    {
        // Try Cache first
        $cached = Cache::get($this->getCacheKey($email));
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        // Try Database
        try {
            if ($this->hasOtpTable()) {
                $record = PasswordResetOtp::where('email', $email)->first();
                if ($record) {
                    return [
                        'email' => $record->email,
                        'otp_hash' => $record->otp_hash,
                        'reset_token' => $record->reset_token,
                        'attempts' => (int) $record->attempts,
                        'expires_at' => $record->expires_at,
                        'reset_token_expires_at' => $record->reset_token_expires_at,
                        'created_at' => $record->created_at,
                        'updated_at' => $record->updated_at,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ForgotPasswordOtp: Database record lookup fallback: ' . $e->getMessage());
        }

        return null;
    }

    protected function incrementAttempts(string $email, int $attempts): void
    {
        // Update Cache
        $cached = Cache::get($this->getCacheKey($email));
        if (is_array($cached)) {
            $cached['attempts'] = $attempts;
            Cache::put($this->getCacheKey($email), $cached, self::OTP_EXPIRY_MINUTES * 60);
        }

        // Update DB
        try {
            if ($this->hasOtpTable()) {
                PasswordResetOtp::where('email', $email)->update(['attempts' => $attempts]);
            }
        } catch (\Throwable) {
            // Handled gracefully
        }
    }

    protected function storeResetToken(string $email, string $resetToken, Carbon $expiresAt): void
    {
        $now = now();

        // Update Cache
        $cached = Cache::get($this->getCacheKey($email)) ?? [];
        $cached['reset_token'] = $resetToken;
        $cached['reset_token_expires_at'] = $expiresAt;
        $cached['otp_hash'] = ''; // Invalidate OTP once verified
        Cache::put($this->getCacheKey($email), $cached, self::RESET_TOKEN_EXPIRY_MINUTES * 60);

        // Update DB
        try {
            if ($this->hasOtpTable()) {
                PasswordResetOtp::where('email', $email)->update([
                    'otp_hash' => '', // Clear OTP so it cannot be reused
                    'reset_token' => $resetToken,
                    'reset_token_expires_at' => $expiresAt,
                    'updated_at' => $now,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('ForgotPasswordOtp: Token store DB fallback: ' . $e->getMessage());
        }
    }

    protected function clearOtpRecord(string $email): void
    {
        Cache::forget($this->getCacheKey($email));
        Cache::forget($this->getCooldownCacheKey($email));

        try {
            if ($this->hasOtpTable()) {
                PasswordResetOtp::where('email', $email)->delete();
            }
        } catch (\Throwable) {
            // Handled gracefully
        }
    }

    /**
     * Check if an email belongs to a dummy/test domain or cannot receive email.
     */
    public function isDummyOrUndeliverableEmail(string $email): bool
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return true;
        }

        $domain = strtolower(trim($parts[1]));

        // Blacklist known dummy / seeded testing domains
        $dummyDomains = [
            'warrg.com',
            'example.com',
            'example.org',
            'example.net',
            'test.com',
            'demo.com',
            'dummy.com',
            'invalid',
            'localhost',
            'fake.com',
        ];

        if (in_array($domain, $dummyDomains, true) || str_ends_with($domain, '.test') || str_ends_with($domain, '.example')) {
            return true;
        }

        // Check MX records if dns functions are available and server has connectivity
        if (function_exists('checkdnsrr')) {
            try {
                if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
                    return true;
                }
            } catch (\Throwable) {
                // If DNS fails to resolve or offline, do not block valid domains
            }
        }

        return false;
    }

    protected function hasOtpTable(): bool
    {
        try {
            return Schema::hasTable('password_reset_otps');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Resolve active EmailConfiguration for the user or system default.
     */
    protected function resolveEmailConfiguration(?User $user): ?\App\Models\EmailConfiguration
    {
        if (!class_exists(\App\Models\EmailConfiguration::class)) {
            return null;
        }

        try {
            // 1. Try finding configuration for the user's specific tenant
            if ($user && !empty($user->tenant_id)) {
                $account = \App\Models\EmailConfiguration::withoutGlobalScopes()
                    ->where('tenant_id', $user->tenant_id)
                    ->where('is_active', true)
                    ->where('is_default', true)
                    ->first()
                    ?: \App\Models\EmailConfiguration::withoutGlobalScopes()
                    ->where('tenant_id', $user->tenant_id)
                    ->where('is_active', true)
                    ->first();

                if ($account) {
                    return $account;
                }
            }

            // 2. Global fallback to any active default email account in database
            return \App\Models\EmailConfiguration::withoutGlobalScopes()
                ->where('is_active', true)
                ->where('is_default', true)
                ->first()
                ?: \App\Models\EmailConfiguration::withoutGlobalScopes()
                ->where('is_active', true)
                ->first();
        } catch (\Throwable $e) {
            Log::warning('ForgotPasswordOtp: Could not resolve EmailConfiguration: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Dynamically configure SMTP mailer from EmailConfiguration model (as used in Recruitment/EmailService).
     */
    protected function applyEmailConfiguration(?\App\Models\EmailConfiguration $account): void
    {
        if (!$account || empty($account->host) || empty($account->username)) {
            return;
        }

        $fromEmail = $account->email_address;
        $fromName = $account->from_name ?: ($account->name ?: config('app.name', 'ERP Portal'));

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $account->host,
            'mail.mailers.smtp.port' => (int) $account->port,
            'mail.mailers.smtp.encryption' => $account->encryption === 'none' ? null : ($account->encryption ?: 'tls'),
            'mail.mailers.smtp.username' => $account->username,
            'mail.mailers.smtp.password' => $account->password,
            'mail.from.address' => $fromEmail,
            'mail.from.name' => $fromName,
        ]);

        Mail::purge('smtp');
    }

    protected function ensureTableExists(): void
    {
        try {
            if (!Schema::hasTable('password_reset_otps')) {
                Schema::create('password_reset_otps', function (Blueprint $table) {
                    $table->id();
                    $table->string('email')->index();
                    $table->string('otp_hash');
                    $table->string('reset_token')->nullable()->index();
                    $table->unsignedTinyInteger('attempts')->default(0);
                    $table->timestamp('expires_at');
                    $table->timestamp('reset_token_expires_at')->nullable();
                    $table->timestamps();
                });
            }
        } catch (\Throwable) {
            // Silently fallback to Cache if DB permission restricts DDL
        }
    }
}


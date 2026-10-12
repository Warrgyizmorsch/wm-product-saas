<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The ECB publishes reference rates around 16:00 CET on working days.
// Requires the server cron: * * * * * cd <app> && php artisan schedule:run
Schedule::command('accounting:sync-exchange-rates')
    ->weekdays()
    ->dailyAt('17:00')
    ->timezone('Europe/Berlin')
    ->withoutOverlapping();

// Missed Razorpay webhooks, grace-period expiry and ended cancellations.
Schedule::command('billing:reconcile-subscriptions')
    ->hourly()
    ->withoutOverlapping();

Artisan::command('fcm:test {target : User ID, Email, or FCM registration token} {--title=MossiERP Push Notification Test} {--message=Firebase Cloud Messaging is active and working! 🚀}', function ($target) {
    $this->info("Checking Firebase configuration...");

    if (!\App\Services\Firebase\FcmService::isConfigured()) {
        $this->error("Firebase credentials are not configured or missing in storage/app/firebase/firebase_credentials.json");
        $creds = \App\Services\Firebase\FcmService::getCredentials();
        $this->line("Resolved path: " . ($creds['resolved_path'] ?? 'none'));
        return 1;
    }

    $creds = \App\Services\Firebase\FcmService::getCredentials();
    $this->info("✓ Firebase Project ID: " . ($creds['project_id'] ?? 'unknown'));
    $this->info("✓ Client Email: " . ($creds['client_email'] ?? 'unknown'));

    $title = $this->option('title');
    $message = $this->option('message');

    if (is_numeric($target)) {
        $user = \App\Models\User::find((int) $target);
        if (!$user) {
            $this->error("User with ID {$target} not found.");
            return 1;
        }
        $this->info("Sending test notification to User #{$user->id} ({$user->name})...");
        $result = \App\Services\Firebase\FcmService::sendToUser($user, $title, $message, null, ['source' => 'artisan_cli']);
    } elseif (filter_var($target, FILTER_VALIDATE_EMAIL)) {
        $user = \App\Models\User::where('email', $target)->first();
        if (!$user) {
            $this->error("User with email {$target} not found.");
            return 1;
        }
        $this->info("Sending test notification to User {$user->email} ({$user->name})...");
        $result = \App\Services\Firebase\FcmService::sendToUser($user, $title, $message, null, ['source' => 'artisan_cli']);
    } else {
        $this->info("Sending test notification directly to device token...");
        $result = \App\Services\Firebase\FcmService::sendToToken($target, $title, $message, ['source' => 'artisan_cli']);
    }

    if (!empty($result['success']) || (!empty($result['success_count']) && $result['success_count'] > 0)) {
        $this->info("✓ Test push notification sent successfully!");
        $this->line(json_encode($result, JSON_PRETTY_PRINT));
        return 0;
    }

    $this->error("✗ Failed to send push notification.");
    $this->line(json_encode($result, JSON_PRETTY_PRINT));
    return 1;
})->purpose('Send a test Firebase Cloud Messaging (FCM) push notification to a user or token');


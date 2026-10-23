<?php

namespace Tests\Feature\Notification;

use App\Models\User;
use App\Models\UserDeviceToken;
use App\Services\Firebase\FcmService;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FcmPushNotificationTest extends TestCase
{
    use RefreshDatabase, NotificationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareNotificationFixtures();
        $this->actAsTenant($this->tenantA);
    }

    public function test_register_device_token_via_api(): void
    {
        $token = 'fcm_sample_device_token_' . uniqid();

        $response = $this->actingAs($this->userA, 'sanctum')
            ->postJson(route('api.notifications.device-token'), [
                'fcm_token' => $token,
                'device_type' => 'android',
                'device_name' => 'Pixel 8 Pro',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Device token registered successfully.',
            ]);

        // Assert record in user_device_tokens table
        $this->assertDatabaseHas('user_device_tokens', [
            'user_id' => $this->userA->id,
            'fcm_token' => $token,
            'device_type' => 'android',
            'device_name' => 'Pixel 8 Pro',
            'is_active' => 1,
        ]);

        // Assert backward compatibility in user settings
        $this->userA->refresh();
        $settings = $this->userA->settings;
        $this->assertSame($token, $settings['fcm_token'] ?? null);
    }

    public function test_remove_device_token_via_api(): void
    {
        $token = 'fcm_remove_token_' . uniqid();

        // Register token
        UserDeviceToken::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'fcm_token' => $token,
            'device_type' => 'ios',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->userA, 'sanctum')
            ->deleteJson(route('api.notifications.device-token.destroy'), [
                'fcm_token' => $token,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Device token removed successfully.',
            ]);

        $this->assertDatabaseHas('user_device_tokens', [
            'user_id' => $this->userA->id,
            'fcm_token' => $token,
            'is_active' => 0,
        ]);
    }

    public function test_user_get_active_fcm_tokens_returns_all_devices(): void
    {
        $token1 = 'token_android_' . uniqid();
        $token2 = 'token_ios_' . uniqid();

        UserDeviceToken::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'fcm_token' => $token1,
            'device_type' => 'android',
            'is_active' => true,
        ]);

        UserDeviceToken::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'fcm_token' => $token2,
            'device_type' => 'ios',
            'is_active' => true,
        ]);

        $tokens = $this->userA->getActiveFcmTokens();

        $this->assertContains($token1, $tokens);
        $this->assertContains($token2, $tokens);
        $this->assertCount(2, $tokens);
    }

    public function test_notification_service_send_never_fails_even_if_fcm_unconfigured(): void
    {
        // Notification should be saved in database and return normally without exception
        $notification = NotificationService::send(
            user: $this->userA,
            title: 'Test Title',
            message: 'Test Message',
            actionUrl: '/hrms/leaves',
            module: 'hrms',
            type: 'leave'
        );

        $this->assertNotNull($notification);
        $this->assertSame('Test Title', $notification->title);
        $this->assertSame('Test Message', $notification->message);
        $this->assertSame($this->userA->id, $notification->user_id);
    }

    public function test_fcm_service_handles_missing_credentials_gracefully(): void
    {
        config(['firebase.credentials_path' => 'non_existent_file.json']);

        $this->assertFalse(FcmService::isConfigured());

        $res = FcmService::sendToToken('dummy_token', 'Test', 'Body');
        $this->assertFalse($res['success']);
        $this->assertNotNull($res['error']);
    }
}

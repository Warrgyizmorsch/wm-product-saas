<?php

namespace Tests\Feature;

use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAndAccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'                => 'Acme Corporation',
            'slug'                => 'acme-corp',
            'status'              => 'active',
            'plan'                => 'enterprise',
            'subscription_status' => 'active',
            'max_users'           => 50,
            'max_storage_mb'      => 5120,
        ]);

        $this->seed(RbacSeeder::class);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Jane Doe',
            'email'     => 'jane@acme.com',
            'phone'     => '+91 9876543210',
            'password'  => Hash::make('password123'),
            'role'      => 'admin',
        ]);
    }

    private function actingAsTenantUser(): self
    {
        return $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->actingAs($this->user);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_profile_page(): void
    {
        $response = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get(route('profile.show'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function authenticated_user_can_access_profile_page_without_hrms_employee(): void
    {
        $response = $this->actingAsTenantUser()
            ->get(route('profile.show'));

        $response->assertOk();
        $response->assertSee('Jane Doe');
        $response->assertSee('jane@acme.com');
        $response->assertSee('Acme Corporation');
        $response->assertDontSee('Exit & NOC');
        $response->assertDontSee('PIP Plans');
    }

    /** @test */
    public function profile_displays_correctly_with_linked_hrms_employee(): void
    {
        $company = Company::create([
            'tenant_id'    => $this->tenant->id,
            'company_name' => 'Acme Labs',
        ]);

        $employee = Employee::create([
            'tenant_id'              => $this->tenant->id,
            'user_id'                => $this->user->id,
            'company_id'             => $company->id,
            'full_name'              => 'Jane Doe',
            'personal_email'         => 'jane@acme.com',
            'personal_mobile_number' => '+91 9999988888',
            'date_of_joining'        => now(),
            'gender'                 => 'Female',
        ]);

        $response = $this->actingAsTenantUser()
            ->get(route('profile.show'));

        $response->assertOk();
        $response->assertSee('Jane Doe');
    }

    /** @test */
    public function profile_update_modifies_user_and_synchronizes_employee_phone(): void
    {
        $company = Company::create([
            'tenant_id'    => $this->tenant->id,
            'company_name' => 'Acme Labs',
        ]);

        $employee = Employee::create([
            'tenant_id'              => $this->tenant->id,
            'user_id'                => $this->user->id,
            'company_id'             => $company->id,
            'full_name'              => 'Jane Doe',
            'personal_email'         => 'jane@acme.com',
            'personal_mobile_number' => '+91 1111111111',
            'date_of_joining'        => now(),
            'gender'                 => 'Female',
        ]);

        $response = $this->actingAsTenantUser()
            ->put(route('profile.update'), [
                'name'  => 'Jane Updated',
                'email' => 'jane@acme.com',
                'phone' => '+91 8888877777',
            ]);

        $response->assertRedirect(route('profile.show'));
        $response->assertSessionHas('success');

        $this->user->refresh();
        $this->assertEquals('Jane Updated', $this->user->name);
        $this->assertEquals('+91 8888877777', $this->user->phone);

        $employee->refresh();
        $this->assertEquals('+91 8888877777', $employee->personal_mobile_number);
    }

    /** @test */
    public function profile_avatar_upload_works_and_stores_file(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $response = $this->actingAsTenantUser()
            ->put(route('profile.update'), [
                'name'   => 'Jane Doe',
                'email'  => 'jane@acme.com',
                'avatar' => $file,
            ]);

        $response->assertRedirect(route('profile.show'));
        $response->assertSessionHas('success');

        $this->user->refresh();
        $this->assertNotNull($this->user->avatar);
        Storage::disk('public')->assertExists($this->user->avatar);
    }

    /** @test */
    public function password_update_succeeds_with_correct_current_password(): void
    {
        $response = $this->actingAsTenantUser()
            ->put(route('profile.password.update'), [
                'current_password'      => 'password123',
                'password'              => 'newSecurePassword99!',
                'password_confirmation' => 'newSecurePassword99!',
            ]);

        $response->assertRedirect(route('profile.show', ['tab' => 'security']));
        $response->assertSessionHas('success');

        $this->user->refresh();
        $this->assertTrue(Hash::check('newSecurePassword99!', $this->user->password));
    }

    /** @test */
    public function password_update_fails_with_incorrect_current_password(): void
    {
        $response = $this->actingAsTenantUser()
            ->put(route('profile.password.update'), [
                'current_password'      => 'wrong-current-pass',
                'password'              => 'newSecurePassword99!',
                'password_confirmation' => 'newSecurePassword99!',
            ]);

        $response->assertSessionHasErrors('current_password');

        $this->user->refresh();
        $this->assertTrue(Hash::check('password123', $this->user->password));
    }

    /** @test */
    public function password_update_fails_when_confirmation_mismatches(): void
    {
        $response = $this->actingAsTenantUser()
            ->put(route('profile.password.update'), [
                'current_password'      => 'password123',
                'password'              => 'newSecurePassword99!',
                'password_confirmation' => 'differentPassword99!',
            ]);

        $response->assertSessionHasErrors('password');
    }

    /** @test */
    public function authenticated_user_can_access_account_settings_page(): void
    {
        $response = $this->actingAsTenantUser()
            ->get(route('account.settings'));

        $response->assertOk();
        $response->assertSee('Notification Preferences');
        $response->assertSee('In-App Header Bell Alerts');
        $response->assertSee('Subscription & Usage Limits');
        $response->assertSee('Danger Zone');
    }

    /** @test */
    public function notification_preferences_can_be_updated_and_persisted(): void
    {
        $response = $this->actingAsTenantUser()
            ->put(route('account.settings.notifications'), [
                'in_app'   => '1',
                'email'    => '1',
                'whatsapp' => '0',
            ]);

        $response->assertRedirect(route('account.settings', ['tab' => 'notifications']));
        $response->assertSessionHas('success');

        $this->user->refresh();
        $this->assertTrue($this->user->settings['notifications']['in_app']);
        $this->assertTrue($this->user->settings['notifications']['email']);
        $this->assertFalse($this->user->settings['notifications']['whatsapp']);
    }

    /** @test */
    public function user_cannot_mutate_another_users_account(): void
    {
        $otherUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Other User',
            'email'     => 'other@acme.com',
            'password'  => Hash::make('secret123'),
        ]);

        // Attempting to send other user's id in update has no effect on other user
        $response = $this->actingAsTenantUser()
            ->put(route('profile.update'), [
                'id'    => $otherUser->id,
                'name'  => 'Hacked Name',
                'email' => 'jane@acme.com',
            ]);

        $otherUser->refresh();
        $this->assertEquals('Other User', $otherUser->name);

        $this->user->refresh();
        $this->assertEquals('Hacked Name', $this->user->name);
    }

    /** @test */
    public function header_navigation_links_to_profile_and_account_settings(): void
    {
        $response = $this->actingAsTenantUser()
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(route('profile.show'), false);
        $response->assertSee(route('account.settings'), false);
    }

    /** @test */
    public function user_account_can_be_safely_deactivated(): void
    {
        // Add another admin so tenant isn't abandoned
        User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Second Admin',
            'email'     => 'admin2@acme.com',
            'password'  => Hash::make('pass123'),
            'role'      => 'admin',
        ]);

        $response = $this->actingAsTenantUser()
            ->delete(route('account.settings.delete'), [
                'current_password' => 'password123',
                'confirmation'     => 'DEACTIVATE',
            ]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();

        $this->user->refresh();
        $this->assertTrue($this->user->settings['is_deactivated']);
    }

    /** @test */
    public function user_account_can_be_deactivated_with_lowercase_or_untrimmed_confirmation(): void
    {
        User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Second Admin',
            'email'     => 'admin2@acme.com',
            'password'  => Hash::make('pass123'),
            'role'      => 'admin',
        ]);

        $response = $this->actingAsTenantUser()
            ->delete(route('account.settings.delete'), [
                'current_password' => 'password123',
                'confirmation'     => '  deactivate  ',
            ]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();

        $this->user->refresh();
        $this->assertTrue($this->user->settings['is_deactivated']);
    }

    /** @test */
    public function deactivation_fails_with_clear_error_message_when_confirmation_is_invalid(): void
    {
        $response = $this->actingAsTenantUser()
            ->delete(route('account.settings.delete'), [
                'current_password' => 'password123',
                'confirmation'     => 'DELETE',
            ]);

        $response->assertRedirect(route('account.settings', ['tab' => 'danger']));
        $response->assertSessionHasErrors(['confirmation' => 'Please type DEACTIVATE in capital letters to confirm account deactivation.']);
    }

    /** @test */
    public function deactivated_user_cannot_log_in(): void
    {
        $this->user->update([
            'settings' => array_merge($this->user->settings ?? [], ['is_deactivated' => true]),
        ]);

        $response = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->post(route('login'), [
                'email'    => $this->user->email,
                'password' => 'password123',
            ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}


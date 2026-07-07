<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_user_can_switch_to_bulgarian_locale(): void
    {
        $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->from('/dashboard')
            ->get('/locale/bg')
            ->assertRedirect('/dashboard')
            ->assertSessionHas('locale', 'bg');

        $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->withSession(['locale' => 'bg'])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('lang="bg"', false)
            ->assertSee('Управленско табло');
    }

    public function test_user_can_switch_to_hindi_locale(): void
    {
        $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->from('/dashboard')
            ->get('/locale/hi')
            ->assertRedirect('/dashboard')
            ->assertSessionHas('locale', 'hi');

        $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->withSession(['locale' => 'hi'])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('lang="hi"', false)
            ->assertSee('कार्यकारी डैशबोर्ड');
    }

    public function test_unsupported_locale_is_not_found(): void
    {
        $this->get('/locale/fr')->assertNotFound();
    }

    public function test_duralux_pages_render_the_language_switcher(): void
    {
        $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('nxl-header-language')
            ->assertSee(route('locale.switch', 'en'))
            ->assertSee(route('locale.switch', 'bg'))
            ->assertSee(route('locale.switch', 'hi'));
    }
}

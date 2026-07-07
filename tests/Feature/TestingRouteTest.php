<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestingRouteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test if the testing page returns a successful response.
     */
    public function test_testing_page_returns_successful_response(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get('/ui-elements');

        $response->assertStatus(200);
    }
}

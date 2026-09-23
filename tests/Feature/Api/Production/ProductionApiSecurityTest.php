<?php

namespace Tests\Feature\Api\Production;

use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $authorizedUser;
    private User $unauthorizedUser;
    private string $validSecret = 'wm-production-secret-test-key';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Acme Manufacturing',
            'slug' => 'acme-mfg',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->authorizedUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Authorized Planner',
            'email' => 'planner@acme.test',
            'password' => 'password',
        ]);

        $this->unauthorizedUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Unauthorized Guest',
            'email' => 'guest@acme.test',
            'password' => 'password',
        ]);

        $this->seed(RbacSeeder::class);

        $productionManagerRole = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create([
            'user_id' => $this->authorizedUser->id,
            'role_id' => $productionManagerRole->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    private function headers(User $user, ?string $secret = null): array
    {
        $headers = [
            'Accept' => 'application/json',
            'X-Tenant' => $this->tenant->slug,
            'Authorization' => 'Bearer ' . $user->createToken('test-token')->plainTextToken,
        ];

        if ($secret !== null) {
            $headers['X-API-SECRET'] = $secret;
        }

        return $headers;
    }

    public function test_missing_api_secret_returns_401_with_generic_denial(): void
    {
        // No X-API-SECRET header provided
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Tenant' => $this->tenant->slug,
            'Authorization' => 'Bearer ' . $this->authorizedUser->createToken('test-token')->plainTextToken,
        ])->getJson('/api/v1/production/orders');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'API access denied.',
            ]);
    }

    public function test_invalid_api_secret_returns_401_with_identical_generic_message(): void
    {
        $response = $this->withHeaders($this->headers($this->authorizedUser, 'completely-invalid-secret-key'))
            ->getJson('/api/v1/production/orders');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'API access denied.',
            ]);
    }

    public function test_valid_secret_without_bearer_token_returns_401(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Tenant' => $this->tenant->slug,
            'X-API-SECRET' => $this->validSecret,
        ])->getJson('/api/v1/production/orders');

        $response->assertStatus(401);
    }

    public function test_valid_secret_with_invalid_bearer_token_returns_401(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Tenant' => $this->tenant->slug,
            'X-API-SECRET' => $this->validSecret,
            'Authorization' => 'Bearer invalid-sanctum-token-12345',
        ])->getJson('/api/v1/production/orders');

        $response->assertStatus(401);
    }

    public function test_valid_secret_and_valid_token_with_permissions_succeeds(): void
    {
        $response = $this->withHeaders($this->headers($this->authorizedUser, $this->validSecret))
            ->getJson('/api/v1/production/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta',
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_authenticated_user_lacking_permission_returns_403(): void
    {
        $response = $this->withHeaders($this->headers($this->unauthorizedUser, $this->validSecret))
            ->postJson('/api/v1/production/orders', [
                'product_id' => 1,
                'bom_id' => 1,
                'quantity' => 10,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }
}

<?php

namespace Tests\Feature;

use App\Domains\Visitor\Models\Visitor;
use App\Domains\Visitor\Models\VisitorPass;
use App\Domains\Visitor\Services\VisitorService;
use App\Exports\VisitorPassExport;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Concerns\FromQuery;
use Tests\TestCase;

class VisitorManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_overstay_calculation_returns_positive_elapsed_difference(): void
    {
        $pass = new VisitorPass([
            'status'                    => 'Checked-In',
            'check_in_at'               => now()->subMinutes(90),
            'expected_duration_minutes' => 60,
        ]);

        $this->assertTrue($pass->isOverstayed(), 'Pass checked in 90 mins ago with 60 min duration should be flagged overstayed.');

        $recentPass = new VisitorPass([
            'status'                    => 'Checked-In',
            'check_in_at'               => now()->subMinutes(20),
            'expected_duration_minutes' => 60,
        ]);

        $this->assertFalse($recentPass->isOverstayed(), 'Pass checked in 20 mins ago with 60 min duration should not be overstayed.');
    }

    public function test_export_sanitizes_csv_formula_injection(): void
    {
        $export = new VisitorPassExport(1, []);
        $pass = new VisitorPass([
            'pass_number' => '=1+1',
            'purpose'     => '+SUM(A1:A10)',
            'notes'       => '-cmd| /C calc',
        ]);

        $mapped = $export->map($pass);
        foreach ($mapped as $cell) {
            if (is_string($cell) && strlen($cell) > 1 && in_array($cell[0], ['=', '+', '-', '@'])) {
                $this->fail("Cell was not sanitized against formula injection: {$cell}");
            }
        }

        $this->assertTrue(true);
    }

    public function test_checked_out_pass_is_never_considered_overstayed(): void
    {
        $pass = new VisitorPass([
            'status'                    => 'Checked-Out',
            'check_in_at'               => now()->subHours(5),
            'check_out_at'              => now()->subHours(4),
            'expected_duration_minutes' => 60,
        ]);

        $this->assertFalse($pass->isOverstayed(), 'Checked-Out pass must never be flagged as overstayed.');
    }

    public function test_export_uses_from_query_for_chunked_streaming(): void
    {
        $export = new VisitorPassExport(1, []);
        $this->assertInstanceOf(FromQuery::class, $export);
    }

    public function test_broadcasting_auth_enforces_user_and_tenant_channel_isolation(): void
    {
        config()->set('services.pusher.app_key', 'test-key');
        config()->set('services.pusher.app_secret', 'test-secret');

        $tenant1 = Tenant::create(['name' => 'T1', 'slug' => 't1-vis', 'status' => 'active', 'plan' => 'enterprise']);
        $tenant2 = Tenant::create(['name' => 'T2', 'slug' => 't2-vis', 'status' => 'active', 'plan' => 'enterprise']);

        $user1 = User::create([
            'tenant_id' => $tenant1->id,
            'name'      => 'User One',
            'email'     => 'u1@vis.test',
            'password'  => bcrypt('secret'),
        ]);

        // Own user channel -> 200 with signed auth
        $resOwn = $this->actingAs($user1)
            ->withHeader('X-Tenant', 't1-vis')
            ->postJson('/broadcasting/auth', [
                'socket_id'    => '1234.5678',
                'channel_name' => "private-user-{$user1->id}",
            ]);
        $resOwn->assertOk()->assertJsonStructure(['auth']);

        // Other user channel -> 403
        $resOtherUser = $this->actingAs($user1)
            ->withHeader('X-Tenant', 't1-vis')
            ->postJson('/broadcasting/auth', [
                'socket_id'    => '1234.5678',
                'channel_name' => 'private-user-999999',
            ]);
        $resOtherUser->assertForbidden();

        // Own tenant channel -> 200
        $resOwnTenant = $this->actingAs($user1)
            ->withHeader('X-Tenant', 't1-vis')
            ->postJson('/broadcasting/auth', [
                'socket_id'    => '1234.5678',
                'channel_name' => "private-visitor-tenant-{$tenant1->id}",
            ]);
        $resOwnTenant->assertOk()->assertJsonStructure(['auth']);

        // Other tenant channel -> 403
        $resOtherTenant = $this->actingAs($user1)
            ->withHeader('X-Tenant', 't1-vis')
            ->postJson('/broadcasting/auth', [
                'socket_id'    => '1234.5678',
                'channel_name' => "private-visitor-tenant-{$tenant2->id}",
            ]);
        $resOtherTenant->assertForbidden();
    }

    public function test_photo_url_rejects_javascript_uri_scheme(): void
    {
        $tenant = Tenant::create(['name' => 'TPhoto', 'slug' => 'tphoto-vis', 'status' => 'active', 'plan' => 'enterprise']);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name'      => 'Admin',
            'email'     => 'admin@tphoto.test',
            'password'  => bcrypt('secret'),
        ]);

        $ownerRole = \App\Models\Access\Role::whereNull('tenant_id')->where('slug', 'tenant_owner')->first();
        if ($ownerRole) {
            \App\Models\Access\UserRole::create([
                'user_id'   => $admin->id,
                'role_id'   => $ownerRole->id,
                'tenant_id' => $tenant->id,
            ]);
        }

        $res = $this->actingAs($admin)
            ->withHeader('X-Tenant', 'tphoto-vis')
            ->postJson('/visitor', [
                'full_name' => 'XSS Visitor',
                'phone'     => '9998887776',
                'purpose'   => 'Meeting',
                'photo_url' => 'javascript:alert(1)',
            ]);

        $res->assertStatus(422)->assertJsonValidationErrors(['photo_url']);
    }

    public function test_base64_photo_url_is_stored_as_file_on_disk_not_raw_db_bloat(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $tenant = Tenant::create(['name' => 'TB64', 'slug' => 'tb64-vis', 'status' => 'active', 'plan' => 'enterprise']);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name'      => 'Admin',
            'email'     => 'admin@tb64.test',
            'password'  => bcrypt('secret'),
        ]);

        $ownerRole = \App\Models\Access\Role::whereNull('tenant_id')->where('slug', 'tenant_owner')->first();
        if ($ownerRole) {
            \App\Models\Access\UserRole::create([
                'user_id'   => $admin->id,
                'role_id'   => $ownerRole->id,
                'tenant_id' => $tenant->id,
            ]);
        }

        $png1x1 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

        $res = $this->actingAs($admin)
            ->withHeader('X-Tenant', 'tb64-vis')
            ->postJson('/visitor', [
                'full_name' => 'Webcam Visitor',
                'phone'     => '9998887700',
                'purpose'   => 'Meeting',
                'photo_url' => $png1x1,
            ]);

        $res->assertOk();
        $visitor = Visitor::where('tenant_id', $tenant->id)->where('phone', '9998887700')->firstOrFail();
        $this->assertStringStartsWith("/storage/visitors/{$tenant->id}/", (string)$visitor->photo_url);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists(str_replace('/storage/', '', $visitor->photo_url));
    }
}

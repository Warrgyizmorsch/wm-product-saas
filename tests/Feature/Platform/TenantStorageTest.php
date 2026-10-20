<?php

namespace Tests\Feature\Platform;

use App\Domains\Platform\Exceptions\UsageLimitExceededException;
use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Models\TenantFile;
use App\Domains\Platform\Services\TenantStorage;
use App\Domains\Platform\Services\UsageLimitService;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantStorageTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->plan = Plan::create([
            'name' => 'Small', 'slug' => 'small', 'price' => 0, 'currency' => 'INR', 'billing_cycle' => 'monthly',
            'features' => ['hrms'], 'max_storage_mb' => 1, 'is_active' => true,
        ]);
        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'small', 'plan_id' => $this->plan->id]);
    }

    private function upload(int $kb, ?int $tenantId = null): string|false
    {
        app()->forgetScopedInstances();

        return UploadedFile::fake()->create("file{$kb}.pdf", $kb)->storeTenantFile('docs', ['disk' => 'public', 'tenant_id' => $tenantId ?? $this->tenant->id]);
    }

    private function used(): int
    {
        return app(TenantStorage::class)->usedBytes($this->tenant->fresh());
    }

    public function test_an_upload_is_stored_and_counted(): void
    {
        $path = $this->upload(300);

        Storage::disk('public')->assertExists($path);
        $this->assertSame(300 * 1024, $this->used());
    }

    public function test_an_upload_past_the_allowance_is_refused_and_not_stored(): void
    {
        $this->upload(700);

        try {
            $this->upload(400);
            $this->fail('Expected the upload to be refused.');
        } catch (UsageLimitExceededException $e) {
            $this->assertStringContainsString('1 MB of file storage is full', $e->getMessage());
        }

        $this->assertCount(1, Storage::disk('public')->allFiles('docs'));
        $this->assertSame(700 * 1024, $this->used());
    }

    public function test_the_tenants_own_allowance_overrides_the_plans(): void
    {
        $this->tenant->update(['max_storage_mb' => 2]);

        $this->upload(700);
        $this->upload(700);

        $this->assertSame(1400 * 1024, $this->used());
    }

    public function test_no_allowance_means_unlimited(): void
    {
        $this->plan->update(['max_storage_mb' => null]);

        $this->upload(900);
        $this->upload(900);

        $this->assertNull(app(TenantStorage::class)->usage($this->tenant->fresh())['limit']);
    }

    public function test_generated_content_is_counted_and_an_overwrite_counts_once(): void
    {
        $storage = app(TenantStorage::class);

        $storage->put('public', 'pdf/a.pdf', str_repeat('x', 1000), $this->tenant->id);
        $storage->put('public', 'pdf/a.pdf', str_repeat('x', 1500), $this->tenant->id);

        $this->assertSame(1500, $this->used());
    }

    public function test_sync_drops_deleted_files_and_backfills_old_uploads(): void
    {
        $tracked = $this->upload(10);
        Storage::disk('public')->delete($tracked);

        // An upload from before tracking existed, referenced by a row with a tenant.
        Storage::disk('public')->put('documents/old.pdf', str_repeat('y', 2048));
        config(['tenant_storage.sources' => [['table' => 'legacy_docs', 'column' => 'file_path']]]);
        \Illuminate\Support\Facades\Schema::create('legacy_docs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('file_path');
        });
        DB::table('legacy_docs')->insert(['tenant_id' => $this->tenant->id, 'file_path' => 'storage/documents/old.pdf']);

        $this->artisan('tenant:storage-sync')->assertSuccessful();

        $this->assertSame(2048, $this->used());
        $this->assertSame(1, TenantFile::query()->withoutGlobalScope('tenant')->count());

        // Re-running records nothing twice.
        $this->artisan('tenant:storage-sync')->assertSuccessful();
        $this->assertSame(1, TenantFile::query()->withoutGlobalScope('tenant')->count());
    }

    /** Storage::fake() bypasses custom drivers, so use the real (tracked) local driver on a temp root. */
    private function useRealPublicDisk(): string
    {
        $root = sys_get_temp_dir().'/tenant-storage-'.uniqid();
        config(['filesystems.disks.public.root' => $root]);
        Storage::forgetDisk('public');

        return $root;
    }

    public function test_deleting_a_file_drops_it_from_usage_immediately(): void
    {
        $root = $this->useRealPublicDisk();

        $keep = $this->upload(100);
        $gone = $this->upload(200);
        $this->assertSame(300 * 1024, $this->used());

        Storage::disk('public')->delete($gone);

        $this->assertSame(100 * 1024, $this->used());
        $this->assertTrue(Storage::disk('public')->exists($keep));

        \Illuminate\Support\Facades\File::deleteDirectory($root);
    }

    public function test_deleting_a_directory_and_moving_a_file_keep_usage_right(): void
    {
        $root = $this->useRealPublicDisk();

        $path = $this->upload(100);
        Storage::disk('public')->move($path, 'archive/moved.pdf');
        $this->assertSame(1, TenantFile::query()->withoutGlobalScope('tenant')->where('path', 'archive/moved.pdf')->count());

        $this->upload(50);
        Storage::disk('public')->deleteDirectory('docs');

        $this->assertSame(100 * 1024, $this->used()); // only the moved file is left

        \Illuminate\Support\Facades\File::deleteDirectory($root);
    }

    public function test_deleting_a_product_removes_its_image_files_and_their_usage(): void
    {
        $root = $this->useRealPublicDisk();
        $product = \App\Domains\Inventory\Models\Product::query()->forceCreate(['tenant_id' => $this->tenant->id, 'name' => 'Chair', 'sku' => 'CH-1']);

        UploadedFile::fake()->create('main.png', 200)
            ->storeTenantFileAs("uploads/tenants/{$this->tenant->id}/products/{$product->id}", 'main.png', ['disk' => 'public', 'tenant_id' => $this->tenant->id]);
        $this->assertSame(200 * 1024, $this->used());

        $product->delete();

        $this->assertFalse(Storage::disk('public')->exists("uploads/tenants/{$this->tenant->id}/products/{$product->id}"));
        $this->assertSame(0, $this->used());

        \Illuminate\Support\Facades\File::deleteDirectory($root);
    }

    public function test_the_platform_console_includes_storage(): void
    {
        $this->upload(512);

        $row = app(UsageLimitService::class)->overview()->firstWhere('tenant.id', $this->tenant->id);

        $this->assertEquals(50, $row['storage']['percent']);
        $this->assertEquals(50, $row['worst_percent']);
    }
}

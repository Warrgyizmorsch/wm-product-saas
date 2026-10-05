<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Upload status for GST returns (Tally's "Upload GST Returns"):
 *  - gst_return_filings: each export/upload action, with the JSON it produced.
 *  - gst_return_documents: which invoice / credit note has been uploaded for
 *    which return period, and exactly what was reported (payload), so later
 *    edits show as "modified" and cancellations can be sent as deletions.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'accounting.gst_returns.view' => 'view',
        'accounting.gst_returns.file' => 'file',
    ];

    public function up(): void
    {
        Schema::create('gst_return_filings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('return_type', 10);
            $table->string('return_period', 6); // MMYYYY, as the portal's "fp"
            $table->string('gstin', 15)->nullable();
            $table->string('action', 20); // export_offline | mark_uploaded
            $table->unsignedInteger('voucher_count')->default(0);
            $table->unsignedInteger('delete_count')->default(0);
            $table->string('file_name')->nullable();
            $table->longText('payload')->nullable();
            $table->json('totals')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'return_type', 'return_period'], 'grf_tenant_period_idx');
        });

        Schema::create('gst_return_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('return_type', 10);
            $table->string('return_period', 6);
            $table->string('document_type', 20); // invoice | credit_note
            $table->unsignedBigInteger('document_id');
            $table->string('document_number')->nullable();
            $table->string('section', 10); // b2b, b2cl, b2cs, cdnr, cdnur
            $table->string('status', 20); // uploaded | delete_requested | deleted
            $table->string('fingerprint', 40)->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('gst_return_filing_id')->nullable()->constrained('gst_return_filings')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'return_type', 'document_type', 'document_id'], 'grd_document_unique');
            $table->index(['tenant_id', 'return_type', 'return_period'], 'grd_tenant_period_idx');
        });

        $this->grantPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('gst_return_documents');
        Schema::dropIfExists('gst_return_filings');

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');
            DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }
    }

    /** Existing installs don't re-run RbacSeeder, so add the permissions and grants here. */
    private function grantPermissions(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $now = now();
        $grants = [
            'accounting.gst_returns.view' => ['tenant_owner', 'company_admin', 'accountant', 'auditor'],
            'accounting.gst_returns.file' => ['tenant_owner', 'company_admin', 'accountant'],
        ];

        foreach (self::PERMISSIONS as $name => $action) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['module' => 'accounting', 'entity' => 'gst_returns', 'action' => $action, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            );
            $permissionId = DB::table('permissions')->where('name', $name)->value('id');

            foreach (DB::table('roles')->whereIn('slug', $grants[$name])->pluck('id') as $roleId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId, 'scope' => 'tenant'],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }

            $superAdmin = DB::table('roles')->where('slug', 'super_admin')->value('id');
            if ($superAdmin) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $superAdmin, 'permission_id' => $permissionId, 'scope' => 'platform'],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }
    }
};

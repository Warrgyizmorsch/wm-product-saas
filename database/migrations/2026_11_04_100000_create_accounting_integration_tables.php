<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Accounting integration kit: two-way sync with external books (Tally first).
 *
 * - accounting_integration_connections: one external company's link to this tenant.
 * - accounting_integration_refs: external id <-> local record, which is what makes
 *   a re-sync update or skip instead of duplicating.
 * - accounting_integration_batches / _records: the sync log, with retry.
 * - accounting_integration_conflicts: anything a person has to decide.
 * - accounting_integration_agent_jobs: work queued for the on-premise relay.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'accounting.integrations.view' => ['entity' => 'integrations', 'action' => 'view'],
        'accounting.integrations.manage' => ['entity' => 'integrations', 'action' => 'manage'],
        'accounting.integrations.sync' => ['entity' => 'integrations', 'action' => 'sync'],
        'accounting.integrations.resolve' => ['entity' => 'integrations', 'action' => 'resolve'],
    ];

    public function up(): void
    {
        Schema::create('accounting_integration_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name');
            $table->string('product', 30); // tally, custom (busy, marg later)
            $table->string('external_company')->nullable(); // Tally's SVCURRENTCOMPANY
            $table->string('status', 20)->default('active');
            $table->json('settings')->nullable();
            $table->timestamp('last_agent_seen_at')->nullable();
            $table->string('agent_version', 40)->nullable();
            $table->timestamp('last_planned_at')->nullable();
            $table->timestamp('first_inbound_completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('accounting_integration_refs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('accounting_integration_connections')->cascadeOnDelete();
            $table->string('direction', 10); // inbound, outbound: which side the record started on
            $table->string('entity_type', 30); // ledger_group, ledger, voucher
            $table->string('external_id', 191);
            $table->string('external_name')->nullable();
            $table->string('external_number', 191)->nullable();
            $table->date('external_date')->nullable();
            $table->string('local_type', 40)->nullable(); // ledger_group, chart_of_account, customer, vendor, employee, transporter, journal
            $table->unsignedBigInteger('local_id')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->string('local_hash', 64)->nullable();
            $table->json('meta')->nullable();
            $table->string('status', 20)->default('synced'); // pending, synced, failed
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['connection_id', 'entity_type', 'external_id'], 'acct_int_refs_external_unique');
            $table->index(['connection_id', 'entity_type', 'local_type', 'local_id'], 'acct_int_refs_local_index');
            $table->index(['connection_id', 'entity_type', 'external_name'], 'acct_int_refs_name_index');
            $table->index(['connection_id', 'entity_type', 'external_date'], 'acct_int_refs_date_index');
        });

        Schema::create('accounting_integration_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('accounting_integration_connections')->cascadeOnDelete();
            $table->string('direction', 10);
            $table->string('entity_type', 30);
            $table->string('channel', 20)->default('api'); // api, agent, file
            $table->string('idempotency_key', 191)->nullable();
            $table->string('request_hash', 64)->nullable();
            $table->string('status', 30)->default('processing');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('unchanged_count')->default(0);
            $table->unsignedInteger('pending_approval_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('conflict_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['connection_id', 'idempotency_key'], 'acct_int_batches_idem_unique');
        });

        Schema::create('accounting_integration_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('accounting_integration_batches')->cascadeOnDelete();
            $table->string('entity_type', 30);
            $table->string('external_id', 191)->nullable();
            $table->string('local_type', 40)->nullable();
            $table->unsignedBigInteger('local_id')->nullable();
            $table->string('outcome', 30); // created, updated, unchanged, pending_approval, failed, conflict, sent
            $table->string('error_code', 50)->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('attempts')->default(1);
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['batch_id', 'outcome']);
        });

        Schema::create('accounting_integration_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('accounting_integration_connections')->cascadeOnDelete();
            $table->foreignId('ref_id')->nullable()->constrained('accounting_integration_refs')->nullOnDelete();
            $table->string('entity_type', 30);
            $table->string('external_id', 191);
            $table->string('reason', 50);
            $table->text('detail')->nullable();
            $table->json('incoming_payload')->nullable();
            $table->string('incoming_hash', 64)->nullable();
            $table->string('status', 20)->default('open'); // open, resolved
            $table->string('resolution', 30)->nullable(); // apply_incoming, keep_local
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['connection_id', 'status']);
            $table->index(['connection_id', 'entity_type', 'external_id'], 'acct_int_conflicts_external_index');
        });

        Schema::create('accounting_integration_agent_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('accounting_integration_connections')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('accounting_integration_batches')->nullOnDelete();
            $table->string('kind', 30); // pull_groups, pull_ledgers, pull_vouchers, push_ledger, push_voucher
            $table->json('params')->nullable();
            $table->longText('request_xml')->nullable();
            $table->string('status', 20)->default('queued'); // queued, leased, completed, failed
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('leased_until')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['connection_id', 'status', 'id'], 'acct_int_jobs_queue_index');
        });

        $this->grantPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_integration_agent_jobs');
        Schema::dropIfExists('accounting_integration_conflicts');
        Schema::dropIfExists('accounting_integration_records');
        Schema::dropIfExists('accounting_integration_batches');
        Schema::dropIfExists('accounting_integration_refs');
        Schema::dropIfExists('accounting_integration_connections');

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');
            DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }
    }

    /** Existing installs don't re-run RbacSeeder. Owner and admin only, plus platform. */
    private function grantPermissions(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $now = now();

        foreach (self::PERMISSIONS as $name => $definition) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['module' => 'accounting'] + $definition + ['is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            );
            $permissionId = DB::table('permissions')->where('name', $name)->value('id');

            foreach (DB::table('roles')->whereIn('slug', ['tenant_owner', 'company_admin'])->pluck('id') as $roleId) {
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const DEFAULT_ROLES = [
        'Decision Maker',
        'Architect',
        'Accounts',
        'Purchase',
        'Project Coordinator',
        'Site Receiver',
        'Sales',
        'Dispatch',
        'Quality',
        'Technical Evaluator',
        'Influencer',
        'End User',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('party_branches')) {
            Schema::create('party_branches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();

                $table->string('party_type', 20); // customer | vendor
                $table->unsignedBigInteger('party_id');
                $table->string('name');
                $table->string('code', 50);

                $table->string('branch_type', 30)->default('branch'); // head_office | branch | site | warehouse | billing | delivery
                $table->string('gstin', 20)->nullable();
                $table->string('gst_state_code', 2)->nullable();
                $table->text('address_line1')->nullable();
                $table->text('address_line2')->nullable();
                $table->string('city', 100)->nullable();
                $table->string('state', 100)->nullable();
                $table->string('country', 100)->nullable();
                $table->string('postal_code', 20)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('email', 255)->nullable();

                $table->boolean('is_billing')->default(true);
                $table->boolean('is_shipping')->default(true);
                $table->boolean('is_default_billing')->default(false);
                $table->boolean('is_default_shipping')->default(false);
                $table->boolean('is_head_office')->default(false);
                $table->string('status', 20)->default('active');

                $table->string('verification_status', 30)->default('verified');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('verified_by')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->text('verification_remarks')->nullable();
                $table->json('verification_checks')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['party_type', 'party_id', 'code'], 'party_branches_party_code_unique');
                $table->index(['party_type', 'party_id', 'status'], 'party_branches_party_status_idx');
                $table->index(['tenant_id', 'party_type', 'party_id'], 'party_branches_tenant_party_idx');
            });
        }

        if (! Schema::hasTable('contacts')) {
            Schema::create('contacts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();

                $table->string('party_type', 20); // customer | vendor | crm_account
                $table->unsignedBigInteger('party_id');
                $table->foreignId('party_branch_id')->nullable()->constrained('party_branches')->nullOnDelete();
                $table->unsignedBigInteger('legacy_crm_contact_id')->nullable()->index();

                $table->string('name');
                $table->string('designation', 150)->nullable();
                $table->string('email', 255)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('mobile', 30)->nullable();
                $table->boolean('is_primary')->default(false);
                $table->string('status', 20)->default('active');

                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'party_type', 'party_id'], 'contacts_tenant_party_idx');
                $table->index(['party_type', 'party_id', 'status'], 'contacts_party_status_idx');
            });
        }

        if (! Schema::hasTable('contact_roles')) {
            Schema::create('contact_roles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name', 100);
                $table->string('slug', 100);
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'slug'], 'contact_roles_tenant_slug_unique');
            });

            $now = now();
            foreach (self::DEFAULT_ROLES as $roleName) {
                $slug = Str::slug($roleName, '_');
                $exists = DB::table('contact_roles')
                    ->whereNull('tenant_id')
                    ->where('slug', $slug)
                    ->exists();
                if (! $exists) {
                    DB::table('contact_roles')->insert([
                        'tenant_id' => null,
                        'name' => $roleName,
                        'slug' => $slug,
                        'is_system' => true,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        if (! Schema::hasTable('contact_role_assignments')) {
            Schema::create('contact_role_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
                $table->foreignId('contact_role_id')->constrained('contact_roles')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['contact_id', 'contact_role_id'], 'contact_role_assignments_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_role_assignments');
        Schema::dropIfExists('contact_roles');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('party_branches');
    }
};

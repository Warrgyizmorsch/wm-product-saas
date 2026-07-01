<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedSmallInteger('level')->default(100);
            $table->boolean('is_system')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'level']);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('module')->index();
            $table->string('entity')->nullable()->index();
            $table->string('action')->index();
            $table->boolean('is_system')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['module', 'entity', 'action']);
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('permission_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('scope', 32)->default('tenant');
            $table->timestamps();

            $table->unique(['role_id', 'permission_id', 'scope']);
            $table->index(['permission_id', 'scope']);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('role_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('department_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'tenant_id']);
            $table->index(['role_id', 'tenant_id']);
            $table->index(['branch_id', 'department_id']);
        });

        Schema::create('user_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('permission_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('department_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('scope', 32)->default('tenant');
            $table->boolean('allowed')->default(true);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'tenant_id']);
            $table->index(['permission_id', 'scope']);
            $table->index(['allowed', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission_overrides');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};

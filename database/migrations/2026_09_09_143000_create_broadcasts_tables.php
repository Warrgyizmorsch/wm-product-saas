<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('broadcast_number', 50)->index(); // e.g. BC-2026-0001
            $table->string('title');
            $table->enum('category', ['announcement', 'policy_update', 'event', 'emergency', 'news'])->default('announcement');
            $table->enum('priority', ['normal', 'important', 'urgent'])->default('normal');
            $table->longText('content');
            $table->enum('target_type', ['all', 'department', 'branch', 'designation', 'specific_employees'])->default('all');
            $table->json('target_ids')->nullable(); // Target department/branch/employee IDs
            $table->string('attachment_path', 500)->nullable();
            $table->string('banner_image_path', 500)->nullable();
            $table->boolean('is_acknowledgement_required')->default(false);
            $table->boolean('allow_comments')->default(true);
            $table->boolean('send_email')->default(false);
            $table->boolean('send_push')->default(false);
            $table->boolean('show_banner')->default(true);
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'published', 'expired', 'archived'])->default('draft');
            $table->unsignedBigInteger('created_by_user_id');
            $table->timestamps();

            $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('broadcast_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('broadcast_id')->index();
            $table->unsignedBigInteger('employee_id')->index();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->dateTime('acknowledged_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('device_type', 100)->nullable();
            $table->timestamps();

            $table->foreign('broadcast_id')->references('id')->on('broadcasts')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->unique(['broadcast_id', 'employee_id']);
        });

        Schema::create('broadcast_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('broadcast_id')->index();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->text('comment_text');
            $table->boolean('is_pinned')->default(false);
            $table->enum('status', ['published', 'hidden', 'deleted'])->default('published');
            $table->timestamps();

            $table->foreign('broadcast_id')->references('id')->on('broadcasts')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('broadcast_comments')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcast_comments');
        Schema::dropIfExists('broadcast_receipts');
        Schema::dropIfExists('broadcasts');
    }
};

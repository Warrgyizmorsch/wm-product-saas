<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('name');
            $table->string('code')->index();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('default_agent_id')->nullable()->index();
            $table->boolean('is_confidential')->default(false);
            $table->integer('default_sla_hours')->default(24);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::create('helpdesk_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('ticket_number')->unique();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('category_id')->index();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'pending_employee', 'resolved', 'closed'])->default('open');
            $table->string('subject');
            $table->text('description');
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->boolean('is_confidential')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('helpdesk_categories')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('employees')->onDelete('set null');
        });

        Schema::create('helpdesk_ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('ticket_id')->index();
            $table->unsignedBigInteger('sender_id')->index(); // Employee ID
            $table->text('message');
            $table->boolean('is_internal_note')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('ticket_id')->references('id')->on('helpdesk_tickets')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('employees')->onDelete('cascade');
        });

        Schema::create('helpdesk_ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id')->index();
            $table->unsignedBigInteger('reply_id')->nullable()->index();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('helpdesk_tickets')->onDelete('cascade');
            $table->foreign('reply_id')->references('id')->on('helpdesk_ticket_replies')->onDelete('cascade');
        });

        Schema::create('helpdesk_kb_articles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('title');
            $table->string('slug');
            $table->longText('content');
            $table->unsignedInteger('view_count')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('helpdesk_categories')->onDelete('set null');
        });

        Schema::create('helpdesk_satisfaction_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id')->unique();
            $table->tinyInteger('rating');
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('helpdesk_tickets')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_satisfaction_ratings');
        Schema::dropIfExists('helpdesk_kb_articles');
        Schema::dropIfExists('helpdesk_ticket_attachments');
        Schema::dropIfExists('helpdesk_ticket_replies');
        Schema::dropIfExists('helpdesk_tickets');
        Schema::dropIfExists('helpdesk_categories');
    }
};

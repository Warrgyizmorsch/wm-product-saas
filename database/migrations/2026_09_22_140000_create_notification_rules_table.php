<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notification_rules')) {
            Schema::create('notification_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                
                $table->string('name');
                $table->string('event_key')->index();
                $table->string('module')->default('system')->index();
                $table->text('description')->nullable();

                // Recipient Mappings
                $table->json('recipient_roles')->nullable(); // e.g. ["Store Manager", "Accountant"]
                $table->json('recipient_user_ids')->nullable(); // e.g. [1, 2, 5]
                $table->boolean('notify_creator')->default(false); // Notify user who created the record
                $table->boolean('notify_assigned_user')->default(false); // Notify assigned executive

                // In-App Header Bell Notification Template
                $table->string('title_template');
                $table->text('body_template');
                $table->string('action_route')->nullable();
                $table->string('icon_class')->default('feather-bell');

                $table->boolean('is_active')->default(true)->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_rules');
    }
};

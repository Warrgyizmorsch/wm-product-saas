<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add columns to purchase_requisitions if not existing
        if (Schema::hasTable('purchase_requisitions')) {
            Schema::table('purchase_requisitions', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_requisitions', 'reminder_count')) {
                    $table->unsignedInteger('reminder_count')->default(0)->after('status');
                }
                if (!Schema::hasColumn('purchase_requisitions', 'last_reminded_at')) {
                    $table->timestamp('last_reminded_at')->nullable()->after('reminder_count');
                }
            });
        }

        // 2. Add columns to purchase_orders if not existing
        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_orders', 'reminder_count')) {
                    $table->unsignedInteger('reminder_count')->default(0)->after('status');
                }
                if (!Schema::hasColumn('purchase_orders', 'last_reminded_at')) {
                    $table->timestamp('last_reminded_at')->nullable()->after('reminder_count');
                }
            });
        }

        // 3. Create approval_reminders table
        if (!Schema::hasTable('approval_reminders')) {
            Schema::create('approval_reminders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->string('remindable_type');
                $table->unsignedBigInteger('remindable_id');
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['remindable_type', 'remindable_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_reminders');

        if (Schema::hasTable('purchase_requisitions')) {
            Schema::table('purchase_requisitions', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_requisitions', 'last_reminded_at')) {
                    $table->dropColumn('last_reminded_at');
                }
                if (Schema::hasColumn('purchase_requisitions', 'reminder_count')) {
                    $table->dropColumn('reminder_count');
                }
            });
        }

        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_orders', 'last_reminded_at')) {
                    $table->dropColumn('last_reminded_at');
                }
                if (Schema::hasColumn('purchase_orders', 'reminder_count')) {
                    $table->dropColumn('reminder_count');
                }
            });
        }
    }
};

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
        if (Schema::hasTable('performance_improvement_plans')) {
            Schema::table('performance_improvement_plans', function (Blueprint $table) {
                if (!Schema::hasColumn('performance_improvement_plans', 'pip_policy_template_id')) {
                    $table->unsignedBigInteger('pip_policy_template_id')->nullable()->after('pip_category_id')->index();
                }
                if (!Schema::hasColumn('performance_improvement_plans', 'support_provided')) {
                    $table->text('support_provided')->nullable()->after('reason_details');
                }
                if (!Schema::hasColumn('performance_improvement_plans', 'consequences')) {
                    $table->text('consequences')->nullable()->after('support_provided');
                }
                if (!Schema::hasColumn('performance_improvement_plans', 'initial_notes')) {
                    $table->text('initial_notes')->nullable()->after('consequences');
                }
                if (!Schema::hasColumn('performance_improvement_plans', 'employee_acknowledged_at')) {
                    $table->timestamp('employee_acknowledged_at')->nullable()->after('completed_at');
                }
                if (!Schema::hasColumn('performance_improvement_plans', 'employee_signature')) {
                    $table->longText('employee_signature')->nullable()->after('employee_acknowledged_at');
                }
            });
        }

        if (Schema::hasTable('pip_checkins')) {
            Schema::table('pip_checkins', function (Blueprint $table) {
                if (!Schema::hasColumn('pip_checkins', 'rating')) {
                    $table->decimal('rating', 3, 1)->nullable()->after('rating_status');
                }
                if (!Schema::hasColumn('pip_checkins', 'concerns_noted')) {
                    $table->text('concerns_noted')->nullable()->after('action_items');
                }
                if (!Schema::hasColumn('pip_checkins', 'support_action_items')) {
                    $table->text('support_action_items')->nullable()->after('concerns_noted');
                }
                if (!Schema::hasColumn('pip_checkins', 'next_checkin_date')) {
                    $table->date('next_checkin_date')->nullable()->after('support_action_items');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('performance_improvement_plans')) {
            Schema::table('performance_improvement_plans', function (Blueprint $table) {
                $columns = [
                    'pip_policy_template_id',
                    'support_provided',
                    'consequences',
                    'initial_notes',
                    'employee_acknowledged_at',
                    'employee_signature',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('performance_improvement_plans', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('pip_checkins')) {
            Schema::table('pip_checkins', function (Blueprint $table) {
                $columns = ['rating', 'concerns_noted', 'support_action_items', 'next_checkin_date'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('pip_checkins', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};

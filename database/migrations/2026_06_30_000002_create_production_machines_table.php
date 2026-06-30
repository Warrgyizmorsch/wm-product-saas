<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->foreignId('work_center_id')
                ->constrained('production_work_centers')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('code');

            $table->string('machine_type')->nullable()
                ->comment('e.g. CNC, Lathe, Press, Conveyor, Robot Arm');
            $table->string('manufacturer')->nullable();
            $table->string('model_number')->nullable();

            $table->decimal('capacity', 12, 4)->nullable()
                ->comment('Machine throughput capacity per hour in relevant units.');

            // Q3: Machine status enum values
            $table->string('status')->default('active')
                ->comment('active | inactive | under_maintenance | decommissioned');

            $table->date('installation_date')->nullable();
            $table->string('maintenance_status')->nullable()
                ->comment('A7: Future maintenance module integration point. e.g. scheduled, overdue, none.');

            $table->timestamps();
            $table->softDeletes(); // A6: SoftDeletes — asset records must be preserved

            $table->index(['tenant_id', 'work_center_id']);
            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_machines');
    }
};

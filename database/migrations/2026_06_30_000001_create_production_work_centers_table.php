<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_work_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('code');

            // A4: work_center_type — plain VARCHAR now, FK-ready when master table arrives
            $table->string('work_center_type', 100)->nullable()
                ->comment('e.g. machining, assembly, painting, inspection, outsourced. Future: FK to work_center_types table.');

            $table->text('description')->nullable();
            $table->string('department_name')->nullable()
                ->comment('Q2: Plain string, no FK. Future HRMS integration will add department_id.');
            $table->string('location')->nullable();

            $table->decimal('capacity_per_hour', 10, 2)->nullable()
                ->comment('Units producible per hour at 100% efficiency.');
            $table->decimal('efficiency_percentage', 5, 2)->default(100.00)
                ->comment('Current operational efficiency. Range: 0-100.');
            $table->decimal('cost_per_hour', 12, 4)->default(0.0000)
                ->comment('Combined overhead cost per operating hour.');

            $table->string('status')->default('active')
                ->comment('active | inactive');

            $table->timestamps();
            $table->softDeletes(); // A6: SoftDeletes — manufacturing master data must be preserved

            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_work_centers');
    }
};

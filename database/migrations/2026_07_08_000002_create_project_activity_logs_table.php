<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->nullableMorphs('subject');
            $table->string('event_type'); // e.g. project.created, project.status_changed
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_activity_logs');
    }
};

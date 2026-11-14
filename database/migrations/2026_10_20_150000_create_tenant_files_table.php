<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per stored upload — what tenant storage usage is summed from.
        Schema::create('tenant_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            // (20 + 225) × 4 bytes (utf8mb4) keeps the disk+path unique key under
            // MyISAM's 1000-byte limit, which some shared hosts still default to.
            $table->string('disk', 20);
            $table->string('path', 225);
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['disk', 'path'], 'tenant_files_disk_path_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_files');
    }
};

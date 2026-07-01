<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('lead_id');
            $table->integer('revision_number')->default(0)->after('parent_id');
            $table->boolean('is_current')->default(true)->after('revision_number');

            if (config('database.default') !== 'sqlite') {
                $table->foreign('parent_id')
                    ->references('id')
                    ->on('quotations')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (config('database.default') !== 'sqlite') {
                $table->dropForeign(['parent_id']);
            }
            $table->dropColumn(['parent_id', 'revision_number', 'is_current']);
        });
    }
};

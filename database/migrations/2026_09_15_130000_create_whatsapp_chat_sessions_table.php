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
        Schema::create('whatsapp_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(1);
            $table->string('session_key')->default('default');
            $table->string('phone')->index();
            $table->string('sender_name')->nullable();
            $table->integer('current_step')->default(0);
            $table->string('category')->nullable(); // 'b2b' or 'b2c'
            $table->json('collected_data')->nullable(); // stores step answers
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_chat_sessions');
    }
};

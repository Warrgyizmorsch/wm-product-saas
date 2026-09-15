<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_messages')) {
            Schema::create('whatsapp_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('session_key')->default('default')->index();
                $table->string('sender_number')->index();
                $table->string('sender_name')->nullable();
                $table->enum('direction', ['inbound', 'outbound'])->default('inbound')->index();
                $table->string('message_type', 50)->default('text');
                $table->text('message_body')->nullable();
                $table->string('message_id', 191)->nullable()->index();
                $table->string('status', 50)->default('received');
                $table->timestamp('received_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'direction']);
                $table->index(['tenant_id', 'sender_number']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('email_configurations')) {
            Schema::create('email_configurations', function (Blueprint $table) {
                $table->id();
                $table->string('name'); // e.g. "Support Desk" or "Primary Sales SMTP"
                $table->string('email_address')->unique();
                $table->string('from_name')->nullable();
                
                // SMTP (Outgoing)
                $table->string('driver')->default('smtp');
                $table->string('host')->nullable();
                $table->integer('port')->default(587);
                $table->string('encryption')->default('tls'); // tls, ssl, none
                $table->string('username')->nullable();
                $table->text('password')->nullable(); // Encrypted at rest
                
                // IMAP / POP3 (Incoming)
                $table->string('incoming_protocol')->default('imap');
                $table->string('incoming_host')->nullable();
                $table->integer('incoming_port')->default(993);
                $table->string('incoming_encryption')->default('ssl');
                $table->string('incoming_username')->nullable();
                $table->text('incoming_password')->nullable(); // Encrypted at rest
                
                $table->json('settings')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('email_messages')) {
            Schema::create('email_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('email_configuration_id')->nullable()->constrained('email_configurations')->nullOnDelete();
                $table->string('thread_id', 100)->index();
                $table->string('message_id', 191)->nullable()->index();
                $table->string('in_reply_to', 191)->nullable()->index();
                
                $table->string('direction', 20)->default('outbound'); // inbound | outbound
                $table->string('folder', 50)->default('sent'); // inbox, sent, drafts, trash
                
                $table->string('from_name')->nullable();
                $table->string('from_email');
                $table->string('to_name')->nullable();
                $table->text('to_email');
                $table->text('cc')->nullable();
                $table->text('bcc')->nullable();
                $table->string('reply_to')->nullable();
                
                $table->string('subject')->default('(No Subject)');
                $table->longText('body_html')->nullable();
                $table->longText('body_plain')->nullable();
                
                $table->boolean('is_read')->default(true);
                $table->boolean('is_starred')->default(false);
                $table->boolean('is_draft')->default(false);
                $table->boolean('has_attachments')->default(false);
                $table->json('raw_headers')->nullable();
                
                $table->string('customer_email')->nullable()->index();
                $table->timestamp('received_at')->nullable();
                $table->timestamps();

                $table->index(['folder', 'is_read']);
                $table->index(['email_configuration_id', 'thread_id']);
            });
        }

        if (!Schema::hasTable('email_attachments')) {
            Schema::create('email_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('email_message_id')->constrained('email_messages')->cascadeOnDelete();
                $table->string('file_name');
                $table->string('file_path');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->boolean('is_inline')->default(false);
                $table->string('content_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_attachments');
        Schema::dropIfExists('email_messages');
        Schema::dropIfExists('email_configurations');
    }
};

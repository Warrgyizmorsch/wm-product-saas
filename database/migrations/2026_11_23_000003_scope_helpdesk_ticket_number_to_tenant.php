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
        if (Schema::hasTable('helpdesk_tickets')) {
            Schema::table('helpdesk_tickets', function (Blueprint $table) {
                try {
                    $table->dropUnique(['ticket_number']);
                } catch (\Throwable $e) {
                    try {
                        $table->dropUnique('helpdesk_tickets_ticket_number_unique');
                    } catch (\Throwable $ex) {
                        // Index may already have been dropped
                    }
                }
                $table->unique(['tenant_id', 'ticket_number']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('helpdesk_tickets')) {
            Schema::table('helpdesk_tickets', function (Blueprint $table) {
                try {
                    $table->dropUnique(['tenant_id', 'ticket_number']);
                } catch (\Throwable $e) {
                    //
                }
                $table->unique(['ticket_number']);
            });
        }
    }
};

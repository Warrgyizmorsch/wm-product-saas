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
        Schema::create('crm_deals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(1)->index();
            $table->foreignId('crm_account_id')->constrained('crm_accounts')->onDelete('cascade');
            $table->foreignId('crm_contact_id')->nullable()->constrained('crm_contacts')->onDelete('set null');
            $table->string('deal_number')->index(); // DL-2026-XXXXX
            $table->string('title'); // e.g. Tiles Supply - ABC Mall Project
            $table->decimal('estimated_value', 15, 2)->default(0.00);
            $table->string('stage')->default('Qualification'); // Qualification, Needs Analysis, Proposal, Negotiation, Closed Won, Closed Lost
            $table->string('close_reason')->nullable(); // Lost: Competitor, Price, Budget. Won: Lowest Price, Relationship
            $table->date('closing_date')->nullable();
            $table->string('lead_source')->nullable();
            $table->integer('probability')->default(20);
            $table->unsignedBigInteger('owner_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_deals');
    }
};

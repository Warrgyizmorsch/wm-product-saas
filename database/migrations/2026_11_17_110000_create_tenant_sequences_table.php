<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_sequences')) {
            Schema::create('tenant_sequences', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('key', 100);
                $table->unsignedBigInteger('value')->default(0);
                $table->timestamps();
                $table->unique(['tenant_id', 'key']);
            });
        }

        // Document numbers must be unique per tenant. Existing data may already
        // contain duplicates (issued by the old count-based numbering); those
        // rows need renumbering by hand first, so the index is only added when
        // the table is clean; otherwise a warning is logged and the index has
        // to be added after the duplicates are renumbered.
        $this->addUniqueIfClean('leads', 'lead_number', 'leads_tenant_lead_number_unique');
        $this->addUniqueIfClean('crm_deals', 'deal_number', 'crm_deals_tenant_deal_number_unique');
    }

    public function down(): void
    {
        foreach (['leads' => 'leads_tenant_lead_number_unique', 'crm_deals' => 'crm_deals_tenant_deal_number_unique'] as $table => $index) {
            if (Schema::hasIndex($table, $index)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropUnique($index));
            }
        }

        Schema::dropIfExists('tenant_sequences');
    }

    private function addUniqueIfClean(string $table, string $column, string $index): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || Schema::hasIndex($table, $index)) {
            return;
        }

        $duplicates = DB::table($table)
            ->whereNotNull($column)
            ->select('tenant_id', $column)
            ->groupBy('tenant_id', $column)
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicates > 0) {
            Log::warning("[tenant_sequences] {$duplicates} duplicate {$table}.{$column} value(s) found; unique index {$index} not added. Renumber them, then add the index.");

            return;
        }

        Schema::table($table, fn (Blueprint $t) => $t->unique(['tenant_id', $column], $index));
    }
};

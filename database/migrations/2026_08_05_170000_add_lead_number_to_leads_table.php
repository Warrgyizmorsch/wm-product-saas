<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Domains\CRM\Models\Lead;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('leads', 'lead_number')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('lead_number', 50)->nullable()->after('tenant_id')->index();
            });
        }

        // Backfill tenant-wise lead numbers for existing leads
        $tenants = Lead::query()->select('tenant_id')->distinct()->pluck('tenant_id');
        foreach ($tenants as $tenantId) {
            $leads = Lead::where('tenant_id', $tenantId)->orderBy('id', 'asc')->get();
            $seq = 1;
            foreach ($leads as $lead) {
                if (empty($lead->lead_number)) {
                    $year = $lead->created_at ? $lead->created_at->format('Y') : date('Y');
                    $lead->updateQuietly([
                        'lead_number' => 'LD-' . $year . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT)
                    ]);
                }
                $seq++;
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leads', 'lead_number')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('lead_number');
            });
        }
    }
};

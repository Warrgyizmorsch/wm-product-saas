<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FRS 2.5 (CRM: Leads and Opportunities):
     * C-01: Response-Time SLA Clock & Escalation
     * C-02: Next-Action Rule & Stale Inactivity Alert
     * C-03: Stage Gating Qualification Fields (BANT)
     * C-04: Structured Loss Reasons & Nurture Followup
     * C-05: Inbound Enquiry Provenance & Acknowledgement
     */
    public function up(): void
    {
        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                // SLA Clock (C-01)
                if (!Schema::hasColumn('leads', 'assigned_at')) {
                    $table->timestamp('assigned_at')->nullable()->after('lead_owner_id');
                }
                if (!Schema::hasColumn('leads', 'first_responded_at')) {
                    $table->timestamp('first_responded_at')->nullable()->after('assigned_at');
                }
                if (!Schema::hasColumn('leads', 'response_sla_minutes')) {
                    $table->integer('response_sla_minutes')->default(60)->after('first_responded_at');
                }
                if (!Schema::hasColumn('leads', 'response_sla_breached')) {
                    $table->boolean('response_sla_breached')->default(false)->after('response_sla_minutes');
                }
                if (!Schema::hasColumn('leads', 'sla_escalated_at')) {
                    $table->timestamp('sla_escalated_at')->nullable()->after('response_sla_breached');
                }

                // Next Action & Activity Tracking (C-02)
                if (!Schema::hasColumn('leads', 'has_active_next_action')) {
                    $table->boolean('has_active_next_action')->default(false)->index()->after('next_followup_date');
                }
                if (!Schema::hasColumn('leads', 'last_activity_at')) {
                    $table->timestamp('last_activity_at')->nullable()->after('has_active_next_action');
                }
                if (!Schema::hasColumn('leads', 'stale_alert_sent_at')) {
                    $table->timestamp('stale_alert_sent_at')->nullable()->after('last_activity_at');
                }

                // Stage Gate Qualification (C-03)
                if (!Schema::hasColumn('leads', 'project_type')) {
                    $table->string('project_type', 100)->nullable()->after('industry_type');
                }
                if (!Schema::hasColumn('leads', 'approx_quantity')) {
                    $table->integer('approx_quantity')->nullable()->after('project_type');
                }
                if (!Schema::hasColumn('leads', 'decision_timeline')) {
                    $table->string('decision_timeline', 50)->nullable()->after('expected_sale_date');
                }
                if (!Schema::hasColumn('leads', 'competitor_name')) {
                    $table->string('competitor_name', 150)->nullable()->after('decision_timeline');
                }
                if (!Schema::hasColumn('leads', 'decision_maker_identified')) {
                    $table->boolean('decision_maker_identified')->default(false)->after('competitor_name');
                }

                // Loss Capture & Nurture (C-04)
                if (!Schema::hasColumn('leads', 'loss_reason_id')) {
                    $table->unsignedBigInteger('loss_reason_id')->nullable()->after('status');
                }
                if (!Schema::hasColumn('leads', 'lost_to_competitor')) {
                    $table->string('lost_to_competitor', 150)->nullable()->after('loss_reason_id');
                }
                if (!Schema::hasColumn('leads', 'loss_remarks')) {
                    $table->text('loss_remarks')->nullable()->after('lost_to_competitor');
                }
                if (!Schema::hasColumn('leads', 'nurture_followup_date')) {
                    $table->date('nurture_followup_date')->nullable()->after('loss_remarks');
                }
                if (!Schema::hasColumn('leads', 'lost_at')) {
                    $table->timestamp('lost_at')->nullable()->after('nurture_followup_date');
                }

                // Inbound Provenance & Acknowledgement (C-05 / F04)
                if (!Schema::hasColumn('leads', 'is_inbound_enquiry')) {
                    $table->boolean('is_inbound_enquiry')->default(true)->after('source');
                }
                if (!Schema::hasColumn('leads', 'acknowledgement_sent_at')) {
                    $table->timestamp('acknowledgement_sent_at')->nullable()->after('is_inbound_enquiry');
                }
                if (!Schema::hasColumn('leads', 'research_provenance')) {
                    $table->json('research_provenance')->nullable()->after('acknowledgement_sent_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                $columns = [
                    'assigned_at',
                    'first_responded_at',
                    'response_sla_minutes',
                    'response_sla_breached',
                    'sla_escalated_at',
                    'has_active_next_action',
                    'last_activity_at',
                    'stale_alert_sent_at',
                    'project_type',
                    'approx_quantity',
                    'decision_timeline',
                    'competitor_name',
                    'decision_maker_identified',
                    'loss_reason_id',
                    'lost_to_competitor',
                    'loss_remarks',
                    'nurture_followup_date',
                    'lost_at',
                    'is_inbound_enquiry',
                    'acknowledgement_sent_at',
                    'research_provenance',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('leads', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};

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
        if (Schema::hasTable('visitors')) {
            Schema::table('visitors', function (Blueprint $table) {
                if (!Schema::hasColumn('visitors', 'visitor_type')) {
                    $table->string('visitor_type', 50)->default('Client')->after('designation');
                }
                if (!Schema::hasColumn('visitors', 'source_contact_type')) {
                    $table->string('source_contact_type', 50)->nullable()->after('metadata');
                }
                if (!Schema::hasColumn('visitors', 'source_contact_id')) {
                    $table->unsignedBigInteger('source_contact_id')->nullable()->after('source_contact_type');
                }
            });
        }

        if (Schema::hasTable('visitor_passes')) {
            Schema::table('visitor_passes', function (Blueprint $table) {
                if (!Schema::hasColumn('visitor_passes', 'visitor_type')) {
                    $table->string('visitor_type', 50)->default('Client')->after('purpose');
                }
                if (!Schema::hasColumn('visitor_passes', 'arrived_at')) {
                    $table->timestamp('arrived_at')->nullable()->after('expected_arrival_at');
                }
                if (!Schema::hasColumn('visitor_passes', 'host_notified_at')) {
                    $table->timestamp('host_notified_at')->nullable()->after('arrived_at');
                }
                if (!Schema::hasColumn('visitor_passes', 'meeting_started_at')) {
                    $table->timestamp('meeting_started_at')->nullable()->after('check_in_at');
                }
                if (!Schema::hasColumn('visitor_passes', 'expected_duration_minutes')) {
                    $table->integer('expected_duration_minutes')->default(60)->after('meeting_started_at');
                }
                if (!Schema::hasColumn('visitor_passes', 'accompanying_count')) {
                    $table->integer('accompanying_count')->default(0)->after('expected_duration_minutes');
                }
                if (!Schema::hasColumn('visitor_passes', 'accompanying_names')) {
                    $table->text('accompanying_names')->nullable()->after('accompanying_count');
                }
                if (!Schema::hasColumn('visitor_passes', 'vehicle_type')) {
                    $table->string('vehicle_type', 50)->nullable()->after('accompanying_names');
                }
                if (!Schema::hasColumn('visitor_passes', 'vehicle_number')) {
                    $table->string('vehicle_number', 50)->nullable()->after('vehicle_type');
                }
                if (!Schema::hasColumn('visitor_passes', 'parking_slot')) {
                    $table->string('parking_slot', 50)->nullable()->after('vehicle_number');
                }
                if (!Schema::hasColumn('visitor_passes', 'id_verification_status')) {
                    $table->string('id_verification_status', 30)->default('Pending')->after('parking_slot'); // Pending, Verified, Exempted, Failed
                }
                if (!Schema::hasColumn('visitor_passes', 'id_verified_by')) {
                    $table->unsignedBigInteger('id_verified_by')->nullable()->after('id_verification_status');
                }
                if (!Schema::hasColumn('visitor_passes', 'badge_number')) {
                    $table->string('badge_number', 50)->nullable()->after('badge_printed');
                }
                if (!Schema::hasColumn('visitor_passes', 'badge_returned')) {
                    $table->boolean('badge_returned')->default(false)->after('badge_number');
                }
                if (!Schema::hasColumn('visitor_passes', 'badge_returned_at')) {
                    $table->timestamp('badge_returned_at')->nullable()->after('badge_returned');
                }
                if (!Schema::hasColumn('visitor_passes', 'nda_safety_acknowledged')) {
                    $table->boolean('nda_safety_acknowledged')->default(false)->after('badge_returned_at');
                }
                if (!Schema::hasColumn('visitor_passes', 'restricted_area_access')) {
                    $table->boolean('restricted_area_access')->default(false)->after('nda_safety_acknowledged');
                }
                if (!Schema::hasColumn('visitor_passes', 'gate_pass_reference')) {
                    $table->string('gate_pass_reference', 100)->nullable()->after('restricted_area_access');
                }
                if (!Schema::hasColumn('visitor_passes', 'source_module')) {
                    $table->string('source_module', 50)->default('manual')->after('gate_pass_reference');
                }
                if (!Schema::hasColumn('visitor_passes', 'source_reference_id')) {
                    $table->unsignedBigInteger('source_reference_id')->nullable()->after('source_module');
                }
                if (!Schema::hasColumn('visitor_passes', 'source_reference_no')) {
                    $table->string('source_reference_no', 100)->nullable()->after('source_reference_id');
                }
                if (!Schema::hasColumn('visitor_passes', 'denied_reason')) {
                    $table->text('denied_reason')->nullable()->after('rejection_reason');
                }
                if (!Schema::hasColumn('visitor_passes', 'incident_reported')) {
                    $table->boolean('incident_reported')->default(false)->after('denied_reason');
                }
                if (!Schema::hasColumn('visitor_passes', 'incident_details')) {
                    $table->text('incident_details')->nullable()->after('incident_reported');
                }
            });
        }

        if (Schema::hasTable('visitor_belongings')) {
            Schema::table('visitor_belongings', function (Blueprint $table) {
                if (!Schema::hasColumn('visitor_belongings', 'is_returnable')) {
                    $table->boolean('is_returnable')->default(true)->after('is_verified_on_exit');
                }
                if (!Schema::hasColumn('visitor_belongings', 'gate_pass_number')) {
                    $table->string('gate_pass_number', 100)->nullable()->after('is_returnable');
                }
                if (!Schema::hasColumn('visitor_belongings', 'exit_verified_by')) {
                    $table->unsignedBigInteger('exit_verified_by')->nullable()->after('gate_pass_number');
                }
                if (!Schema::hasColumn('visitor_belongings', 'exit_verified_at')) {
                    $table->timestamp('exit_verified_at')->nullable()->after('exit_verified_by');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('visitor_belongings')) {
            Schema::table('visitor_belongings', function (Blueprint $table) {
                $table->dropColumn(['is_returnable', 'gate_pass_number', 'exit_verified_by', 'exit_verified_at']);
            });
        }

        if (Schema::hasTable('visitor_passes')) {
            Schema::table('visitor_passes', function (Blueprint $table) {
                $table->dropColumn([
                    'visitor_type', 'arrived_at', 'host_notified_at', 'meeting_started_at',
                    'expected_duration_minutes', 'accompanying_count', 'accompanying_names',
                    'vehicle_type', 'vehicle_number', 'parking_slot', 'id_verification_status',
                    'id_verified_by', 'badge_number', 'badge_returned', 'badge_returned_at',
                    'nda_safety_acknowledged', 'restricted_area_access', 'gate_pass_reference',
                    'source_module', 'source_reference_id', 'source_reference_no', 'denied_reason',
                    'incident_reported', 'incident_details'
                ]);
            });
        }

        if (Schema::hasTable('visitors')) {
            Schema::table('visitors', function (Blueprint $table) {
                $table->dropColumn(['visitor_type', 'source_contact_type', 'source_contact_id']);
            });
        }
    }
};

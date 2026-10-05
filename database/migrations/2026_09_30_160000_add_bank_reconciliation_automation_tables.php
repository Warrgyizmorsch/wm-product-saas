<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank reconciliation automation.
 *
 * - bank_statement_layouts: where the header row and each column sit in a
 *   bank's statement export, saved per bank account so the next file from the
 *   same bank imports without questions.
 * - bank_reconciliation_rules: "narration contains X → ledger Y (party Z)",
 *   entered by users or learned from entries they confirm.
 * - bank_statement_lines.balance: the running balance printed on the
 *   statement, used to derive and check opening/closing balances.
 * - bank_statement_matches.method: how a match was made (reference, amount,
 *   manual, rule, adjustment) — the basis of the auto-match rate.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bank_statement_layouts')) {
            Schema::create('bank_statement_layouts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
                $table->string('name')->nullable();
                // sha1 of the normalised header cells — identifies "the same export format".
                $table->string('signature', 64);
                $table->unsignedSmallInteger('header_row');
                // canonical field => zero-based column index
                $table->json('column_map');
                $table->json('header_cells')->nullable();
                $table->string('source', 20)->default('detected'); // detected | manual
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'chart_of_account_id', 'signature'], 'bsl_layout_unique');
            });
        }

        if (! Schema::hasTable('bank_reconciliation_rules')) {
            Schema::create('bank_reconciliation_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                // Null = applies to every bank account of the tenant.
                $table->foreignId('bank_account_id')->nullable()->constrained('chart_of_accounts')->cascadeOnDelete();
                $table->string('name');
                $table->string('match_type', 20)->default('contains'); // contains | starts_with | equals | regex
                $table->string('pattern');
                $table->string('direction', 10)->default('any'); // any | in | out
                $table->decimal('min_amount', 15, 2)->nullable();
                $table->decimal('max_amount', 15, 2)->nullable();
                $table->foreignId('target_account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
                $table->string('party_name')->nullable();
                $table->string('narration')->nullable();
                $table->unsignedSmallInteger('priority')->default(100);
                // Post automatically during Auto-Match (otherwise only suggested).
                $table->boolean('auto_post')->default(false);
                $table->boolean('is_active')->default(true);
                $table->string('source', 20)->default('manual'); // manual | learned
                $table->unsignedInteger('hits')->default(0);
                $table->timestamp('last_used_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'is_active', 'priority'], 'brr_tenant_active_priority_idx');
            });
        }

        Schema::table('bank_statement_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_statement_lines', 'balance')) {
                $table->decimal('balance', 15, 2)->nullable()->after('amount');
            }
        });

        Schema::table('bank_statement_matches', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_statement_matches', 'method')) {
                $table->string('method', 20)->default('manual')->after('amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_statement_matches', function (Blueprint $table) {
            if (Schema::hasColumn('bank_statement_matches', 'method')) {
                $table->dropColumn('method');
            }
        });

        Schema::table('bank_statement_lines', function (Blueprint $table) {
            if (Schema::hasColumn('bank_statement_lines', 'balance')) {
                $table->dropColumn('balance');
            }
        });

        Schema::dropIfExists('bank_reconciliation_rules');
        Schema::dropIfExists('bank_statement_layouts');
    }
};

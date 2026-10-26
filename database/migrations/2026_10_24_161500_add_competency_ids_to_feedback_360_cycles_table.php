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
        // 1. Add competency_ids JSON array column to feedback_360_cycles
        if (Schema::hasTable('feedback_360_cycles') && !Schema::hasColumn('feedback_360_cycles', 'competency_ids')) {
            Schema::table('feedback_360_cycles', function (Blueprint $table) {
                $table->json('competency_ids')->nullable()->after('status');
            });
        }

        // 2. Remove obsolete columns from feedback_360_competencies (cycle_id, code, category, weightage)
        if (Schema::hasTable('feedback_360_competencies')) {
            if (Schema::hasColumn('feedback_360_competencies', 'cycle_id')) {
                try {
                    Schema::table('feedback_360_competencies', function (Blueprint $table) {
                        $table->dropForeign(['cycle_id']);
                    });
                } catch (\Throwable $e) {
                    // Ignore if foreign key was already dropped or unsupported
                }
            }

            Schema::table('feedback_360_competencies', function (Blueprint $table) {
                foreach (['cycle_id', 'code', 'category', 'weightage'] as $col) {
                    if (Schema::hasColumn('feedback_360_competencies', $col)) {
                        try {
                            $table->dropColumn($col);
                        } catch (\Throwable $e) {
                            // Safe fallback
                        }
                    }
                }
            });
        }

        // 3. Remove obsolete cycle_id column from feedback_360_questions
        if (Schema::hasTable('feedback_360_questions')) {
            if (Schema::hasColumn('feedback_360_questions', 'cycle_id')) {
                try {
                    Schema::table('feedback_360_questions', function (Blueprint $table) {
                        $table->dropForeign(['cycle_id']);
                    });
                } catch (\Throwable $e) {
                    // Ignore if foreign key was already dropped or unsupported
                }

                Schema::table('feedback_360_questions', function (Blueprint $table) {
                    try {
                        $table->dropColumn('cycle_id');
                    } catch (\Throwable $e) {
                        // Safe fallback
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Restore cycle_id on feedback_360_questions
        if (Schema::hasTable('feedback_360_questions') && !Schema::hasColumn('feedback_360_questions', 'cycle_id')) {
            Schema::table('feedback_360_questions', function (Blueprint $table) {
                $table->unsignedBigInteger('cycle_id')->nullable()->after('competency_id')->index();
            });
        }

        // 2. Restore columns on feedback_360_competencies
        if (Schema::hasTable('feedback_360_competencies')) {
            Schema::table('feedback_360_competencies', function (Blueprint $table) {
                if (!Schema::hasColumn('feedback_360_competencies', 'cycle_id')) {
                    $table->unsignedBigInteger('cycle_id')->nullable()->after('company_id')->index();
                }
                if (!Schema::hasColumn('feedback_360_competencies', 'code')) {
                    $table->string('code')->nullable()->after('name');
                }
                if (!Schema::hasColumn('feedback_360_competencies', 'category')) {
                    $table->string('category')->default('Core Competencies')->after('name');
                }
                if (!Schema::hasColumn('feedback_360_competencies', 'weightage')) {
                    $table->decimal('weightage', 5, 2)->default(100.00)->after('description');
                }
            });
        }

        // 3. Remove competency_ids from feedback_360_cycles
        if (Schema::hasTable('feedback_360_cycles') && Schema::hasColumn('feedback_360_cycles', 'competency_ids')) {
            Schema::table('feedback_360_cycles', function (Blueprint $table) {
                $table->dropColumn('competency_ids');
            });
        }
    }
};

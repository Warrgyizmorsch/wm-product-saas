<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a party open on either side: a customer usually owes you (debit) but
 * may have paid an advance (credit); a vendor is usually owed (credit) but may
 * have been paid an advance (debit). Defaults keep existing rows unchanged.
 */
return new class extends Migration
{
    /** @var array<string, string> table => default side */
    private array $tables = [
        'customers' => 'debit',
        'vendors' => 'credit',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $default) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'opening_balance_type')) {
                Schema::table($table, function (Blueprint $blueprint) use ($default) {
                    $blueprint->string('opening_balance_type', 10)->default($default)->after('opening_balance');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->tables) as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'opening_balance_type')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('opening_balance_type'));
            }
        }
    }
};

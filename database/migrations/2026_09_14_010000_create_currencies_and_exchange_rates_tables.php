<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-currency schema for the Accounting ledger.
 *
 * - `currencies` is global ISO 4217 reference data (not tenant-owned): code, symbol
 *   and minor-unit decimals. It is independent of config/currency.php, which only
 *   drives Production's display-currency switcher.
 * - `exchange_rates` is tenant-owned and tenant-wide (a market rate is the same fact
 *   for every company in the tenant). 1 unit of from_currency = rate units of
 *   to_currency, effective from effective_date until superseded.
 * - journals / journal_entries keep debit/credit in the company's base currency, so
 *   every existing report stays correct. The transaction currency, the rate used and
 *   the original foreign amounts are recorded alongside. A null currency_code means a
 *   base-currency journal (all rows posted before this migration).
 */
return new class extends Migration
{
    /** @var array<int, array{0: string, 1: string, 2: string, 3: int}> code, name, symbol, decimals */
    private array $currencies = [
        ['INR', 'Indian Rupee', '₹', 2],
        ['USD', 'US Dollar', '$', 2],
        ['EUR', 'Euro', '€', 2],
        ['GBP', 'Pound Sterling', '£', 2],
        ['AED', 'UAE Dirham', 'د.إ', 2],
        ['SAR', 'Saudi Riyal', '﷼', 2],
        ['SGD', 'Singapore Dollar', 'S$', 2],
        ['AUD', 'Australian Dollar', 'A$', 2],
        ['CAD', 'Canadian Dollar', 'C$', 2],
        ['CNY', 'Chinese Yuan', '¥', 2],
        ['BGN', 'Bulgarian Lev', 'лв', 2],
        ['JPY', 'Japanese Yen', '¥', 0],
        ['KWD', 'Kuwaiti Dinar', 'د.ك', 3],
        ['BHD', 'Bahraini Dinar', '.د.ب', 3],
        ['OMR', 'Omani Rial', 'ر.ع.', 3],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table) {
                $table->id();
                $table->char('code', 3)->unique();
                $table->string('name');
                $table->string('symbol', 10);
                $table->unsignedTinyInteger('decimals')->default(2);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $now = now();
        foreach ($this->currencies as [$code, $name, $symbol, $decimals]) {
            DB::table('currencies')->insertOrIgnore([
                'code' => $code,
                'name' => $name,
                'symbol' => $symbol,
                'decimals' => $decimals,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! Schema::hasTable('exchange_rates')) {
            Schema::create('exchange_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->char('from_currency', 3);
                $table->char('to_currency', 3);
                $table->decimal('rate', 20, 10);
                $table->date('effective_date');
                $table->string('source')->default('manual'); // manual, api
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['tenant_id', 'from_currency', 'to_currency', 'effective_date'], 'exchange_rates_pair_date_unique');
                $table->index(['tenant_id', 'from_currency', 'to_currency'], 'exchange_rates_pair_index');
            });
        }

        if (! Schema::hasColumn('journals', 'currency_code')) {
            Schema::table('journals', function (Blueprint $table) {
                $table->char('currency_code', 3)->nullable()->after('voucher_type');
                $table->decimal('exchange_rate', 20, 10)->default(1)->after('currency_code');
            });
        }

        if (! Schema::hasColumn('journal_entries', 'foreign_debit')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->decimal('foreign_debit', 18, 3)->nullable()->after('credit');
                $table->decimal('foreign_credit', 18, 3)->nullable()->after('foreign_debit');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('journal_entries', 'foreign_debit')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->dropColumn(['foreign_debit', 'foreign_credit']);
            });
        }

        if (Schema::hasColumn('journals', 'currency_code')) {
            Schema::table('journals', function (Blueprint $table) {
                $table->dropColumn(['currency_code', 'exchange_rate']);
            });
        }

        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
    }
};

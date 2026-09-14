<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ISO 4217 currency master, shared by every tenant — a plain Model rather than
 * BaseModel since it has no tenant_id. Maintained by platform admins
 * (Platform\CurrencyController) and read by Accounting and HRMS.
 *
 * Codes are never deleted, only deactivated: they are referenced by
 * companies.currency, journals.currency_code and exchange_rates.
 *
 * Not to be confused with config/currency.php, which only feeds Production's
 * display-currency switcher.
 */
class Currency extends Model
{
    protected $table = 'currencies';

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimals',
        'is_active',
    ];

    protected $casts = [
        'decimals' => 'integer',
        'is_active' => 'boolean',
    ];
}

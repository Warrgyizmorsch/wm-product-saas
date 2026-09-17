<?php

namespace App\Domains\Accounting\Support;

/**
 * Chart-of-account codes that posting logic depends on by identity rather than
 * by user configuration.
 *
 * The codes themselves are seeded by ChartOfAccountsService::provisionDefaults()
 * and are unique per tenant on (tenant_id, code). Historically every posting site
 * has hardcoded these as bare string literals — there are roughly two dozen such
 * literals across Sales, Purchase, Inventory and Accounting. This class is
 * deliberately NOT a refactor of those: it exists so that new code (currency
 * conversion, FX gain/loss, rounding plugs) has one honest place to name an
 * account, without taking on the risk of rewriting every existing posting site
 * for no functional gain.
 *
 * Resolve a code to an account through ChartOfAccountRepository::findByCode(),
 * which scopes by tenant. Never assume an id.
 */
final class AccountCode
{
    /** Accounts Receivable — control account for customer balances. */
    public const AR = '1100';

    /** Accounts Payable — control account for vendor balances. */
    public const AP = '2010';

    /** Bank — default cash-at-bank account. */
    public const BANK = '1020';

    /**
     * Round Off. Absorbs sub-unit differences that cannot be attributed to a
     * real account — document-level adjustments today, and currency-conversion
     * rounding residuals once multi-currency posting lands.
     */
    public const ROUND_OFF = '5730';

    /**
     * Foreign Exchange Gain — indirect income, credit-normal, child of 4000.
     * Credited when a foreign-currency balance settles or revalues in our favour.
     */
    public const FX_GAIN = '4930';

    /**
     * Foreign Exchange Loss — indirect expense, debit-normal, child of 5000.
     * Debited when a foreign-currency balance settles or revalues against us.
     */
    public const FX_LOSS = '5740';

    /** Fixed Assets — gross block, used when an asset category has no account of its own. */
    public const FIXED_ASSETS = '1500';

    /** Accumulated Depreciation — contra-asset, credit-normal, child of 1000. */
    public const ACCUMULATED_DEPRECIATION = '1510';

    /** Revaluation Reserve — equity, credited with asset revaluation surpluses. */
    public const REVALUATION_RESERVE = '3200';

    /** Gain on Sale of Fixed Assets — indirect income. */
    public const GAIN_ON_ASSET_DISPOSAL = '4940';

    /** Depreciation Expense — indirect expense. */
    public const DEPRECIATION_EXPENSE = '5400';

    /** Loss on Sale of Fixed Assets — indirect expense, for disposals and write-offs. */
    public const LOSS_ON_ASSET_DISPOSAL = '5910';

    /** Impairment Loss — indirect expense, for revaluation deficits. */
    public const IMPAIRMENT_LOSS = '5920';

    private function __construct()
    {
    }
}

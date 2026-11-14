<?php

namespace App\Domains\Accounting\Services\Integration\Tally;

use App\Domains\Accounting\Models\ChartOfAccount;

/**
 * Tally's predefined groups and what they mean in this chart of accounts. Every
 * Tally ledger sits under one of these, directly or through custom groups, which
 * is how its account type is known.
 */
final class TallyGroupMap
{
    /** @var array<string, array{0: string, 1: string, 2?: bool}> name => [type, subtype, is_cash_or_bank] */
    private const GROUPS = [
        'capital account' => [ChartOfAccount::TYPE_EQUITY, ChartOfAccount::SUBTYPE_CAPITAL],
        'reserves & surplus' => [ChartOfAccount::TYPE_EQUITY, ChartOfAccount::SUBTYPE_RESERVES_SURPLUS],
        'current assets' => [ChartOfAccount::TYPE_ASSET, ChartOfAccount::SUBTYPE_CURRENT_ASSET],
        'bank accounts' => [ChartOfAccount::TYPE_ASSET, ChartOfAccount::SUBTYPE_CURRENT_ASSET, true],
        'cash-in-hand' => [ChartOfAccount::TYPE_ASSET, ChartOfAccount::SUBTYPE_CURRENT_ASSET, true],
        'deposits (asset)' => [ChartOfAccount::TYPE_ASSET, ChartOfAccount::SUBTYPE_SECURITY_DEPOSIT],
        'loans & advances (asset)' => [ChartOfAccount::TYPE_ASSET, ChartOfAccount::SUBTYPE_LOANS_ADVANCES],
        'stock-in-hand' => [ChartOfAccount::TYPE_ASSET, ChartOfAccount::SUBTYPE_CURRENT_ASSET],
        'sundry debtors' => [ChartOfAccount::TYPE_ASSET, ChartOfAccount::SUBTYPE_CURRENT_ASSET],
        'fixed assets' => [ChartOfAccount::TYPE_ASSET, ChartOfAccount::SUBTYPE_FIXED_ASSET],
        'investments' => [ChartOfAccount::TYPE_ASSET, ChartOfAccount::SUBTYPE_CURRENT_ASSET],
        'misc. expenses (asset)' => [ChartOfAccount::TYPE_ASSET, ChartOfAccount::SUBTYPE_CURRENT_ASSET],
        'branch / divisions' => [ChartOfAccount::TYPE_ASSET, ChartOfAccount::SUBTYPE_CURRENT_ASSET],
        'current liabilities' => [ChartOfAccount::TYPE_LIABILITY, ChartOfAccount::SUBTYPE_CURRENT_LIABILITY],
        'duties & taxes' => [ChartOfAccount::TYPE_LIABILITY, ChartOfAccount::SUBTYPE_DUTIES_TAXES],
        'provisions' => [ChartOfAccount::TYPE_LIABILITY, ChartOfAccount::SUBTYPE_PROVISIONS],
        'sundry creditors' => [ChartOfAccount::TYPE_LIABILITY, ChartOfAccount::SUBTYPE_CURRENT_LIABILITY],
        'loans (liability)' => [ChartOfAccount::TYPE_LIABILITY, ChartOfAccount::SUBTYPE_LONG_TERM_LIABILITY],
        'bank od a/c' => [ChartOfAccount::TYPE_LIABILITY, ChartOfAccount::SUBTYPE_CURRENT_LIABILITY, true],
        'secured loans' => [ChartOfAccount::TYPE_LIABILITY, ChartOfAccount::SUBTYPE_LONG_TERM_LIABILITY],
        'unsecured loans' => [ChartOfAccount::TYPE_LIABILITY, ChartOfAccount::SUBTYPE_LONG_TERM_LIABILITY],
        'suspense a/c' => [ChartOfAccount::TYPE_LIABILITY, ChartOfAccount::SUBTYPE_SUSPENSE],
        'sales accounts' => [ChartOfAccount::TYPE_INCOME, ChartOfAccount::SUBTYPE_DIRECT_INCOME],
        'direct incomes' => [ChartOfAccount::TYPE_INCOME, ChartOfAccount::SUBTYPE_DIRECT_INCOME],
        'indirect incomes' => [ChartOfAccount::TYPE_INCOME, ChartOfAccount::SUBTYPE_INDIRECT_INCOME],
        'purchase accounts' => [ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::SUBTYPE_COGS],
        'direct expenses' => [ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::SUBTYPE_COGS],
        'indirect expenses' => [ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::SUBTYPE_INDIRECT_EXPENSE],
    ];

    public static function isPredefined(?string $name): bool
    {
        return $name !== null && isset(self::GROUPS[self::key($name)]);
    }

    /** @return array{type: string, subtype: string, is_cash_or_bank: bool}|null */
    public static function classify(?string $name): ?array
    {
        $row = $name === null ? null : (self::GROUPS[self::key($name)] ?? null);

        return $row === null ? null : [
            'type' => $row[0],
            'subtype' => $row[1],
            'is_cash_or_bank' => $row[2] ?? false,
        ];
    }

    /** The Tally group a ledger pushed from this ERP should be created under. */
    public static function parentFor(?ChartOfAccount $account, ?string $partyType = null): string
    {
        if ($partyType === 'customer') {
            return 'Sundry Debtors';
        }

        if ($partyType !== null) {
            return 'Sundry Creditors';
        }

        if ($account === null) {
            return 'Suspense A/c';
        }

        if ($account->is_cash_or_bank) {
            return stripos($account->name, 'cash') !== false ? 'Cash-in-Hand' : 'Bank Accounts';
        }

        return match ($account->subtype) {
            ChartOfAccount::SUBTYPE_FIXED_ASSET => 'Fixed Assets',
            ChartOfAccount::SUBTYPE_SECURITY_DEPOSIT => 'Deposits (Asset)',
            ChartOfAccount::SUBTYPE_LOANS_ADVANCES => 'Loans & Advances (Asset)',
            ChartOfAccount::SUBTYPE_DUTIES_TAXES => 'Duties & Taxes',
            ChartOfAccount::SUBTYPE_PROVISIONS => 'Provisions',
            ChartOfAccount::SUBTYPE_LONG_TERM_LIABILITY => 'Loans (Liability)',
            ChartOfAccount::SUBTYPE_SUSPENSE => 'Suspense A/c',
            ChartOfAccount::SUBTYPE_RESERVES_SURPLUS => 'Reserves & Surplus',
            ChartOfAccount::SUBTYPE_DIRECT_INCOME => 'Direct Incomes',
            ChartOfAccount::SUBTYPE_COGS => 'Direct Expenses',
            default => match ($account->type) {
                ChartOfAccount::TYPE_ASSET => 'Current Assets',
                ChartOfAccount::TYPE_LIABILITY => 'Current Liabilities',
                ChartOfAccount::TYPE_EQUITY => 'Capital Account',
                ChartOfAccount::TYPE_INCOME => 'Indirect Incomes',
                default => 'Indirect Expenses',
            },
        };
    }

    private static function key(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}

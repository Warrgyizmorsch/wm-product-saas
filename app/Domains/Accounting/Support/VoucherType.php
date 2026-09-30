<?php

namespace App\Domains\Accounting\Support;

use App\Domains\Accounting\Models\Journal;

/**
 * Metadata for the 7 voucher document types, all of which reuse Journal's
 * VOUCHER_TYPE_* constants as the source of truth for the underlying value.
 *
 * Purchase/Sales vouchers here are manually-entered documents (source stays
 * Journal::SOURCE_MANUAL) — distinct from the auto-posted journals Purchase
 * and Sales modules create themselves (Journal::SOURCE_PURCHASE/SOURCE_SALES),
 * which carry no voucher_type at all.
 */
final class VoucherType
{
    public const PAYMENT = Journal::VOUCHER_TYPE_PAYMENT;
    public const RECEIPT = Journal::VOUCHER_TYPE_RECEIPT;
    public const CONTRA = Journal::VOUCHER_TYPE_CONTRA;
    public const CREDIT_NOTE = Journal::VOUCHER_TYPE_CREDIT_NOTE;
    public const DEBIT_NOTE = Journal::VOUCHER_TYPE_DEBIT_NOTE;
    public const PURCHASE = Journal::VOUCHER_TYPE_PURCHASE;
    public const SALES = Journal::VOUCHER_TYPE_SALES;

    public const ALL = [
        self::PAYMENT,
        self::RECEIPT,
        self::CONTRA,
        self::CREDIT_NOTE,
        self::DEBIT_NOTE,
        self::PURCHASE,
        self::SALES,
    ];

    public const PREFIXES = [
        self::PAYMENT => 'PAY',
        self::RECEIPT => 'REC',
        self::CONTRA => 'CTR',
        self::CREDIT_NOTE => 'CN',
        self::DEBIT_NOTE => 'DN',
        self::PURCHASE => 'PUR',
        self::SALES => 'SAL',
    ];

    public const LABELS = [
        self::PAYMENT => 'Payment Voucher',
        self::RECEIPT => 'Receipt Voucher',
        self::CONTRA => 'Contra Voucher',
        self::CREDIT_NOTE => 'Credit Note',
        self::DEBIT_NOTE => 'Debit Note',
        self::PURCHASE => 'Purchase Voucher',
        self::SALES => 'Sales Voucher',
    ];

    public static function isValid(string $type): bool
    {
        return in_array($type, self::ALL, true);
    }

    public static function label(string $type): string
    {
        return self::LABELS[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    public static function prefix(string $type): string
    {
        return self::PREFIXES[$type] ?? 'JNL';
    }
}

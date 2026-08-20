<?php

namespace App\Domains\Accounting\Support;

use App\Domains\Accounting\Models\Journal;

/**
 * Metadata for the 5 voucher document types, all of which reuse Journal's
 * VOUCHER_TYPE_* constants as the source of truth for the underlying value.
 */
final class VoucherType
{
    public const PAYMENT = Journal::VOUCHER_TYPE_PAYMENT;
    public const RECEIPT = Journal::VOUCHER_TYPE_RECEIPT;
    public const CONTRA = Journal::VOUCHER_TYPE_CONTRA;
    public const CREDIT_NOTE = Journal::VOUCHER_TYPE_CREDIT_NOTE;
    public const DEBIT_NOTE = Journal::VOUCHER_TYPE_DEBIT_NOTE;

    public const ALL = [
        self::PAYMENT,
        self::RECEIPT,
        self::CONTRA,
        self::CREDIT_NOTE,
        self::DEBIT_NOTE,
    ];

    public const PREFIXES = [
        self::PAYMENT => 'PAY',
        self::RECEIPT => 'REC',
        self::CONTRA => 'CTR',
        self::CREDIT_NOTE => 'CN',
        self::DEBIT_NOTE => 'DN',
    ];

    public const LABELS = [
        self::PAYMENT => 'Payment Voucher',
        self::RECEIPT => 'Receipt Voucher',
        self::CONTRA => 'Contra Voucher',
        self::CREDIT_NOTE => 'Credit Note',
        self::DEBIT_NOTE => 'Debit Note',
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

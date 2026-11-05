<?php

namespace App\Domains\Accounting\Services\Integration;

use RuntimeException;

/** A record that can't be processed yet, with a stable code an integrator can act on. */
class IntegrationRecordException extends RuntimeException
{
    public const INVALID = 'invalid_record';
    public const UNMAPPED_LEDGER = 'unmapped_ledger';
    public const UNMAPPED_GROUP = 'unmapped_group';
    public const UNMAPPED_VOUCHER_TYPE = 'unmapped_voucher_type';
    public const UNBALANCED = 'unbalanced';

    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}

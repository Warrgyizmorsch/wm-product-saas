<?php

namespace App\Domains\Accounting\Services\StatementExtraction;

use App\Domains\Accounting\Models\BankStatementUpload;
use InvalidArgumentException;

/**
 * The statement file was stored, but its header row / columns couldn't be
 * recognised automatically. The user maps them once on the mapping screen;
 * the mapping is then saved for that bank account.
 */
class StatementNeedsMappingException extends InvalidArgumentException
{
    public function __construct(public readonly BankStatementUpload $upload)
    {
        parent::__construct("The columns in {$upload->original_filename} could not be recognised automatically. Please map them once; the layout will be remembered for this bank account.");
    }
}

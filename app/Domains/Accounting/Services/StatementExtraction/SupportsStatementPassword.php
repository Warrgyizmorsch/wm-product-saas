<?php

namespace App\Domains\Accounting\Services\StatementExtraction;

/**
 * Optional capability for extraction providers that can open
 * password-protected statements (most Indian bank e-statements are locked
 * with a PAN/DOB-based password). Kept separate from
 * StatementExtractionProvider so existing providers don't need to change.
 */
interface SupportsStatementPassword
{
    /**
     * A copy of the provider that sends $password with the next extraction.
     */
    public function withPassword(?string $password): static;
}

<?php

namespace App\Rules;

use App\Services\TaxIdentityService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidGstin implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }

        $strVal = trim((string) $value);

        if (!TaxIdentityService::isValidGstin($strVal)) {
            $fail("The {$attribute} must be a valid 15-character Indian GSTIN (e.g. 27AAAAA0000A1Z5).");
        }
    }
}

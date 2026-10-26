<?php

namespace App\Rules;

use App\Services\TaxIdentityService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPan implements ValidationRule
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

        if (!TaxIdentityService::isValidPan($strVal)) {
            $fail("The {$attribute} must be a valid 10-character Indian PAN number (e.g. AAAAA0000A).");
        }
    }
}

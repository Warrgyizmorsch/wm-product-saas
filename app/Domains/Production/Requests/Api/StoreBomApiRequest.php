<?php

namespace App\Domains\Production\Requests\Api;

use App\Domains\Production\Requests\Concerns\ValidatesBomAllocations;

class StoreBomApiRequest extends ApiFormRequest
{
    use ValidatesBomAllocations;

    protected function prepareForValidation(): void
    {
        $this->prepareBomAllocationsInput();
    }

    public function rules(): array
    {
        return array_merge(
            $this->bomHeaderRules(isUpdate: false, isApiPartial: false),
            $this->bomAllocationRules(itemsRequired: true)
        );
    }

    public function withValidator($validator): void
    {
        $validator->after(fn ($v) => $this->validateBomAllocations($v, null));
    }
}

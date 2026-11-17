<?php

namespace App\Domains\Production\Requests;

use App\Domains\Production\Requests\Concerns\ValidatesBomAllocations;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductionBomRequest extends FormRequest
{
    use ValidatesBomAllocations;

    public function authorize(): bool
    {
        return true;
    }

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

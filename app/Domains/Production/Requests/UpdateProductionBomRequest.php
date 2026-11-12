<?php

namespace App\Domains\Production\Requests;

use App\Domains\Production\Requests\Concerns\ValidatesBomAllocations;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductionBomRequest extends FormRequest
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
            $this->bomHeaderRules(isUpdate: true, isApiPartial: false),
            $this->bomAllocationRules(itemsRequired: true)
        );
    }

    public function withValidator($validator): void
    {
        $validator->after(fn ($v) => $this->validateBomAllocations($v, $this->resolveRouteBom()));
    }
}

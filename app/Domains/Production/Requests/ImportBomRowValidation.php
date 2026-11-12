<?php

namespace App\Domains\Production\Requests;

use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Requests\Concerns\ValidatesBomAllocations;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

/**
 * BM-IMPL-18 #2: the BOM import runs the SAME rules as the BOM form and API (shared ValidatesBomAllocations) on the
 * payload it built for one BOM. Not used as a route request — call {@see errorsFor()}.
 */
class ImportBomRowValidation extends FormRequest
{
    use ValidatesBomAllocations;

    private ?ProductionBom $existingBom = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(
            $this->bomHeaderRules(isUpdate: $this->existingBom !== null, isApiPartial: false),
            $this->bomAllocationRules(itemsRequired: true)
        );
    }

    protected function resolveRouteBom(): ?ProductionBom
    {
        return $this->existingBom;
    }

    /**
     * @return array<int, string> error messages (empty = valid)
     */
    public static function errorsFor(array $payload, ?ProductionBom $existingBom = null): array
    {
        $request = static::create('/', 'POST', $payload);
        $request->setContainer(app());
        $request->existingBom = $existingBom;
        $request->prepareBomAllocationsInput();

        $data = $request->all();
        $validator = Validator::make($data, $request->rules());
        $validator->after(fn ($v) => $request->validateBomAllocations($v, $existingBom));

        return $validator->fails() ? array_values(array_unique($validator->errors()->all())) : [];
    }
}

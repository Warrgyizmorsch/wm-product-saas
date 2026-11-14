<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Requests\Concerns\ValidatesRoutingOperations;
use Illuminate\Support\Facades\Validator;

/**
 * RT-IMPL-16 (RT-27): the routing import uses the SAME operation rules and checks as the web form and the API
 * (shared ValidatesRoutingOperations) — section required, one resource, active resources, alternatives, and the
 * cost-figure check against the latest section snapshot.
 */
class RoutingImportValidator
{
    use ValidatesRoutingOperations;

    /**
     * @param  array<int, array>  $operations  operations in the create-payload shape (figures included)
     * @return array<int, string>  error messages (empty = valid)
     */
    public function validate(array $operations): array
    {
        $validator = Validator::make(['operations' => $operations], $this->routingOperationRules(), $this->routingOperationMessages());
        $validator->after(fn($v) => $this->validateRoutingOperations($v, $operations));

        return $validator->fails() ? array_values(array_unique($validator->errors()->all())) : [];
    }
}

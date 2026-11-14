<?php

namespace App\Domains\Production\Requests;

use App\Domains\Production\Requests\Concerns\ValidatesRoutingOperations;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoutingRequest extends FormRequest
{
    use ValidatesRoutingOperations; // RT-IMPL-07: operation rules + checks shared with the API

    /** Short Full Review Point 2: the web form sends figures in the active currency (converted to base by the controller). */
    protected function routingFiguresCurrency(): string
    {
        return 'active';
    }

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'version'        => $this->input('version') ?: '1.0.0',
            'effective_from' => $this->input('effective_from') ?: now()->toDateString(),
            'is_default'     => $this->has('is_default') ? (bool) $this->input('is_default') : true,
        ]);
    }

    public function rules(): array
    {
        return array_merge([
            'routing_number' => 'nullable|string|max:50',
            'name'           => 'required|string|max:255',
            'version'        => [
                'nullable', 'string', 'max:50',
                Rule::unique('routings')->where(fn($query) => $query->where('tenant_id', tenant_id() ?? 1)
                    ->where('name', $this->input('name'))
                    ->whereNull('deleted_at')),
            ],
            'is_default'     => 'boolean',
            'effective_from' => 'nullable|date',
            'effective_to'   => 'nullable|date|after_or_equal:effective_from',
            'description'    => 'nullable|string',
            // RT-IMPL-20: set when saving a Duplicate Version copy
            'source_routing_id' => ['nullable', 'integer', $this->tenantExists('routings')],
        ], $this->routingOperationRules());
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            try {
                app(\App\Domains\Production\Services\RoutingLifecycleService::class)->validateEffectiveDates(
                    $this->input('effective_from'),
                    $this->input('effective_to'),
                    0,
                    null,
                    tenant_id() ?? 1
                );
            } catch (\InvalidArgumentException $e) {
                $validator->errors()->add('effective_from', $e->getMessage());
            }

            $this->validateRoutingOperations($validator, (array) $this->input('operations', []));
        });
    }

    public function messages(): array
    {
        return $this->routingOperationMessages();
    }
}

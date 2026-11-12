<?php

namespace App\Domains\Production\Requests;

use App\Domains\Production\Requests\Concerns\ValidatesRoutingOperations;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoutingRequest extends FormRequest
{
    use ValidatesRoutingOperations; // RT-IMPL-07: operation rules + checks shared with the API

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

    private function routingId(): ?int
    {
        $r = $this->route('routing');

        return $r instanceof \App\Domains\Production\Models\Routing ? $r->id : ((int) $r ?: null);
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
                    ->where('id', '!=', $this->routingId())
                    ->whereNull('deleted_at')),
            ],
            'is_default'     => 'boolean',
            'effective_from' => 'nullable|date',
            'effective_to'   => 'nullable|date|after_or_equal:effective_from',
            'description'    => 'nullable|string',
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
                    $this->routingId(),
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

<?php

namespace App\Domains\Platform\Requests;

use App\Domains\Platform\Services\DashboardService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDashboardLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'dashboard' => ['nullable', Rule::in(DashboardService::DASHBOARDS)],
            'scope' => ['required', Rule::in(['personal', 'role', 'tenant'])],
            'role_id' => ['required_if:scope,role', 'nullable', 'integer', Rule::exists('roles', 'id')->where(
                fn ($q) => $q->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', tenant_id() ?? $this->user()->tenant_id)),
            )],
            'widgets' => ['present', 'array', 'max:40'],
            'widgets.*.id' => ['nullable', 'string', 'max:64'],
            'widgets.*.key' => ['required', 'string', 'max:100'],
            'widgets.*.x' => ['nullable', 'integer'],
            'widgets.*.y' => ['nullable', 'integer'],
            'widgets.*.w' => ['nullable', 'integer'],
            'widgets.*.h' => ['nullable', 'integer'],
            'widgets.*.config' => ['nullable', 'array'],
            'widgets.*.config.title' => ['nullable', 'string', 'max:60'],
            'widgets.*.config.period' => ['nullable', 'string', 'max:20'],
            'widgets.*.config.limit' => ['nullable', 'integer'],
        ];
    }
}

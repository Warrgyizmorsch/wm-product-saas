<?php

namespace App\Domains\Platform\Controllers;

use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Services\PlanService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function __construct(
        private readonly PlanService $plans,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Plan::class);

        return view('modules.platform.plans.index', [
            'plans' => $this->plans->all(),
            'summary' => $this->plans->summary(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Plan::class);

        return view('modules.platform.plans.create', [
            'plan' => new Plan(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Plan::class);

        $plan = $this->plans->create($this->validated($request));

        return redirect()
            ->route('platform.plans.index')
            ->with('success', 'Plan '.$plan->name.' created successfully.');
    }

    public function edit(Plan $plan): View
    {
        $this->authorize('view', $plan);

        return view('modules.platform.plans.edit', [
            'plan' => $plan,
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $this->plans->update($plan, $this->validated($request, $plan));

        return redirect()
            ->route('platform.plans.index')
            ->with('success', 'Plan '.$plan->name.' updated successfully.');
    }

    private function validated(Request $request, ?Plan $plan = null): array
    {
        $planId = $plan?->id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('plans', 'slug')->ignore($planId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'billing_cycle' => ['required', 'string', Rule::in(['monthly', 'yearly'])],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_storage_mb' => ['nullable', 'integer', 'min:1'],
            'trial_days' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string'],
            'is_demo' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['is_demo'] = $request->boolean('is_demo');
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}

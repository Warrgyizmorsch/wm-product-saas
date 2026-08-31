<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\CostCenter;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CostCenterController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', CostCenter::class);

        return view('modules.accounting.cost-centers.index', [
            'costCenters' => CostCenter::orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CostCenter::class);

        $validated = $this->validated($request);
        $validated['is_active'] = true;

        CostCenter::create($validated);

        return redirect()->route('accounting.cost-centers.index')
            ->with('success', 'Cost center created successfully.');
    }

    public function update(Request $request, CostCenter $costCenter): RedirectResponse
    {
        $this->authorize('update', $costCenter);

        $validated = $this->validated($request, $costCenter->id);
        $validated['is_active'] = $request->boolean('is_active');

        $costCenter->update($validated);

        return redirect()->route('accounting.cost-centers.index')
            ->with('success', 'Cost center updated successfully.');
    }

    public function destroy(CostCenter $costCenter): RedirectResponse
    {
        $this->authorize('delete', $costCenter);

        if ($costCenter->entries()->exists()) {
            return redirect()->route('accounting.cost-centers.index')
                ->with('error', "Cannot delete '{$costCenter->name}' — it has journal entries posted against it.");
        }

        $costCenter->delete();

        return redirect()->route('accounting.cost-centers.index')
            ->with('success', 'Cost center deleted successfully.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('cost_centers', 'code')->where('tenant_id', auth()->user()->tenant_id)->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);
    }
}

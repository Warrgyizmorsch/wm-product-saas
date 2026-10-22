<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\Models\ExternalWorkerPayGrade;
use App\Domains\Production\Requests\StoreExternalWorkerPayGradeRequest;
use App\Domains\Production\Requests\UpdateExternalWorkerPayGradeRequest;
use App\Domains\Production\Services\ExternalWorkerPayGradeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class ExternalWorkerPayGradeController extends Controller
{
    public function __construct(
        private readonly ExternalWorkerPayGradeService $service
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ExternalWorkerPayGrade::class);

        $tenantId = require_tenant_id();
        $query = ExternalWorkerPayGrade::withoutGlobalScopes()
            ->where('tenant_id', $tenantId);

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'active') {
                $query->where('active', true);
            } elseif ($status === 'inactive') {
                $query->where('active', false);
            }
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $payGrades = $query->withCount([
            'allocations as active_allocations_count' => function ($q) {
                $q->where('active', true);
            },
            'allocations as total_allocations_count',
        ])
        ->orderBy('name')
        ->paginate(15)
        ->withQueryString();

        $filters = $request->only(['status', 'search']);

        return view('modules.production.pay-grades.index', compact('payGrades', 'filters'));
    }

    public function create(): View
    {
        Gate::authorize('create', ExternalWorkerPayGrade::class);

        return view('modules.production.pay-grades.create');
    }

    public function store(StoreExternalWorkerPayGradeRequest $request): RedirectResponse
    {
        Gate::authorize('create', ExternalWorkerPayGrade::class);

        try {
            $tenantId = require_tenant_id();
            $data = $request->validated();
            if (isset($data['hourly_rate'])) {
                $data['hourly_rate'] = convert_to_base($data['hourly_rate']);
            }
            $data['active'] = $request->boolean('active', true);

            $payGrade = $this->service->create($data, $tenantId);

            return redirect()
                ->route('production.pay-grades.index')
                ->with('success', "Pay grade '{$payGrade->name}' created successfully.");
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'An error occurred while creating the pay grade: ' . $e->getMessage());
        }
    }

    public function edit(int $id): View
    {
        $tenantId = require_tenant_id();
        $payGrade = ExternalWorkerPayGrade::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($id);

        abort_if(!$payGrade, 404, 'Pay grade not found.');

        Gate::authorize('update', $payGrade);

        return view('modules.production.pay-grades.edit', compact('payGrade'));
    }

    public function update(UpdateExternalWorkerPayGradeRequest $request, int $id): RedirectResponse
    {
        $tenantId = require_tenant_id();
        $payGrade = ExternalWorkerPayGrade::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($id);

        abort_if(!$payGrade, 404, 'Pay grade not found.');

        Gate::authorize('update', $payGrade);

        try {
            $data = $request->validated();
            if (isset($data['hourly_rate'])) {
                $data['hourly_rate'] = convert_to_base($data['hourly_rate']);
            }
            $data['active'] = $request->boolean('active', true);

            $updated = $this->service->update($id, $data, $tenantId);

            return redirect()
                ->route('production.pay-grades.index')
                ->with('success', "Pay grade '{$updated->name}' updated successfully.");
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'An error occurred while updating the pay grade: ' . $e->getMessage());
        }
    }

    public function toggleStatus(int $id): RedirectResponse
    {
        $tenantId = require_tenant_id();
        $payGrade = ExternalWorkerPayGrade::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($id);

        abort_if(!$payGrade, 404, 'Pay grade not found.');

        Gate::authorize('update', $payGrade);

        try {
            $updated = $this->service->update($id, ['active' => !$payGrade->active], $tenantId);
            $statusText = $updated->active ? 'activated' : 'deactivated';

            return redirect()
                ->route('production.pay-grades.index')
                ->with('success', "Pay grade '{$updated->name}' {$statusText} successfully.");
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        $tenantId = require_tenant_id();
        $payGrade = ExternalWorkerPayGrade::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($id);

        abort_if(!$payGrade, 404, 'Pay grade not found.');

        Gate::authorize('delete', $payGrade);

        try {
            $this->service->delete($id, $tenantId);

            return redirect()
                ->route('production.pay-grades.index')
                ->with('success', "Pay grade '{$payGrade->name}' deleted successfully.");
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while deleting the pay grade: ' . $e->getMessage());
        }
    }
}

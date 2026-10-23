<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\Models\ExternalWorkerAllocation;
use App\Domains\Production\Requests\StoreExternalWorkerAllocationRequest;
use App\Domains\Production\Requests\UpdateExternalWorkerAllocationQuantityRequest;
use App\Domains\Production\Services\ExternalWorkerAllocationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ExternalWorkerAllocationController extends Controller
{
    public function __construct(
        private readonly ExternalWorkerAllocationService $service
    ) {}

    public function store(StoreExternalWorkerAllocationRequest $request): RedirectResponse
    {
        Gate::authorize('create', ExternalWorkerAllocation::class);

        try {
            $tenantId = require_tenant_id();
            $data = $request->validated();

            $sectionId = (int) $data['section_id'];
            $shiftId = (int) $data['shift_id'];
            $payGradeId = (int) $data['pay_grade_id'];
            $numberOfExternalWorkers = (int) ($data['number_of_external_workers'] ?? 1);
            $active = $request->boolean('active', true);
            $updateIfExists = $request->boolean('update_if_exists', false);

            $this->service->allocate(
                $sectionId,
                $shiftId,
                $payGradeId,
                $numberOfExternalWorkers,
                $tenantId,
                $active,
                $updateIfExists
            );

            return redirect()->back()->with('success', 'External worker allocation saved successfully.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'An error occurred while saving allocation: ' . $e->getMessage());
        }
    }

    public function updateQuantity(UpdateExternalWorkerAllocationQuantityRequest $request, int $id): RedirectResponse
    {
        $tenantId = require_tenant_id();
        $allocation = ExternalWorkerAllocation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($id);

        abort_if(!$allocation, 404, 'External worker allocation not found.');

        Gate::authorize('update', $allocation);

        try {
            $quantity = (int) $request->validated('number_of_external_workers');
            $this->service->updateQuantity($id, $quantity, $tenantId);

            return redirect()->back()->with('success', 'Number of external workers updated successfully.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'An error occurred while updating allocation: ' . $e->getMessage());
        }
    }

    public function toggleStatus(Request $request, int $id): RedirectResponse
    {
        $tenantId = require_tenant_id();
        $allocation = ExternalWorkerAllocation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($id);

        abort_if(!$allocation, 404, 'External worker allocation not found.');

        Gate::authorize('update', $allocation);

        try {
            $targetActive = $request->has('active') ? $request->boolean('active') : !$allocation->active;
            $this->service->setActive($id, $targetActive, $tenantId);

            $statusText = $targetActive ? 'activated' : 'deactivated';
            return redirect()->back()->with('success', "External worker allocation {$statusText} successfully.");
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while updating allocation status: ' . $e->getMessage());
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        $tenantId = require_tenant_id();
        $allocation = ExternalWorkerAllocation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($id);

        abort_if(!$allocation, 404, 'External worker allocation not found.');

        Gate::authorize('delete', $allocation);

        try {
            $this->service->deallocate($id, $tenantId);

            return redirect()->back()->with('success', 'External worker allocation removed successfully.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while removing allocation: ' . $e->getMessage());
        }
    }
}

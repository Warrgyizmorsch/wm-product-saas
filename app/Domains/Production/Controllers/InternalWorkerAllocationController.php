<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\Models\InternalWorkerAllocation;
use App\Domains\Production\Services\InternalWorkerAllocationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class InternalWorkerAllocationController extends Controller
{
    public function __construct(
        private readonly InternalWorkerAllocationService $service
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'section_id' => 'required|integer',
            'shift_id'   => 'required|integer',
            'headcount'  => 'required|integer|min:1',
            'active'     => 'nullable|boolean',
        ]);

        try {
            $tenantId = require_tenant_id();
            $sectionId = (int) $request->input('section_id');
            $shiftId = (int) $request->input('shift_id');
            $headcount = (int) $request->input('headcount');
            $active = $request->boolean('active', true);
            $updateIfExists = $request->boolean('update_if_exists', true);

            $this->service->allocate(
                $sectionId,
                $shiftId,
                $headcount,
                $tenantId,
                $active,
                $updateIfExists
            );

            return redirect()->back()->with('success', 'Internal worker allocation saved successfully.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'An error occurred while saving internal worker allocation: ' . $e->getMessage());
        }
    }

    public function updateHeadcount(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'headcount' => 'required|integer|min:1',
        ]);

        $tenantId = require_tenant_id();

        try {
            $headcount = (int) $request->input('headcount');
            $this->service->updateHeadcount($id, $headcount, $tenantId);

            return redirect()->back()->with('success', 'Internal worker headcount updated successfully.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'An error occurred while updating internal worker headcount: ' . $e->getMessage());
        }
    }

    public function toggleStatus(Request $request, int $id): RedirectResponse
    {
        $tenantId = require_tenant_id();
        $allocation = InternalWorkerAllocation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($id);

        abort_if(!$allocation, 404, 'Internal worker allocation not found.');

        try {
            $targetActive = $request->has('active') ? $request->boolean('active') : !$allocation->active;
            $this->service->setActive($id, $targetActive, $tenantId);

            $statusText = $targetActive ? 'activated' : 'deactivated';
            return redirect()->back()->with('success', "Internal worker allocation {$statusText} successfully.");
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while updating allocation status: ' . $e->getMessage());
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        $tenantId = require_tenant_id();

        try {
            $this->service->deallocate($id, $tenantId);

            return redirect()->back()->with('success', 'Internal worker allocation deallocated successfully.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while deallocating internal worker: ' . $e->getMessage());
        }
    }
}

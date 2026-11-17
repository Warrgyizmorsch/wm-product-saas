<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\Models\ExternalWorkerAllocation;
use App\Domains\Production\Requests\StoreExternalWorkerAllocationRequest;
use App\Domains\Production\Requests\UpdateExternalWorkerAllocationQuantityRequest;
use App\Domains\Production\Services\ExternalWorkerAllocationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ExternalWorkerAllocationController extends Controller
{
    public function __construct(
        private readonly ExternalWorkerAllocationService $service
    ) {}

    public function store(StoreExternalWorkerAllocationRequest $request): RedirectResponse|JsonResponse
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

            $allocation = $this->service->allocate(
                $sectionId,
                $shiftId,
                $payGradeId,
                $numberOfExternalWorkers,
                $tenantId,
                $active,
                $updateIfExists
            );

            $this->triggerCalculationCountdown($allocation);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'External worker allocation saved successfully.',
                    'allocation' => $allocation,
                ]);
            }
            return redirect()->back()->with('success', 'External worker allocation saved successfully.');
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'An error occurred while saving allocation: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->withInput()->with('error', 'An error occurred while saving allocation: ' . $e->getMessage());
        }
    }

    public function updateQuantity(UpdateExternalWorkerAllocationQuantityRequest $request, int $id): RedirectResponse|JsonResponse
    {
        $tenantId = require_tenant_id();
        $allocation = ExternalWorkerAllocation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($id);

        abort_if(!$allocation, 404, 'External worker allocation not found.');

        Gate::authorize('update', $allocation);

        try {
            $quantity = (int) $request->validated('number_of_external_workers');
            $allocation = $this->service->updateQuantity($id, $quantity, $tenantId);
            $this->triggerCalculationCountdown($allocation);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Number of external workers updated successfully.',
                    'allocation' => $allocation,
                ]);
            }
            return redirect()->back()->with('success', 'Number of external workers updated successfully.');
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'An error occurred while updating allocation: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->withInput()->with('error', 'An error occurred while updating allocation: ' . $e->getMessage());
        }
    }

    public function toggleStatus(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $tenantId = require_tenant_id();
        $allocation = ExternalWorkerAllocation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($id);

        abort_if(!$allocation, 404, 'External worker allocation not found.');

        Gate::authorize('update', $allocation);

        try {
            $targetActive = $request->has('active') ? $request->boolean('active') : !$allocation->active;
            $allocation = $this->service->setActive($id, $targetActive, $tenantId);
            $this->triggerCalculationCountdown($allocation);

            $statusText = $targetActive ? 'activated' : 'deactivated';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "External worker allocation {$statusText} successfully.",
                    'active' => $targetActive,
                ]);
            }
            return redirect()->back()->with('success', "External worker allocation {$statusText} successfully.");
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'An error occurred while updating allocation status: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'An error occurred while updating allocation status: ' . $e->getMessage());
        }
    }

    public function destroy(int $id): RedirectResponse|JsonResponse
    {
        $tenantId = require_tenant_id();
        $allocation = ExternalWorkerAllocation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($id);

        abort_if(!$allocation, 404, 'External worker allocation not found.');

        Gate::authorize('delete', $allocation);

        try {
            $this->triggerCalculationCountdown($allocation);
            $this->service->deallocate($id, $tenantId);

            if (request()->expectsJson() || request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'External worker allocation removed successfully.',
                ]);
            }
            return redirect()->back()->with('success', 'External worker allocation removed successfully.');
        } catch (InvalidArgumentException $e) {
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json(['success' => false, 'message' => 'An error occurred while removing allocation: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'An error occurred while removing allocation: ' . $e->getMessage());
        }
    }

    private function triggerCalculationCountdown(?ExternalWorkerAllocation $allocation): void
    {
        if (!$allocation) {
            return;
        }

        $section = $allocation->section;
        $workCenter = $section?->workCenter;
        if ($workCenter) {
            app(\App\Domains\Production\Services\CountdownCalculationEngine::class)->triggerCountdown($workCenter, $allocation->section_id);
        }
    }
}

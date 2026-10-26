<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\Models\ExternalWorkerAllocation;
use App\Domains\Production\Models\SectionManualProduction;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\CountdownCalculationEngine;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class SectionManualProductionController extends Controller
{
    public function __construct(
        private readonly CountdownCalculationEngine $countdownEngine,
    ) {}

    public function store(Request $request, int $sectionId): RedirectResponse|JsonResponse
    {
        $tenantId = require_tenant_id();
        $section = WorkCenterSection::where('tenant_id', $tenantId)->find($sectionId);
        abort_if(!$section, 404, 'Section not found.');

        Gate::authorize('update', $section->workCenter);

        $validator = Validator::make($request->all(), [
            'batch_name'                => 'required|string|max:255',
            'shift_id'                  => 'required|integer|exists:production_shifts,id',
            'capacity_per_hour'         => 'required|numeric|min:0.0001',
            'active'                    => 'nullable|boolean',
            'employee_ids'              => 'nullable|array',
            'employee_ids.*'            => 'integer|exists:production_work_center_employees,id',
            'external_allocation_ids'   => 'nullable|array',
            'external_allocation_ids.*' => 'integer|exists:production_external_worker_allocations,id',
        ]);

        $validator->after(function ($validator) use ($request, $tenantId) {
            $empIds = array_filter((array) $request->input('employee_ids', []));
            $extIds = array_filter((array) $request->input('external_allocation_ids', []));
            if (empty($empIds) && empty($extIds)) {
                $validator->errors()->add('employee_ids', 'A manual production batch cannot be created without selecting at least one worker or employee.');
                return;
            }

            $this->validateWorkerExclusivity($validator, $tenantId, $empIds, $extIds);
        });

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors(),
                ], 422);
            }

            return redirect()->back()->withInput()->withErrors($validator);
        }

        $validated = $validator->validated();

        try {
            DB::beginTransaction();

            $batch = SectionManualProduction::create([
                'tenant_id'         => $tenantId,
                'section_id'        => $section->id,
                'shift_id'          => (int) $validated['shift_id'],
                'batch_name'        => $validated['batch_name'],
                'capacity_per_hour' => (float) $validated['capacity_per_hour'],
                'active'            => $request->boolean('active'),
            ]);

            // Sync internal employee assignments
            if (!empty($validated['employee_ids'])) {
                $batch->employees()->sync(array_unique($validated['employee_ids']));
            }

            // Link external worker allocations
            if (!empty($validated['external_allocation_ids'])) {
                ExternalWorkerAllocation::where('tenant_id', $tenantId)
                    ->where('section_id', $section->id)
                    ->whereIn('id', $validated['external_allocation_ids'])
                    ->update(['manual_production_id' => $batch->id]);
            }

            // Trigger countdown update safely
            try {
                $this->countdownEngine->onSectionModified($section, 'manual_batch_created', $batch->id);
            } catch (\Throwable $e) {}

            DB::commit();

            $msg = "Manual production batch '{$batch->batch_name}' created successfully.";

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'data'    => $batch,
                ]);
            }

            return redirect()
                ->route('production.work-centers.show', $section->work_center_id)
                ->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function update(Request $request, mixed $id = null): RedirectResponse|JsonResponse
    {
        $tenantId = require_tenant_id();
        $routeParam = $request->route('batch') ?? $request->route('id') ?? $id ?? $request->input('batch_id');
        $batchId = $routeParam instanceof SectionManualProduction ? $routeParam->id : (int) $routeParam;
        $batch = SectionManualProduction::where('tenant_id', $tenantId)->find($batchId);
        abort_if(!$batch, 404, 'Manual production batch not found.');

        $section = $batch->section;
        Gate::authorize('update', $section->workCenter);

        $validator = Validator::make($request->all(), [
            'batch_name'                => 'required|string|max:255',
            'shift_id'                  => 'required|integer|exists:production_shifts,id',
            'capacity_per_hour'         => 'required|numeric|min:0.0001',
            'active'                    => 'nullable|boolean',
            'employee_ids'              => 'nullable|array',
            'employee_ids.*'            => 'integer|exists:production_work_center_employees,id',
            'external_allocation_ids'   => 'nullable|array',
            'external_allocation_ids.*' => 'integer|exists:production_external_worker_allocations,id',
        ]);

        $validator->after(function ($validator) use ($request, $tenantId, $batch) {
            $empIds = array_filter((array) $request->input('employee_ids', []));
            $extIds = array_filter((array) $request->input('external_allocation_ids', []));
            if (empty($empIds) && empty($extIds)) {
                $validator->errors()->add('employee_ids', 'A manual production batch cannot be updated without selecting at least one worker or employee.');
                return;
            }

            $this->validateWorkerExclusivity($validator, $tenantId, $empIds, $extIds, (int) $batch->id);
        });

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors(),
                ], 422);
            }

            return redirect()->back()->withInput()->withErrors($validator);
        }

        $validated = $validator->validated();

        try {
            DB::beginTransaction();

            $batch->update([
                'shift_id'          => (int) $validated['shift_id'],
                'batch_name'        => $validated['batch_name'],
                'capacity_per_hour' => (float) $validated['capacity_per_hour'],
                'active'            => $request->boolean('active'),
            ]);

            // Sync internal employees
            $batch->employees()->sync($validated['employee_ids'] ?? []);

            // Sync external allocations
            $newExtIds = $validated['external_allocation_ids'] ?? [];
            ExternalWorkerAllocation::where('tenant_id', $tenantId)
                ->where('manual_production_id', $batch->id)
                ->whereNotIn('id', $newExtIds)
                ->update(['manual_production_id' => null]);

            if (!empty($newExtIds)) {
                ExternalWorkerAllocation::where('tenant_id', $tenantId)
                    ->where('section_id', $batch->section_id)
                    ->whereIn('id', $newExtIds)
                    ->update(['manual_production_id' => $batch->id]);
            }

            try {
                $this->countdownEngine->onSectionModified($section, 'manual_batch_updated', $batch->id);
            } catch (\Throwable $e) {}

            DB::commit();

            $msg = "Manual production batch '{$batch->batch_name}' updated successfully.";

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'data'    => $batch->fresh(['employees.employee', 'externalAllocations.payGrade']),
                ]);
            }

            return redirect()
                ->route('production.work-centers.show', $section->work_center_id)
                ->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function toggleStatus(Request $request, mixed $id = null): RedirectResponse|JsonResponse
    {
        $tenantId = require_tenant_id();
        $routeParam = $request->route('batch') ?? $request->route('id') ?? $id;
        $batchId = $routeParam instanceof SectionManualProduction ? $routeParam->id : (int) $routeParam;
        $batch = SectionManualProduction::where('tenant_id', $tenantId)->find($batchId);
        abort_if(!$batch, 404, 'Manual production batch not found.');

        $section = $batch->section;
        Gate::authorize('update', $section->workCenter);

        try {
            DB::beginTransaction();

            $newActive = $request->has('active') ? $request->boolean('active') : !$batch->active;
            $batch->update(['active' => $newActive]);

            try {
                $this->countdownEngine->onSectionModified($section, 'manual_batch_toggled', $batch->id);
            } catch (\Throwable $e) {}

            DB::commit();

            $statusText = $newActive ? 'activated' : 'deactivated';
            $msg = "Manual batch '{$batch->batch_name}' {$statusText} successfully.";

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'active'  => $newActive,
                ]);
            }

            return redirect()
                ->route('production.work-centers.show', $section->work_center_id)
                ->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function destroy(Request $request, mixed $id = null): RedirectResponse|JsonResponse
    {
        $tenantId = require_tenant_id();
        $routeParam = $request->route('batch') ?? $request->route('id') ?? $id;
        $batchId = $routeParam instanceof SectionManualProduction ? $routeParam->id : (int) $routeParam;
        $batch = SectionManualProduction::where('tenant_id', $tenantId)->find($batchId);
        abort_if(!$batch, 404, 'Manual production batch not found.');

        $section = $batch->section;
        Gate::authorize('update', $section->workCenter);

        try {
            DB::beginTransaction();

            $workCenterId = $section->work_center_id;
            $batchName = $batch->batch_name;

            // Clear external allocations first
            ExternalWorkerAllocation::where('tenant_id', $tenantId)
                ->where('manual_production_id', $batch->id)
                ->update(['manual_production_id' => null]);

            // Detach employees
            $batch->employees()->detach();
            $batch->delete();

            // Arm / trigger debounce countdown safely
            try {
                $this->countdownEngine->onSectionModified($section, 'manual_batch_deleted', $id);
            } catch (\Throwable $e) {}

            DB::commit();

            $msg = "Manual production batch '{$batchName}' removed successfully.";

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                ]);
            }

            return redirect()
                ->route('production.work-centers.show', $workCenterId)
                ->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Validate that selected employees and external workers are not already assigned
     * to another active (non-deleted) manual production batch.
     * Batches that are soft deleted (deleted_at IS NOT NULL) do not lock workers.
     */
    private function validateWorkerExclusivity(
        \Illuminate\Contracts\Validation\Validator $validator,
        int $tenantId,
        array $empIds,
        array $extIds,
        ?int $excludeBatchId = null
    ): void {
        // 1. Internal Employees Check
        if (!empty($empIds)) {
            $targetEmployeeIds = DB::table('production_work_center_employees')
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $empIds)
                ->pluck('employee_id')
                ->filter()
                ->all();

            $occupiedEmployees = DB::table('production_section_manual_production_employees as smpe')
                ->join('production_section_manual_productions as smp', 'smp.id', '=', 'smpe.manual_production_id')
                ->join('production_work_center_employees as wce', 'wce.id', '=', 'smpe.work_center_employee_id')
                ->leftJoin('employees as e', 'e.id', '=', 'wce.employee_id')
                ->where('smp.tenant_id', $tenantId)
                ->whereNull('smp.deleted_at')
                ->whereNull('wce.deleted_at')
                ->when($excludeBatchId, fn($q) => $q->where('smp.id', '!=', $excludeBatchId))
                ->where(function ($q) use ($empIds, $targetEmployeeIds) {
                    $q->whereIn('smpe.work_center_employee_id', $empIds);
                    if (!empty($targetEmployeeIds)) {
                        $q->orWhereIn('wce.employee_id', $targetEmployeeIds);
                    }
                })
                ->select(
                    'smpe.work_center_employee_id',
                    'smp.id as batch_id',
                    'smp.batch_name',
                    DB::raw("COALESCE(e.full_name, 'Employee') as emp_name")
                )
                ->get();

            if ($occupiedEmployees->isNotEmpty()) {
                $first = $occupiedEmployees->first();
                $validator->errors()->add(
                    'employee_ids',
                    "Employee '{$first->emp_name}' is already assigned to active batch '{$first->batch_name}'. A worker cannot be assigned to another batch unless the previous batch is deleted."
                );
            }
        }

        // 2. External Worker Allocations Check
        if (!empty($extIds)) {
            $occupiedExternals = DB::table('production_external_worker_allocations as ewa')
                ->join('production_section_manual_productions as smp', 'smp.id', '=', 'ewa.manual_production_id')
                ->leftJoin('production_external_worker_pay_grades as pg', 'pg.id', '=', 'ewa.pay_grade_id')
                ->where('ewa.tenant_id', $tenantId)
                ->whereNull('ewa.deleted_at')
                ->whereNull('smp.deleted_at')
                ->when($excludeBatchId, fn($q) => $q->where('smp.id', '!=', $excludeBatchId))
                ->whereIn('ewa.id', $extIds)
                ->select(
                    'ewa.id as allocation_id',
                    'smp.id as batch_id',
                    'smp.batch_name',
                    'pg.name as grade_name'
                )
                ->get();

            if ($occupiedExternals->isNotEmpty()) {
                $first = $occupiedExternals->first();
                $grade = $first->grade_name ? " ({$first->grade_name})" : '';
                $validator->errors()->add(
                    'external_allocation_ids',
                    "External worker allocation #{$first->allocation_id}{$grade} is already assigned to active batch '{$first->batch_name}'. A worker cannot be assigned to another batch unless the previous batch is deleted."
                );
            }
        }
    }
}

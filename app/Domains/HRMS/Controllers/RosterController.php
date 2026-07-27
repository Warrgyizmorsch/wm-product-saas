<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Repositories\RosterRepositoryInterface;
use App\Domains\Production\Models\ProductionShift;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RosterController extends Controller
{
    public function __construct(
        private readonly RosterRepositoryInterface $rosterRepository
    ) {}

    public function index(Request $request): View
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $data = $this->rosterRepository->getIndexData($request->all());

        return view('modules.hrms.roster.index', $data);
    }

    public function storeShift(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:production_shifts,code',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'grace_period_minutes' => 'nullable|integer|min:0',
            'overtime_allowed' => 'nullable|boolean',
            'active' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        $validated['overtime_allowed'] = $request->has('overtime_allowed');
        $validated['active'] = $request->has('active');

        $this->rosterRepository->storeShift($validated);

        return redirect()->route('hrms.roster.index', ['tab' => 'shifts'])
            ->with('success', 'Shift created successfully.');
    }

    public function updateShift(Request $request, ProductionShift $shift): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:50', Rule::unique('production_shifts', 'code')->ignore($shift->id)],
            'start_time' => 'required',
            'end_time' => 'required',
            'grace_period_minutes' => 'nullable|integer|min:0',
            'overtime_allowed' => 'nullable|boolean',
            'active' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        $validated['overtime_allowed'] = $request->has('overtime_allowed');
        $validated['active'] = $request->has('active');

        $this->rosterRepository->updateShift($shift, $validated);

        return redirect()->route('hrms.roster.index', ['tab' => 'shifts'])
            ->with('success', 'Shift updated successfully.');
    }

    public function destroyShift(ProductionShift $shift): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $this->rosterRepository->deleteShift($shift);

        return redirect()->route('hrms.roster.index', ['tab' => 'shifts'])
            ->with('success', 'Shift deleted successfully.');
    }

    public function assignShift(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'shift_id' => 'required|exists:production_shifts,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'note' => 'nullable|string|max:255',
        ]);

        $this->rosterRepository->assignShiftRoster($validated);

        return redirect()->route('hrms.roster.index', ['tab' => 'roster', 'start_date' => $validated['start_date']])
            ->with('success', 'Shift roster assigned successfully.');
    }

    public function clearRoster(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $this->rosterRepository->clearShiftRoster($validated);

        return redirect()->route('hrms.roster.index', ['tab' => 'roster', 'start_date' => $validated['start_date']])
            ->with('success', 'Shift roster cleared for selected period.');
    }
}

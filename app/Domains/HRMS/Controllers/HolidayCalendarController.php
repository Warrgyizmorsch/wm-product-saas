<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\HolidayCalendar;
use App\Domains\HRMS\Repositories\HolidayCalendarRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HolidayCalendarController extends Controller
{
    public function __construct(
        private readonly HolidayCalendarRepositoryInterface $holidayCalendarRepository
    ) {}

    /**
     * Display a listing of the holiday calendar entries.
     */
    public function index(Request $request): View
    {
        $data = $this->holidayCalendarRepository->getIndexData($request->all());

        return view('modules.hrms.holiday-calendar.index', $data);
    }

    /**
     * Store a newly created holiday in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'holiday_date' => 'required|date',
            'description' => 'nullable|string|max:1000',
            'company_id' => 'nullable|exists:companies,id',
            'business_unit_id' => 'nullable|exists:business_units,id',
            'branch_id' => 'nullable|exists:branches,id',
            'status' => 'nullable|boolean',
        ]);

        $validated['status'] = $request->has('status') ? (bool) $request->status : true;

        $this->holidayCalendarRepository->storeHoliday($validated);

        return redirect()->route('hrms.holidays.index')
            ->with('success', __('hrms.holiday.created_success'));
    }

    /**
     * Update the specified holiday in storage.
     */
    public function update(Request $request, HolidayCalendar $holiday): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'holiday_date' => 'required|date',
            'description' => 'nullable|string|max:1000',
            'company_id' => 'nullable|exists:companies,id',
            'business_unit_id' => 'nullable|exists:business_units,id',
            'branch_id' => 'nullable|exists:branches,id',
            'status' => 'nullable|boolean',
        ]);

        $validated['status'] = $request->has('status') ? (bool) $request->status : false;

        $this->holidayCalendarRepository->updateHoliday($holiday, $validated);

        return redirect()->route('hrms.holidays.index')
            ->with('success', __('hrms.holiday.updated_success'));
    }

    /**
     * Remove the specified holiday from storage.
     */
    public function destroy(HolidayCalendar $holiday): RedirectResponse
    {
        $this->holidayCalendarRepository->deleteHoliday($holiday);

        return redirect()->route('hrms.holidays.index')
            ->with('success', __('hrms.holiday.deleted_success'));
    }
}

<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionCalendar;
use App\Domains\Production\Models\ProductionCalendarHoliday;
use App\Domains\Production\Requests\StoreCalendarRequest;
use App\Domains\Production\Requests\StoreHolidayRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();

        $query = ProductionCalendar::where('tenant_id', $tenantId);

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where('name', 'like', $search);
        }

        $calendars = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        return view('modules.production.calendars.index', compact('calendars'));
    }

    public function create()
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        return view('modules.production.calendars.create');
    }

    public function store(StoreCalendarRequest $request)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();

        $data = $request->validated();
        $data['tenant_id'] = $tenantId;
        $data['is_default'] = $request->boolean('is_default');

        // Cast working_days to integers
        $data['working_days'] = array_map('intval', $data['working_days']);

        DB::transaction(function () use ($tenantId, $data) {
            if ($data['is_default']) {
                ProductionCalendar::where('tenant_id', $tenantId)->update(['is_default' => false]);
            }
            ProductionCalendar::create($data);
        });

        return redirect()->route('production.calendars.index')
            ->with('success', 'Production Calendar created successfully.');
    }

    public function edit(int $id)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();
        $calendar = ProductionCalendar::where('tenant_id', $tenantId)
            ->with(['holidays' => function ($query) {
                $query->orderBy('holiday_date', 'asc');
            }])
            ->findOrFail($id);

        return view('modules.production.calendars.edit', compact('calendar'));
    }

    public function update(StoreCalendarRequest $request, int $id)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();
        $calendar = ProductionCalendar::where('tenant_id', $tenantId)->findOrFail($id);

        $data = $request->validated();
        $data['is_default'] = $request->boolean('is_default');

        // Cast working_days to integers
        $data['working_days'] = array_map('intval', $data['working_days']);

        DB::transaction(function () use ($tenantId, $calendar, $data) {
            if ($data['is_default']) {
                ProductionCalendar::where('tenant_id', $tenantId)->update(['is_default' => false]);
            }
            $calendar->update($data);
        });

        return redirect()->route('production.calendars.index')
            ->with('success', 'Production Calendar updated successfully.');
    }

    public function destroy(int $id)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();
        $calendar = ProductionCalendar::where('tenant_id', $tenantId)->findOrFail($id);

        $calendar->delete();

        return redirect()->route('production.calendars.index')
            ->with('success', 'Production Calendar deleted.');
    }

    public function storeHoliday(StoreHolidayRequest $request, int $calendarId)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();
        $calendar = ProductionCalendar::where('tenant_id', $tenantId)->findOrFail($calendarId);

        $data = $request->validated();
        $data['tenant_id'] = $tenantId;
        $data['production_calendar_id'] = $calendar->id;
        $data['is_full_day'] = $request->boolean('is_full_day', true);
        $data['active'] = $request->boolean('active', true);

        if ($data['is_full_day']) {
            $data['start_time'] = null;
            $data['end_time'] = null;
        }

        ProductionCalendarHoliday::create($data);

        return redirect()->back()->with('success', 'Holiday added to calendar.');
    }

    public function updateHoliday(StoreHolidayRequest $request, int $calendarId, int $holidayId)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();
        $calendar = ProductionCalendar::where('tenant_id', $tenantId)->findOrFail($calendarId);
        $holiday = ProductionCalendarHoliday::where('tenant_id', $tenantId)
            ->where('production_calendar_id', $calendar->id)
            ->findOrFail($holidayId);

        $data = $request->validated();
        $data['is_full_day'] = $request->boolean('is_full_day', true);
        $data['active'] = $request->boolean('active', true);

        if ($data['is_full_day']) {
            $data['start_time'] = null;
            $data['end_time'] = null;
        }

        $holiday->update($data);

        return redirect()->back()->with('success', 'Holiday updated.');
    }

    public function destroyHoliday(int $calendarId, int $holidayId)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();
        $calendar = ProductionCalendar::where('tenant_id', $tenantId)->findOrFail($calendarId);
        $holiday = ProductionCalendarHoliday::where('tenant_id', $tenantId)
            ->where('production_calendar_id', $calendar->id)
            ->findOrFail($holidayId);

        $holiday->delete();

        return redirect()->back()->with('success', 'Holiday removed.');
    }
}

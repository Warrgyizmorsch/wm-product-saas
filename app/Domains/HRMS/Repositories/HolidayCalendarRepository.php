<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\HolidayCalendar;
use Illuminate\Support\Facades\DB;

class HolidayCalendarRepository implements HolidayCalendarRepositoryInterface
{
    public function getIndexData(array $inputs): array
    {
        $companies = Company::query()->orderBy('company_name', 'asc')->get();
        $businessUnits = BusinessUnit::query()->orderBy('name', 'asc')->get();
        $branches = Branch::query()->orderBy('name', 'asc')->get();

        $query = HolidayCalendar::query()->with(['company', 'businessUnit', 'branch']);

        // Search text
        if (!empty($inputs['search'])) {
            $search = trim($inputs['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('holiday_date', 'like', "%{$search}%");
            });
        }

        // Scope filters
        if (!empty($inputs['company_id'])) {
            $query->where('company_id', $inputs['company_id']);
        }
        if (!empty($inputs['business_unit_id'])) {
            $query->where('business_unit_id', $inputs['business_unit_id']);
        }
        if (!empty($inputs['branch_id'])) {
            $query->where('branch_id', $inputs['branch_id']);
        }

        // Status
        if (isset($inputs['status']) && $inputs['status'] !== '') {
            $query->where('status', (bool) $inputs['status']);
        }

        // Year filter
        if (!empty($inputs['year'])) {
            $query->whereYear('holiday_date', (int) $inputs['year']);
        }

        // Sorting
        $sort = $inputs['sort'] ?? 'date_asc';
        if ($sort === 'date_desc') {
            $query->orderBy('holiday_date', 'desc')->orderBy('id', 'desc');
        } elseif ($sort === 'name_asc') {
            $query->orderBy('name', 'asc')->orderBy('id', 'asc');
        } elseif ($sort === 'name_desc') {
            $query->orderBy('name', 'desc')->orderBy('id', 'desc');
        } else {
            $query->orderBy('holiday_date', 'asc')->orderBy('id', 'asc');
        }

        $holidays = $query->paginate(10)->withQueryString();

        // Get unique years that have holidays for the year filter dropdown (DB-agnostic)
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $availableYears = HolidayCalendar::query()
                ->selectRaw("strftime('%Y', holiday_date) as year")
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->map(fn($y) => (int) $y)
                ->filter(fn($y) => $y > 0)
                ->values()
                ->toArray();
        } else {
            $availableYears = HolidayCalendar::query()
                ->selectRaw('YEAR(holiday_date) as year')
                ->distinct()
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->map(fn($y) => (int) $y)
                ->filter(fn($y) => $y > 0)
                ->values()
                ->toArray();
        }

        $filters = array_merge([
            'search' => '',
            'company_id' => '',
            'business_unit_id' => '',
            'branch_id' => '',
            'status' => '',
            'year' => '',
            'sort' => 'date_asc',
        ], $inputs);

        return [
            'holidays' => $holidays,
            'companies' => $companies,
            'businessUnits' => $businessUnits,
            'branches' => $branches,
            'availableYears' => $availableYears,
            'filters' => $filters,
        ];
    }

    public function storeHoliday(array $validated): HolidayCalendar
    {
        return HolidayCalendar::create($validated);
    }

    public function updateHoliday(HolidayCalendar $holiday, array $validated): bool
    {
        return $holiday->update($validated);
    }

    public function deleteHoliday(HolidayCalendar $holiday): bool
    {
        return $holiday->delete();
    }
}

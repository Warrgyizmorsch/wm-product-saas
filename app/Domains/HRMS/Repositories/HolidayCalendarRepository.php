<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\HolidayCalendar;

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
            $search = $inputs['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
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
            $query->whereYear('holiday_date', $inputs['year']);
        }

        // Sorting
        $sort = $inputs['sort'] ?? 'date_asc';
        if ($sort === 'date_desc') {
            $query->orderBy('holiday_date', 'desc');
        } elseif ($sort === 'name_asc') {
            $query->orderBy('name', 'asc');
        } elseif ($sort === 'name_desc') {
            $query->orderBy('name', 'desc');
        } else {
            $query->orderBy('holiday_date', 'asc');
        }

        $holidays = $query->paginate(10)->withQueryString();

        // Get unique years that have holidays for the year filter dropdown
        $availableYears = HolidayCalendar::query()
            ->selectRaw('YEAR(holiday_date) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        // If current year is not in available years, prepopulate it
        $currentYear = now()->year;
        if (!in_array($currentYear, $availableYears)) {
            array_unshift($availableYears, $currentYear);
        }

        return [
            'holidays' => $holidays,
            'companies' => $companies,
            'businessUnits' => $businessUnits,
            'branches' => $branches,
            'availableYears' => $availableYears,
            'filters' => $inputs,
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

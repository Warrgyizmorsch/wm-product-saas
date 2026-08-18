<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\HolidayCalendar;

interface HolidayCalendarRepositoryInterface
{
    public function getIndexData(array $inputs): array;

    public function storeHoliday(array $validated): HolidayCalendar;

    public function updateHoliday(HolidayCalendar $holiday, array $validated): bool;

    public function deleteHoliday(HolidayCalendar $holiday): bool;
}

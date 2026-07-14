<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => 'required|string|max:255',
            'holiday_date' => 'required|date',
            'holiday_type' => 'required|string|in:public_holiday,weekend,maintenance_shutdown,other',
            'description'  => 'nullable|string|max:1000',
            'is_full_day'  => 'nullable|boolean',
            'start_time'   => 'nullable',
            'end_time'     => 'nullable',
            'active'       => 'nullable|boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $isFullDay = $this->boolean('is_full_day', true);
            
            if (!$isFullDay) {
                $startTime = $this->input('start_time');
                $endTime = $this->input('end_time');

                if (empty($startTime)) {
                    $validator->errors()->add('start_time', 'Start time is required for partial-day holidays.');
                }
                if (empty($endTime)) {
                    $validator->errors()->add('end_time', 'End time is required for partial-day holidays.');
                }

                if (!empty($startTime) && !empty($endTime)) {
                    if (strtotime($endTime) <= strtotime($startTime)) {
                        $validator->errors()->add('end_time', 'End time must be after the start time.');
                    }
                }
            }

            // Prevent duplicate entries (same calendar and same date)
            $tenantId = require_tenant_id();
            $calendarId = $this->route('calendar');
            // If updating, the route parameter might be 'holiday' (depending on route binding or controller method arg)
            $holidayId = $this->route('holiday');

            $query = \App\Domains\Production\Models\ProductionCalendarHoliday::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('production_calendar_id', $calendarId)
                ->whereDate('holiday_date', $this->input('holiday_date'));

            if ($holidayId) {
                $query->where('id', '!=', $holidayId);
            }

            if ($query->exists()) {
                $validator->errors()->add('holiday_date', 'A holiday already exists on this date for this calendar.');
            }
        });
    }
}

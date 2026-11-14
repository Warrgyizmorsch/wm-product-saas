<?php

namespace App\Domains\Projects\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class StoreTimeLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('is_billable')) {
            $this->merge(['is_billable' => true]);
        }

        if ($this->filled('start_time') && $this->filled('end_time')) {
            try {
                $start = Carbon::createFromFormat('H:i', $this->input('start_time'));
                $end = Carbon::createFromFormat('H:i', $this->input('end_time'));
                if ($end->gt($start)) {
                    $hours = round($start->floatDiffInHours($end), 2);
                    $this->merge(['hours' => $hours]);
                }
            } catch (\Throwable) {
                // If invalid format, validator will catch it
            }
        }
    }

    public function rules(): array
    {
        return [
            'log_date'    => ['required', 'date'],
            'start_time'  => ['nullable', 'date_format:H:i'],
            'end_time'    => ['nullable', 'date_format:H:i'],
            'hours'       => ['required', 'numeric', 'min:0.01', 'max:24'],
            'is_billable' => ['nullable', 'boolean'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('start_time') && $this->filled('end_time')) {
                if ($this->input('end_time') <= $this->input('start_time')) {
                    $validator->errors()->add('end_time', __('projects.end_time_must_be_after_start_time', ['default' => 'The end time must be after the start time.']));
                }
            }
        });
    }
}

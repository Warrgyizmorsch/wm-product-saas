@extends('layouts.duralux')

@section('title', 'Edit Calendar | SaaS ERP')
@section('page-title', 'Edit Production Calendar')
@section('breadcrumb', 'Edit Calendar')

@section('content')
    <div class="erp-single-panel bg-white">
        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" title="Validation Failed: {{ $errors->first() }}" />
        @endif

        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        <form method="POST" action="{{ route('production.calendars.update', $calendar->id) }}">
            @csrf
            @method('PUT')

            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">Edit Calendar - {{ $calendar->name }}</h4>
                    <a href="{{ route('production.calendars.index') }}" class="text-muted hover-danger fs-18">
                        <i class="feather-x"></i>
                    </a>
                </div>

                <!-- Form Fields -->
                <div class="row g-4 mb-4 fs-13 text-dark">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Calendar Name" name="name" placeholder="e.g. Standard Weekday Calendar" :value="old('name', $calendar->name)" :required="true" :error-text="$errors->first('name')" />
                        
                        <div class="mt-4 pt-2">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_default" id="is_default" value="1" @checked(old('is_default', $calendar->is_default))>
                                <label class="form-check-label fw-semibold text-dark" for="is_default">Mark as Default System Calendar</label>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark mb-2">Select Working Days</label>
                        <div class="border rounded p-3 bg-light">
                            @php
                                $daysOfWeek = [
                                    1 => 'Monday',
                                    2 => 'Tuesday',
                                    3 => 'Wednesday',
                                    4 => 'Thursday',
                                    5 => 'Friday',
                                    6 => 'Saturday',
                                    0 => 'Sunday'
                                ];
                                $dayNamesMap = [
                                    'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
                                    'thursday' => 4, 'friday' => 5, 'saturday' => 6
                                ];
                                $oldWorkingDays = array_map(function($day) use ($dayNamesMap) {
                                    if (is_numeric($day)) {
                                        return (int)$day;
                                    }
                                    return $dayNamesMap[strtolower($day)] ?? $day;
                                }, old('working_days', $calendar->working_days ?? []));
                            @endphp
                            @foreach($daysOfWeek as $value => $label)
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="working_days[]" id="day_{{ $value }}" value="{{ $value }}" @checked(in_array($value, $oldWorkingDays))>
                                    <label class="form-check-label fw-medium text-dark" for="day_{{ $value }}">
                                        {{ $label }}
                                    </label>
                                </div>
                            @endforeach
                            @error('working_days')
                                <span class="text-danger fs-11 mt-1 d-block">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('production.calendars.index') }}" class="btn btn-light border">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Calendar</button>
                </div>
            </x-ui.odoo-form-ui>
        </form>

        <!-- Holiday Management Section -->
        <div class="card mt-4 border border-light shadow-sm">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
                <div>
                    <h5 class="fw-bold text-dark mb-1"><i class="feather-calendar me-2 text-primary"></i>Calendar Holidays</h5>
                    <small class="text-muted">Manage scheduled shut-downs, maintenance windows, and public holidays.</small>
                </div>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createHolidayModal">
                    <i class="feather-plus me-1"></i>Add Holiday
                </button>
            </div>
            <div class="card-body p-0">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 25%">Holiday Name</th>
                            <th style="width: 15%">Date</th>
                            <th style="width: 15%">Type</th>
                            <th style="width: 25%">Working Hours</th>
                            <th style="width: 10%">Status</th>
                            <th class="text-end" style="width: 10%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($calendar->holidays as $holiday)
                            <tr>
                                <td class="fw-semibold text-dark">
                                    {{ $holiday->name }}
                                    @if($holiday->description)
                                        <small class="text-muted d-block font-normal">{{ $holiday->description }}</small>
                                    @endif
                                </td>
                                <td class="font-monospace text-dark">{{ $holiday->holiday_date->format('Y-m-d') }}</td>
                                <td>
                                    @php
                                        $typeLabels = [
                                            'public_holiday' => 'Public Holiday',
                                            'weekend' => 'Weekend Override',
                                            'maintenance_shutdown' => 'Maintenance Shutdown',
                                            'other' => 'Other Holiday'
                                        ];
                                        $typeBadges = [
                                            'public_holiday' => 'bg-soft-primary text-primary',
                                            'weekend' => 'bg-soft-warning text-warning',
                                            'maintenance_shutdown' => 'bg-soft-danger text-danger',
                                            'other' => 'bg-soft-secondary text-secondary'
                                        ];
                                    @endphp
                                    <span class="badge {{ $typeBadges[$holiday->holiday_type] ?? 'bg-soft-secondary text-secondary' }}">{{ $typeLabels[$holiday->holiday_type] ?? $holiday->holiday_type }}</span>
                                </td>
                                <td>
                                    @if($holiday->is_full_day)
                                        <span class="text-dark"><i class="feather-clock me-1 text-muted"></i>Full Day (All Shifts Closed)</span>
                                    @else
                                        <span class="text-primary fw-semibold"><i class="feather-clock me-1 text-primary"></i>Partial ({{ substr($holiday->start_time, 0, 5) }} - {{ substr($holiday->end_time, 0, 5) }})</span>
                                    @endif
                                </td>
                                <td>
                                    @if($holiday->active)
                                        <span class="badge bg-soft-success text-success">Active</span>
                                    @else
                                        <span class="badge bg-soft-light text-muted">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-sm btn-link text-primary p-0" data-bs-toggle="modal" data-bs-target="#editHolidayModal{{ $holiday->id }}" title="Edit">
                                            <i class="feather-edit fs-14"></i>
                                        </button>
                                        <form method="POST" action="{{ route('production.calendars.holidays.destroy', [$calendar->id, $holiday->id]) }}" onsubmit="return confirm('Are you sure you want to delete this holiday?');" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Delete">
                                                <i class="feather-trash-2 fs-14"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="feather-info me-2 fs-16"></i>No holidays configured for this calendar.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        </div>
    </div>

    <!-- Create Holiday Modal -->
    <div class="modal fade text-dark" id="createHolidayModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="createHolidayModalLabel" aria-hidden="true" x-data="{ isFullDay: true }">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg">
                <form action="{{ route('production.calendars.holidays.store', $calendar->id) }}" method="POST">
                    @csrf
                    <div class="modal-header bg-primary text-white border-0 py-3">
                        <h5 class="modal-title fw-bold" id="createHolidayModalLabel"><i class="feather-calendar me-2"></i>Add Calendar Holiday</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="input" label="Holiday Name" name="name" placeholder="e.g. Christmas Day" :required="true" />
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" label="Holiday Date" name="holiday_date" inputType="date" :required="true" />
                            </div>
                            <div class="col-md-6">
                                <label class="odoo-form-label mb-1">Holiday Type</label>
                                <select class="form-select fs-13" name="holiday_type" required>
                                    <option value="public_holiday">Public Holiday</option>
                                    <option value="weekend">Weekend Override</option>
                                    <option value="maintenance_shutdown">Maintenance Shutdown</option>
                                    <option value="other">Other Holiday</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="textarea" label="Description" name="description" placeholder="e.g. Yearly corporate maintenance shutdown..." rows="2" />
                        </div>
                        <div class="row align-items-center mb-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_full_day" id="create_is_full_day" value="1" x-model="isFullDay" checked>
                                    <label class="form-check-label fw-semibold" for="create_is_full_day">Is Full-Day Holiday</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="active" id="create_active" value="1" checked>
                                    <label class="form-check-label fw-semibold" for="create_active">Active Status</label>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3" x-show="!isFullDay" x-transition>
                            <div class="col-6">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">Start Time</label>
                                <input type="time" name="start_time" class="form-control fs-13">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">End Time</label>
                                <input type="time" name="end_time" class="form-control fs-13">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Holiday</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach($calendar->holidays as $holiday)
        <!-- Edit Holiday Modal {{ $holiday->id }} -->
        <div class="modal fade text-dark" id="editHolidayModal{{ $holiday->id }}" data-bs-backdrop="static" tabindex="-1" aria-labelledby="editHolidayModal{{ $holiday->id }}Label" aria-hidden="true" x-data="{ isFullDay: {{ $holiday->is_full_day ? 'true' : 'false' }} }">
            <div class="modal-dialog">
                <div class="modal-content border-0 shadow-lg">
                    <form action="{{ route('production.calendars.holidays.update', [$calendar->id, $holiday->id]) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="modal-header bg-primary text-white border-0 py-3">
                            <h5 class="modal-title fw-bold" id="editHolidayModal{{ $holiday->id }}Label"><i class="feather-calendar me-2"></i>Edit Calendar Holiday</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <x-ui.odoo-form-ui type="input" label="Holiday Name" name="name" placeholder="e.g. Christmas Day" :value="$holiday->name" :required="true" />
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" label="Holiday Date" name="holiday_date" inputType="date" :value="$holiday->holiday_date->format('Y-m-d')" :required="true" />
                                </div>
                                <div class="col-md-6">
                                    <label class="odoo-form-label mb-1">Holiday Type</label>
                                    <select class="form-select fs-13" name="holiday_type" required>
                                        <option value="public_holiday" @selected($holiday->holiday_type === 'public_holiday')>Public Holiday</option>
                                        <option value="weekend" @selected($holiday->holiday_type === 'weekend')>Weekend Override</option>
                                        <option value="maintenance_shutdown" @selected($holiday->holiday_type === 'maintenance_shutdown')>Maintenance Shutdown</option>
                                        <option value="other" @selected($holiday->holiday_type === 'other')>Other Holiday</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <x-ui.odoo-form-ui type="textarea" label="Description" name="description" placeholder="e.g. Yearly corporate maintenance shutdown..." rows="2" :value="$holiday->description" />
                            </div>
                            <div class="row align-items-center mb-3">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="is_full_day" id="edit_is_full_day_{{ $holiday->id }}" value="1" x-model="isFullDay" @checked($holiday->is_full_day)>
                                        <label class="form-check-label fw-semibold" for="edit_is_full_day_{{ $holiday->id }}">Is Full-Day Holiday</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="active" id="edit_active_{{ $holiday->id }}" value="1" @checked($holiday->active)>
                                        <label class="form-check-label fw-semibold" for="edit_active_{{ $holiday->id }}">Active Status</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3" x-show="!isFullDay" x-transition>
                                <div class="col-6">
                                    <label class="form-label fw-bold text-dark fs-12 mb-1">Start Time</label>
                                    <input type="time" name="start_time" class="form-control fs-13" value="{{ $holiday->start_time ? substr($holiday->start_time, 0, 5) : '' }}">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold text-dark fs-12 mb-1">End Time</label>
                                    <input type="time" name="end_time" class="form-control fs-13" value="{{ $holiday->end_time ? substr($holiday->end_time, 0, 5) : '' }}">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Holiday</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

    @push('scripts')
        <script>
            $(document).ready(function() {
                $('.modal').appendTo('body');
            });
        </script>
    @endpush
@endsection

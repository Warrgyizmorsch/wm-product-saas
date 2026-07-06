@extends('layouts.duralux')

@section('title', 'Create Schedule | SaaS ERP')
@section('page-title', 'Create Production Schedule')
@section('breadcrumb', 'Create Schedule')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('content')
    <div class="erp-single-panel bg-white">

        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" title="Validation Failed: {{ $errors->first() }}" />
        @endif

        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <form method="POST" action="{{ route('production.schedules.store') }}">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                {{-- Sheet Header --}}
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">New Production Schedule</h4>
                    <a href="{{ route('production.schedules.index') }}" class="text-muted hover-danger fs-18">
                        <i class="feather-x"></i>
                    </a>
                </div>

                <div class="row g-4 mb-4 fs-13 text-dark">
                    <div class="col-md-6">
                        {{-- Production Order --}}
                        <x-ui.odoo-form-ui
                            type="select"
                            label="Production Order"
                            name="production_order_id"
                            :required="true"
                            :error-text="$errors->first('production_order_id')"
                            data-select2-selector="default"
                        >
                            <option value="">Select Released Production Order...</option>
                            @foreach($orders as $order)
                                <option value="{{ $order->id }}" {{ old('production_order_id') == $order->id ? 'selected' : '' }}>
                                    {{ $order->order_number }} — {{ $order->product->name ?? 'N/A' }}
                                    ({{ ucfirst(str_replace('_', ' ', $order->status)) }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        {{-- Schedule Start Date --}}
                        <x-ui.odoo-form-ui
                            type="input"
                            label="Schedule Start Date"
                            name="start_date"
                            inputType="datetime-local"
                            :value="old('start_date', now()->format('Y-m-d\TH:i'))"
                            :required="true"
                            :error-text="$errors->first('start_date')"
                        />
                    </div>

                    <div class="col-md-6">
                        {{-- Scheduling Type --}}
                        <div class="mb-3">
                            <x-ui.odoo-form-ui
                                type="select"
                                label="Scheduling Type"
                                name="scheduling_type"
                                :required="true"
                                :error-text="$errors->first('scheduling_type')"
                            >
                                <option value="forward" {{ old('scheduling_type', 'forward') === 'forward' ? 'selected' : '' }}>
                                    Forward Scheduling (Start → Finish)
                                </option>
                                <option value="backward" disabled title="Coming in future release">
                                    Backward Scheduling (Finish → Start) — Coming Soon
                                </option>
                                <option value="manual" disabled title="Coming in future release">
                                    Manual Scheduling — Coming Soon
                                </option>
                            </x-ui.odoo-form-ui>
                            <small class="text-muted fs-11 mt-1 d-block">
                                <i class="feather-info me-1"></i>Only Forward Scheduling is available in this release. Additional strategies are in development.
                            </small>
                        </div>

                        {{-- Notes --}}
                        <x-ui.odoo-form-ui
                            type="textarea"
                            label="Notes"
                            name="notes"
                            placeholder="Optional scheduling notes or remarks..."
                            :value="old('notes')"
                            :error-text="$errors->first('notes')"
                        />
                    </div>
                </div>

                {{-- Information Alert --}}
                <div class="alert alert-info border-info bg-soft-info d-flex align-items-start p-3 rounded mb-4">
                    <i class="feather-info me-3 text-info mt-1"></i>
                    <div>
                        <strong class="text-info">How Scheduling Works</strong>
                        <p class="mb-0 fs-12 text-info-800 mt-1">
                            The scheduling engine will automatically:
                            <br>• Calculate planned start and finish times for each operation based on routing sequence.
                            <br>• Assign operations to their configured Work Centers and Machines.
                            <br>• Validate machine availability and work center eligibility.
                            <br>• Alert you to any capacity overloads or conflicts.
                        </p>
                    </div>
                </div>

                {{-- Submit Buttons --}}
                <div class="d-flex align-items-center gap-2 border-top pt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="feather-calendar me-2"></i>Generate Schedule
                    </button>
                    <a href="{{ route('production.schedules.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection

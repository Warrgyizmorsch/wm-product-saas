@extends('layouts.duralux')

@section('title', 'Initiate CAPA | SaaS ERP')
@section('page-title', 'Create CAPA Record')
@section('breadcrumb', 'New CAPA')

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

        <form method="POST" action="{{ route('production.capas.store') }}">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                {{-- Header with Close Button --}}
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">Initiate Corrective & Preventive Action (CAPA)</h4>
                    <a href="{{ route('production.capas.index') }}" class="text-muted hover-danger fs-18">
                        <i class="feather-x"></i>
                    </a>
                </div>

                {{-- Form Fields --}}
                <div class="row g-4 mb-4 fs-13 text-dark">
                    {{-- Left Column --}}
                    <div class="col-md-6 border-end">
                        <x-ui.odoo-form-ui type="select" label="Linked NCR (Optional)" name="ncr_id" :error-text="$errors->first('ncr_id')">
                            <option value="">None / General CAPA Investigation</option>
                            @foreach($ncrs as $ncr)
                                <option value="{{ $ncr->id }}" @selected(old('ncr_id') == $ncr->id)>
                                    {{ $ncr->ncr_number }} — {{ Str::limit($ncr->description, 50) }}
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" label="Action Owner" name="action_owner_id" :required="true" :error-text="$errors->first('action_owner_id')">
                            <option value="">Select Assignee</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected(old('action_owner_id') == $user->id)>
                                    {{ $user->name }} ({{ $user->email }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="input" label="Target Closure Date" name="target_date" inputType="date" :value="old('target_date')" :required="true" :error-text="$errors->first('target_date')" />
                    </div>

                    {{-- Right Column --}}
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="textarea" label="Corrective Action Plan" name="corrective_action" placeholder="Describe the corrective measures to fix the root cause..." rows="4" :required="true" :error-text="$errors->first('corrective_action')">{{ old('corrective_action') }}</x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="textarea" label="Preventive Action Plan" name="preventive_action" placeholder="Describe measures to prevent recurrence..." rows="4" :error-text="$errors->first('preventive_action')">{{ old('preventive_action') }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                {{-- Footer Action Buttons --}}
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="feather-check-circle me-2"></i>Initiate CAPA Action
                    </button>
                    <a href="{{ route('production.capas.index') }}" class="btn btn-secondary px-4">Cancel</a>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection

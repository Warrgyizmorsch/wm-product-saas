@extends('layouts.duralux')

@section('title', 'Map Skill | SaaS ERP')
@section('page-title', 'Map Operator Skill')
@section('breadcrumb', 'Map Skill')

@section('content')
    <div class="erp-single-panel bg-white">
        <form method="POST" action="{{ route('production.operator-skills.store') }}">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">Map Operator Skill Qualification</h4>
                    <a href="{{ route('production.operator-skills.index') }}" class="text-muted hover-danger fs-18">
                        <i class="feather-x"></i>
                    </a>
                </div>

                <!-- Form Fields -->
                <div class="row g-4 mb-4 fs-13 text-dark">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="select" label="Operator / User" name="user_id" id="user_id" :required="true" :error-text="$errors->first('user_id')">
                            <option value="">Select User...</option>
                            @foreach($users as $usr)
                                <option value="{{ $usr->id }}" @selected(old('user_id') == $usr->id)>{{ $usr->name }} ({{ $usr->email }})</option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="input" label="Skill Code / Qualification" name="skill_code" placeholder="e.g. SKL-WELD, SKL-CNC" :value="old('skill_code')" :required="true" :error-text="$errors->first('skill_code')" />
                    </div>

                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="select" label="Limit to Work Center" name="work_center_id" id="work_center_id" :error-text="$errors->first('work_center_id')">
                            <option value="">No Work Center Restriction (All)</option>
                            @foreach($workCenters as $wc)
                                <option value="{{ $wc->id }}" @selected(old('work_center_id') == $wc->id)>{{ $wc->name }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" label="Limit to Machine" name="machine_id" id="machine_id" :error-text="$errors->first('machine_id')">
                            <option value="">No Machine Restriction (All)</option>
                            @foreach($machines as $m)
                                <option value="{{ $m->id }}" @selected(old('machine_id') == $m->id)>{{ $m->name }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <div class="mt-4 pt-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="active" id="active" value="1" @checked(old('active', true))>
                                <label class="form-check-label fw-semibold text-dark" for="active">Active Qualification</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('production.operator-skills.index') }}" class="btn btn-light border">Cancel</a>
                    <button type="submit" class="btn btn-primary">Map Skill</button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection

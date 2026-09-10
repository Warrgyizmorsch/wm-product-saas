@extends('layouts.duralux')

@section('title', __('production.edit_machine_asset') . ' | SaaS ERP')
@section('page-title', __('production.edit_machine_asset'))
@section('breadcrumb', __('production.edit_machine_asset'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('content')
    <div class="erp-single-panel">
        <!-- Validation Errors -->
        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" title="{{ __('production.validation_failed') ?? 'Validation Failed' }}: {{ $errors->first() }}" />
        @endif

        <div class="card border mb-4">
            <div class="card-body py-3">
                @if($machine->isLinkedToAsset())
                    @php $linkedAsset = $machine->asset; @endphp
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <div class="fs-11 text-uppercase fw-bold text-muted mb-1">Linked Fixed Asset</div>
                            <a href="{{ route('accounting.fixed-assets.show', $linkedAsset->id) }}" class="fw-bold text-primary font-monospace text-decoration-none">
                                {{ $linkedAsset->asset_code }}
                            </a>
                            <span class="text-dark ms-1">{{ $linkedAsset->name }}</span>
                            <div class="fs-12 text-muted mt-1">
                                {{ $linkedAsset->category->name ?? '—' }} &middot;
                                Cost {{ number_format($linkedAsset->purchase_cost, 2) }} &middot;
                                <x-ui.status-badge :status="$linkedAsset->status" size="sm" />
                            </div>
                        </div>
                        <form action="{{ route('production.machines.unlink-asset', $machine->id) }}" method="POST"
                              onsubmit="return confirm('Unlink this fixed asset from the machine? The asset itself will not be affected.');">
                            @csrf
                            <x-ui.button type="submit" variant="light" icon="feather-link-2" class="border text-danger">
                                Unlink
                            </x-ui.button>
                        </form>
                    </div>
                @else
                    <div class="fs-11 text-uppercase fw-bold text-muted mb-2">Linked Fixed Asset</div>
                    @if($linkableAssets->isEmpty())
                        <div class="fs-12 text-muted">No unregistered machinery assets available to link.</div>
                    @else
                        <form action="{{ route('production.machines.link-asset', $machine->id) }}" method="POST" class="d-flex gap-2 align-items-end">
                            @csrf
                            <div class="flex-grow-1" style="max-width: 420px;">
                                <x-ui.odoo-form-ui type="select" name="asset_id" select2-selector="default">
                                    <option value="">Select a purchased asset...</option>
                                    @foreach($linkableAssets as $asset)
                                        <option value="{{ $asset->id }}">{{ $asset->asset_code }} — {{ $asset->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <x-ui.button type="submit" variant="primary" icon="feather-link">
                                Link
                            </x-ui.button>
                        </form>
                    @endif
                @endif
            </div>
        </div>

        <form method="POST" action="{{ route('production.machines.update', $machine->id) }}">
            @csrf
            @method('PUT')

            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">{{ __('production.edit_machine_asset_with_name', ['name' => $machine->code]) }}</h4>
                    <x-ui.button :href="route('production.machines.index')" variant="light" class="border">{{ __('production.cancel') }}</x-ui.button>
                </div>

                <div class="row g-4 fs-13 text-dark">
                    <!-- Left Column -->
                    <div class="col-md-6 border-end">
                        <x-ui.odoo-form-ui type="select" :label="__('production.work_center_assignment')" name="work_center_id" :required="true">
                            <option value="">{{ __('production.select_work_center') }}</option>
                            @foreach($workCenters as $wc)
                                <option value="{{ $wc->id }}" @selected(old('work_center_id', $machine->work_center_id) == $wc->id)>
                                    {{ $wc->name }}
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.machine_name')" name="name" :value="old('name', $machine->name)" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.machine_asset_code')" name="code" :value="old('code', $machine->code)" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.machine_type')" name="machine_type" :value="old('machine_type', $machine->machine_type)" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.manufacturer')" name="manufacturer" :value="old('manufacturer', $machine->manufacturer)" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.model_number')" name="model_number" :value="old('model_number', $machine->model_number)" />
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('production.machine_hourly_capacity')" name="capacity" inputType="number" :value="old('capacity', $machine->capacity)" />
                        
                        <x-ui.odoo-form-ui type="select" :label="__('production.status')" name="status" :required="true">
                            @foreach($statuses as $k => $v)
                                <option value="{{ $k }}" @selected(old('status', $machine->status) == $k)>{{ __('production.' . $k) ?? $v }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.installation_date')" name="installation_date" inputType="date" :value="old('installation_date', $machine->installation_date ? $machine->installation_date->format('Y-m-d') : '')" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.maintenance_details')" name="maintenance_status" :value="old('maintenance_status', $machine->maintenance_status)" />
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <x-ui.button type="submit" variant="primary" class="px-4">{{ __('production.update_machine_asset') }}</x-ui.button>
                    <x-ui.button :href="route('production.machines.index')" variant="secondary" class="px-4">{{ __('production.cancel') }}</x-ui.button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection

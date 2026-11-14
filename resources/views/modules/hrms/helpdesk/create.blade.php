@extends('layouts.duralux')

@section('title', __('hrms.helpdesk.title_create'))
@section('page-title', __('hrms.helpdesk.page_title_create'))
@section('breadcrumb', __('hrms.helpdesk.breadcrumb_create'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="outline-secondary" icon="feather-arrow-left" href="{{ route('hrms.helpdesk.tickets.index') }}">
            {{ __('hrms.helpdesk.btn_back_to_tickets') }}
        </x-ui.button>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <x-ui.alert variant="success" dismissible class="mb-4">
            <i class="feather-check-circle me-2"></i>{{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" dismissible class="mb-4">
            <i class="feather-alert-triangle me-2"></i>{{ session('error') }}
        </x-ui.alert>
    @endif

    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="erp-single-panel bg-white p-4 p-md-5 shadow-sm rounded border-0 text-dark">
                <div class="border-bottom pb-3 mb-4 d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="fw-bold text-dark mb-1"><i class="feather-life-buoy text-primary me-2"></i> {{ __('hrms.helpdesk.card_create_ticket_title') }}</h5>
                        <p class="text-muted small mb-0">{{ __('hrms.helpdesk.card_create_ticket_subtitle') }}</p>
                    </div>
                    <x-ui.badge variant="primary" soft class="border border-primary-subtle px-3 py-2">
                        <i class="feather-shield me-1"></i> {{ __('hrms.helpdesk.badge_fast_sla') }}
                    </x-ui.badge>
                </div>

                <form action="{{ route('hrms.helpdesk.tickets.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-4">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" :label="__('hrms.helpdesk.field_support_category')" name="category_id" :required="true">
                                <option value="">{{ __('hrms.helpdesk.select_category_placeholder') }}</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>
                                        {{ $cat->name }} ({{ __('hrms.helpdesk.target_sla_prefix', ['hours' => $cat->default_sla_hours]) }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                            @error('category_id')
                                <small class="text-danger mt-1 d-block">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" :label="__('hrms.helpdesk.field_priority_level')" name="priority" :required="true">
                                <option value="low" @selected(old('priority') === 'low')>{{ __('hrms.helpdesk.priority_low_desc') }}</option>
                                <option value="medium" @selected(old('priority', 'medium') === 'medium')>{{ __('hrms.helpdesk.priority_medium_desc') }}</option>
                                <option value="high" @selected(old('priority') === 'high')>{{ __('hrms.helpdesk.priority_high_desc') }}</option>
                                <option value="urgent" @selected(old('priority') === 'urgent')>{{ __('hrms.helpdesk.priority_urgent_desc') }}</option>
                            </x-ui.odoo-form-ui>
                            @error('priority')
                                <small class="text-danger mt-1 d-block">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" :label="__('hrms.helpdesk.field_subject_summary')" id="createTicketSubjectInput" name="subject" value="{{ old('subject') }}" :placeholder="__('hrms.helpdesk.placeholder_subject')" :required="true" />
                            @error('subject')
                                <small class="text-danger mt-1 d-block">{{ $message }}</small>
                            @enderror

                            <!-- Live Ticket Deflection Container -->
                            <div id="createDeflectionContainer" class="mt-2 d-none">
                                <div class="card bg-light border-info">
                                    <div class="card-body p-3">
                                        <div class="fw-bold text-info small mb-1">
                                            <i class="feather-help-circle me-1"></i> {{ __('hrms.helpdesk.deflection_heading') }}
                                        </div>
                                        <ul id="createDeflectionList" class="mb-0 ps-3 small text-dark"></ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" :label="__('hrms.helpdesk.field_detailed_description')" name="description" rows="6" :placeholder="__('hrms.helpdesk.placeholder_description_create')" :required="true">{{ old('description') }}</x-ui.odoo-form-ui>
                            @error('description')
                                <small class="text-danger mt-1 d-block">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="file" :label="__('hrms.helpdesk.field_attachments_optional')" name="attachments[]" :required="false" multiple />
                            <small class="text-muted d-block mt-1">{{ __('hrms.helpdesk.hint_supported_files') }}</small>
                        </div>

                        <div class="col-12 d-flex align-items-center justify-content-end gap-2 pt-3 border-top mt-4">
                            <x-ui.button variant="light" href="{{ route('hrms.helpdesk.tickets.index') }}" class="border px-4">{{ __('hrms.helpdesk.btn_cancel') }}</x-ui.button>
                            <x-ui.button type="submit" variant="primary" icon="feather-send" class="px-4 fw-bold">
                                {{ __('hrms.helpdesk.btn_submit_ticket') }}
                            </x-ui.button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const subjectInput = document.getElementById('createTicketSubjectInput');
        const deflectionContainer = document.getElementById('createDeflectionContainer');
        const deflectionList = document.getElementById('createDeflectionList');

        let debounceTimer;

        if (subjectInput) {
            subjectInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                const query = this.value.trim();

                if (query.length < 3) {
                    deflectionContainer.classList.add('d-none');
                    return;
                }

                debounceTimer = setTimeout(() => {
                    fetch(`{{ route('hrms.helpdesk.kb.suggest') }}?q=${encodeURIComponent(query)}`)
                        .then(res => res.json())
                        .then(articles => {
                            if (articles.length > 0) {
                                deflectionList.innerHTML = articles.map(art => `
                                    <li class="mb-1">
                                        <a href="{{ url('hrms/helpdesk/kb') }}/${art.slug}" target="_blank" class="fw-semibold text-primary text-decoration-none">${art.title}</a>
                                    </li>
                                `).join('');
                                deflectionContainer.classList.remove('d-none');
                            } else {
                                deflectionContainer.classList.add('d-none');
                            }
                        })
                        .catch(() => deflectionContainer.classList.add('d-none'));
                }, 300);
            });
        }
    });
</script>
@endpush
@endsection

@extends('layouts.duralux')

@section('title', 'Submit Support Ticket | Helpdesk')
@section('page-title', 'Submit Helpdesk Ticket')
@section('breadcrumb', 'HRMS / Helpdesk / Submit Ticket')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="outline-secondary" icon="feather-arrow-left" href="{{ route('hrms.helpdesk.tickets.index') }}">
            Back to Tickets
        </x-ui.button>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="feather-alert-triangle me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="erp-single-panel bg-white p-4 p-md-5 shadow-sm rounded border-0 text-dark">
                <div class="border-bottom pb-3 mb-4 d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="fw-bold text-dark mb-1"><i class="feather-life-buoy text-primary me-2"></i> Create Support Ticket</h5>
                        <p class="text-muted small mb-0">Describe your inquiry or issue below. Our support team will respond according to category SLA targets.</p>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                        <i class="feather-shield me-1"></i> Fast SLA Support
                    </span>
                </div>

                <form action="{{ route('hrms.helpdesk.tickets.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-4">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Support Category" name="category_id" :required="true">
                                <option value="">-- Select Category --</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>
                                        {{ $cat->name }} (Target SLA: {{ $cat->default_sla_hours }}h)
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                            @error('category_id')
                                <small class="text-danger mt-1 d-block">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Priority Level" name="priority" :required="true">
                                <option value="low" @selected(old('priority') === 'low')>Low (General Inquiry)</option>
                                <option value="medium" @selected(old('priority', 'medium') === 'medium')>Medium (Standard Request)</option>
                                <option value="high" @selected(old('priority') === 'high')>High (Urgent Attention)</option>
                                <option value="urgent" @selected(old('priority') === 'urgent')>Urgent (Blocking / Critical)</option>
                            </x-ui.odoo-form-ui>
                            @error('priority')
                                <small class="text-danger mt-1 d-block">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="Subject / Short Summary" id="createTicketSubjectInput" name="subject" value="{{ old('subject') }}" placeholder="e.g. Discrepancy in August Payslip Tax Calculation" :required="true" />
                            @error('subject')
                                <small class="text-danger mt-1 d-block">{{ $message }}</small>
                            @enderror

                            <!-- Live Ticket Deflection Container -->
                            <div id="createDeflectionContainer" class="mt-2 d-none">
                                <div class="card bg-light border-info">
                                    <div class="card-body p-3">
                                        <div class="fw-bold text-info small mb-1">
                                            <i class="feather-help-circle me-1"></i> Frequently Asked Articles (Click to resolve query without ticket):
                                        </div>
                                        <ul id="createDeflectionList" class="mb-0 ps-3 small text-dark"></ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Detailed Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="6" placeholder="Please describe your issue or query clearly with any relevant context, dates, error messages..." required>{{ old('description') }}</textarea>
                            @error('description')
                                <small class="text-danger mt-1 d-block">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="file" label="Attachments (Optional)" name="attachments[]" :required="false" multiple />
                            <small class="text-muted d-block mt-1">Supported file types: PDF, Images (PNG, JPG), DOCX, XLSX, CSV (Max 10MB each).</small>
                        </div>

                        <div class="col-12 d-flex align-items-center justify-content-end gap-2 pt-3 border-top mt-4">
                            <a href="{{ route('hrms.helpdesk.tickets.index') }}" class="btn btn-light px-4">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4 fw-bold">
                                <i class="feather-send me-1"></i> Submit Ticket
                            </button>
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

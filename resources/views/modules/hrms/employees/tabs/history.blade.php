<div class="tab-pane fade {{ $activeTabName === 'history' ? 'show active' : '' }}" id="history-pane" role="tabpanel" aria-labelledby="history-tab">
    <div class="card-custom">
        <div class="card-custom-header">
            <div>
                <h5 class="card-custom-title"><i class="feather-clock text-primary"></i> {{ __('hrms.employees.lbl_prev_history') }}</h5>
                <small class="text-muted d-block mt-1">{{ __('hrms.employees.lbl_prev_history_desc') }}</small>
            </div>
            <x-ui.button type="button" variant="primary" size="sm" class="fw-bold text-uppercase d-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#addHistoryModal">
                <i class="feather-plus-circle"></i> {{ __('hrms.employees.btn_add_exp') }}
            </x-ui.button>
        </div>
        <div class="card-body p-4">
            @if(!isset($employee->employmentHistories) || $employee->employmentHistories->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="feather-clock fs-32 d-block mb-3 text-secondary"></i>
                    <div class="fw-bold mb-1">{{ __('hrms.employees.lbl_no_history') }}</div>
                    <div>{{ __('hrms.employees.lbl_no_history_desc') }}</div>
                </div>
            @else
                <div class="timeline-custom">
                    @foreach($employee->employmentHistories as $history)
                        @php
                            $duration = '';
                            if ($history->start_date) {
                                $start = $history->start_date->format('M Y');
                                $end = $history->end_date ? $history->end_date->format('M Y') : __('hrms.employees.lbl_present');
                                $duration = $start . ' — ' . $end;
                            }
                        @endphp
                        <div class="timeline-item d-flex justify-content-between align-items-start gap-3 pb-4">
                             <div class="timeline-content">
                                <h6 class="fw-bold text-dark mb-1">{{ $history->designation }}</h6>
                                <div class="fw-semibold text-primary mb-1 fs-13">{{ $history->company_name }}</div>
                                <div class="text-muted fs-11 mb-2"><i class="feather-calendar me-1"></i>{{ $duration }}</div>
                                @if($history->job_description)
                                    <p class="text-secondary fs-13 mb-0 text-wrap" style="max-width: 100%;">{{ $history->job_description }}</p>
                                @endif
                            </div>
                            <div>
                                    <form action="{{ route('hrms.employees.history.destroy', [$employee->id, $history->id]) }}" method="POST" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.employees.confirm_delete_history') }}', { title: '{{ __('hrms.employees.lbl_delete_history') }}', variant: 'danger', confirmButtonText: '{{ __('hrms.common.delete') }}' });">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-soft-danger border d-flex align-items-center justify-content-center p-0" style="border-radius: 8px; width: 32px; height: 32px; background: rgba(220, 53, 69, 0.05);" title="{{ __('hrms.common.delete') }}">
                                            <i class="feather-trash-2 fs-13"></i>
                                        </button>
                                    </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="modal fade" id="addHistoryModal" tabindex="-1" aria-labelledby="addHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="addHistoryModalLabel">
                        <i class="feather-clock me-2 text-primary" style="font-size: 16px;"></i>{{ __('hrms.employees.mdl_add_history_title') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.employees.history.store', $employee->id) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_company_name') }}" name="company_name" placeholder="{{ __('hrms.employees.mdl_company_name_placeholder') }}" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_designation') }}" name="designation" placeholder="{{ __('hrms.employees.mdl_designation_placeholder') }}" :required="true" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_start_date') }}" name="start_date" inputType="date" :required="true" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_end_date') }}" name="end_date" inputType="date" placeholder="{{ __('hrms.employees.mdl_end_date_placeholder') }}" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.employees.mdl_job_desc') }}" name="job_description" rows="3" placeholder="{{ __('hrms.employees.mdl_job_desc_placeholder') }}" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_save_exp') }}</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_discard') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

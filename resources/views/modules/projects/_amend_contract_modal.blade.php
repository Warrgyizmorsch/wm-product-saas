<div class="modal fade" id="modalAmendContractSetting" tabindex="-1" aria-labelledby="modalAmendContractSettingLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 500px;">
        <div class="modal-content shadow rounded-3 border-0 overflow-hidden">
            {{-- Modal Header --}}
            <div class="modal-header py-3 px-4 bg-light border-bottom align-items-center">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-circle bg-warning-subtle text-warning d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="feather-alert-triangle fs-16 text-warning"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0 fs-14" id="modalAmendContractSettingLabel">
                            {{ __('projects.amend_contractual_setting') }}
                        </h6>
                        <small class="text-muted fs-11">{{ __('projects.amend_contractual_setting_desc') }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            {{-- Modal Body --}}
            <div class="modal-body p-4">
                {{-- Field Summary Card --}}
                <div class="p-3 rounded-3 border mb-3" style="background-color: #f8fafc;">
                    <div class="row align-items-center g-2">
                        <div class="col-5">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block" id="modalAmendFieldLabel">Setting</span>
                            <span class="badge bg-white text-dark border shadow-sm fw-bold fs-12 mt-1 py-1 px-2 text-truncate d-inline-block" style="max-width: 100%;" id="modalAmendCurrentValueBadge">—</span>
                        </div>
                        <div class="col-2 text-center text-muted">
                            <i class="feather-arrow-right fs-16"></i>
                        </div>
                        <div class="col-5">
                            <label for="modalAmendNewValueSelect" class="form-label fs-11 text-uppercase fw-semibold text-primary mb-1">
                                {{ __('projects.new_value') }} <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm fw-semibold" id="modalAmendNewValueSelect">
                                {{-- Populated dynamically via JS --}}
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Important Notice Alert --}}
                <div class="alert alert-warning py-2.5 px-3 fs-12 mb-3 rounded-3 border-0 shadow-sm"
                     style="background-color: #fffbeb; border-left: 4px solid #f59e0b !important; color: #92400e;">
                    <div class="d-flex align-items-start gap-2">
                        <i class="feather-alert-circle fs-16 flex-shrink-0 mt-0.5" style="color: #d97706;"></i>
                        <div class="lh-sm" id="modalAmendNoticeText">
                            {{ __('projects.amend_notice_general') }}
                        </div>
                    </div>
                </div>

                {{-- Reason / Audit Note --}}
                <div class="mb-3">
                    <label for="modalAmendReasonInput" class="form-label fs-12 fw-bold text-dark mb-1">
                        {{ __('projects.amendment_reason') }} <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control fs-13" id="modalAmendReasonInput" rows="2"
                              placeholder="{{ __('projects.amendment_reason_placeholder') }}" maxlength="1000"></textarea>
                    <div class="text-danger fs-11 mt-1 d-none" id="modalAmendReasonError">
                        {{ __('projects.amendment_reason_required') }}
                    </div>
                </div>

                {{-- Confirmation Checkbox --}}
                <div class="form-check fs-12 text-muted">
                    <input class="form-check-input" type="checkbox" id="modalAmendConfirmCheck">
                    <label class="form-check-label fw-medium text-dark" for="modalAmendConfirmCheck">
                        {{ __('projects.amend_confirm_checkbox') }}
                    </label>
                </div>

                <div class="alert alert-danger py-2 px-3 fs-11 mt-3 mb-0 rounded-2 d-none" id="modalAmendServerError"></div>
            </div>

            {{-- Modal Footer --}}
            <div class="modal-footer py-2.5 px-4 bg-light border-top d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-light border px-3 fw-medium" data-bs-dismiss="modal">
                    {{ __('projects.cancel') }}
                </button>
                <button type="button" class="btn btn-sm btn-danger px-3 fw-semibold d-inline-flex align-items-center gap-1.5" id="btnConfirmAmendContract" disabled>
                    <i class="feather-check"></i>
                    <span>{{ __('projects.confirm_and_amend') }}</span>
                </button>
            </div>
        </div>
    </div>
</div>


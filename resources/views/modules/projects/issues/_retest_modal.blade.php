<x-ui.modal id="retestIssueModal" :title="__('projects.retest_issue')" size="md" :showFooter="false">
    <form id="retestIssueForm" method="POST" action="{{ route('projects.issues.retest', [$project, $issue]) }}">
        @csrf

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.retest_outcome') }} <span class="text-danger">*</span></label>
                <div class="d-flex flex-column gap-2 mt-1">
                    <div class="form-check p-2 border rounded bg-light">
                        <input class="form-check-input ms-1 me-2" type="radio" name="passed" id="retestPassed" value="1" checked>
                        <label class="form-check-label fw-semibold text-success fs-13" for="retestPassed">
                            <i class="feather-check-circle me-1"></i>{{ __('projects.retest_passed') }}
                        </label>
                    </div>
                    <div class="form-check p-2 border rounded bg-light">
                        <input class="form-check-input ms-1 me-2" type="radio" name="passed" id="retestFailed" value="0">
                        <label class="form-check-label fw-semibold text-danger fs-13" for="retestFailed">
                            <i class="feather-x-circle me-1"></i>{{ __('projects.retest_failed') }}
                        </label>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.retest_notes') }}</label>
                <x-ui.odoo-form-ui type="textarea" name="resolution_notes" rows="3" :placeholder="__('projects.retest_notes_placeholder')" />
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('projects.cancel') }}</button>
            <button type="submit" class="btn btn-primary">
                <i class="feather-send me-1"></i>{{ __('projects.retest_issue') }}
            </button>
        </div>
    </form>
</x-ui.modal>

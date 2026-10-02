<x-ui.modal id="modalCloseProject" size="lg" :title="__('projects.close_project')" :showFooter="false">
    <div class="mb-3 text-muted fs-13">
        {{ __('projects.close_project_description') }}
    </div>

    {{-- Gate Diagnostics Card --}}
    <div class="card border mb-4 bg-light">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
            <span class="fw-semibold text-dark fs-13">
                <i class="feather-check-square me-1 text-primary"></i> {{ __('projects.closure_checklist') }}
            </span>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 fs-11" onclick="fetchClosureGates()">
                <i class="feather-refresh-cw me-1"></i> {{ __('projects.refresh') }}
            </button>
        </div>
        <div class="card-body p-3" id="closureGatesContainer">
            <div class="text-center py-3 text-muted">
                <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                <div class="fs-12">{{ __('projects.checking_gates') }}</div>
            </div>
        </div>
    </div>

    <form action="{{ route('projects.close', $project) }}" method="POST" id="formCloseProject">
        @csrf

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <x-ui.odoo-form-ui
                    type="select"
                    name="closure_status"
                    id="closureStatusSelect"
                    :label="__('projects.closure_status')"
                    :required="true"
                    :searchable="false"
                >
                    <option value="{{ \App\Domains\Projects\Models\Project::CLOSURE_STATUS_COMPLETED }}" @selected(old('closure_status') === \App\Domains\Projects\Models\Project::CLOSURE_STATUS_COMPLETED)>
                        {{ __('projects.closure_status_completed') }}
                    </option>
                    <option value="{{ \App\Domains\Projects\Models\Project::CLOSURE_STATUS_HANDED_OVER }}" @selected(old('closure_status') === \App\Domains\Projects\Models\Project::CLOSURE_STATUS_HANDED_OVER)>
                        {{ __('projects.closure_status_handed_over') }}
                    </option>
                    <option value="{{ \App\Domains\Projects\Models\Project::CLOSURE_STATUS_TERMINATED }}" @selected(old('closure_status') === \App\Domains\Projects\Models\Project::CLOSURE_STATUS_TERMINATED)>
                        {{ __('projects.closure_status_terminated') }}
                    </option>
                </x-ui.odoo-form-ui>
            </div>

            <div class="col-md-6">
                <x-ui.odoo-form-ui
                    type="date"
                    name="closure_date"
                    id="closureDateField"
                    :label="__('projects.closure_date')"
                    :value="old('closure_date', now()->toDateString())"
                    required
                />
            </div>

            <div class="col-md-12">
                <x-ui.odoo-form-ui
                    type="text"
                    name="client_approval_ref"
                    id="clientApprovalRefField"
                    :label="__('projects.client_approval_ref')"
                    :value="old('client_approval_ref')"
                    placeholder="e.g. CR-2026-FINAL / UAT-SIGN-OFF-42"
                />
            </div>

            <div class="col-md-12">
                <x-ui.odoo-form-ui
                    type="textarea"
                    name="final_remarks"
                    id="finalRemarksField"
                    :label="__('projects.final_remarks')"
                    :value="old('final_remarks')"
                    rows="3"
                    placeholder="Document closure outcome, acceptance notes, or lessons learned..."
                />
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 border-top pt-3">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                {{ __('projects.cancel') }}
            </button>
            <button type="submit" class="btn btn-danger" id="btnSubmitCloseProject" disabled>
                <i class="feather-lock me-1"></i> {{ __('projects.confirm_close_project') }}
            </button>
        </div>
    </form>
</x-ui.modal>

@push('scripts')
<script>
    function fetchClosureGates() {
        var container = document.getElementById('closureGatesContainer');
        var submitBtn = document.getElementById('btnSubmitCloseProject');
        if (!container) return;

        container.innerHTML = `
            <div class="text-center py-3 text-muted">
                <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                <div class="fs-12">{{ __('projects.checking_gates') }}</div>
            </div>
        `;

        fetch('{{ route('projects.closure.check', $project) }}', {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success) {
                container.innerHTML = '<div class="alert alert-danger mb-0">' + (data.message || 'Failed to check closure gates.') + '</div>';
                return;
            }

            var gatesHtml = '<ul class="list-group list-group-flush fs-13">';
            var gateKeys = [
                { key: 'tasks', label: @js(__('projects.gate_tasks')) },
                { key: 'issues', label: @js(__('projects.gate_issues')) },
                { key: 'reviews', label: @js(__('projects.gate_reviews')) },
                { key: 'billing', label: @js(__('projects.gate_billing')) },
                { key: 'milestones', label: @js(__('projects.gate_milestones')) }
            ];

            gateKeys.forEach(function(g) {
                var gateData = data.gates && data.gates[g.key];
                var passed = gateData ? (typeof gateData === 'object' ? gateData.passed : gateData) : false;
                var badge = passed 
                    ? '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="feather-check me-1"></i>' + @js(__('projects.gate_passed')) + '</span>'
                    : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="feather-alert-triangle me-1"></i>' + @js(__('projects.gate_blocked_label')) + '</span>';
                gatesHtml += `
                    <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="${passed ? 'text-dark' : 'text-danger fw-semibold'}">
                            <i class="feather-${passed ? 'check-circle text-success' : 'x-circle text-danger'} me-2"></i>
                            ${g.label}
                        </span>
                        ${badge}
                    </li>
                `;
            });
            gatesHtml += '</ul>';

            if (data.can_close) {
                gatesHtml += `
                    <div class="alert alert-success mt-3 mb-0 fs-12 py-2 d-flex align-items-center">
                        <i class="feather-check-circle me-2 fs-5"></i>
                        <div>` + @js(__('projects.gates_passed')) + `</div>
                    </div>
                `;
                if (submitBtn) submitBtn.disabled = false;
            } else {
                var blockerList = '';
                if (data.blockers && data.blockers.length > 0) {
                    blockerList = '<ul class="mb-0 ps-3 mt-1">' + data.blockers.map(function(b) { return '<li>' + b + '</li>'; }).join('') + '</ul>';
                }
                gatesHtml += `
                    <div class="alert alert-warning mt-3 mb-0 fs-12 py-2">
                        <div class="fw-semibold"><i class="feather-alert-triangle me-1"></i>` + @js(__('projects.gates_blocked')) + `</div>
                        ${blockerList}
                    </div>
                `;
                if (submitBtn) submitBtn.disabled = true;
            }

            container.innerHTML = gatesHtml;
        })
        .catch(function(err) {
            console.error(err);
            container.innerHTML = '<div class="alert alert-danger mb-0">Failed to load closure diagnostics.</div>';
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        var modalEl = document.getElementById('modalCloseProject');
        if (modalEl) {
            modalEl.addEventListener('show.bs.modal', function() {
                fetchClosureGates();
            });
        }
    });
</script>
@endpush

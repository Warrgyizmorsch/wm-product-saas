<x-ui.modal id="createIssueModal" :title="__('projects.report_issue')" size="lg" :showFooter="false">
    <form id="createIssueForm" method="POST" action="{{ route('projects.issues.store', $project) }}">
        @csrf
        <input type="hidden" name="_issue_form" value="add">
        <input type="hidden" name="_modal" value="createIssueModal">

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.issue_title') }} <span class="text-danger">*</span></label>
                <x-ui.odoo-form-ui type="input" name="title" :placeholder="__('projects.issue_title')" :value="old('title')" required />
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.severity') }} <span class="text-danger">*</span></label>
                <x-ui.odoo-form-ui type="select" name="severity" required>
                    @foreach (\App\Domains\Projects\Models\Issue::SEVERITIES as $sev)
                        <option value="{{ $sev }}" @selected(old('severity', \App\Domains\Projects\Models\Issue::SEVERITY_MAJOR) === $sev)>
                            {{ __('projects.severities.' . $sev) }}
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.priority') }} <span class="text-danger">*</span></label>
                <x-ui.odoo-form-ui type="select" name="priority" required>
                    @foreach (\App\Domains\Projects\Models\Issue::PRIORITIES as $prio)
                        <option value="{{ $prio }}" @selected(old('priority', \App\Domains\Projects\Models\Issue::PRIORITY_MEDIUM) === $prio)>
                            {{ __('projects.priorities.' . $prio) }}
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.assignee') }}</label>
                <x-ui.odoo-form-ui type="select" name="assignee_id" select2Selector="default">
                    <option value="">{{ __('projects.none_option') }}</option>
                    @foreach ($activeMemberOptions as $memberUser)
                        <option value="{{ $memberUser->id }}" @selected(old('assignee_id') == $memberUser->id)>
                            {{ $memberUser->name }}
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.linked_task') }}</label>
                <x-ui.odoo-form-ui type="select" name="task_id" select2Selector="default">
                    <option value="">{{ __('projects.no_linked_task') }}</option>
                    @foreach ($allTasks as $t)
                        <option value="{{ $t->id }}" @selected(old('task_id') == $t->id)>
                            {{ $t->task_number }} - {{ $t->title }}
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>

            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.steps_to_reproduce') }}</label>
                <x-ui.odoo-form-ui type="textarea" name="steps_to_reproduce" rows="3" :placeholder="__('projects.steps_to_reproduce')">{{ old('steps_to_reproduce') }}</x-ui.odoo-form-ui>
            </div>

            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.description') }}</label>
                <x-ui.odoo-form-ui type="textarea" name="description" rows="3" :placeholder="__('projects.description')">{{ old('description') }}</x-ui.odoo-form-ui>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('projects.cancel') }}</button>
            <button type="submit" class="btn btn-primary">
                <i class="feather-check me-1"></i>{{ __('projects.report_issue') }}
            </button>
        </div>
    </form>
</x-ui.modal>

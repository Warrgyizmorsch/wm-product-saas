<input type="hidden" name="_task_form" id="task_form_mode" value="{{ old('_task_form') }}">
<input type="hidden" name="_task_id" id="task_form_id" value="{{ old('_task_id') }}">

<x-ui.odoo-form-ui type="select" id="task_task_list_id" label="{{ __('projects.task_list') }}" name="task_list_id" :required="true"
    :errorText="$errors->first('task_list_id')">
    <option value="">{{ __('projects.select_option') }}</option>
    @foreach ($taskLists as $taskListOption)
        <option value="{{ $taskListOption->id }}" @selected((int) old('task_list_id') === $taskListOption->id)>
            {{ $taskListOption->name }}
        </option>
    @endforeach
</x-ui.odoo-form-ui>
<x-ui.odoo-form-ui type="input" id="task_title" label="{{ __('projects.task_title') }}" name="title" :required="true"
    maxlength="255"
    :value="old('title')"
    :errorText="$errors->first('title')" />
<x-ui.odoo-form-ui type="textarea" id="task_description" label="{{ __('projects.description') }}" name="description"
    :value="old('description')"
    :errorText="$errors->first('description')" />
<x-ui.odoo-form-ui type="select" id="task_assignee_id" label="{{ __('projects.task_assignee') }}" name="assignee_id"
    select2Selector="user" :errorText="$errors->first('assignee_id')">
    <option value="">{{ __('projects.select_user') }}</option>
    @foreach ($activeMembers as $memberOption)
        <option value="{{ $memberOption->user_id }}" @selected((int) old('assignee_id') === $memberOption->user_id)
                data-avatar="{{ $memberOption->user?->avatar_url }}">
            {{ $memberOption->user?->name }}
        </option>
    @endforeach
</x-ui.odoo-form-ui>
<x-ui.odoo-form-ui type="select" id="task_reviewer_id" label="{{ __('projects.task_reviewer') }}" name="reviewer_id"
    select2Selector="user" :errorText="$errors->first('reviewer_id')">
    <option value="">{{ __('projects.select_user') }}</option>
    @foreach ($activeMembers as $memberOption)
        <option value="{{ $memberOption->user_id }}" @selected((int) old('reviewer_id') === $memberOption->user_id)
                data-avatar="{{ $memberOption->user?->avatar_url }}">
            {{ $memberOption->user?->name }}
        </option>
    @endforeach
</x-ui.odoo-form-ui>
<x-ui.odoo-form-ui type="select" id="task_priority" label="{{ __('projects.priority') }}" name="priority"
    :errorText="$errors->first('priority')">
    @foreach (\App\Domains\Projects\Models\Task::PRIORITIES as $priorityOption)
        <option value="{{ $priorityOption }}" @selected(old('priority', 'Medium') === $priorityOption)>
            {{ __('projects.priorities.' . $priorityOption) }}
        </option>
    @endforeach
</x-ui.odoo-form-ui>
<div class="row g-2">
    <div class="col-6">
        <x-ui.odoo-form-ui type="input" inputType="date" id="task_start_date" label="{{ __('projects.start_date') }}" name="start_date"
            :value="old('start_date')"
            :errorText="$errors->first('start_date')" />
    </div>
    <div class="col-6">
        <x-ui.odoo-form-ui type="input" inputType="date" id="task_due_date" label="{{ __('projects.due_date') }}" name="due_date"
            :value="old('due_date')"
            :errorText="$errors->first('due_date')" />
    </div>
</div>
<x-ui.odoo-form-ui type="input" inputType="number" id="task_estimated_hours" label="{{ __('projects.estimated_hours') }}" name="estimated_hours"
    step="0.01" min="0" inputmode="decimal"
    :value="old('estimated_hours')"
    :errorText="$errors->first('estimated_hours')" />

@if (isset($project) && $project->billing_method === \App\Domains\Projects\Models\Project::BILLING_METHOD_TASK_BASED)
    @if ($project->budget_type === \App\Domains\Projects\Models\Project::BUDGET_TYPE_FIXED)
        <div class="card bg-light border-0 mb-3 p-3 mt-3">
            <label class="form-label fw-bold fs-12 text-uppercase text-muted mb-2">
                <i class="feather-dollar-sign me-1 text-primary"></i>{{ __('projects.task_billing') }}
            </label>
            <div class="row g-2">
                <div class="col-12">
                    <x-ui.odoo-form-ui type="input" inputType="number" id="task_billing_amount"
                        label="{{ __('projects.fixed_task_price') }} ({{ active_currency_symbol() }})" name="billing_amount"
                        step="0.01" min="0" inputmode="decimal"
                        :value="old('billing_amount', '0.00')"
                        :errorText="$errors->first('billing_amount')" />
                </div>
                <div class="col-12">
                    <small class="text-muted">
                        {{ __('projects.billable_on_completion') }}
                    </small>
                </div>
            </div>
        </div>
    @else
        <div class="card bg-light border-0 mb-3 p-3 mt-3">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-info-subtle text-info"><i class="feather-clock me-1"></i>{{ __('projects.time_and_material') }}</span>
                <span class="fs-12 text-muted">{{ __('projects.task_tm_billing_hint', ['default' => 'Billed based on approved timesheet hours at team member rates.']) }}</span>
            </div>
        </div>
    @endif
@endif

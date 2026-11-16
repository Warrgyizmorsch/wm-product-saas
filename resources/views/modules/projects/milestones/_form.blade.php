<input type="hidden" name="_milestone_form" id="milestone_form_mode" value="{{ old('_milestone_form') }}">
<input type="hidden" name="_milestone_id" id="milestone_form_id" value="{{ old('_milestone_id') }}">

<x-ui.odoo-form-ui type="input" id="milestone_name" label="{{ __('projects.milestone_name') }}" name="name" maxlength="255" :required="true"
    :value="old('name')"
    :errorText="$errors->first('name')" />
<x-ui.odoo-form-ui type="textarea" id="milestone_description" label="{{ __('projects.description') }}" name="description"
    :value="old('description')"
    :errorText="$errors->first('description')" />
@if ($activeMemberOptions->isEmpty())
    <div class="mb-3">
        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.milestone_owner') }}</label>
        @include('modules.projects._no_collaborators_notice')
    </div>
@else
    <x-ui.odoo-form-ui type="select" id="milestone_owner_id" label="{{ __('projects.milestone_owner') }}" name="owner_id"
        select2Selector="user" :errorText="$errors->first('owner_id')">
        <option value="">{{ __('projects.select_user') }}</option>
        @foreach ($activeMemberOptions as $memberOption)
            <option value="{{ $memberOption->id }}" @selected((int) old('owner_id') === $memberOption->id)
                    data-avatar="{{ $memberOption->avatar_url }}">
                {{ $memberOption->name }}
            </option>
        @endforeach
    </x-ui.odoo-form-ui>
@endif
<x-ui.odoo-form-ui type="input" id="milestone_start_date" inputType="date" label="{{ __('projects.start_date') }}" name="start_date"
    :value="old('start_date')"
    :errorText="$errors->first('start_date')" />
<x-ui.odoo-form-ui type="input" id="milestone_due_date" inputType="date" label="{{ __('projects.due_date') }}" name="due_date"
    :value="old('due_date')"
    :errorText="$errors->first('due_date')" />
<x-ui.odoo-form-ui type="select" id="milestone_status" label="{{ __('projects.status') }}" name="status"
    :errorText="$errors->first('status')">
    @foreach(\App\Domains\Projects\Models\Milestone::STATUSES as $statusOption)
        <option value="{{ $statusOption }}" @selected(old('status', 'Draft') === $statusOption)>
            {{ __('projects.statuses.' . $statusOption) }}
        </option>
    @endforeach
</x-ui.odoo-form-ui>
<x-ui.odoo-form-ui type="input" id="milestone_completion_percentage" inputType="number" label="{{ __('projects.completion_percentage') }}" name="completion_percentage"
    min="0" max="100" step="1" inputmode="numeric"
    :value="old('completion_percentage', 0)"
    :errorText="$errors->first('completion_percentage')" />

@php
    $formProject = $project ?? null;
    $projectBudgetAmount = $formProject ? (float) ($formProject->budget_amount ?? 0) : 0;
    $isTm = $formProject && $formProject->budget_type === 'Time & Material';
    $isProjectBased = $formProject && !$isTm && $formProject->billing_method === 'Project Based' && $projectBudgetAmount > 0;
    $isMilestoneBased = $formProject && !$isTm && $formProject->billing_method === 'Milestone Based';
    $isTaskOrUserBased = $formProject && in_array($formProject->billing_method, ['Task Based', 'User Based'], true);
@endphp

@if ($isTm)
    {{-- In Time & Material projects, milestones represent timeline phases/sprints; billing is driven by approved timesheets --}}
    <input type="hidden" id="milestone_billing_amount" name="billing_amount" value="0">
    <div class="mb-3">
        <div class="alert alert-info py-2 px-3 fs-12 mb-0 d-flex align-items-center gap-2">
            <i class="feather-info"></i>
            <span>{{ __('projects.milestone_tm_phase_notice') }}</span>
        </div>
    </div>
@elseif ($isTaskOrUserBased)
    {{-- For Task Based and User Based billing, milestones are pure schedule/timeline markers, not billed directly --}}
    <input type="hidden" id="milestone_billing_amount" name="billing_amount" value="0">
@elseif ($isProjectBased)
    <div class="mb-3">
        <div class="odoo-form-group d-flex align-items-center mb-1">
            <label for="milestone_billing_percentage" class="odoo-form-label">{{ __('projects.installment_percentage') }}</label>
            <div class="input-group flex-grow-1" style="max-width: 220px;">
                <input type="number" id="milestone_billing_percentage" name="billing_percentage" class="form-control form-control-sm"
                       min="0" max="100" step="0.1" placeholder="20"
                       value="{{ old('billing_percentage') }}"
                       data-budget="{{ $projectBudgetAmount }}"
                       inputmode="decimal">
                <span class="input-group-text py-0 px-2 fw-bold fs-12">%</span>
            </div>
            <input type="hidden" id="milestone_billing_amount" name="billing_amount" value="{{ old('billing_amount', 0) }}">
        </div>
        <div class="d-flex align-items-center gap-2 mt-1" style="margin-left: 130px;">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fs-12" id="milestone_calc_display">
                <i class="feather-info me-1"></i>{{ __('projects.calculated_installment_amount', ['amount' => format_currency(0)]) }}
            </span>
            <span class="text-muted fs-11">({{ __('projects.hours_of_budget', ['budget' => format_currency($projectBudgetAmount)]) }})</span>
        </div>
        @if ($errors->has('billing_percentage') || $errors->has('billing_amount'))
            <div class="invalid-feedback d-block fs-11" style="margin-left: 130px;">
                {{ $errors->first('billing_percentage') ?: $errors->first('billing_amount') }}
            </div>
        @endif
    </div>
@else
    @php
        $label = $isMilestoneBased
            ? __('projects.deliverable_price') . ' (' . active_currency_symbol() . ')'
            : __('projects.billing_amount') . ' (' . active_currency_symbol() . ')';
        $helper = $isMilestoneBased
            ? ($projectBudgetAmount > 0
                ? __('projects.deliverable_help_with_budget', ['budget' => format_currency($projectBudgetAmount)])
                : __('projects.deliverable_help'))
            : __('projects.installment_help_no_budget');
    @endphp
    <x-ui.odoo-form-ui type="input" id="milestone_billing_amount" inputType="number"
        :label="$label"
        name="billing_amount" min="0" step="0.01"
        :value="old('billing_amount', 0)"
        :errorText="$errors->first('billing_amount')"
        :helperText="$helper" />
@endif

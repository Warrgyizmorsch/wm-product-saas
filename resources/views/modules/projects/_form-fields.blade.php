{{--
    Shared Project edit fields; expects $project, $customers, $users, $statusOptions.

    Rendered once per project's edit modal (one per row on the index listing, plus one
    on the show page), all on the same request/page. old() and $errors are global to the
    request, not scoped per-form, so without the guard below a validation failure on one
    project's edit would leak its stale input/errors into every other project's modal on
    the page. $useOld gates both to only the modal that actually submitted.
--}}
@php
    $modalKey = 'editProjectModal-' . $project->id;
    $useOld = old('_modal') === $modalKey;
    $old = fn (string $key, $default = null) => $useOld ? old($key, $default) : $default;
    $fieldError = fn (string $key) => $useOld ? $errors->first($key) : null;

    $startDateValue = $old('start_date', $project->start_date?->format('Y-m-d'));
    $endDateValue = $old('end_date', $project->end_date?->format('Y-m-d'));
@endphp
<div class="row g-3">
    <div class="col-md-12">
        <x-ui.odoo-form-ui type="input" label="{{ __('projects.project_name') }}" name="name"
            :value="$old('name', $project->name)" :required="true"
            :errorText="$fieldError('name')" />
    </div>

    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('projects.client') }}" name="customer_id"
            :errorText="$fieldError('customer_id')">
            <option value="">{{ __('projects.none_option') }}</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected($old('customer_id', $project->customer_id) == $customer->id)>
                    {{ $customer->name }}
                </option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('projects.project_owner') }}" name="owner_id" :required="true"
            :errorText="$fieldError('owner_id')">
            <option value="">{{ __('projects.select_option') }}</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected($old('owner_id', $project->owner_id) == $user->id)>
                    {{ $user->name }}
                </option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>

    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('projects.project_manager') }}" name="manager_id"
            :errorText="$fieldError('manager_id')">
            <option value="">{{ __('projects.none_option') }}</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected($old('manager_id', $project->manager_id) == $user->id)>
                    {{ $user->name }}
                </option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('projects.start_date') }}" name="start_date" class="js-project-start-date" :required="true"
            :value="$startDateValue"
            :errorText="$fieldError('start_date')" />
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('projects.end_date') }}" name="end_date" class="js-project-end-date"
            :value="$endDateValue"
            :errorText="$fieldError('end_date')"
            min="{{ $startDateValue }}" />
    </div>

    @once
        @push('scripts')
            <script>
                document.addEventListener('change', function (e) {
                    if (!e.target || !e.target.classList.contains('js-project-start-date')) return;

                    var form = e.target.closest('form');
                    var endDateInput = form ? form.querySelector('.js-project-end-date') : null;
                    if (!endDateInput) return;

                    endDateInput.min = e.target.value;
                    if (endDateInput.value && e.target.value && endDateInput.value < e.target.value) {
                        endDateInput.value = e.target.value;
                    }
                });
            </script>
        @endpush
    @endonce

    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('projects.budget_type') }}" name="budget_type"
            :errorText="$fieldError('budget_type')">
            <option value="">{{ __('projects.none_option') }}</option>
            @foreach (\App\Domains\Projects\Models\Project::BUDGET_TYPES as $type)
                <option value="{{ $type }}" @selected($old('budget_type', $project->budget_type) === $type)>{{ __('projects.budget_types.' . $type) }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('projects.budget_amount') }}" name="budget_amount"
            :value="$old('budget_amount', $project->budget_amount)"
            :errorText="$fieldError('budget_amount')" min="0" step="0.01" />
    </div>

    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('projects.budget_hours') }}" name="budget_hours"
            :value="$old('budget_hours', $project->budget_hours)"
            :errorText="$fieldError('budget_hours')" min="0" step="0.5" />
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('projects.billing_method') }}" name="billing_method"
            :errorText="$fieldError('billing_method')">
            <option value="">{{ __('projects.none_option') }}</option>
            @foreach (\App\Domains\Projects\Models\Project::BILLING_METHODS as $method)
                <option value="{{ $method }}" @selected($old('billing_method', $project->billing_method) === $method)>{{ __('projects.billing_methods.' . $method) }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>

    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('projects.priority') }}" name="priority" :required="true"
            :errorText="$fieldError('priority')">
            @foreach (\App\Domains\Projects\Models\Project::PRIORITIES as $priority)
                <option value="{{ $priority }}" @selected($old('priority', $project->priority ?? \App\Domains\Projects\Models\Project::PRIORITY_MEDIUM) === $priority)>{{ __('projects.priorities.' . $priority) }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('projects.status') }}" name="status" :required="true"
            :errorText="$fieldError('status')">
            @foreach ($statusOptions as $status)
                <option value="{{ $status }}" @selected($old('status', $project->status ?? \App\Domains\Projects\Models\Project::STATUS_DRAFT) === $status)>{{ __('projects.statuses.' . $status) }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>

    <div class="col-md-12">
        <x-ui.odoo-form-ui type="textarea" label="{{ __('projects.description') }}" name="description" rows="4"
            :errorText="$fieldError('description')">{{ $old('description', $project->description) }}</x-ui.odoo-form-ui>
    </div>
</div>

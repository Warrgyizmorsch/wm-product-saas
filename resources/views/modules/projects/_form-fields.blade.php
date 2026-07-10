{{-- Shared Project form fields; expects optional $project, plus $customers, $users, $statusOptions --}}
<div class="row g-3">
    <div class="col-md-12">
        <x-ui.odoo-form-ui type="input" label="{{ __('projects.project_name') }}" name="name"
            :value="old('name', $project->name ?? '')" :required="true"
            :errorText="$errors->first('name')" />
    </div>

    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('projects.client') }}" name="customer_id"
            :errorText="$errors->first('customer_id')">
            <option value="">{{ __('projects.none_option') }}</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected(old('customer_id', $project->customer_id ?? '') == $customer->id)>
                    {{ $customer->name }}
                </option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('projects.project_owner') }}" name="owner_id" :required="true"
            :errorText="$errors->first('owner_id')">
            <option value="">{{ __('projects.select_option') }}</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected(old('owner_id', $project->owner_id ?? '') == $user->id)>
                    {{ $user->name }}
                </option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>

    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('projects.project_manager') }}" name="manager_id"
            :errorText="$errors->first('manager_id')">
            <option value="">{{ __('projects.none_option') }}</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected(old('manager_id', $project->manager_id ?? '') == $user->id)>
                    {{ $user->name }}
                </option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-3">
        <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('projects.start_date') }}" name="start_date" :required="true"
            :value="old('start_date', optional($project)->start_date?->format('Y-m-d'))"
            :errorText="$errors->first('start_date')" />
    </div>
    <div class="col-md-3">
        <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('projects.end_date') }}" name="end_date"
            :value="old('end_date', optional($project)->end_date?->format('Y-m-d'))"
            :errorText="$errors->first('end_date')" />
    </div>

    <div class="col-md-4">
        <x-ui.odoo-form-ui type="select" label="{{ __('projects.budget_type') }}" name="budget_type"
            :errorText="$errors->first('budget_type')">
            <option value="">{{ __('projects.none_option') }}</option>
            @foreach (\App\Domains\Projects\Models\Project::BUDGET_TYPES as $type)
                <option value="{{ $type }}" @selected(old('budget_type', $project->budget_type ?? '') === $type)>{{ __('projects.budget_types.' . $type) }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('projects.budget_amount') }}" name="budget_amount"
            :value="old('budget_amount', $project->budget_amount ?? '')"
            :errorText="$errors->first('budget_amount')" />
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('projects.budget_hours') }}" name="budget_hours"
            :value="old('budget_hours', $project->budget_hours ?? '')"
            :errorText="$errors->first('budget_hours')" />
    </div>

    <div class="col-md-4">
        <x-ui.odoo-form-ui type="select" label="{{ __('projects.billing_method') }}" name="billing_method"
            :errorText="$errors->first('billing_method')">
            <option value="">{{ __('projects.none_option') }}</option>
            @foreach (\App\Domains\Projects\Models\Project::BILLING_METHODS as $method)
                <option value="{{ $method }}" @selected(old('billing_method', $project->billing_method ?? '') === $method)>{{ __('projects.billing_methods.' . $method) }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="select" label="{{ __('projects.priority') }}" name="priority" :required="true"
            :errorText="$errors->first('priority')">
            @foreach (\App\Domains\Projects\Models\Project::PRIORITIES as $priority)
                <option value="{{ $priority }}" @selected(old('priority', $project->priority ?? 'Medium') === $priority)>{{ __('projects.priorities.' . $priority) }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="select" label="{{ __('projects.status') }}" name="status" :required="true"
            :errorText="$errors->first('status')">
            @foreach ($statusOptions as $status)
                <option value="{{ $status }}" @selected(old('status', $project->status ?? 'Draft') === $status)>{{ __('projects.statuses.' . $status) }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>

    <div class="col-md-12">
        <x-ui.odoo-form-ui type="textarea" label="{{ __('projects.description') }}" name="description" rows="4"
            :errorText="$errors->first('description')">{{ old('description', $project->description ?? '') }}</x-ui.odoo-form-ui>
    </div>
</div>

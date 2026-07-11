<input type="hidden" name="_milestone_form" id="milestone_form_mode" value="{{ old('_milestone_form') }}">
<input type="hidden" name="_milestone_id" id="milestone_form_id" value="{{ old('_milestone_id') }}">

<x-ui.odoo-form-ui type="input" id="milestone_name" label="{{ __('projects.milestone_name') }}" name="name" :required="true"
    :value="old('name')"
    :errorText="$errors->first('name')" />
<x-ui.odoo-form-ui type="textarea" id="milestone_description" label="{{ __('projects.description') }}" name="description"
    :value="old('description')"
    :errorText="$errors->first('description')" />
<x-ui.odoo-form-ui type="select" id="milestone_owner_id" label="{{ __('projects.milestone_owner') }}" name="owner_id"
    select2Selector="user" :errorText="$errors->first('owner_id')">
    <option value="">{{ __('projects.select_user') }}</option>
    @foreach ($tenantUsers as $tenantUser)
        <option value="{{ $tenantUser->id }}" @selected((int) old('owner_id') === $tenantUser->id)>
            {{ $tenantUser->name }}
        </option>
    @endforeach
</x-ui.odoo-form-ui>
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
    min="0" max="100"
    :value="old('completion_percentage', 0)"
    :errorText="$errors->first('completion_percentage')" />

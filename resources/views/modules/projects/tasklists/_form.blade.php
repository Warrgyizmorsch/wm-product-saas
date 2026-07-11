<input type="hidden" name="_tasklist_form" id="tasklist_form_mode" value="{{ old('_tasklist_form') }}">
<input type="hidden" name="_tasklist_id" id="tasklist_form_id" value="{{ old('_tasklist_id') }}">

<x-ui.odoo-form-ui type="input" id="tasklist_name" label="{{ __('projects.tasklist_name') }}" name="name" :required="true"
    :value="old('name')"
    :errorText="$errors->first('name')" />
<x-ui.odoo-form-ui type="textarea" id="tasklist_description" label="{{ __('projects.description') }}" name="description"
    :value="old('description')"
    :errorText="$errors->first('description')" />
<x-ui.odoo-form-ui type="select" id="tasklist_milestone_id" label="{{ __('projects.milestone') }}" name="milestone_id"
    :errorText="$errors->first('milestone_id')">
    <option value="">{{ __('projects.none_option') }}</option>
    @foreach ($milestones as $milestoneOption)
        <option value="{{ $milestoneOption->id }}" @selected((int) old('milestone_id') === $milestoneOption->id)>
            {{ $milestoneOption->name }}
        </option>
    @endforeach
</x-ui.odoo-form-ui>
<x-ui.odoo-form-ui type="select" id="tasklist_owner_id" label="{{ __('projects.tasklist_owner') }}" name="owner_id"
    select2Selector="user" :errorText="$errors->first('owner_id')">
    <option value="">{{ __('projects.select_user') }}</option>
    @foreach ($tenantUsers as $tenantUser)
        <option value="{{ $tenantUser->id }}" @selected((int) old('owner_id') === $tenantUser->id)>
            {{ $tenantUser->name }}
        </option>
    @endforeach
</x-ui.odoo-form-ui>

<input type="hidden" name="_member_form" id="member_form_mode" value="{{ old('_member_form') }}">
<input type="hidden" name="_member_id" id="member_form_id" value="{{ old('_member_id') }}">

<x-ui.odoo-form-ui type="select" id="member_user_id" label="{{ __('projects.member') }}" name="user_id" :required="true"
    select2Selector="user" :errorText="$errors->first('user_id')">
    <option value="">{{ __('projects.select_user') }}</option>
    @foreach ($tenantUsers as $tenantUser)
        <option value="{{ $tenantUser->id }}" @selected((int) old('user_id') === $tenantUser->id)>
            {{ $tenantUser->name }}
        </option>
    @endforeach
</x-ui.odoo-form-ui>
<x-ui.odoo-form-ui type="input" id="member_project_role" label="{{ __('projects.project_role') }}" name="project_role"
    :value="old('project_role')"
    :errorText="$errors->first('project_role')" />
<x-ui.odoo-form-ui type="input" id="member_rate_per_hour" inputType="number" label="{{ __('projects.rate_per_hour') }}" name="rate_per_hour"
    :value="old('rate_per_hour')"
    :errorText="$errors->first('rate_per_hour')" />
<x-ui.odoo-form-ui type="input" id="member_cost_per_hour" inputType="number" label="{{ __('projects.cost_per_hour') }}" name="cost_per_hour"
    :value="old('cost_per_hour')"
    :errorText="$errors->first('cost_per_hour')" />
<x-ui.odoo-form-ui type="input" id="member_budget_hours" inputType="number" label="{{ __('projects.budget_hours') }}" name="budget_hours"
    :value="old('budget_hours')"
    :errorText="$errors->first('budget_hours')" />

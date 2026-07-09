<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
        <h5 class="card-title mb-0 fw-bold text-dark">
            <i class="feather-users me-2 text-primary"></i>{{ __('projects.members') }}
        </h5>
        @if ($canManageMembers)
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddMember">
                <i class="feather-plus me-1"></i>{{ __('projects.add_member') }}
            </button>
        @endif
    </div>
    <div class="card-body p-0">
        <x-ui.table>
            <thead>
                <tr>
                    <th scope="col">{{ __('projects.member') }}</th>
                    <th scope="col">{{ __('projects.project_role') }}</th>
                    <th scope="col">{{ __('projects.rate_per_hour') }}</th>
                    <th scope="col">{{ __('projects.cost_per_hour') }}</th>
                    <th scope="col">{{ __('projects.budget_hours') }}</th>
                    <th scope="col">{{ __('projects.status') }}</th>
                    @if ($canManageMembers)
                        <th scope="col" class="text-end">{{ __('projects.actions') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($members as $member)
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark">{{ $member->user?->name ?: '—' }}</div>
                            <div class="fs-11 text-muted">{{ $member->user?->email }}</div>
                        </td>
                        <td>{{ $member->project_role ?: '—' }}</td>
                        <td>{{ $member->rate_per_hour !== null ? number_format((float) $member->rate_per_hour, 2) : '—' }}</td>
                        <td>{{ $member->cost_per_hour !== null ? number_format((float) $member->cost_per_hour, 2) : '—' }}</td>
                        <td>{{ $member->budget_hours !== null ? number_format((float) $member->budget_hours, 2) : '—' }}</td>
                        <td>
                            @if ($member->is_active)
                                <span class="badge bg-soft-success text-success px-2 py-1 fs-11 fw-semibold">{{ __('projects.is_active') }}</span>
                            @else
                                <span class="badge bg-soft-secondary text-secondary px-2 py-1 fs-11 fw-semibold">{{ __('projects.inactive') }}</span>
                            @endif
                        </td>
                        @if ($canManageMembers)
                            <td class="text-end">
                                <x-ui.action-dropdown id="memberActions{{ $member->id }}">
                                    <li>
                                        <x-ui.dropdown-item href="javascript:void(0);" icon="feather feather-edit-2"
                                            data-bs-toggle="modal" data-bs-target="#modalEditMember{{ $member->id }}">
                                            {{ __('projects.edit') }}
                                        </x-ui.dropdown-item>
                                    </li>
                                    <li>
                                        <form action="{{ route('projects.members.toggle-active', [$project, $member->id]) }}" method="POST"
                                              onsubmit="return confirm('{{ __('projects.confirm_toggle_active') }}');">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="dropdown-item">
                                                <i class="feather {{ $member->is_active ? 'feather-slash' : 'feather-check' }}"></i>
                                                {{ $member->is_active ? __('projects.deactivate') : __('projects.activate') }}
                                            </button>
                                        </form>
                                    </li>
                                    <li>
                                        <form action="{{ route('projects.members.destroy', [$project, $member->id]) }}" method="POST"
                                              onsubmit="return confirm('{{ __('projects.confirm_remove_member') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="feather feather-trash-2"></i>{{ __('projects.remove') }}
                                            </button>
                                        </form>
                                    </li>
                                </x-ui.action-dropdown>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManageMembers ? 7 : 6 }}" class="text-center text-muted py-4">
                            {{ __('projects.no_members') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </div>
</div>

@if ($canManageMembers)
    @php
        $isFailedAdd = old('_member_form') === 'add';
    @endphp

    <x-ui.modal id="modalAddMember" title="{{ __('projects.add_member') }}" :centered="true"
        :formAction="route('projects.members.store', $project)" formMethod="POST"
        submitText="{{ __('projects.add_member') }}" closeText="{{ __('projects.cancel') }}">
        <input type="hidden" name="_member_form" value="add">

        <x-ui.odoo-form-ui type="select" label="{{ __('projects.member') }}" name="user_id" :required="true"
            select2Selector="user" :errorText="$isFailedAdd ? $errors->first('user_id') : null">
            <option value="">{{ __('projects.select_user') }}</option>
            @foreach ($tenantUsers as $tenantUser)
                <option value="{{ $tenantUser->id }}" @selected($isFailedAdd && (int) old('user_id') === $tenantUser->id)>
                    {{ $tenantUser->name }}
                </option>
            @endforeach
        </x-ui.odoo-form-ui>
        <x-ui.odoo-form-ui type="input" label="{{ __('projects.project_role') }}" name="project_role"
            :value="$isFailedAdd ? old('project_role') : null"
            :errorText="$isFailedAdd ? $errors->first('project_role') : null" />
        <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('projects.rate_per_hour') }}" name="rate_per_hour"
            :value="$isFailedAdd ? old('rate_per_hour') : null"
            :errorText="$isFailedAdd ? $errors->first('rate_per_hour') : null" />
        <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('projects.cost_per_hour') }}" name="cost_per_hour"
            :value="$isFailedAdd ? old('cost_per_hour') : null"
            :errorText="$isFailedAdd ? $errors->first('cost_per_hour') : null" />
        <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('projects.budget_hours') }}" name="budget_hours"
            :value="$isFailedAdd ? old('budget_hours') : null"
            :errorText="$isFailedAdd ? $errors->first('budget_hours') : null" />
    </x-ui.modal>

    @foreach ($members as $member)
        @php
            $isFailedEdit = old('_member_form') === 'edit' && (int) old('_member_id') === $member->id;
        @endphp

        <x-ui.modal id="modalEditMember{{ $member->id }}" title="{{ __('projects.edit_member') }}" :centered="true"
            :formAction="route('projects.members.update', [$project, $member->id])" formMethod="PUT"
            submitText="{{ __('projects.edit_member') }}" closeText="{{ __('projects.cancel') }}">
            <input type="hidden" name="_member_form" value="edit">
            <input type="hidden" name="_member_id" value="{{ $member->id }}">

            <x-ui.odoo-form-ui type="select" label="{{ __('projects.member') }}" name="user_id" :required="true"
                select2Selector="user" :errorText="$isFailedEdit ? $errors->first('user_id') : null">
                @foreach ($tenantUsers as $tenantUser)
                    <option value="{{ $tenantUser->id }}" @selected(($isFailedEdit ? (int) old('user_id') : $member->user_id) === $tenantUser->id)>
                        {{ $tenantUser->name }}
                    </option>
                @endforeach
            </x-ui.odoo-form-ui>
            <x-ui.odoo-form-ui type="input" label="{{ __('projects.project_role') }}" name="project_role"
                :value="$isFailedEdit ? old('project_role') : $member->project_role"
                :errorText="$isFailedEdit ? $errors->first('project_role') : null" />
            <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('projects.rate_per_hour') }}" name="rate_per_hour"
                :value="$isFailedEdit ? old('rate_per_hour') : $member->rate_per_hour"
                :errorText="$isFailedEdit ? $errors->first('rate_per_hour') : null" />
            <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('projects.cost_per_hour') }}" name="cost_per_hour"
                :value="$isFailedEdit ? old('cost_per_hour') : $member->cost_per_hour"
                :errorText="$isFailedEdit ? $errors->first('cost_per_hour') : null" />
            <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('projects.budget_hours') }}" name="budget_hours"
                :value="$isFailedEdit ? old('budget_hours') : $member->budget_hours"
                :errorText="$isFailedEdit ? $errors->first('budget_hours') : null" />
        </x-ui.modal>
    @endforeach

    @if ($errors->any() && in_array(old('_member_form'), ['add', 'edit'], true))
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var modalId = null;
                    @if (old('_member_form') === 'add')
                        modalId = 'modalAddMember';
                    @elseif (old('_member_form') === 'edit' && old('_member_id'))
                        modalId = 'modalEditMember{{ old('_member_id') }}';
                    @endif
                    if (modalId) {
                        var modalEl = document.getElementById(modalId);
                        if (modalEl) {
                            bootstrap.Modal.getOrCreateInstance(modalEl).show();
                        }
                    }
                });
            </script>
        @endpush
    @endif
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
        <h5 class="card-title mb-0 fw-bold text-dark">
            <i class="feather-users me-2 text-primary"></i>{{ __('projects.members') }}
        </h5>
        @if ($canManageMembers)
            <button type="button" class="btn btn-primary btn-sm" onclick="openMemberDrawer('add')">
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
                    <th scope="col">{{ __('projects.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($members as $member)
                    <tr @if ($canManageMembers) role="button" style="cursor: pointer;"
                            onclick="openMemberDrawer('edit', {
                                id: {{ $member->id }},
                                updateUrl: @js(route('projects.members.update', [$project, $member->id])),
                                deleteUrl: @js(route('projects.members.destroy', [$project, $member->id])),
                                toggleActiveUrl: @js(route('projects.members.toggle-active', [$project, $member->id])),
                                userId: {{ $member->user_id }},
                                projectRole: @js($member->project_role),
                                ratePerHour: {{ $member->rate_per_hour ?? 'null' }},
                                costPerHour: {{ $member->cost_per_hour ?? 'null' }},
                                budgetHours: {{ $member->budget_hours ?? 'null' }},
                                isActive: @js((bool) $member->is_active)
                            })"
                        @endif>
                        <td>
                            <div class="fw-semibold text-dark">{{ $member->user?->name ?: '—' }}</div>
                            <div class="fs-11 text-muted">{{ $member->user?->email }}</div>
                        </td>
                        <td>{{ $member->project_role ?: '—' }}</td>
                        <td>
                            @if ($member->is_active)
                                <span class="badge bg-soft-success text-success px-2 py-1 fs-11 fw-semibold">{{ __('projects.is_active') }}</span>
                            @else
                                <span class="badge bg-soft-secondary text-secondary px-2 py-1 fs-11 fw-semibold">{{ __('projects.inactive') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center text-muted py-4">
                            {{ __('projects.no_members') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </div>
</div>

@if ($canManageMembers)
    @include('modules.projects.members._drawer')

    @if ($errors->any() && in_array(old('_member_form'), ['add', 'edit'], true))
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    @if (old('_member_form') === 'edit')
                        openMemberDrawer('edit', {
                            id: {{ (int) old('_member_id') }},
                            updateUrl: @js(route('projects.members.update', [$project, (int) old('_member_id')])),
                            deleteUrl: @js(route('projects.members.destroy', [$project, (int) old('_member_id')])),
                            toggleActiveUrl: @js(route('projects.members.toggle-active', [$project, (int) old('_member_id')])),
                            userId: @js(old('user_id')),
                            projectRole: @js(old('project_role')),
                            ratePerHour: @js(old('rate_per_hour')),
                            costPerHour: @js(old('cost_per_hour')),
                            budgetHours: @js(old('budget_hours')),
                        });
                    @else
                        openMemberDrawer('add', {
                            userId: @js(old('user_id')),
                            projectRole: @js(old('project_role')),
                            ratePerHour: @js(old('rate_per_hour')),
                            costPerHour: @js(old('cost_per_hour')),
                            budgetHours: @js(old('budget_hours')),
                        });
                    @endif
                });
            </script>
        @endpush
    @endif
@endif

@extends('layouts.duralux')

@section('title', __('notifications.notification_master') . ' | SaaS ERP')
@section('page-title', __('notifications.notification_master'))
@section('breadcrumb', __('notifications.platform_automation'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('platform.notification-rules.create') }}" class="btn btn-primary shadow-sm">
            <i class="feather-plus me-1"></i> {{ __('notifications.new_rule') }}
        </a>
    </div>
@endsection

@section('content')
    <div class="erp-single-panel">

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert">
                <i class="feather-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Summary KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-2xs p-3 bg-soft-primary">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar avatar-md rounded-3 bg-primary text-white d-flex align-items-center justify-content-center">
                            <i class="feather-bell fs-18"></i>
                        </div>
                        <div>
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">{{ __('notifications.total_configured_rules') }}</span>
                            <h4 class="fw-bold text-dark mb-0">{{ $rules->total() }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-2xs p-3 bg-soft-success">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar avatar-md rounded-3 bg-success text-white d-flex align-items-center justify-content-center">
                            <i class="feather-check-circle fs-18"></i>
                        </div>
                        <div>
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">{{ __('notifications.active_rules') }}</span>
                            <h4 class="fw-bold text-dark mb-0">{{ $rules->where('is_active', true)->count() }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-2xs p-3 bg-soft-info">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar avatar-md rounded-3 bg-info text-white d-flex align-items-center justify-content-center">
                            <i class="feather-zap fs-18"></i>
                        </div>
                        <div>
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">{{ __('notifications.available_erp_events') }}</span>
                            <h4 class="fw-bold text-dark mb-0">
                                @php
                                    $totalEvents = 0;
                                    foreach($eventCatalog as $m) $totalEvents += count($m['events']);
                                @endphp
                                {{ $totalEvents }}
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter and Search Toolbar -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('platform.notification-rules.index') }}" class="btn btn-sm {{ !request('module') || request('module') === 'all' ? 'btn-primary' : 'btn-light border' }}">
                    {{ __('notifications.all_modules') }}
                </a>
                <a href="{{ route('platform.notification-rules.index', ['module' => 'crm']) }}" class="btn btn-sm {{ request('module') === 'crm' ? 'btn-primary' : 'btn-light border' }}">
                    CRM
                </a>
                <a href="{{ route('platform.notification-rules.index', ['module' => 'sales']) }}" class="btn btn-sm {{ request('module') === 'sales' ? 'btn-primary' : 'btn-light border' }}">
                    {{ __('ui.sales') }}
                </a>
                <a href="{{ route('platform.notification-rules.index', ['module' => 'inventory']) }}" class="btn btn-sm {{ request('module') === 'inventory' ? 'btn-primary' : 'btn-light border' }}">
                    {{ __('ui.inventory') }}
                </a>
                <a href="{{ route('platform.notification-rules.index', ['module' => 'purchase']) }}" class="btn btn-sm {{ request('module') === 'purchase' ? 'btn-primary' : 'btn-light border' }}">
                    {{ __('ui.purchase') }}
                </a>
                <a href="{{ route('platform.notification-rules.index', ['module' => 'production']) }}" class="btn btn-sm {{ request('module') === 'production' ? 'btn-primary' : 'btn-light border' }}">
                    {{ __('ui.production') }}
                </a>
                <a href="{{ route('platform.notification-rules.index', ['module' => 'hrms']) }}" class="btn btn-sm {{ request('module') === 'hrms' ? 'btn-primary' : 'btn-light border' }}">
                    {{ __('ui.hrms') }}
                </a>
                <a href="{{ route('platform.notification-rules.index', ['module' => 'document']) }}" class="btn btn-sm {{ request('module') === 'document' ? 'btn-primary' : 'btn-light border' }}">
                    Documents
                </a>
            </div>

            <form method="GET" action="{{ route('platform.notification-rules.index') }}" class="d-flex align-items-center gap-2">
                @if(request('module'))
                    <input type="hidden" name="module" value="{{ request('module') }}">
                @endif
                <div class="input-group input-group-sm" style="width: 260px;">
                    <input type="text" name="search" class="form-control" placeholder="{{ __('notifications.search_rules') }}" value="{{ request('search') }}">
                    <button class="btn btn-outline-secondary" type="submit"><i class="feather-search"></i></button>
                </div>
                @if(request('search'))
                    <a href="{{ route('platform.notification-rules.index', ['module' => request('module')]) }}" class="btn btn-sm btn-light border" title="Clear">
                        <i class="feather-x"></i>
                    </a>
                @endif
            </form>
        </div>

        <!-- Rules Table -->
        <div class="table-responsive bg-white rounded-3 shadow-sm p-3">
            <x-ui.odoo-form-ui type="table" id="notificationRulesTable">
                <thead>
                    <tr>
                        <th>{{ __('notifications.rule_name_event') }}</th>
                        <th>{{ __('notifications.module') }}</th>
                        <th>{{ __('notifications.recipients') }}</th>
                        <th>{{ __('notifications.bell_message_preview') }}</th>
                        <th class="text-center" style="width: 100px;">{{ __('notifications.status') }}</th>
                        <th class="text-end pe-4" style="width: 140px;">{{ __('notifications.action') }}</th>
                    </tr>
                </thead>
                <tbody class="fs-13 text-dark">
                    @forelse ($rules as $rule)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="avatar avatar-sm rounded-3 bg-light text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
                                        <i class="{{ $rule->icon_class ?: 'feather-bell' }} fs-15"></i>
                                    </div>
                                    <div>
                                        <a href="{{ route('platform.notification-rules.edit', $rule) }}" class="fw-bold text-dark hover-primary d-block">
                                            {{ $rule->name }}
                                        </a>
                                        <span class="badge bg-soft-secondary text-secondary font-monospace fs-10">
                                            {{ $rule->event_key }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @php
                                    $modColor = match($rule->module) {
                                        'crm' => 'purple',
                                        'sales' => 'success',
                                        'inventory' => 'info',
                                        'purchase' => 'warning',
                                        'production' => 'danger',
                                        'hrms' => 'primary',
                                        'document' => 'secondary',
                                        default => 'secondary'
                                    };
                                @endphp
                                <span class="badge bg-soft-{{ $modColor }} text-{{ $modColor }} fw-semibold px-2 py-1 fs-11 text-capitalize">
                                    {{ $rule->module }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @if(!empty($rule->recipient_roles))
                                        @foreach($rule->recipient_roles as $role)
                                            <span class="badge bg-soft-primary text-primary fs-11">
                                                <i class="feather-shield me-1"></i>{{ $role }}
                                            </span>
                                        @endforeach
                                    @endif
                                    @if($rule->notify_creator)
                                        <span class="badge bg-soft-info text-info fs-11" title="{{ __('notifications.notify_creator') }}">
                                            <i class="feather-user-check me-1"></i>{{ __('notifications.creator') }}
                                        </span>
                                    @endif
                                    @if($rule->notify_assigned_user)
                                        <span class="badge bg-soft-warning text-warning fs-11" title="{{ __('notifications.notify_assigned_user') }}">
                                            <i class="feather-user me-1"></i>{{ __('notifications.assigned_user') }}
                                        </span>
                                    @endif
                                    @if(!empty($rule->recipient_user_ids))
                                        <span class="badge bg-soft-secondary text-secondary fs-11">
                                            {{ __('notifications.users_count', ['count' => count($rule->recipient_user_ids)]) }}
                                        </span>
                                    @endif
                                    @if(empty($rule->recipient_roles) && empty($rule->recipient_user_ids) && !$rule->notify_creator && !$rule->notify_assigned_user)
                                        <span class="text-muted fs-11">{{ __('notifications.none') }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="text-truncate" style="max-width: 280px;" title="{{ $rule->title_template }}">
                                    <strong class="text-dark fs-12">{{ $rule->title_template }}</strong>
                                </div>
                                <div class="text-muted text-truncate fs-11" style="max-width: 280px;" title="{{ $rule->body_template }}">
                                    {{ $rule->body_template }}
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="form-check form-switch d-inline-block">
                                    <input class="form-check-input status-toggle" type="checkbox" role="switch" data-url="{{ route('platform.notification-rules.toggle-status', $rule) }}" {{ $rule->is_active ? 'checked' : '' }}>
                                </div>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex gap-1 align-items-center">
                                    <button type="button" class="btn btn-xs btn-soft-warning test-send-btn" data-url="{{ route('platform.notification-rules.test-send', $rule) }}" title="{{ __('notifications.test_send_tooltip') }}">
                                        <i class="feather-send"></i>
                                    </button>
                                    <a href="{{ route('platform.notification-rules.edit', $rule) }}" class="btn btn-xs btn-soft-primary" title="{{ __('notifications.edit_tooltip') }}">
                                        <i class="feather-edit"></i>
                                    </a>
                                    <form action="{{ route('platform.notification-rules.destroy', $rule) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('notifications.confirm_delete') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-soft-danger" title="{{ __('notifications.delete_tooltip') }}">
                                            <i class="feather-trash-2"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="feather-bell fs-1 d-block mb-2 text-light"></i>
                                <h6>{{ __('notifications.no_rules_found') }}</h6>
                                <p class="fs-12 mb-3">{{ __('notifications.no_rules_help') }}</p>
                                <a href="{{ route('platform.notification-rules.create') }}" class="btn btn-sm btn-primary">
                                    <i class="feather-plus me-1"></i> {{ __('notifications.create_first_rule') }}
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <div class="pt-3">
            {{ $rules->links() }}
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Toggle Active Status
        document.querySelectorAll('.status-toggle').forEach(function (toggle) {
            toggle.addEventListener('change', function () {
                const url = this.dataset.url;
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    }
                }).then(res => res.json()).then(data => {
                    if (data.success) {
                        toastr ? toastr.success(data.message) : alert(data.message);
                    }
                }).catch(() => {
                    this.checked = !this.checked;
                });
            });
        });

        // Test Send Notification to Bell
        document.querySelectorAll('.test-send-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const url = this.dataset.url;
                const originalHtml = this.innerHTML;
                this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                this.disabled = true;

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    }
                }).then(res => res.json()).then(data => {
                    this.innerHTML = originalHtml;
                    this.disabled = false;
                    if (data.success) {
                        toastr ? toastr.success(data.message) : alert(data.message);
                    }
                }).catch(() => {
                    this.innerHTML = originalHtml;
                    this.disabled = false;
                });
            });
        });
    });
</script>
@endpush

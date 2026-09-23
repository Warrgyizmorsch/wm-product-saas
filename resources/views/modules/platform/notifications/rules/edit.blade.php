@extends('layouts.duralux')

@section('title', __('notifications.edit_rule') . ' | SaaS ERP')
@section('page-title', __('notifications.edit_rule'))
@section('breadcrumb', __('notifications.platform_edit'))

@push('styles')
    <style>
        .odoo-sheet-card {
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 10px 25px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid #eef2f6;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            margin-bottom: 18px;
            border-bottom: 1px solid #f1f5f9;
        }

        .section-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #eef2ff;
            color: #4f46e5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
            letter-spacing: -0.2px;
        }

        /* Modern Variable Chips */
        .variable-chip {
            cursor: pointer;
            transition: all 0.18s ease;
            user-select: none;
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #e2e8f0;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-family: var(--bs-font-monospace, monospace);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .variable-chip:hover {
            background: #e0e7ff;
            color: #4338ca;
            border-color: #c7d2fe;
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(99, 102, 241, 0.15);
        }

        .variable-chip:active {
            transform: translateY(0);
        }

        /* Clean Select2 & Multiselect Styling */
        .select2-container {
            width: 100% !important;
        }

        .select2-container--bootstrap-5 .select2-selection {
            min-height: 40px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            padding: 4px 8px !important;
            background-color: #ffffff !important;
            transition: all 0.2s ease;
        }

        .select2-container--bootstrap-5.select2-container--focus .select2-selection,
        .select2-container--bootstrap-5 .select2-selection:focus {
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15) !important;
        }

        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
            background-color: #eff6ff !important;
            color: #1d4ed8 !important;
            border: 1px solid #bfdbfe !important;
            border-radius: 5px !important;
            padding: 3px 8px !important;
            font-size: 12px !important;
            font-weight: 500 !important;
            margin: 2px 4px 2px 0 !important;
            display: inline-flex !important;
            align-items: center !important;
        }

        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove {
            color: #1d4ed8 !important;
            margin-right: 5px !important;
            font-weight: bold !important;
            cursor: pointer !important;
            border: none !important;
            background: transparent !important;
        }

        /* Recipient Option Switch Tiles */
        .switch-tile {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px 16px;
            transition: all 0.2s ease;
            height: 100%;
        }

        .switch-tile:hover {
            border-color: #cbd5e1;
            background: #f1f5f9;
        }

        /* Live Bell Preview Card */
        .bell-preview-card {
            border-left: 4px solid #4f46e5;
            background: #f8fafc;
            border-radius: 8px;
            padding: 16px;
            border-top: 1px solid #eef2f6;
            border-right: 1px solid #eef2f6;
            border-bottom: 1px solid #eef2f6;
            transition: all 0.2s ease;
        }

        .bell-preview-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
    </style>
@endpush

@section('page-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('platform.notification-rules.index') }}" class="btn btn-light border">
            <i class="feather-arrow-left me-1"></i> {{ __('notifications.back_to_rules') }}
        </a>
    </div>
@endsection

@section('content')
    <div class="row g-4">
        <!-- Main Form Column -->
        <div class="col-lg-8">
            <div class="odoo-sheet-card p-4 p-md-5">
                <form action="{{ route('platform.notification-rules.update', $notificationRule) }}" method="POST" id="notificationRuleForm" class="odoo-sheet">
                    @csrf
                    @method('PUT')

                    <!-- 1. Trigger Event Selection -->
                    <div class="mb-5">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="feather-zap"></i>
                            </div>
                            <div>
                                <h6 class="section-title">{{ __('notifications.sec_1_edit_title') }}</h6>
                                <small class="text-muted fs-12">{{ __('notifications.sec_1_edit_subtitle') }}</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui 
                                type="select" 
                                label="{{ __('notifications.event_trigger') }}" 
                                name="event_key" 
                                id="event_key" 
                                :required="true">
                                @foreach($eventCatalog as $modKey => $module)
                                    <optgroup label="{{ $module['label'] }}">
                                        @foreach($module['events'] as $eKey => $event)
                                            <option value="{{ $eKey }}" 
                                                data-module="{{ $modKey }}"
                                                data-label="{{ $event['label'] }}"
                                                data-title="{{ $event['default_title'] }}"
                                                data-body="{{ $event['default_body'] }}"
                                                data-icon="{{ $event['icon'] }}"
                                                data-route="{{ $event['action_route'] }}"
                                                data-vars="{{ json_encode($event['variables'] ?? []) }}"
                                                {{ (old('event_key', $notificationRule->event_key) === $eKey) ? 'selected' : '' }}>
                                                {{ $event['label'] }} ({{ $eKey }})
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui 
                                type="input" 
                                label="{{ __('notifications.rule_name') }}" 
                                name="name" 
                                id="rule_name" 
                                :value="old('name', $notificationRule->name)" 
                                :required="true" />
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui 
                                type="input" 
                                label="{{ __('notifications.module') }}" 
                                name="module" 
                                id="rule_module" 
                                :value="old('module', $notificationRule->module)" 
                                :readonly="true" />
                        </div>
                    </div>

                    <!-- 2. Recipient Configuration -->
                    <div class="mb-5">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="feather-users"></i>
                            </div>
                            <div>
                                <h6 class="section-title">{{ __('notifications.sec_2_title') }}</h6>
                                <small class="text-muted fs-12">{{ __('notifications.sec_2_subtitle') }}</small>
                            </div>
                        </div>

                        @php
                            $savedRoles = $notificationRule->recipient_roles ?? [];
                        @endphp
                        <div class="mb-3">
                            <x-ui.odoo-form-ui 
                                type="select" 
                                label="{{ __('notifications.target_roles') }}" 
                                name="recipient_roles[]" 
                                id="recipient_roles" 
                                :multiple="true"
                                data-placeholder="{{ __('notifications.target_roles_placeholder') }}"
                                helperText="{{ __('notifications.target_roles_helper') }}">
                                @foreach($roles as $role)
                                    <option value="{{ $role->name }}" {{ in_array($role->name, old('recipient_roles', $savedRoles)) ? 'selected' : '' }}>
                                        {{ $role->name }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        @php
                            $savedUserIds = $notificationRule->recipient_user_ids ?? [];
                        @endphp
                        <div class="mb-3">
                            <x-ui.odoo-form-ui 
                                type="select" 
                                label="{{ __('notifications.specific_users') }}" 
                                name="recipient_user_ids[]" 
                                id="recipient_user_ids" 
                                :multiple="true"
                                data-placeholder="{{ __('notifications.specific_users_placeholder') }}"
                                helperText="{{ __('notifications.specific_users_helper') }}">
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ in_array($user->id, old('recipient_user_ids', $savedUserIds)) ? 'selected' : '' }}>
                                        {{ $user->name }} ({{ $user->email }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="row g-3 pt-2">
                            <div class="col-md-6">
                                <div class="switch-tile">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input ms-0 me-2" type="checkbox" name="notify_creator" id="notify_creator" value="1" {{ old('notify_creator', $notificationRule->notify_creator) ? 'checked' : '' }}>
                                        <label class="form-check-label fw-bold text-dark fs-13" for="notify_creator">
                                            {{ __('notifications.notify_creator') }}
                                        </label>
                                        <small class="text-muted d-block fs-11 mt-1">{{ __('notifications.notify_creator_desc') }}</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="switch-tile">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input ms-0 me-2" type="checkbox" name="notify_assigned_user" id="notify_assigned_user" value="1" {{ old('notify_assigned_user', $notificationRule->notify_assigned_user) ? 'checked' : '' }}>
                                        <label class="form-check-label fw-bold text-dark fs-13" for="notify_assigned_user">
                                            {{ __('notifications.notify_assigned_user') }}
                                        </label>
                                        <small class="text-muted d-block fs-11 mt-1">{{ __('notifications.notify_assigned_user_desc') }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Notification Message Template -->
                    <div class="mb-4">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="feather-bell"></i>
                            </div>
                            <div>
                                <h6 class="section-title">{{ __('notifications.sec_3_title') }}</h6>
                                <small class="text-muted fs-12">{{ __('notifications.sec_3_subtitle') }}</small>
                            </div>
                        </div>

                        <!-- Dynamic Variable Chips -->
                        <div class="mb-3 p-3 bg-light rounded-3 border">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted d-block mb-2">
                                <i class="feather-tag me-1"></i> {{ __('notifications.dynamic_placeholders') }}
                            </label>
                            <div id="variableChipsContainer" class="d-flex flex-wrap gap-2">
                                @foreach($eventDetails['variables'] ?? ['doc_no', 'customer_name', 'amount', 'created_by'] as $var)
                                    <span class="variable-chip" data-var="@{{ {{ $var }} }}"><i class="feather-plus fs-10"></i> @{{ {{ $var }} }}</span>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui 
                                type="input" 
                                label="{{ __('notifications.notification_title') }}" 
                                name="title_template" 
                                id="title_template" 
                                :value="old('title_template', $notificationRule->title_template)" 
                                :required="true" />
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui 
                                type="textarea" 
                                label="{{ __('notifications.notification_body') }}" 
                                name="body_template" 
                                id="body_template" 
                                :value="old('body_template', $notificationRule->body_template)" 
                                :required="true" />
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui 
                                type="input" 
                                label="{{ __('notifications.action_route') }}" 
                                name="action_route" 
                                id="action_route" 
                                :value="old('action_route', $notificationRule->action_route)" />
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui 
                                type="input" 
                                label="{{ __('notifications.icon_class') }}" 
                                name="icon_class" 
                                id="icon_class" 
                                :value="old('icon_class', $notificationRule->icon_class ?? 'feather-bell')" />
                        </div>
                    </div>

                    <!-- 4. Active Status & Submit -->
                    <div class="d-flex align-items-center justify-content-between pt-4 border-top mt-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $notificationRule->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold text-dark fs-13" for="is_active">{{ __('notifications.rule_is_active') }}</label>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="{{ route('platform.notification-rules.index') }}" class="btn btn-light border px-3">{{ __('notifications.cancel') }}</a>
                            <button type="submit" class="btn btn-primary px-4 shadow-sm">
                                <i class="feather-save me-1"></i> {{ __('notifications.update_rule') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Side: Live Header Bell Card Preview -->
        <div class="col-lg-4">
            <div class="odoo-sheet-card p-4 sticky-top" style="top: 20px;">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="feather-eye text-primary"></i> {{ __('notifications.live_preview_title') }}
                    </h6>
                    <span class="badge bg-soft-success text-success fs-10">{{ __('notifications.realtime') }}</span>
                </div>
                
                <p class="fs-12 text-muted mb-3">{{ __('notifications.live_preview_desc') }}</p>

                <div class="bell-preview-card">
                    <div class="d-flex gap-2.5">
                        <div class="avatar avatar-sm rounded-circle bg-soft-primary text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
                            <i id="previewIcon" class="{{ $notificationRule->icon_class ?? 'feather-bell' }} fs-15"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-soft-primary text-primary fs-9 text-uppercase fw-bold" id="previewModuleBadge">{{ strtoupper($notificationRule->module) }}</span>
                                <small class="text-muted fs-10">{{ __('notifications.just_now') }}</small>
                            </div>
                            <h6 class="fw-bold text-dark fs-13 mb-1 text-truncate" id="previewTitle">{{ $notificationRule->title_template }}</h6>
                            <p class="text-muted fs-11 mb-0" id="previewBody" style="line-height: 1.4;">{{ $notificationRule->body_template }}</p>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info border-0 bg-soft-info p-3 mt-4 rounded-3 mb-0">
                    <div class="d-flex gap-2.5">
                        <i class="feather-info fs-16 text-info mt-0.5 flex-shrink-0"></i>
                        <div class="fs-11 text-dark" style="line-height: 1.45;">
                            <strong>{{ __('notifications.delivery_behavior') }}</strong><br>
                            {{ __('notifications.delivery_desc') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        // Initialize Select2 explicitly for clean multi-select rendering
        if (typeof $.fn.select2 === 'function') {
            $('#recipient_roles').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: '{{ __('notifications.target_roles_placeholder') }}',
                allowClear: true
            });

            $('#recipient_user_ids').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: '{{ __('notifications.specific_users_placeholder') }}',
                allowClear: true
            });
        }

        const ruleNameInput = document.getElementById('rule_name');
        const moduleInput = document.getElementById('rule_module');
        const titleInput = document.getElementById('title_template');
        const bodyInput = document.getElementById('body_template');
        const iconInput = document.getElementById('icon_class');
        const routeInput = document.getElementById('action_route');
        const previewTitle = document.getElementById('previewTitle');
        const previewBody = document.getElementById('previewBody');
        const previewIcon = document.getElementById('previewIcon');
        const previewBadge = document.getElementById('previewModuleBadge');
        const chipsContainer = document.getElementById('variableChipsContainer');

        function updatePreview() {
            if (previewTitle && titleInput) previewTitle.textContent = titleInput.value || 'Notification Title';
            if (previewBody && bodyInput) previewBody.textContent = bodyInput.value || 'Notification message content will appear here...';
            if (previewBadge && moduleInput) previewBadge.textContent = (moduleInput.value || 'SYSTEM').toUpperCase();
            if (previewIcon && iconInput && iconInput.value) {
                previewIcon.className = iconInput.value + ' fs-15';
            }
        }

        if (titleInput) titleInput.addEventListener('input', updatePreview);
        if (bodyInput) bodyInput.addEventListener('input', updatePreview);
        if (iconInput) iconInput.addEventListener('input', updatePreview);

        // When Event Trigger Changes
        $('#event_key').on('change', function () {
            const opt = this.options[this.selectedIndex];
            if (!opt || !opt.value) return;

            const mod = opt.dataset.module || 'system';
            const label = opt.dataset.label || '';
            const defTitle = opt.dataset.title || '';
            const defBody = opt.dataset.body || '';
            const defIcon = opt.dataset.icon || 'feather-bell';
            const defRoute = opt.dataset.route || '';
            const vars = JSON.parse(opt.dataset.vars || '[]');

            if (moduleInput) moduleInput.value = mod;
            if (defTitle && !titleInput.value) titleInput.value = defTitle;
            if (defBody && !bodyInput.value) bodyInput.value = defBody;
            if (defIcon) iconInput.value = defIcon;
            if (defRoute) routeInput.value = defRoute;

            // Update Dynamic Variable Chips safely without Blade syntax collision
            if (chipsContainer) {
                chipsContainer.innerHTML = '';
                vars.forEach(function (v) {
                    const tag = '{' + '{' + v + '}' + '}';
                    const chip = document.createElement('span');
                    chip.className = 'variable-chip';
                    chip.dataset.var = tag;
                    chip.innerHTML = '<i class="feather-plus fs-10"></i> ' + tag;
                    chipsContainer.appendChild(chip);
                });
            }

            updatePreview();
        });

        // Insert variable tag into active input on click
        document.addEventListener('click', function (e) {
            const chip = e.target.closest('.variable-chip');
            if (chip) {
                const tag = chip.dataset.var;
                const active = document.activeElement;
                if (active === titleInput || active === bodyInput) {
                    const start = active.selectionStart;
                    const end = active.selectionEnd;
                    active.value = active.value.substring(0, start) + tag + active.value.substring(end);
                    active.selectionStart = active.selectionEnd = start + tag.length;
                    active.focus();
                } else if (bodyInput) {
                    bodyInput.value += ' ' + tag;
                }
                updatePreview();
            }
        });

        updatePreview();
    });
</script>
@endpush

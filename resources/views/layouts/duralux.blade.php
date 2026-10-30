<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $currentLanguage['dir'] ?? 'ltr' }}">

<head>
    @include('layouts.partials.head')
</head>

<body>
    {{-- Where you are (tenant, company, branch, FY, apps) — resolved once, shared by the shell partials. --}}
    @php($shell = app(\App\Support\ShellContext::class)->resolve())

    @include('partials.duralux.sidebar')
    @include('partials.duralux.header')

    <main class="nxl-container">
        <div class="nxl-content">
            @if(request()->routeIs('production.*') || request()->is('production*'))
                @include('partials.duralux.production-workflow-strip')
            @endif

            {{-- Context bar: page title + status chips on the left, page actions on the right (ui-reference "SecondaryActionBar"). --}}
            <div class="page-header ax-context-bar">
                <div class="page-header-left d-flex align-items-center gap-2 min-w-0">
                    @hasSection('page-back-button')
                        <div class="me-1">
                            @yield('page-back-button')
                        </div>
                    @endif
                    <div class="page-header-title d-flex flex-wrap align-items-center gap-2 min-w-0">
                        <h5 class="ax-page-title mb-0">@yield('page-title', __('ui.dashboard'))</h5>
                        {{-- Ledger amounts carry no symbol of their own; say once, per page, what they are in. --}}
                        @if (request()->routeIs('accounting.*') && !request()->routeIs('accounting.exchange-rates.*') && company())
                            @php($reportingCurrency = company_currency())
                            <span class="ax-ref ax-tone-positive" title="{{ $reportingCurrency['name'] }} — the company's base currency">Amounts in {{ $reportingCurrency['code'] }} ({{ $reportingCurrency['symbol'] }})</span>
                        @endif
                        @yield('page-badge')
                    </div>
                </div>
                <div class="page-header-right ms-auto">
                    <div class="page-header-right-items">
                        <div class="d-flex d-md-none">
                            <a href="javascript:void(0)" class="page-header-right-close-toggle">
                                <i class="feather-arrow-left me-2"></i>
                                <span>{{ __('ui.back') }}</span>
                            </a>
                        </div>
                        <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                            @yield('page-actions')
                        </div>
                    </div>
                    <div class="d-md-none d-flex align-items-center">
                        <a href="javascript:void(0)" class="page-header-right-open-toggle">
                            <i class="feather-align-right fs-20"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="main-content">
                @if (tenant()?->subscription_status === \App\Models\Tenant::SUBSCRIPTION_PAST_DUE && auth()->user()?->tenant_id !== null)
                    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                        <span><i class="feather-alert-triangle me-2"></i>Your subscription renewal payment failed. Please renew soon to keep using the workspace — your data is safe.</span>
                        @can('viewSubscription', tenant())
                            <a href="{{ route('platform.subscription.index') }}" class="btn btn-sm btn-warning">Go to billing</a>
                        @endcan
                    </div>
                @endif
                @if(session('success'))
                    <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
                @endif
                @if(session('error'))
                    <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
                @endif
                @if(session('warning'))
                    <x-ui.toast :auto="true" type="warning" title="{{ session('warning') }}" />
                @endif
                @yield('content')
            </div>
        </div>

        @include('partials.duralux.footer')
    </main>

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="{{ asset('assets/js/production.js') }}"></script>

    <script src="{{ asset('assets/vendors/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/vendors.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/moment.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/daterangepicker.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script src="{{ asset('assets/js/erp-searchable-select.js') }}?v={{ @filemtime(public_path('assets/js/erp-searchable-select.js')) }}"></script>
    {{-- nxlNavigation is already bundled in vendors.min.js; loading it again bound every
         sidebar/menu handler twice (the mobile menu opened and closed on the same tap). --}}
    <script src="{{ asset('assets/js/common-init.min.js') }}"></script>
    <script>
        $(document).on('click', '.language_select a[data-flag]', function () {
            var selected = $(this);

            $('.language_select').removeClass('active');
            selected.closest('.language_select').addClass('active');
            $('.nxl-language-link img').attr({
                src: selected.data('flag'),
                alt: selected.data('language')
            });
        });

        // User format for Select2 with data-avatar support and fallback
        window.userformat = function (user) {
            if (!user.id) {
                return user.text;
            }
            var avatar = '/assets/images/avatar/default.png';
            if (user.element) {
                var opt = $(user.element);
                var dataAvatar = opt.data('avatar') || opt.attr('data-avatar');
                if (dataAvatar) {
                    avatar = dataAvatar;
                } else if (opt.data('user')) {
                    avatar = '/assets/images/avatar/' + opt.data('user') + '.png';
                }
            }
            return $(
                '<span class="hstack gap-2 align-items-center">' +
                    '<img src="' + avatar + '" class="avatar-image avatar-sm object-fit-cover rounded-circle flex-shrink-0" style="width: 22px; height: 22px; border-radius: 50%;" onerror="this.onerror=null; this.src=\'/assets/images/avatar/default.png\';" /> ' +
                    '<span>' + (user.text || '') + '</span>' +
                '</span>'
            );
        };

        // Select2 search focus fix inside Bootstrap modals
        $(document).on('show.bs.modal', '.modal', function () {
            var modal = $(this);
            modal.find('[data-select2-selector]').each(function () {
                var select = $(this);
                if (select.hasClass('select2-hidden-accessible')) {
                    select.select2('destroy');
                }
                var selectorType = select.data('select2-selector');
                var options = {
                    theme: "bootstrap-5",
                    dropdownParent: modal
                };

                if (selectorType === 'icon' || selectorType === 'visibility' || selectorType === 'privacy') {
                    options.templateResult = typeof iformat !== 'undefined' ? iformat : undefined;
                    options.templateSelection = typeof iformat !== 'undefined' ? iformat : undefined;
                } else if (selectorType === 'storage') {
                    options.templateResult = typeof storageformat !== 'undefined' ? storageformat : undefined;
                    options.templateSelection = typeof storageformat !== 'undefined' ? storageformat : undefined;
                } else if (selectorType === 'tag' || selectorType === 'status' || selectorType === 'priority' || selectorType === 'label' || selectorType === 'type') {
                    options.templateResult = typeof bgformat !== 'undefined' ? bgformat : undefined;
                    options.templateSelection = typeof bgformat !== 'undefined' ? bgformat : undefined;
                } else if (selectorType === 'user') {
                    options.templateResult = typeof userformat !== 'undefined' ? userformat : undefined;
                    options.templateSelection = typeof userformat !== 'undefined' ? userformat : undefined;
                } else if (selectorType === 'payment') {
                    options.templateResult = typeof paymentformat !== 'undefined' ? paymentformat : undefined;
                    options.templateSelection = typeof paymentformat !== 'undefined' ? paymentformat : undefined;
                } else if (selectorType === 'flag') {
                    options.templateResult = typeof flagformat !== 'undefined' ? flagformat : undefined;
                    options.templateSelection = typeof flagformat !== 'undefined' ? flagformat : undefined;
                } else if (selectorType === 'country') {
                    options.templateResult = typeof countryformat !== 'undefined' ? countryformat : undefined;
                    options.templateSelection = typeof countryformat !== 'undefined' ? countryformat : undefined;
                } else if (selectorType === 'tzone') {
                    options.templateResult = typeof tzoneformat !== 'undefined' ? tzoneformat : undefined;
                    options.templateSelection = typeof tzoneformat !== 'undefined' ? tzoneformat : undefined;
                } else if (selectorType === 'state') {
                    options.templateResult = typeof stateformat !== 'undefined' ? stateformat : undefined;
                    options.templateSelection = typeof stateformat !== 'undefined' ? stateformat : undefined;
                } else if (selectorType === 'city') {
                    options.templateResult = typeof cityformat !== 'undefined' ? cityformat : undefined;
                    options.templateSelection = typeof cityformat !== 'undefined' ? cityformat : undefined;
                } else if (selectorType === 'language') {
                    options.templateResult = typeof languageformat !== 'undefined' ? languageformat : undefined;
                    options.templateSelection = typeof languageformat !== 'undefined' ? languageformat : undefined;
                } else if (selectorType === 'currency') {
                    options.templateResult = typeof currencyformat !== 'undefined' ? currencyformat : undefined;
                    options.templateSelection = typeof currencyformat !== 'undefined' ? currencyformat : undefined;
                } else if (selectorType === 'programming') {
                    options.templateResult = typeof programmingformat !== 'undefined' ? programmingformat : undefined;
                    options.templateSelection = typeof programmingformat !== 'undefined' ? programmingformat : undefined;
                }

                select.select2(options);
            });
        });
        // Initialize and bind primary color picker
        $(document).ready(function () {
            var savedColor = localStorage.getItem('erp_primary_color') || '#4f46e5';
            var picker = $('#primaryColorPicker');
            var preview = $('#primaryColorPreview');
            if (picker.length) {
                picker.val(savedColor);
                if (preview.length) {
                    preview.css('background-color', savedColor);
                }
                picker.on('input change', function () {
                    var color = $(this).val();
                    window.erpApplyPrimaryColor(color);
                    localStorage.setItem('erp_primary_color', color);
                    if (preview.length) {
                        preview.css('background-color', color);
                    }
                });
            }
        });

        // Dark / Light Mode Toggle Handler
        $(document).ready(function () {
            // Light / Dark is a segmented control in the user menu: mark the current one.
            function syncThemeUI(isDark) {
                $('.dark-button').toggleClass('active', isDark);
                $('.light-button').toggleClass('active', !isDark);
            }

            var initialDark = $('html').hasClass('app-skin-dark');
            syncThemeUI(initialDark);

            $(document).on('click', '.dark-button', function (e) {
                e.preventDefault();
                $('html').addClass('app-skin-dark');
                localStorage.setItem('app-skin-dark', 'app-skin-dark');
                localStorage.setItem('app-skin', 'app-skin-dark');
                syncThemeUI(true);
            });

            $(document).on('click', '.light-button', function (e) {
                e.preventDefault();
                $('html').removeClass('app-skin-dark');
                localStorage.setItem('app-skin-dark', 'app-skin-light');
                localStorage.setItem('app-skin', 'app-skin-light');
                syncThemeUI(false);
            });
        });

        // Generic Quick Create Master Dropdown handler (Supports Single & Multiselect)
        function handleQuickCreateSelect(select, overrideVal) {
            var val = overrideVal || select.val();
            var isAddNew = false;

            if (Array.isArray(val)) {
                isAddNew = val.includes('__ADD_NEW__');
            } else {
                isAddNew = (val === '__ADD_NEW__');
            }

            if (isAddNew) {
                // Get master from select attr first, then fallback to selected option attr, then fallback by element ID
                var master = select.attr('data-master')
                    || select.data('master')
                    || select.find('option[value="__ADD_NEW__"]').attr('data-master')
                    || (select.attr('id') === 'crm_contact_id' ? 'contact' : null);

                if (!master) return;

                var modalId = 'quickCreateModal_' + master;
                var modalEl = $('#' + modalId);
                if (modalEl.length) {
                    if (typeof modalEl.modal === 'function') {
                        modalEl.modal('show');
                    } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        var modal = bootstrap.Modal.getOrCreateInstance(modalEl[0]);
                        modal.show();
                    }
                    modalEl.data('trigger-select', select);
                }

                if (Array.isArray(val)) {
                    var newVal = val.filter(function (v) { return v !== '__ADD_NEW__'; });
                    select.val(newVal).trigger('change.select2');
                } else {
                    select.val('').trigger('change.select2');
                }
            }
        }

        // Native select change
        $(document).on('change', 'select', function () {
            handleQuickCreateSelect($(this));
        });

        // Select2 select event (fires when user picks from Select2 dropdown)
        $(document).on('select2:select', 'select', function (e) {
            var selectedId = (e && e.params && e.params.data) ? e.params.data.id : null;
            if (selectedId === '__ADD_NEW__') {
                handleQuickCreateSelect($(this), '__ADD_NEW__');
            }
        });

        // Dynamic Additional Contacts Repeater inside Quick Create Contact Modal
        var modalContactIdx = 0;
        $(document).on('click', '#modalAddContactRowBtn', function (e) {
            e.preventDefault();
            modalContactIdx++;
            var newCard = $(`
                <div class="border rounded-3 p-2 bg-light position-relative modal-contact-row">
                    <button type="button" class="btn btn-link text-danger p-0 position-absolute top-0 end-0 me-2 mt-1 remove-modal-contact-row" title="Remove Contact">
                        <i class="feather-trash-2 fs-12"></i>
                    </button>
                    <div class="row g-2 pe-3 align-items-center">
                        <div class="col-md-4">
                            <input type="text" name="additional_contacts[${modalContactIdx}][name]" class="form-control form-control-sm" placeholder="e.g. Jane Doe">
                        </div>
                        <div class="col-md-4">
                            <input type="tel" name="additional_contacts[${modalContactIdx}][phone]" class="form-control form-control-sm" placeholder="e.g. +91 9876543211">
                        </div>
                        <div class="col-md-4">
                            <input type="email" name="additional_contacts[${modalContactIdx}][email]" class="form-control form-control-sm" placeholder="e.g. jane.doe@gmail.com">
                        </div>
                    </div>
                </div>
            `);
            $('#modalAdditionalContactsContainer').append(newCard);
        });

        $(document).on('click', '.remove-modal-contact-row', function (e) {
            e.preventDefault();
            $(this).closest('.modal-contact-row').remove();
        });

        // Ensure button[form] works across all browsers/shells
        $(document).on('click', 'button[form]', function (e) {
            var btn = $(this);
            var formId = btn.attr('form');
            if (formId) {
                var form = $('#' + formId);
                if (form.length) {
                    e.preventDefault();
                    form.submit();
                }
            }
        });

        // Click handler for div-based quick-create forms
        $(document).on('click', '.btn-save-master', function (e) {
            e.preventDefault();
            var btn = $(this);
            var formId = btn.attr('data-form');
            var formEl = $('#' + formId);
            if (formEl.length) {
                submitQuickCreateForm(formEl, btn);
            }
        });

        // Keydown Enter handler for quick-create input fields
        $(document).on('keydown', '.quick-create-form input', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var formEl = $(this).closest('.quick-create-form');
                var modalEl = formEl.closest('.modal');
                var btn = modalEl.find('.btn-save-master');
                if (btn.length) {
                    submitQuickCreateForm(formEl, btn);
                }
            }
        });

        function submitQuickCreateForm(form, submitBtn) {
            var modalEl = form.closest('.modal');
            var triggerSelect = modalEl.data('trigger-select');

            submitBtn.prop('disabled', true);
            form.find('.invalid-feedback').remove();
            form.find('.is-invalid').removeClass('is-invalid');
            form.find('.alert-danger').remove();

            var inputs = form.find('input, select, textarea');
            var formData = inputs.serialize();

            $.ajax({
                url: form.attr('data-action'),
                method: 'POST',
                data: formData,
                success: function (response) {
                    submitBtn.prop('disabled', false);
                    if (response.id && response.name) {
                        if (typeof modalEl.modal === 'function') {
                            modalEl.modal('hide');
                        } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            var modal = bootstrap.Modal.getInstance(modalEl[0]);
                            if (modal) modal.hide();
                        }

                        // Clear inputs
                        inputs.each(function () {
                            var el = $(this);
                            if (el.attr('type') !== 'hidden' && el.attr('name') !== '_token') {
                                el.val('');
                            }
                        });

                        if (triggerSelect && $(triggerSelect).length) {
                            var isMultiple = $(triggerSelect).prop('multiple');
                            var displayText = response.name + (response.sku ? ' (' + response.sku + ')' : '');

                            var optionEl = $('<option>', {
                                value: response.id,
                                text: displayText,
                                selected: true
                            });
                            if (response.type) {
                                optionEl.attr('data-type', response.type);
                            }
                            if (response.billing_address) {
                                optionEl.attr('data-billing', response.billing_address);
                            }
                            if (response.shipping_address) {
                                optionEl.attr('data-shipping', response.shipping_address);
                            }

                            // Append new DB option to dropdown
                            $(triggerSelect).append(optionEl);

                            if (isMultiple) {
                                var currentVals = $(triggerSelect).val() || [];
                                if (!Array.isArray(currentVals)) {
                                    currentVals = [currentVals];
                                }
                                // Filter out dummy __ADD_NEW__ value
                                currentVals = currentVals.filter(function (v) { return v !== '__ADD_NEW__' && v !== ''; });
                                var strId = String(response.id);
                                if (!currentVals.includes(strId) && !currentVals.includes(Number(response.id))) {
                                    currentVals.push(strId);
                                }
                                $(triggerSelect).val(currentVals).trigger('change').trigger('change.select2');
                            } else {
                                $(triggerSelect).val(response.id).trigger('change').trigger('change.select2');
                            }
                        } else {
                            // If modal was opened from listing page button (e.g. + New Customer), reload page to show new record in table
                            window.location.reload();
                        }
                    }
                },
                error: function (xhr) {
                    submitBtn.prop('disabled', false);
                    if (xhr.status === 422) {
                        var errors = xhr.responseJSON.errors;
                        $.each(errors, function (field, messages) {
                            var input = form.find('[name="' + field + '"]');
                            if (input.length) {
                                input.addClass('is-invalid');
                                input.after('<div class="invalid-feedback">' + messages[0] + '</div>');
                            } else {
                                form.prepend('<div class="alert alert-danger mb-3">' + messages[0] + '</div>');
                            }
                        });
                    } else {
                        form.prepend('<div class="alert alert-danger mb-3">' + (xhr.responseJSON?.message || 'An error occurred.') + '</div>');
                    }
                }
            });
        }

        // Global numeric-only restriction for Phone and Zip fields across modals and forms
        var numericFieldSelector = 'input[name="phone"], input[name="contact_phone"], input[name="company_phone"], input[name="mobile"], input[name="zip_code"], input[name="billing_zip"]';
        $(document).on('input', numericFieldSelector, function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        $(document).on('keypress', numericFieldSelector, function(e) {
            if (e.which < 48 || e.which > 57) {
                if (e.which !== 8 && e.which !== 0 && !e.ctrlKey && !e.metaKey) {
                    e.preventDefault();
                }
            }
        });
        $(document).on('paste', numericFieldSelector, function(e) {
            var pasteData = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
            if (pasteData && !/^\d+$/.test(pasteData)) {
                e.preventDefault();
                var clean = pasteData.replace(/\D/g, '');
                document.execCommand('insertText', false, clean);
            }
        });
    </script>

    <!-- Upgraded Custom Premium Confirmation Modal Component -->
    <x-ui.confirmation-modal />

    <script>
        // Helper for plain <form onsubmit="return confirmFormSubmit(event, '...')"> usages.
        function confirmFormSubmit(event, message, options) {
            event.preventDefault();
            var form = event.target;
            confirmAction(message, function () {
                form.submit();
            }, options);
            return false;
        }

        // Toast helper for AJAX flows, mirroring the config used by the x-ui.toast component
        window.showAppToast = function (type, message) {
            if (!message) return;
            var iconType = (type === 'danger' || type === 'error') ? 'error' : (type === 'warning' ? 'warning' : 'success');
            if (typeof Swal !== 'undefined') {
                Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true,
                    didOpen: function (toast) {
                        toast.addEventListener('mouseenter', Swal.stopTimer);
                        toast.addEventListener('mouseleave', Swal.resumeTimer);
                    }
                }).fire({
                    icon: iconType,
                    title: message
                });
            } else if (typeof toastr !== 'undefined') {
                if (iconType === 'error') toastr.error(message);
                else if (iconType === 'warning') toastr.warning(message);
                else toastr.success(message);
            }
        };
        function showAppToast(type, message) {
            window.showAppToast(type, message);
        }
    </script>

    <!-- Global Toast Notifications -->
    <div class="erp-toast-container">
        @if (session('success'))
            <x-ui.toast :auto="true" title="{{ session('success') }}" type="success" delay="6000">
            </x-ui.toast>
        @endif

        @if (session('error'))
            <x-ui.toast :auto="true" title="{{ session('error') }}" type="error" delay="5000">
            </x-ui.toast>
        @endif

        @if (session('danger'))
            <x-ui.toast :auto="true" title="{{ session('danger') }}" type="error" delay="5000">
            </x-ui.toast>
        @endif

        @if ($errors->any())
            <x-ui.toast :auto="true" title="{{ $errors->first() }}" type="error" delay="5000">
            </x-ui.toast>
        @endif
    </div>

    <script>
        $(function () {
            @if (session('success'))
                var successToastEl = document.getElementById('globalSuccessToast');
                if (successToastEl) {
                    var successToast = bootstrap.Toast.getOrCreateInstance(successToastEl);
                    successToast.show();
                }
            @endif
                @if (session('error') || session('danger') || $errors->any())
                    var errorToastEl = document.getElementById('globalErrorToast');
                    if (errorToastEl) {
                        var errorToast = bootstrap.Toast.getOrCreateInstance(errorToastEl);
                        errorToast.show();
                    }
                @endif
        });
    </script>

    {{-- Google Workspace / Calendar Sync Warning Modal (Appears only on sync error / missing auth) --}}
    @if(session('google_sync_warning'))
        <x-ui.modal 
            id="googleSyncWarningModal" 
            :title="'<i class=\'feather-alert-triangle text-warning me-2 fs-18\'></i>Google Calendar & Meet Sync Notice'" 
            size="md" 
            :centered="true"
            :showFooter="true"
        >
            <div class="text-center py-2">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-soft-warning text-warning mb-3" style="width: 56px; height: 56px;">
                    <i class="feather-video" style="font-size: 28px;"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Activity Saved in CRM (Google Sync Notice)</h5>
                <div class="text-start bg-light p-3 rounded-2 border mb-3">
                    <div class="fw-semibold text-danger fs-13 mb-1">
                        <i class="feather-info me-1"></i> {{ session('google_sync_warning') }}
                    </div>
                    <div class="text-muted fs-12">
                        Your CRM activity is saved. However, live Google Meet conference link and calendar invite could not be created until your Google Workspace account is connected.
                    </div>
                </div>
            </div>

            <x-slot name="footer">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Close</button>
                    @if(session('google_auth_url'))
                        <a href="{{ session('google_auth_url') }}" class="btn btn-sm btn-primary px-3 shadow-sm">
                            <i class="feather-link me-1"></i> Connect Google Account
                        </a>
                    @endif
                </div>
            </x-slot>
        </x-ui.modal>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modalEl = document.getElementById('googleSyncWarningModal');
                if (modalEl && typeof bootstrap !== 'undefined') {
                    var m = new bootstrap.Modal(modalEl);
                    m.show();
                }
            });
        </script>
    @endif

    <script src="{{ asset('assets/js/dynamic-geography.js') }}"></script>
    <script src="{{ asset('assets/js/tax-identity-formatter.js') }}?v={{ @filemtime(public_path('assets/js/tax-identity-formatter.js')) }}"></script>
    <script src="{{ asset('assets/js/crm-meeting-location-picker.js') }}?v={{ @filemtime(public_path('assets/js/crm-meeting-location-picker.js')) }}"></script>
    <script>
        // Global safety: automatically hoist any opened Bootstrap modal to document.body
        // to prevent CSS stacking context traps (e.g. nested inside .nxl-container or .main-content)
        document.addEventListener('show.bs.modal', function (e) {
            if (e.target && e.target.classList && e.target.classList.contains('modal') && e.target.parentElement !== document.body) {
                document.body.appendChild(e.target);
            }
        }, true);
    </script>
    @stack('scripts')
</body>

</html>
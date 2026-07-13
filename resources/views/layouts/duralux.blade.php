<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $currentLanguage['dir'] ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="SaaS ERP admin dashboard">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'SaaS ERP'))</title>
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/images/favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/vendors.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/daterangepicker.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/theme.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/erp.css') }}">
    <script>
        (function() {
            var savedColor = localStorage.getItem('erp_primary_color');
            if (savedColor) {
                document.documentElement.style.setProperty('--bs-primary', savedColor);
            }
        })();
    </script>
    <style>
        .nxl-container .nxl-content .main-content {
            padding: 10px !important;
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="loader-bg">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">{{ __('ui.loading') }}</span>
        </div>
    </div>

    @include('partials.duralux.sidebar')
    @include('partials.duralux.header')

    <main class="nxl-container">
        <div class="nxl-content">
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">@yield('page-title', __('ui.dashboard'))</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('ui.home') }}</a></li>
                        <li class="breadcrumb-item">@yield('breadcrumb', __('ui.dashboard'))</li>
                    </ul>
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
                @yield('content')
            </div>
        </div>

        @include('partials.duralux.footer')
    </main>

    <script src="{{ asset('assets/vendors/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/vendors.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/moment.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/daterangepicker.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/nxlNavigation.min.js') }}"></script>
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
        $(document).ready(function() {
            var savedColor = localStorage.getItem('erp_primary_color') || '#0000FF';
            var picker = $('#primaryColorPicker');
            if (picker.length) {
                picker.val(savedColor);
                picker.on('input change', function() {
                    var color = $(this).val();
                    document.documentElement.style.setProperty('--bs-primary', color);
                    localStorage.setItem('erp_primary_color', color);
                });
            }
        });

        // Generic Quick Create Master Dropdown handler
        $(document).on('change', '.erp-premium-select, select[data-master]', function() {
            var select = $(this);
            if (select.val() === '__ADD_NEW__') {
                var master = select.attr('data-master');
                var modalId = 'quickCreateModal_' + master;
                var modalEl = document.getElementById(modalId);
                if (modalEl) {
                    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.show();
                    $(modalEl).data('trigger-select', select);
                }
                select.val('').trigger('change.select2'); // Reset selection
            }
        });

        // Ensure button[form] works across all browsers/shells
        $(document).on('click', 'button[form]', function(e) {
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
        $(document).on('click', '.btn-save-master', function(e) {
            e.preventDefault();
            var btn = $(this);
            var formId = btn.attr('data-form');
            var formEl = $('#' + formId);
            if (formEl.length) {
                submitQuickCreateForm(formEl, btn);
            }
        });

        // Keydown Enter handler for quick-create input fields
        $(document).on('keydown', '.quick-create-form input', function(e) {
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
                success: function(response) {
                    submitBtn.prop('disabled', false);
                    if (response.id && response.name) {
                        var modal = bootstrap.Modal.getInstance(modalEl[0]);
                        modal.hide();
                        
                        // Clear inputs
                        inputs.each(function() {
                            var el = $(this);
                            if (el.attr('type') !== 'hidden' && el.attr('name') !== '_token') {
                                el.val('');
                            }
                        });

                        if (triggerSelect) {
                            var optionEl = $('<option>', {
                                value: response.id,
                                text: response.name,
                                selected: true
                            });
                            if (response.type) {
                                optionEl.attr('data-type', response.type);
                            }
                            $(triggerSelect).append(optionEl).trigger('change');
                        }
                    }
                },
                error: function(xhr) {
                    submitBtn.prop('disabled', false);
                    if (xhr.status === 422) {
                        var errors = xhr.responseJSON.errors;
                        $.each(errors, function(field, messages) {
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
    </script>

    <!-- Global confirmation modal, replacing native window.confirm() dialogs throughout the app.
         Bootstrap's modal stacks at z-index 1055, above offcanvas drawers (1051), so it always
         renders correctly on top even when triggered from inside an open drawer. -->
    <div class="modal fade" id="globalConfirmModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="globalConfirmModalTitle">{{ __('ui.confirm_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="globalConfirmModalMessage" class="mb-0"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal" id="globalConfirmModalCancelBtn">{{ __('ui.confirm_cancel') }}</button>
                    <button type="button" class="btn btn-danger" id="globalConfirmModalConfirmBtn">{{ __('ui.confirm_yes') }}</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmAction(message, onConfirm, options) {
            options = options || {};

            var modalEl = document.getElementById('globalConfirmModal');
            if (!modalEl || !window.bootstrap) {
                // Fallback if the modal markup or Bootstrap JS failed to load for any reason.
                if (window.confirm(message) && typeof onConfirm === 'function') {
                    onConfirm();
                }
                return;
            }

            document.getElementById('globalConfirmModalTitle').textContent = options.title || @js(__('ui.confirm_title'));
            document.getElementById('globalConfirmModalMessage').textContent = message;

            var cancelBtn = document.getElementById('globalConfirmModalCancelBtn');
            cancelBtn.textContent = options.cancelButtonText || @js(__('ui.confirm_cancel'));

            var confirmBtn = document.getElementById('globalConfirmModalConfirmBtn');
            confirmBtn.textContent = options.confirmButtonText || @js(__('ui.confirm_yes'));
            confirmBtn.className = 'btn ' + (options.confirmButtonClass || 'btn-danger');

            // Clone-and-replace clears any listener bound by a previous confirmAction() call.
            var freshConfirmBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(freshConfirmBtn, confirmBtn);

            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            freshConfirmBtn.addEventListener('click', function () {
                modal.hide();
                if (typeof onConfirm === 'function') {
                    onConfirm();
                }
            }, { once: true });

            modal.show();
        }

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
        // (which only fires from server-rendered session flash on full page loads).
        function showAppToast(type, message) {
            if (typeof Swal === 'undefined' || !message) return;

            Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true,
                didOpen: function (toast) {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            }).fire({
                icon: type,
                title: message
            });
        }
    </script>

    <!-- Global Toast Notifications -->
    <div class="erp-toast-container">
        @if (session('success'))
            <x-ui.toast :auto="true" title="{{ session('success') }}" type="success" delay="5000">
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
        $(function() {
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

    <script src="{{ asset('assets/js/dynamic-geography.js') }}"></script>
    @stack('scripts')
</body>
</html>

@props([
    'id' => 'globalConfirmModal',
    'title' => 'Confirm Action',
    'confirmText' => 'Confirm',
    'cancelText' => 'Cancel',
    'variant' => 'primary' // primary, danger, warning, success
])

@once
    @push('styles')
        <style>
            .erp-confirm-modal .modal-content {
                border-radius: 16px !important;
                border: none !important;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15) !important;
                overflow: hidden !important;
            }
            .erp-confirm-modal .modal-body {
                padding: 24px 24px 20px !important;
            }
            .erp-confirm-modal .modal-footer {
                padding: 16px 24px 20px !important;
                background-color: #f8fafc !important;
                border-top: 1px solid #f1f5f9 !important;
                border-bottom-left-radius: 16px !important;
                border-bottom-right-radius: 16px !important;
            }
            .erp-confirm-modal .confirm-icon-wrapper {
                width: 44px !important;
                height: 44px !important;
                border-radius: 50% !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                flex-shrink: 0 !important;
            }
            .erp-confirm-modal .modal-title {
                font-size: 16px !important;
                font-weight: 700 !important;
                color: #0f172a !important;
                margin-top: 2px !important;
            }
            .erp-confirm-modal .confirmation-message {
                color: #475569 !important;
                font-size: 13.5px !important;
                line-height: 1.5 !important;
                margin-top: 6px !important;
            }
            .erp-confirm-modal .btn-confirm {
                font-size: 12px !important;
                font-weight: 600 !important;
                padding: 8px 18px !important;
                border-radius: 8px !important;
                height: 38px !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                min-width: 90px !important;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
                transition: all 0.15s ease !important;
            }
            .erp-confirm-modal .btn-confirm:hover {
                transform: translateY(-1px) !important;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.08) !important;
            }
            .erp-confirm-modal .btn-confirm:active {
                transform: translateY(0) !important;
            }
        </style>
    @endpush
@endonce

<div class="modal fade erp-confirm-modal" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm" style="max-width: 420px;">
        <div class="modal-content">
            <div class="modal-body">
                <div class="d-flex align-items-start gap-3">
                    <!-- Dynamic Circle Badge -->
                    <div class="confirm-icon-wrapper">
                        <i class="confirm-icon fs-18"></i>
                    </div>
                    
                    <div class="flex-grow-1">
                        <h5 class="modal-title" id="{{ $id }}Title">{{ $title }}</h5>
                        <p id="{{ $id }}Message" class="mb-0 confirmation-message">Are you sure you want to perform this action?</p>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 gap-2">
                <button type="button" class="btn btn-confirm btn-light border text-secondary" data-bs-dismiss="modal" id="{{ $id }}CancelBtn">{{ $cancelText }}</button>
                <button type="button" class="btn btn-confirm text-white confirm-button" id="{{ $id }}ConfirmBtn">{{ $confirmText }}</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var modalEl = document.getElementById('{{ $id }}');
        if (modalEl) {
            document.body.appendChild(modalEl);
        }
    })();

    // Upgraded confirmAction script compatible with legacy calls: confirmAction(message, onConfirm, options)
    // and modern calls: confirmAction(options)
    window.confirmAction = function(messageOrOptions, onConfirm, options) {
        const modalEl = document.getElementById('{{ $id }}');
        if (!modalEl) return;

        let message = '';
        let callback = null;
        let opts = {};

        if (typeof messageOrOptions === 'object' && messageOrOptions !== null) {
            opts = messageOrOptions;
            message = opts.message || '';
            callback = opts.onConfirm;
        } else {
            message = messageOrOptions || '';
            callback = onConfirm;
            opts = options || {};
        }

        // Set title
        const titleEl = document.getElementById('{{ $id }}Title');
        if (titleEl) {
            titleEl.textContent = opts.title || '{{ $title }}';
        }

        // Set message
        const messageEl = document.getElementById('{{ $id }}Message');
        if (messageEl) {
            messageEl.textContent = message || 'Are you sure you want to perform this action?';
        }

        // Set Cancel Button Text
        const cancelBtn = document.getElementById('{{ $id }}CancelBtn');
        if (cancelBtn) {
            cancelBtn.textContent = opts.cancelButtonText || '{{ $cancelText }}';
        }

        // Set Confirm Button Text
        const confirmBtn = document.getElementById('{{ $id }}ConfirmBtn');
        if (confirmBtn) {
            confirmBtn.textContent = opts.confirmButtonText || opts.confirmText || '{{ $confirmText }}';
        }

        // Determine variant
        let variant = 'primary';
        if (opts.variant) {
            variant = opts.variant;
        } else if (opts.confirmButtonClass) {
            // Extract from bootstrap class (e.g. 'btn-danger' -> 'danger')
            const match = opts.confirmButtonClass.match(/btn-([a-z-]+)/);
            if (match && match[1]) {
                variant = match[1];
            }
        }

        // Normalize variants to modern color tokens and icons
        let iconBg = '#dbeafe'; // primary blue
        let iconColor = '#3b82f6';
        let iconClass = 'feather-help-circle';
        let btnBgClass = 'btn-primary';

        if (variant === 'danger' || variant === 'delete') {
            iconBg = '#fee2e2'; // light red
            iconColor = '#ef4444'; // deep red
            iconClass = 'feather-trash-2';
            btnBgClass = 'btn-danger';
        } else if (variant === 'warning' || variant === 'cancel') {
            iconBg = '#fef3c7'; // light orange/yellow
            iconColor = '#d97706'; // deep yellow
            iconClass = 'feather-alert-triangle';
            btnBgClass = 'btn-warning';
        } else if (variant === 'success' || variant === 'approve') {
            iconBg = '#dcfce7'; // light green
            iconColor = '#22c55e'; // deep green
            iconClass = 'feather-check-circle';
            btnBgClass = 'btn-success';
        } else if (variant === 'info') {
            iconBg = '#e0f2fe';
            iconColor = '#0284c7';
            iconClass = 'feather-info';
            btnBgClass = 'btn-info';
        }

        // Update icon styles
        const iconWrapper = modalEl.querySelector('.confirm-icon-wrapper');
        const iconEl = modalEl.querySelector('.confirm-icon');
        if (iconWrapper && iconEl) {
            iconWrapper.style.backgroundColor = iconBg;
            iconWrapper.style.color = iconColor;
            iconEl.className = 'confirm-icon ' + iconClass + ' fs-18';
        }

        // Update confirm button classes
        if (confirmBtn) {
            confirmBtn.className = 'btn btn-confirm text-white ' + btnBgClass;
        }

        // Bind callback on click
        const freshConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(freshConfirmBtn, confirmBtn);

        const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);

        freshConfirmBtn.addEventListener('click', function() {
            bsModal.hide();
            if (typeof callback === 'function') {
                callback();
            }
        }, { once: true });

        bsModal.show();
    };
</script>

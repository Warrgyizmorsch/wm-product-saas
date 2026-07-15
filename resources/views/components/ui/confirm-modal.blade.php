@props(['id' => 'confirmActionModal'])

{{-- Generic confirm-before-submit modal. Usage: put <x-ui.confirm-modal /> once per
     page, then on any form's submit trigger, use type="button" plus
     data-confirm-title="..." data-confirm-message="..." instead of an inline
     onsubmit="confirm(...)" — this replaces the native browser confirm() popup
     with a styled modal matching the rest of the app. --}}
<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $id }}Label">Confirm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body fs-13" data-confirm-modal-message>Are you sure?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" data-confirm-modal-submit>Confirm</button>
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
</script>

@once
    @push('scripts')
        <script>
            document.addEventListener('click', function (e) {
                const trigger = e.target.closest('[data-confirm-message]');
                if (!trigger) {
                    return;
                }

                e.preventDefault();

                const modalId = trigger.dataset.confirmModal || 'confirmActionModal';
                const modalEl = document.getElementById(modalId);
                if (!modalEl) {
                    return;
                }

                modalEl.querySelector('.modal-title').textContent = trigger.dataset.confirmTitle || 'Confirm';
                modalEl.querySelector('[data-confirm-modal-message]').textContent = trigger.dataset.confirmMessage;

                const submitBtn = modalEl.querySelector('[data-confirm-modal-submit]');
                const freshBtn = submitBtn.cloneNode(true);
                submitBtn.parentNode.replaceChild(freshBtn, submitBtn);
                freshBtn.addEventListener('click', function () {
                    const form = trigger.closest('form');
                    if (form) {
                        form.submit();
                    }
                });

                new bootstrap.Modal(modalEl).show();
            });
        </script>
    @endpush
@endonce

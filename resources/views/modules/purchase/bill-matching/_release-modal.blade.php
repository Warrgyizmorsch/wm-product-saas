{{-- Shared "release hold" dialog. Buttons with data-bs-target="#releaseHoldModal" and data-release-url open it. --}}
<x-ui.modal id="releaseHoldModal" title="Release <span data-release-number></span>" :centered="true" formAction="#">
    <p class="fs-13 text-muted">The bill will post to the ledger and become payable, even though it doesn't match the PO / GRN. Say why this is acceptable — it is kept with the bill.</p>
    <x-ui.textarea label="Reason" name="reason" id="releaseReason" rows="3" maxlength="500" :required="true" />

    <x-slot:footer>
        <x-ui.button type="button" variant="light-brand" size="sm" data-bs-dismiss="modal">Cancel</x-ui.button>
        <x-ui.button type="submit" variant="primary" size="sm" icon="feather-unlock">Release &amp; post</x-ui.button>
    </x-slot:footer>
</x-ui.modal>

@push('scripts')
    <script>
        document.getElementById('releaseHoldModal')?.addEventListener('show.bs.modal', function (event) {
            const trigger = event.relatedTarget;
            if (!trigger) return;
            this.querySelector('form').action = trigger.dataset.releaseUrl;
            this.querySelector('[data-release-number]').textContent = trigger.dataset.billNumber || '';
            this.querySelector('#releaseReason').value = '';
        });
    </script>
@endpush

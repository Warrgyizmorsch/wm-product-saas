{{-- Shared reject dialog. Any button with data-bs-target="#rejectJournalModal" and data-reject-url opens it for that journal. --}}
<x-ui.modal id="rejectJournalModal" title="Reject <span data-reject-number></span>" :centered="true" formAction="#">
    <p class="fs-13 text-muted">It will not post. The person who entered it sees your reason.</p>
    <x-ui.textarea label="Reason" name="reason" id="rejectReason" rows="3" maxlength="500" :required="true" />

    <x-slot:footer>
        <x-ui.button type="button" variant="light-brand" size="sm" data-bs-dismiss="modal">Cancel</x-ui.button>
        <x-ui.button type="submit" variant="primary" size="sm" icon="feather-x">Reject entry</x-ui.button>
    </x-slot:footer>
</x-ui.modal>

@push('scripts')
    <script>
        document.getElementById('rejectJournalModal')?.addEventListener('show.bs.modal', function (event) {
            const trigger = event.relatedTarget;
            if (!trigger) return;
            this.querySelector('form').action = trigger.dataset.rejectUrl;
            this.querySelector('[data-reject-number]').textContent = trigger.dataset.journalNumber || '';
            this.querySelector('#rejectReason').value = '';
        });
    </script>
@endpush

{{-- One place for journal/voucher status badges. Usage: @include('modules.accounting.journals._status-badge', ['status' => $journal->status]) --}}
@switch($status)
    @case('posted')
        <x-ui.badge variant="success" soft>Posted</x-ui.badge>
        @break
    @case('reversed')
        <x-ui.badge variant="secondary" soft>Reversed</x-ui.badge>
        @break
    @case('pending_approval')
        <x-ui.badge variant="primary" soft>Awaiting approval</x-ui.badge>
        @break
    @case('rejected')
        <x-ui.badge variant="danger" soft>Rejected</x-ui.badge>
        @break
    @default
        <x-ui.badge variant="warning" soft>Draft</x-ui.badge>
@endswitch

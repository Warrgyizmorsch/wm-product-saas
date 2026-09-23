@extends('layouts.duralux')

@section('title', 'Payment Gateway | SaaS ERP')
@section('page-title', 'Payment Gateway')
@section('breadcrumb', 'Tenant Console / Payment Gateway')

@section('content')

    <x-ui.card title="Active Payment Gateway" bodyClass="p-0">
        <div class="p-3 border-bottom fs-13 text-muted">
            Every tenant's Subscription checkout goes through whichever gateway is active here. Switching gateways is a setting, not a code change — credentials for each one still come from server config (.env), never entered here.
        </div>
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Gateway</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($gateways as $gateway)
                    @php
                        $isActive = $activeIdentifier === $gateway->identifier();
                        $isConfigured = $gateway->isConfigured();
                    @endphp
                    <tr>
                        <td class="ps-4">
                            <span class="fw-semibold text-dark">{{ $gateway->label() }}</span>
                            <span class="fs-11 text-muted d-block">{{ $gateway->identifier() }}</span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                @if ($isActive)
                                    <x-ui.badge variant="primary" soft>Active</x-ui.badge>
                                @endif
                                @if ($isConfigured)
                                    <x-ui.badge variant="success" soft>Configured</x-ui.badge>
                                @else
                                    <x-ui.badge variant="warning" soft>Missing credentials</x-ui.badge>
                                @endif
                            </div>
                        </td>
                        <td class="text-end pe-4">
                            @if ($isActive)
                                <x-ui.button variant="light" size="sm" class="border" disabled>Current</x-ui.button>
                            @else
                                <form action="{{ route('platform.payment-gateway.update') }}" method="POST" id="setActiveGatewayForm{{ $gateway->identifier() }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="gateway" value="{{ $gateway->identifier() }}">
                                    <x-ui.button type="button" variant="primary" size="sm" :disabled="! $isConfigured"
                                        onclick="confirmAction({title: 'Set Active Gateway', message: 'Switch every tenant checkout to {{ $gateway->label() }}?', variant: 'primary', confirmText: 'Set Active'}, function() { document.getElementById('setActiveGatewayForm{{ $gateway->identifier() }}').submit(); })">
                                        Set Active
                                    </x-ui.button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center py-5 text-muted">No payment gateways are registered.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>

@endsection
